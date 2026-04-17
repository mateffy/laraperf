<?php

declare(strict_types=1);

namespace Mateffy\Laraperf\Storage;

use Illuminate\Support\Facades\File;

/**
 * File-based storage for perf sessions.
 *
 * Two-file design:
 *
 *   tracker.json  (storage/perf/tracker.json)
 *     Tiny single file: session_id, status, started_at, duration_seconds, tag.
 *     Read on every PHP-FPM boot to decide whether to attach DB::listen().
 *     Only one tracker exists at a time — perf:watch overwrites it,
 *     perf:stop/removes it, expiry auto-removes it.
 *     One file_exists + one file_get_contents, no glob.
 *
 *   Data files    (storage/perf/<session_id>.json)
 *     Full query payload. Only read during analysis and append operations.
 *     Retained for MAX_SESSIONS completed sessions, then pruned.
 */
class PerfStore
{
    public const MAX_SESSIONS = 10;

    protected string $base_path;

    /** @var array<string, mixed>|null In-memory cache of the session data currently being appended to. */
    protected ?array $sessionCache = null;

    /** @var string|null The session_id corresponding to the cached session data. */
    protected ?string $sessionCacheId = null;

    public function __construct(?string $base_path = null)
    {
        /** @var string $config_path */
        $config_path = config('laraperf.storage_path', storage_path('perf'));
        $this->base_path = $base_path ?? $config_path;
        @mkdir($this->base_path, 0755, true);
    }

    // -------------------------------------------------------------------------
    // Tracker file (single file, no glob)
    // -------------------------------------------------------------------------

    public function trackerPath(): string
    {
        return $this->base_path.'/tracker.json';
    }

    /**
     * @return array<string, mixed>
     */
    public function emptyTracker(string $session_id, int $duration_seconds = 300, ?string $tag = null): array
    {
        return [
            'session_id' => $session_id,
            'status' => 'active',
            'started_at' => now()->toIso8601String(),
            'duration_seconds' => $duration_seconds,
            'tag' => $tag,
        ];
    }

    /**
     * Read the tracker file. Returns null when it does not exist.
     *
     * @return array<string, mixed>|null
     */
    public function readTracker(): ?array
    {
        $path = $this->trackerPath();

        if (! File::exists($path)) {
            return null;
        }

        $json = File::get($path);
        if ($json === false) {
            return null;
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    /** Write the tracker to disk atomically.
     *
     * @param  array<string, mixed>  $tracker
     */
    public function writeTracker(array $tracker): void
    {
        $path = $this->trackerPath();
        $tmp = $path.'.tmp.'.getmypid();

        $content = json_encode($tracker, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($content !== false) {
            File::put($tmp, $content);
            rename($tmp, $path);
        }
    }

    /** Remove the tracker file. */
    public function removeTracker(): void
    {
        $path = $this->trackerPath();

        if (File::exists($path)) {
            File::delete($path);
        }
    }

    /**
     * Read the active tracker, auto-finalizing and removing if expired.
     * Single file_exists + file_get_contents — no glob.
     *
     * @return array<string, mixed>|null
     */
    public function activeTracker(): ?array
    {
        $tracker = $this->readTracker();

        if (! $tracker || ($tracker['status'] ?? null) !== 'active') {
            return null;
        }

        if ($this->trackerExpired($tracker)) {
            if (isset($tracker['session_id'])) {
                /** @var string $tracker_session_id */
                $tracker_session_id = $tracker['session_id'];
                $this->finalizeSession($tracker_session_id);
            }

            $this->removeTracker();

            return null;
        }

        return $tracker;
    }

    /** Check whether a tracker has expired.
     *
     * @param  array<string, mixed>  $tracker
     */
    public function trackerExpired(array $tracker): bool
    {
        $started_at = $tracker['started_at'] ?? null;
        $duration = $tracker['duration_seconds'] ?? null;

        if (! is_string($started_at) || ! is_int($duration)) {
            return false;
        }

        $start_time = strtotime($started_at);

        return is_int($start_time) && time() >= ($start_time + $duration);
    }

    // -------------------------------------------------------------------------
    // Data files (large, only read during analysis)
    // -------------------------------------------------------------------------

    public function sessionPath(string $session_id): string
    {
        return $this->base_path.'/'.$session_id.'.json';
    }

    /**
     * Read a session data file. Returns null when it does not exist.
     *
     * @return array<string, mixed>|null
     */
    public function readSession(string $session_id): ?array
    {
        $path = $this->sessionPath($session_id);

        if (! File::exists($path)) {
            return null;
        }

        $json = File::get($path);
        if ($json === false) {
            return null;
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Write (overwrite) a session data file atomically.
     *
     * @param  array<string, mixed>  $data
     */
    public function writeSession(string $session_id, array $data): void
    {
        $path = $this->sessionPath($session_id);
        $tmp = $path.'.tmp.'.getmypid();

        $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($content !== false) {
            File::put($tmp, $content);
            rename($tmp, $path);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function emptySession(string $session_id): array
    {
        return [
            'session_id' => $session_id,
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
            'updated_at' => now()->toIso8601String(),
            'query_count' => 0,
            'queries' => [],
        ];
    }

    /**
     * Append a query record to a session's data file.
     *
     * Uses an in-memory cache so that repeated calls within the same
     * PHP-FPM request avoid reading the data JSON from disk every time.
     *
     * @param  array<string, mixed>  $query
     */
    public function appendQuery(string $session_id, array $query): void
    {
        $session = $this->cachedSession($session_id);

        if (! $session) {
            return;
        }

        /** @var array<int, array<string, mixed>> $queries */
        $queries = $session['queries'] ?? [];
        $queries[] = $query;
        $session['queries'] = $queries;
        $session['query_count'] = count($queries);
        $session['updated_at'] = now()->toIso8601String();
        $this->writeSession($session_id, $session);

        $this->sessionCache = $session;
        $this->sessionCacheId = $session_id;
    }

    /**
     * Get session data from the in-memory cache, or read from disk.
     *
     * @return array<string, mixed>|null
     */
    protected function cachedSession(string $session_id): ?array
    {
        if ($this->sessionCacheId === $session_id && $this->sessionCache !== null) {
            return $this->sessionCache;
        }

        $session = $this->readSession($session_id);

        if ($session !== null) {
            $this->sessionCache = $session;
            $this->sessionCacheId = $session_id;
        }

        return $session;
    }

    /** Invalidate the in-memory session data cache. */
    protected function invalidateCache(?string $session_id = null): void
    {
        if ($session_id === null || $this->sessionCacheId === $session_id) {
            $this->sessionCache = null;
            $this->sessionCacheId = null;
        }
    }

    /** Prime the in-memory cache with an already-resolved session.
     *
     * @param  array<string, mixed>  $session
     */
    public function cacheSession(array $session): void
    {
        $this->sessionCache = $session;
        /** @var string|null $session_id */
        $session_id = $session['session_id'] ?? null;
        $this->sessionCacheId = $session_id !== null ? (string) $session_id : null;
    }

    /** Mark a session's data file as completed. */
    public function finalizeSession(string $session_id): void
    {
        $session = $this->readSession($session_id);

        if (! $session) {
            return;
        }

        /** @var string $finished_at */
        $finished_at = now()->toIso8601String();
        $session['finished_at'] = $finished_at;
        $this->writeSession($session_id, $session);

        $this->invalidateCache($session_id);
        $this->pruneOldSessions();
    }

    // -------------------------------------------------------------------------
    // Combined session resolution (tracker + data)
    // -------------------------------------------------------------------------

    /**
     * Return the most recently modified completed session, or null.
     * Reads data files only.
     *
     * @return array<string, mixed>|null
     */
    public function latestSession(): ?array
    {
        $sessions = [];

        /** @var array<int, string> $files */
        $files = File::glob($this->base_path.'/*.json');
        $files = is_array($files) ? $files : [];

        foreach ($files as $file) {
            if (str_contains((string) $file, '.tmp.')) {
                continue;
            }

            $id = basename((string) $file, '.json');

            // Skip the tracker file
            if ($id === 'tracker') {
                continue;
            }

            $session = $this->readSession($id);

            if ($session && ($session['finished_at'] ?? null)) {
                $sessions[] = $session;
            }
        }

        if (empty($sessions)) {
            return null;
        }

        usort($sessions, function (array $a, array $b) {
            // These are completed sessions, so finished_at is guaranteed to exist
            /** @var string $a_time */
            $a_time = $a['finished_at'];
            /** @var string $b_time */
            $b_time = $b['finished_at'];

            return $b_time <=> $a_time
                ?: $b['session_id'] <=> $a['session_id'];
        });

        return $sessions[0];
    }

    /**
     * Return the active tracker, or null.
     * Single file read — no glob.
     *
     * @return array<string, mixed>|null
     */
    public function activeSession(): ?array
    {
        return $this->activeTracker();
    }

    public function sessionExists(string $session_id): bool
    {
        return File::exists($this->sessionPath($session_id));
    }

    // -------------------------------------------------------------------------
    // Housekeeping
    // -------------------------------------------------------------------------

    /** Keep only the newest MAX_SESSIONS completed data files, delete the rest. */
    protected function pruneOldSessions(): void
    {
        /** @var array<int, string> $files */
        $files = File::glob($this->base_path.'/*.json');
        $files = is_array($files) ? $files : [];

        $completed = collect($files)
            ->filter(fn (string $f) => ! str_contains($f, '.tmp.'))
            ->filter(fn (string $f) => basename($f) !== 'tracker.json')
            ->map(function (string $f) {
                $id = basename($f, '.json');
                $session = $this->readSession($id);

                return $session && ($session['finished_at'] ?? null)
                    ? ['id' => $id, 'mtime' => File::lastModified($f)]
                    : null;
            })
            ->filter()
            ->sortByDesc('mtime')
            ->values();

        foreach ($completed->slice(self::MAX_SESSIONS) as $old) {
            if (is_array($old) && isset($old['id'])) {
                File::delete($this->sessionPath((string) $old['id']));
            }
        }
    }

    /** Delete all data files and the tracker. */
    public function clearAll(): void
    {
        /** @var array<int, string> $files */
        $files = File::glob($this->base_path.'/*.json');
        $files = is_array($files) ? $files : [];

        foreach ($files as $file) {
            File::delete((string) $file);
        }
    }
}
