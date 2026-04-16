<?php

declare(strict_types=1);

namespace Mateffy\Laraperf\Testing;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

/**
 * Global session manager for performance testing with parallel test support.
 *
 * This class manages multiple concurrent capture sessions, which is essential
 * for parallel testing (Pest/PHPUnit --parallel). Each test gets its own
 * isolated session identified by a unique ID.
 *
 * In-memory storage is used by default for speed, with optional file-based
 * persistence for cross-process scenarios or debugging.
 */
class PerformanceSessionManager
{
    /**
     * Active capture sessions indexed by session ID.
     *
     * @var array<string, PerformanceCapture>
     */
    protected static array $sessions = [];

    /**
     * Currently active session ID in this process.
     */
    protected static ?string $currentSessionId = null;

    /**
     * Whether query listener is registered.
     */
    protected static bool $listenerRegistered = false;

    /**
     * Temporary file path for cross-process communication (when needed).
     */
    protected static ?string $tempDir = null;

    /**
     * Register a new capture session and attach the query listener if needed.
     */
    public static function register(string $sessionId, PerformanceCapture $capture): void
    {
        self::$sessions[$sessionId] = $capture;
        self::$currentSessionId = $sessionId;

        // Ensure query listener is attached
        self::ensureQueryListener();
    }

    /**
     * Unregister a capture session.
     */
    public static function unregister(string $sessionId): void
    {
        unset(self::$sessions[$sessionId]);

        // Clear current session if this was it
        if (self::$currentSessionId === $sessionId) {
            self::$currentSessionId = null;
        }

        // Detach listener if no more sessions
        if (empty(self::$sessions)) {
            self::detachQueryListener();
        }
    }

    /**
     * Get a specific capture session by ID.
     */
    public static function get(string $sessionId): ?PerformanceCapture
    {
        return self::$sessions[$sessionId] ?? null;
    }

    /**
     * Get the currently active capture session.
     */
    public static function current(): ?PerformanceCapture
    {
        if (self::$currentSessionId === null) {
            return null;
        }

        return self::$sessions[self::$currentSessionId] ?? null;
    }

    /**
     * Check if any capture session is currently active.
     */
    public static function isActive(): bool
    {
        return self::$currentSessionId !== null && isset(self::$sessions[self::$currentSessionId]);
    }

    /**
     * Get the current session ID.
     */
    public static function currentSessionId(): ?string
    {
        return self::$currentSessionId;
    }

    /**
     * Route a captured query to the appropriate session.
     *
     * Called by the query event listener.
     */
    public static function routeQuery(QueryRecord $query): void
    {
        // Route to all active sessions (each session gets its own copy)
        foreach (self::$sessions as $session) {
            $session->recordQuery($query);
        }
    }

    /**
     * Get all active session IDs.
     *
     * @return list<string>
     */
    public static function activeSessions(): array
    {
        return array_keys(self::$sessions);
    }

    /**
     * Clear all sessions (useful for testing).
     */
    public static function clear(): void
    {
        self::$sessions = [];
        self::$currentSessionId = null;
        self::detachQueryListener();
    }

    /**
     * Count of active sessions.
     */
    public static function count(): int
    {
        return count(self::$sessions);
    }

    // -------------------------------------------------------------------------
    // Query Listener Management
    // -------------------------------------------------------------------------

    /**
     * Ensure the database query listener is attached.
     */
    protected static function ensureQueryListener(): void
    {
        if (self::$listenerRegistered) {
            return;
        }

        DB::listen(function (QueryExecuted $event) {
            self::handleQueryEvent($event);
        });

        self::$listenerRegistered = true;
    }

    /**
     * Detach the query listener when no sessions are active.
     */
    protected static function detachQueryListener(): void
    {
        // Note: Laravel's DB::listen doesn't provide a way to remove listeners,
        // so we just mark it as detached and check in the handler.
        self::$listenerRegistered = false;
    }

    /**
     * Handle a database query event.
     */
    protected static function handleQueryEvent(QueryExecuted $event): void
    {
        // Skip if no active sessions (even if listener is still attached)
        if (empty(self::$sessions)) {
            return;
        }

        // Convert to QueryRecord
        $record = self::createQueryRecord($event);

        // Route to all active sessions
        self::routeQuery($record);
    }

    /**
     * Create a QueryRecord from a Laravel query event.
     */
    protected static function createQueryRecord(QueryExecuted $event): QueryRecord
    {
        $rawSql = $event->toRawSql();

        // Extract operation and table (basic parsing)
        $sql = $event->sql;
        $operation = strtoupper((string) strtok($sql, ' ') ?: 'UNKNOWN');
        $table = self::extractTableName($sql);

        return new QueryRecord(
            sql: $sql,
            rawSql: $rawSql,
            bindings: $event->bindings,
            time_ms: round((float) $event->time, 3),
            connection: $event->connectionName ?? 'default',
            driver: $event->connection->getDriverName(),
            operation: $operation,
            table: $table,
            hash: md5(preg_replace('/\d+/', '?', $sql) ?: $sql),
            batch_id: self::$currentSessionId ?? 'none',
            source: [],
            captured_at: now()->toIso8601String(),
            query_id: uniqid('q_', true),
        );
    }

    /**
     * Extract table name from SQL (basic implementation).
     */
    protected static function extractTableName(string $sql): ?string
    {
        $sql = strtolower($sql);

        // FROM table_name
        if (preg_match('/from\s+["\']?(\w+)["\']?/i', $sql, $matches)) {
            return $matches[1];
        }

        // INTO table_name
        if (preg_match('/into\s+["\']?(\w+)["\']?/i', $sql, $matches)) {
            return $matches[1];
        }

        // UPDATE table_name
        if (preg_match('/update\s+["\']?(\w+)["\']?/i', $sql, $matches)) {
            return $matches[1];
        }

        // INSERT INTO table_name
        if (preg_match('/insert\s+into\s+["\']?(\w+)["\']?/i', $sql, $matches)) {
            return $matches[1];
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Cross-Process Support (for Parallel Testing)
    // -------------------------------------------------------------------------

    /**
     * Enable file-based persistence for cross-process scenarios.
     */
    public static function enableFilePersistence(?string $tempDir = null): void
    {
        self::$tempDir = $tempDir ?? storage_path('perf/sessions');

        if (! is_dir(self::$tempDir)) {
            mkdir(self::$tempDir, 0755, true);
        }
    }

    /**
     * Store session data to temp file (for cross-process access).
     *
     * @param  array<string, mixed>  $data
     */
    public static function persistToFile(string $sessionId, array $data): void
    {
        if (self::$tempDir === null) {
            return;
        }

        $file = self::$tempDir."/{$sessionId}.json";
        file_put_contents($file, json_encode($data));
    }

    /**
     * Load session data from temp file.
     *
     * @return array<string, mixed>|null
     */
    public static function loadFromFile(string $sessionId): ?array
    {
        if (self::$tempDir === null) {
            return null;
        }

        $file = self::$tempDir."/{$sessionId}.json";
        if (! file_exists($file)) {
            return null;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);

        return is_array($data) ? $data : null;
    }

    /**
     * Clean up temp files.
     */
    public static function cleanupFiles(): void
    {
        if (self::$tempDir === null) {
            return;
        }

        /** @var list<string> $files */
        $files = glob(self::$tempDir.'/*.json');
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            unlink($file);
        }
    }
}
