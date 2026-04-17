<?php

declare(strict_types=1);

namespace Mateffy\Laraperf\Storage;

use Illuminate\Support\Facades\File;

/**
 * File-based storage for perf sessions.
 *
 * Two-file design:
 *
 *   Tracker  (storage/perf/trackers/<id>.json)
 *     Tiny metadata file: session_id, status, started_at, duration_seconds, tag.
 *     Checked on every PHP-FPM boot to decide whether to attach DB::listen().
 *     Auto-finalized and cleaned up when expired.
 *
 *   Data     (storage/perf/<id>.json)
 *     Full query payload: queries array, timings, etc.
 *     Only read during analysis (perf:query, perf:explain).
 *     Retained for MAX_SESSIONS completed sessions, then pruned.
 */
class PerfStore
{
    public const MAX_SESSIONS = 10;

    protected string $base_path;

    protected string $tracker_dir;

    /** @var array<string, mixed>|null In-memory cache of the session data currently being appended to. */
    protected ?array $sessionCache = null;

    /** @var string|null The session_id corresponding to the cached session data. */
    protected ?string $sessionCacheId = null;

    public function __construct(?string $base_path = null)
    {
        $this->base_path = $base_path ?? config('laraperf.storage_path', storage_path('perf'));
        $this->tracker_dir = $this->base_path.'/trackers';
        @mkdir($this->base_path, 0755, true);
        @mkdir($this->tracker_dir, 0755, true);
    }

    // -------------------------------------------------------------------------
    // Tracker files (tiny, checked on every PHP-FPM boot)
    // -------------------------------------------------------------------------

    public function trackerPath(string $session_id): string
    {
        return $this->tracker_dir.'/'.$session_id.'.json';
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
     * Read a tracker file. Returns null when it does not exist.
     *
     * @return array<string, mixed>|null
     */
    public function readTracker(string $session_id): ?array
    {
        $path = $this->trackerPath($session_id);

        if (! File::exists($path)) {
            return null;
        }

        $json = File::get($path);
        if ($json === false) {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    /** Write a tracker to disk atomically. */
    public function writeTracker(string $session_id, array $tracker): void
    {
        $path = $this->trackerPath($session_id);
        $tmp = $path.'.tmp.'.getmypid();

        $content = json_encode($tracker, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($content !== false) {
            File::put($tmp, $content);
            rename($tmp, $path);
        }
    }

    /** Mark a tracker as completed. */
    public function finalizeTracker(string $session_id): void
    {
        $tracker = $this->readTracker($session_id);

        if (! $tracker) {
            return;
        }

        $tracker['status'] = 'completed';
        $this->writeTracker($session_id, $tracker);
    }

    /** Remove a tracker file. */
    public function removeTracker(string $session_id): void
    {
        $path = $this->trackerPath($session_id);

        if (File::exists($path)) {
            File::delete($path);
        }
    }

    /** Remove stale (completed or expired) tracker files. Called on perf:watch. */
    public function cleanupStaleTrackers(): int
    {
        $removed = 0;

        /** @var list<string> $files */
        $files = File::glob($this->tracker_dir.'/*.json');

        foreach ($files as $file) {
            if (str_contains((string) $file, '.tmp.')) {
                continue;
            }

            $id = basename((string) $file, '.json');
            $tracker = $this->readTracker($id);

            if (! $tracker) {
                continue;
            }

            if ($tracker['status'] === 'completed' || $this->trackerExpired($tracker)) {
                if ($this->trackerExpired($tracker) && $tracker['status'] === 'active') {
                    $this->finalizeTracker($id);
                }
                $this->removeTracker($id);
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * Return any active, non-expired tracker, or null.
     * Auto-finalizes expired trackers.
     *
     * @return array<string, mixed>|null
     */
    public function activeTracker(): ?array
    {
        /** @var list<string> $files */
        $files = File::glob($this->tracker_dir.'/*.json');

        foreach ($files as $file) {
            if (str_contains((string) $file, '.tmp.')) {
                continue;
            }

            $id = basename((string) $file, '.json');
            $tracker = $this->readTracker($id);

            if (! $tracker || $tracker['status'] !== 'active') {
                continue;
            }

            if ($this->trackerExpired($tracker)) {
                $this->finalizeTracker($id);
                $this->removeTracker($id);

                continue;
            }

            return $tracker;
        }

        return null;
    }

    /** Check whether a tracker has expired. */
    public function trackerExpired(array $tracker): bool
    {
        $started_at = $tracker['started_at'] ?? null;
        $duration = $tracker['duration_seconds'] ?? null;

        if (! $started_at || ! $duration) {
            return false;
        }

        return time() >= (strtotime($started_at) + $duration);
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

        $session['queries'][] = $query;
        $session['query_count'] = count($session['queries']);
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

    /** Prime the in-memory cache with an already-resolved session. */
    public function cacheSession(array $session): void
    {
        $this->sessionCache = $session;
        $this->sessionCacheId = $session['session_id'] ?? null;
    }

    /** Mark a session's data file as completed. */
    public function finalizeSession(string $session_id): void
    {
        $session = $this->readSession($session_id);

        if (! $session) {
            return;
        }

        $session['finished_at'] = now()->toIso8601String();
        $this->writeSession($session_id, $session);

        $this->invalidateCache($session_id);
        $this->pruneOldSessions();
    }

    // -------------------------------------------------------------------------
    // Combined session resolution (tracker + data)
    // -------------------------------------------------------------------------

    /**
     * Return the most recently modified completed session, or null.
     * Reads data files only (no tracker glob).
     *
     * @return array<string, mixed>|null
     */
    public function latestSession(): ?array
    {
        $sessions = [];

        /** @var list<string> $files */
        $files = File::glob($this->base_path.'/*.json');

        foreach ($files as $file) {
            if (str_contains((string) $file, '.tmp.')) {
                continue;
            }

            $id = basename((string) $file, '.json');
            $session = $this->readSession($id);

            if ($session && ($session['finished_at'] ?? null)) {
                $sessions[] = $session;
            }
        }

        if (empty($sessions)) {
            return null;
        }

        usort($sessions, function (array $a, array $b) {
            $a_time = $a['finished_at'] ?? $a['updated_at'] ?? $a['started_at'] ?? '';
            $b_time = $b['finished_at'] ?? $b['updated_at'] ?? $b['started_at'] ?? '';

            return $b_time <=> $a_time
                ?: $b['session_id'] <=> $a['session_id'];
        });

        return $sessions[0];
    }

    /**
     * Return the active tracker, or null.
     * This is the fast path — only reads tiny tracker files.
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
        /** @var list<string> $files */
        $files = File::glob($this->base_path.'/*.json');

        $completed = collect($files)
            ->filter(fn (string $f) => ! str_contains($f, '.tmp.'))
            ->filter(fn (string $f) => ! str_starts_with(basename($f), '.'))
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

    /** Delete all data files and tracker files. */
    public function clearAll(): void
    {
        /** @var list<string> $files */
        $files = File::glob($this->base_path.'/*.json');

        foreach ($files as $file) {
            File::delete((string) $file);
        }

        /** @var list<string> $trackers */
        $trackers = File::glob($this->tracker_dir.'/*.json');

        foreach ($trackers as $file) {
            File::delete((string) $file);
        }
    }
}
