<?php

declare(strict_types=1);

namespace Mateffy\Laraperf\Analysis;

/**
 * Immutable value object representing a detected N+1 query pattern.
 */
class N1Candidate
{
    /**
     * @param  string  $hash  The normalized SQL hash
     * @param  string  $batchId  The request batch identifier
     * @param  int  $count  Number of identical queries executed
     * @param  float  $totalTimeMs  Total time spent on these queries
     * @param  ?string  $table  Target table name
     * @param  string  $normalizedSql  The SQL pattern (with values masked)
     * @param  float  $avgTimeMs  Average time per query
     * @param  array<int, array<string, mixed>>  $examples  Sample query records
     */
    public function __construct(
        public readonly string $hash,
        public readonly string $batchId,
        public readonly int $count,
        public readonly float $totalTimeMs,
        public readonly ?string $table,
        public readonly string $normalizedSql,
        public readonly float $avgTimeMs,
        public readonly array $examples,
    ) {}

    /**
     * Convert to array for serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'hash' => $this->hash,
            'batch_id' => $this->batchId,
            'count' => $this->count,
            'total_time_ms' => $this->totalTimeMs,
            'table' => $this->table,
            'normalized_sql' => $this->normalizedSql,
            'avg_time_ms' => $this->avgTimeMs,
            'examples' => $this->examples,
        ];
    }

    /**
     * Get summary description.
     */
    public function description(): string
    {
        return "{$this->count}× queries on '{$this->table}': {$this->normalizedSql}";
    }
}
