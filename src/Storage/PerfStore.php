<?php

declare(strict_types=1);

namespace Mateffy\Laraperf\Storage;

use Illuminate\Support\Facades\File;

/**
 * File-based storage for perf sessions.
 *
 * All data lives under storage/perf/ (gitignored). Each session is a
 * single JSON file named by session ID. A sentinel file .watcher-{pid}
 * is written by detached watcher processes so PerfStopCommand can kill them.
 */
class PerfStore
{
    /** Maximum number of completed sessions to retain on disk. */
    public const MAX_SESSIONS = 10;

    protected string $base_path;

    public function __construct(?string $base_path = null)
    {
        $this->base_path = $base_path ?? config('laraperf.storage_path', storage_path('perf'));
        @mkdir($this->base_path, 0755, true);
    }

    // -------------------------------------------------------------------------
    // Session files
    // -------------------------------------------------------------------------

    public function sessionPath(string $session_id): string
    {
        return $this->base_path.'/'.$session_id.'.json';
    }

    /**
     * Read a session. Returns null when the file does not exist.
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
     * Write (overwrite) a session to disk atomically via a temp file.
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
     * Append a query record to an active session file (read-modify-write).
     *
     * @param  array<string, mixed>  $query
     */
    public function appendQuery(string $session_id, array $query): void
    {
        $session = $this->readSession($session_id) ?? $this->emptySession($session_id);
        $session['queries'][] = $query;
        $session['query_count'] = count($session['queries']);
        $session['updated_at'] = now()->toIso8601String();
        $this->writeSession($session_id, $session);
    }

    /** Mark a session as completed and prune old sessions. */
    public function finalizeSession(string $session_id): void
    {
        $session = $this->readSession($session_id);

        if (! $session) {
            return;
        }

        $session['status'] = 'completed';
        $session['finished_at'] = now()->toIso8601String();
        $this->writeSession($session_id, $session);

        $this->pruneOldSessions();
    }

    /**
     * @return array<string, mixed>
     */
    public function emptySession(string $session_id): array
    {
        return [
            'session_id' => $session_id,
            'status' => 'active',
            'started_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'finished_at' => null,
            'query_count' => 0,
            'queries' => [],
        ];
    }

    /**
     * Return the most recently modified completed session, or null.
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

            if ($session && $session['status'] === 'completed') {
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
     * Return any active session (running watcher), or null.
     *
     * @return array<string, mixed>|null
     */
    public function activeSession(): ?array
    {
        /** @var list<string> $files */
        $files = File::glob($this->base_path.'/*.json');

        foreach ($files as $file) {
            if (str_contains((string) $file, '.tmp.')) {
                continue;
            }

            $id = basename((string) $file, '.json');
            $session = $this->readSession($id);

            if ($session && $session['status'] === 'active') {
                return $session;
            }
        }

        return null;
    }

    public function sessionExists(string $session_id): bool
    {
        return File::exists($this->sessionPath($session_id));
    }

    // -------------------------------------------------------------------------
    // Watcher PID sentinel files
    // -------------------------------------------------------------------------

    public function watcherPidPath(int $pid): string
    {
        return $this->base_path.'/.watcher-'.$pid;
    }

    public function writeWatcherPid(int $pid, string $session_id): void
    {
        $content = json_encode([
            'pid' => $pid,
            'session_id' => $session_id,
            'started_at' => now()->toIso8601String(),
        ]);
        if ($content !== false) {
            File::put($this->watcherPidPath($pid), $content);
        }
    }

    public function removeWatcherPid(int $pid): void
    {
        $path = $this->watcherPidPath($pid);

        if (File::exists($path)) {
            File::delete($path);
        }
    }

    /**
     * Returns all live watcher PID records.
     *
     * @return array<int, array{pid: int, session_id: string, started_at: string}>
     */
    public function allWatcherPids(): array
    {
        $result = [];

        /** @var list<string> $files */
        $files = File::glob($this->base_path.'/.watcher-*');

        foreach ($files as $file) {
            $raw = File::get((string) $file);
            if ($raw === false) {
                continue;
            }
            $content = json_decode($raw, true);

            if (is_array($content) && isset($content['pid'], $content['session_id'], $content['started_at'])) {
                $result[(int) $content['pid']] = [
                    'pid' => (int) $content['pid'],
                    'session_id' => (string) $content['session_id'],
                    'started_at' => (string) $content['started_at'],
                ];
            }
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // Housekeeping
    // -------------------------------------------------------------------------

    /** Keep only the newest MAX_SESSIONS completed sessions, delete the rest. */
    protected function pruneOldSessions(): void
    {
        /** @var list<string> $files */
        $files = File::glob($this->base_path.'/*.json');

        $completed = collect($files)
            ->filter(fn (string $f) => ! str_contains($f, '.tmp.'))
            ->map(function (string $f) {
                $id = basename($f, '.json');
                $session = $this->readSession($id);

                return $session && $session['status'] === 'completed'
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

    public function clearAll(): void
    {
        /** @var list<string> $files */
        $files = File::glob($this->base_path.'/*.json');

        foreach ($files as $file) {
            File::delete((string) $file);
        }
    }
}
