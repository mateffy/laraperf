<?php

declare(strict_types=1);

namespace Mateffy\Laraperf\Pest\Expectations;

use Mateffy\Laraperf\Testing\PerformanceResult;
use Mateffy\Laraperf\Testing\QueryRecord;
use Pest\Expectation;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * Fluent expectation API for performance assertions.
 *
 * Provides the expect()->performance()->queries()->count()->toBeLessThan() chain.
 */
class PerformanceExpectation
{
    protected PerformanceResult $result;

    public function __construct(PerformanceResult $result)
    {
        $this->result = $result;
    }

    /**
     * Access query-related assertions.
     */
    public function queries(): QueryExpectation
    {
        return new QueryExpectation($this->result->queries);
    }

    /**
     * Access duration assertions.
     */
    public function duration(): NumericExpectation
    {
        return new NumericExpectation(
            $this->result->durationMs(),
            'duration',
            fn () => "Duration was {$this->result->durationMs()}ms"
        );
    }

    /**
     * Access memory assertions.
     */
    public function memory(): NumericExpectation
    {
        return new NumericExpectation(
            $this->result->netMemoryBytes(),
            'memory',
            fn () => "Memory usage was {$this->result->netMemoryHuman()}",
            true // isMemory = true for human formatting
        );
    }

    /**
     * Access N+1 candidate assertions.
     */
    public function n1(int $threshold = 3): NumericExpectation
    {
        $count = $this->result->n1Count($threshold);

        return new NumericExpectation(
            $count,
            "N+1 candidates (threshold: {$threshold})",
            fn () => "Found {$count} N+1 patterns"
        );
    }

    /**
     * Assert no N+1 patterns exist.
     *
     * @throws ExpectationFailedException
     */
    public function toHaveNoN1(int $threshold = 3): void
    {
        $count = $this->result->n1Count($threshold);

        if ($count > 0) {
            $candidates = $this->result->n1Candidates($threshold);
            $details = $candidates->map(fn (N1Candidate $c) => "{$c->count}× {$c->table}: {$c->normalizedSql}")->implode("\n");

            throw new ExpectationFailedException(
                "Expected no N+1 patterns (threshold: {$threshold}), but found {$count}:\n{$details}"
            );
        }
    }

    /**
     * Assert that no slow queries exist.
     *
     * @throws ExpectationFailedException
     */
    public function toHaveNoSlowQueries(float $thresholdMs = 100): void
    {
        $slow = $this->result->slowQueries($thresholdMs);

        if ($slow->isNotEmpty()) {
            $details = $slow->map(fn (QueryRecord $q) => "{$q->timeHuman()}: {$q->rawSql}"
            )->implode("\n");

            throw new ExpectationFailedException(
                "Expected no queries slower than {$thresholdMs}ms, but found {$slow->count()}:\n{$details}"
            );
        }
    }

    /**
     * Assert the raw result value.
     *
     * @return Expectation<PerformanceResult>
     */
    public function result(): Expectation
    {
        return expect($this->result->result);
    }

    /**
     * Assert performance summary matches expectations.
     *
     * @param  array<string, mixed>  $constraints
     *
     * @throws ExpectationFailedException
     */
    public function toMeetConstraints(array $constraints): void
    {
        $errors = [];

        if (isset($constraints['max_queries'])) {
            /** @var int $maxQueries */
            $maxQueries = $constraints['max_queries'];
            if ($this->result->queryCount() > $maxQueries) {
                $errors[] = "Query count {$this->result->queryCount()} > {$maxQueries}";
            }
        }

        if (isset($constraints['max_query_duration_ms'])) {
            /** @var float $maxQueryDuration */
            $maxQueryDuration = $constraints['max_query_duration_ms'];
            if ($this->result->slowestQueryTimeMs() > $maxQueryDuration) {
                $errors[] = "Slowest query {$this->result->slowestQueryTimeMs()}ms > {$maxQueryDuration}ms";
            }
        }

        if (isset($constraints['max_duration_ms'])) {
            /** @var float $maxDuration */
            $maxDuration = $constraints['max_duration_ms'];
            if ($this->result->durationMs() > $maxDuration) {
                $errors[] = "Duration {$this->result->durationMs()}ms > {$maxDuration}ms";
            }
        }

        if (isset($constraints['max_memory'])) {
            $maxBytes = is_string($constraints['max_memory'])
                ? self::parseMemoryString($constraints['max_memory'])
                : (int) $constraints['max_memory'];

            if ($this->result->netMemoryBytes() > $maxBytes) {
                $errors[] = "Memory {$this->result->netMemoryHuman()} > {$constraints['max_memory']}";
            }
        }

        if (isset($constraints['max_n1_candidates'])) {
            /** @var int $maxN1 */
            $maxN1 = $constraints['max_n1_candidates'];
            /** @var int $threshold */
            $threshold = $constraints['n1_threshold'] ?? 3;
            $count = $this->result->n1Count($threshold);
            if ($count > $maxN1) {
                $errors[] = "N+1 candidates {$count} > {$maxN1} (threshold: {$threshold})";
            }
        }

        if (! empty($errors)) {
            throw new ExpectationFailedException(
                "Performance constraints not met:\n- ".implode("\n- ", $errors)
            );
        }
    }

    /**
     * Get the underlying PerformanceResult for advanced assertions.
     */
    public function getResult(): PerformanceResult
    {
        return $this->result;
    }

    private static function parseMemoryString(string $str): int
    {
        $str = strtoupper(trim($str));
        $value = (int) $str;
        $unit = preg_replace('/\d/', '', $str);

        return match ($unit) {
            'B' => $value,
            'K', 'KB' => $value * 1024,
            'M', 'MB' => $value * 1024 * 1024,
            'G', 'GB' => $value * 1024 * 1024 * 1024,
            default => $value,
        };
    }
}
