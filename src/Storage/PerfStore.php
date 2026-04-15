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

    public function __construct()
    {
        $this->base_path = storage_path('perf');
        File::ensureDirectoryExists($this->base_path);
    }

    // -------------------------------------------------------------------------
    // Session files
    // -------------------------------------------------------------------------

    public function sessionPath(string $session_id): string
    {
        return $this->base_path.'/'.$session_id.'.json';
    }

    /** Read a session. Returns null when the file does not exist. */
    public function readSession(string $session_id): ?array
    {
        $path = $this->sessionPath($session_id);

        if (! File::exists($path)) {
            return null;
        }

        $json = File::get($path);
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Write (overwrite) a session to disk atomically via a temp file.
     * This prevents partial reads when the watcher process is appending
     * queries while another process reads the session.
     */
    public function writeSession(string $session_id, array $data): void
    {
        $path = $this->sessionPath($session_id);
        $tmp = $path.'.tmp.'.getmypid();

        File::put($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        rename($tmp, $path);
    }

    /** Append a query record to an active session file (read-modify-write). */
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

    /** Return the most recently modified completed session, or null. */
    public function latestSession(): ?array
    {
        $sessions = [];

        foreach (File::glob($this->base_path.'/*.json') as $file) {
            if (str_contains($file, '.tmp.')) {
                continue;
            }

            $id = basename($file, '.json');
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

    /** Return any active session (running watcher), or null. */
    public function activeSession(): ?array
    {
        foreach (File::glob($this->base_path.'/*.json') as $file) {
            if (str_contains($file, '.tmp.')) {
                continue;
            }

            $id = basename($file, '.json');
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
        File::put($this->watcherPidPath($pid), json_encode([
            'pid' => $pid,
            'session_id' => $session_id,
            'started_at' => now()->toIso8601String(),
        ]));
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

        foreach (File::glob($this->base_path.'/.watcher-*') as $file) {
            $content = json_decode(File::get($file), true);

            if (is_array($content) && isset($content['pid'])) {
                $result[(int) $content['pid']] = $content;
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
        $completed = collect(File::glob($this->base_path.'/*.json'))
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
            File::delete($this->sessionPath($old['id']));
        }
    }

    public function clearAll(): void
    {
        foreach (File::glob($this->base_path.'/*.json') as $file) {
            File::delete($file);
        }
    }
}
