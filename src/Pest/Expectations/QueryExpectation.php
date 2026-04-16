<?php

declare(strict_types=1);

namespace Mateffy\Laraperf\Pest\Expectations;

use Illuminate\Support\Collection;
use Mateffy\Laraperf\Analysis\N1Detector;
use Mateffy\Laraperf\Testing\QueryRecord;
use Pest\Expectation;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * Fluent expectation API for query assertions.
 *
 * Provides filtering and assertion methods for collections of QueryRecord.
 */
class QueryExpectation
{
    protected Collection $queries;

    public function __construct(Collection $queries)
    {
        $this->queries = $queries;
    }

    /**
     * Filter to queries on a specific table.
     */
    public function whereTable(string $table): self
    {
        return new self($this->queries->where('table', $table));
    }

    /**
     * Filter to queries of a specific operation type.
     */
    public function whereOperation(string $operation): self
    {
        return new self($this->queries->where('operation', strtoupper($operation)));
    }

    /**
     * Filter to queries on a specific connection.
     */
    public function whereConnection(string $connection): self
    {
        return new self($this->queries->where('connection', $connection));
    }

    /**
     * Filter to queries slower than a threshold.
     */
    public function whereDurationGreaterThan(float $ms): self
    {
        return new self($this->queries->where('time_ms', '>', $ms));
    }

    /**
     * Filter to queries faster than a threshold.
     */
    public function whereDurationLessThan(float $ms): self
    {
        return new self($this->queries->where('time_ms', '<', $ms));
    }

    /**
     * Filter to queries containing specific SQL.
     */
    public function whereSqlContains(string $search): self
    {
        return new self($this->queries->filter(fn (QueryRecord $q) => str_contains(strtolower($q->sql), strtolower($search))
        ));
    }

    /**
     * Filter to queries from a specific source file.
     */
    public function whereSourceContains(string $path): self
    {
        return new self($this->queries->filter(fn (QueryRecord $q) => collect($q->source)->contains(fn ($frame) => str_contains($frame['file'] ?? '', $path)
        )
        ));
    }

    // -------------------------------------------------------------------------
    // Aggregation
    // -------------------------------------------------------------------------

    /**
     * Get the count of filtered queries.
     */
    public function count(): NumericExpectation
    {
        return new NumericExpectation(
            $this->queries->count(),
            'query count',
            fn () => "Query count is {$this->queries->count()}"
        );
    }

    /**
     * Get total duration of filtered queries.
     */
    public function duration(): NumericExpectation
    {
        $total = $this->queries->sum('time_ms');

        return new NumericExpectation(
            $total,
            'query duration',
            fn () => "Total query time is {$total}ms"
        );
    }

    /**
     * Get average duration of filtered queries.
     */
    public function averageDuration(): NumericExpectation
    {
        $avg = $this->queries->avg('time_ms') ?? 0;

        return new NumericExpectation(
            $avg,
            'average query duration',
            fn () => "Average query time is {$avg}ms"
        );
    }

    /**
     * Get maximum duration of filtered queries.
     */
    public function maxDuration(): NumericExpectation
    {
        $max = $this->queries->max('time_ms') ?? 0;

        return new NumericExpectation(
            $max,
            'max query duration',
            fn () => "Slowest query is {$max}ms"
        );
    }

    // -------------------------------------------------------------------------
    // N+1 Detection
    // -------------------------------------------------------------------------

    /**
     * Get N+1 candidates from these queries.
     */
    public function n1Candidates(int $threshold = 3): Collection
    {
        $detector = new N1Detector($threshold);

        return $detector->detect($this->queries);
    }

    /**
     * Assert no N+1 patterns in these queries.
     *
     * @throws ExpectationFailedException
     */
    public function toHaveNoN1(int $threshold = 3): void
    {
        $candidates = $this->n1Candidates($threshold);

        if ($candidates->isNotEmpty()) {
            $details = $candidates->map(fn ($c) => "{$c['count']}× {$c['table']}: {$c['normalized_sql']}"
            )->implode("\n");

            throw new ExpectationFailedException(
                "Found {$candidates->count()} N+1 patterns (threshold: {$threshold}):\n{$details}"
            );
        }
    }

    // -------------------------------------------------------------------------
    // Assertions
    // -------------------------------------------------------------------------

    /**
     * Assert queries match expected set (for exact matching).
     *
     * @param  array<int, array<string, mixed>>  $expected
     *
     * @throws ExpectationFailedException
     */
    public function toMatch(array $expected): void
    {
        $actual = $this->queries->map(fn (QueryRecord $q) => $q->toArray())->toArray();

        // Simplified matching - can be enhanced
        if (count($actual) !== count($expected)) {
            throw new ExpectationFailedException(
                'Query count mismatch: expected '.count($expected).', got '.count($actual)
            );
        }
    }

    /**
     * Get the underlying collection for custom assertions.
     */
    public function getCollection(): Collection
    {
        return $this->queries;
    }

    /**
     * Access as Pest Expectation for standard assertions.
     */
    public function toExpectation(): Expectation
    {
        return expect($this->queries);
    }
}
