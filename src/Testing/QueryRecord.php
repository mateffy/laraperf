<?php

declare(strict_types=1);

namespace Mateffy\Laraperf\Testing;

/**
 * Immutable value object representing a captured SQL query.
 *
 * Contains all relevant information about a single query execution,
 * including timing, source location, and metadata.
 */
class QueryRecord
{
    /**
     * @param  string  $sql  The SQL with placeholders
     * @param  string  $rawSql  The SQL with bindings interpolated
     * @param  array  $bindings  Parameter bindings
     * @param  float  $time_ms  Execution time in milliseconds
     * @param  string  $connection  Connection name (e.g., 'mysql', 'pgsql')
     * @param  string  $driver  Database driver name
     * @param  string  $operation  SQL operation (SELECT, INSERT, UPDATE, DELETE)
     * @param  ?string  $table  Target table name (extracted from SQL)
     * @param  string  $hash  Normalized SQL hash for N+1 detection
     * @param  string  $batch_id  Per-request unique identifier
     * @param  array<int, array{file: string, line: int, function: string, class: ?string}>  $source  Stack trace source frames
     * @param  string  $captured_at  ISO8601 timestamp
     * @param  ?string  $query_id  Optional unique identifier
     */
    public function __construct(
        public readonly string $sql,
        public readonly string $rawSql,
        public readonly array $bindings,
        public readonly float $time_ms,
        public readonly string $connection,
        public readonly string $driver,
        public readonly string $operation,
        public readonly ?string $table,
        public readonly string $hash,
        public readonly string $batch_id,
        public readonly array $source,
        public readonly string $captured_at,
        public readonly ?string $query_id = null,
    ) {}

    /**
     * Create from an array (useful for deserialization).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            sql: $data['sql'] ?? '',
            rawSql: $data['raw_sql'] ?? $data['rawSql'] ?? '',
            bindings: $data['bindings'] ?? [],
            time_ms: (float) ($data['time_ms'] ?? $data['timeMs'] ?? 0),
            connection: $data['connection'] ?? 'default',
            driver: $data['driver'] ?? 'unknown',
            operation: $data['operation'] ?? 'UNKNOWN',
            table: $data['table'] ?? null,
            hash: $data['hash'] ?? '',
            batch_id: $data['batch_id'] ?? $data['batchId'] ?? '',
            source: $data['source'] ?? [],
            captured_at: $data['captured_at'] ?? $data['capturedAt'] ?? now()->toIso8601String(),
            query_id: $data['query_id'] ?? $data['queryId'] ?? null,
        );
    }

    /**
     * Convert to array for serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sql' => $this->sql,
            'raw_sql' => $this->rawSql,
            'bindings' => $this->bindings,
            'time_ms' => $this->time_ms,
            'connection' => $this->connection,
            'driver' => $this->driver,
            'operation' => $this->operation,
            'table' => $this->table,
            'hash' => $this->hash,
            'batch_id' => $this->batch_id,
            'source' => $this->source,
            'captured_at' => $this->captured_at,
            'query_id' => $this->query_id,
        ];
    }

    /**
     * Check if this query is slower than a threshold.
     */
    public function isSlowerThan(float $ms): bool
    {
        return $this->time_ms > $ms;
    }

    /**
     * Check if query targets a specific table.
     */
    public function isOnTable(string $table): bool
    {
        return $this->table === $table;
    }

    /**
     * Check if query is a specific operation type.
     */
    public function isOperation(string $operation): bool
    {
        return strtoupper($this->operation) === strtoupper($operation);
    }

    /**
     * Get the primary source location (first frame).
     *
     * @return ?array{file: string, line: int, function: string, class: ?string}
     */
    public function primarySource(): ?array
    {
        return $this->source[0] ?? null;
    }

    /**
     * Format time in human-readable form.
     */
    public function timeHuman(): string
    {
        if ($this->time_ms < 1) {
            return round($this->time_ms * 1000, 2).' μs';
        }

        return round($this->time_ms, 2).' ms';
    }
}
