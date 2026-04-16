<?php

declare(strict_types=1);

namespace Mateffy\Laraperf\Pest;

use Mateffy\Laraperf\Testing\PerformanceCapture;
use Mateffy\Laraperf\Testing\PerformanceResult;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * Trait providing performance testing methods accessible via $this in tests.
 *
 * This trait is automatically registered with Pest tests and provides:
 * - $this->performance() - access to the current test's performance results
 * - $this->startPerformanceCapture() - manual capture control
 * - $this->stopPerformanceCapture() - manual capture control
 */
trait PerformanceTestingTrait
{
    /**
     * Performance capture instance for this test.
     */
    protected ?PerformanceCapture $performanceCapture = null;

    /**
     * Performance results from the test execution.
     */
    protected ?PerformanceResult $performanceResult = null;

    /**
     * Performance constraints set via test()->maxQueryCount() etc.
     *
     * @var array<string, mixed>
     */
    protected array $performanceConstraints = [];

    /**
     * Get the performance result for this test.
     *
     * @throws \RuntimeException if performance capture hasn't run
     */
    public function performance(): PerformanceResult
    {
        if ($this->performanceResult === null) {
            throw new \RuntimeException(
                'No performance data available. '.
                'Make sure performance testing is enabled or call $this->startPerformanceCapture() manually.'
            );
        }

        return $this->performanceResult;
    }

    /**
     * Check if performance result is available (or capture is in progress).
     */
    public function hasPerformanceData(): bool
    {
        return $this->performanceResult !== null
            || ($this->performanceCapture !== null && $this->performanceCapture->isActive());
    }

    /**
     * Manually start a performance capture session.
     *
     * This is useful when you want to measure only a specific part of the test,
     * rather than the entire test execution.
     */
    public function startPerformanceCapture(): PerformanceCapture
    {
        if ($this->performanceCapture !== null && $this->performanceCapture->isActive()) {
            throw new \RuntimeException('Performance capture already started');
        }

        $this->performanceCapture = new PerformanceCapture;
        $this->performanceCapture->start();

        return $this->performanceCapture;
    }

    /**
     * Stop the current performance capture and return results.
     *
     * @param  mixed  $result  Optional result to include in PerformanceResult
     */
    public function stopPerformanceCapture(mixed $result = null): PerformanceResult
    {
        if ($this->performanceCapture === null || ! $this->performanceCapture->isActive()) {
            throw new \RuntimeException('No active performance capture to stop');
        }

        $this->performanceResult = $this->performanceCapture->stop($result);

        return $this->performanceResult;
    }

    /**
     * Measure a callback and store results.
     *
     * Shortcut for manual capture + measure pattern.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return PerformanceResult<T>
     */
    public function measurePerformance(callable $callback): PerformanceResult
    {
        $this->startPerformanceCapture();

        $result = $callback();

        return $this->stopPerformanceCapture($result);
    }

    /**
     * Set a performance constraint (used by TestCall extensions).
     *
     * @internal Used by test()->maxQueryCount() etc.
     */
    public function setPerformanceConstraint(string $key, mixed $value): void
    {
        $this->performanceConstraints[$key] = $value;
    }

    /**
     * Get a performance constraint value.
     *
     * @internal Used by validation logic.
     */
    public function getPerformanceConstraint(string $key): mixed
    {
        return $this->performanceConstraints[$key] ?? null;
    }

    /**
     * Get all performance constraints.
     *
     * @return array<string, mixed>
     */
    public function getAllPerformanceConstraints(): array
    {
        return $this->performanceConstraints;
    }

    /**
     * Validate performance constraints against results.
     *
     * @throws ExpectationFailedException on constraint violation
     *
     * @internal Called by afterEach hook.
     */
    public function validatePerformanceConstraints(): void
    {
        if ($this->performanceResult === null || empty($this->performanceConstraints)) {
            return;
        }

        $result = $this->performanceResult;
        $errors = [];

        // Validate max query count
        $maxQueries = $this->performanceConstraints['max_queries'] ?? null;
        if ($maxQueries !== null && $result->queryCount() > $maxQueries) {
            $errors[] = "Query count {$result->queryCount()} exceeds maximum {$maxQueries}";
        }

        // Validate max query duration
        $maxQueryDuration = $this->performanceConstraints['max_query_duration_ms'] ?? null;
        if ($maxQueryDuration !== null && $result->slowestQueryTimeMs() > $maxQueryDuration) {
            $errors[] = "Slowest query {$result->slowestQueryTimeMs()}ms exceeds maximum {$maxQueryDuration}ms";
        }

        // Validate max total duration
        $maxDuration = $this->performanceConstraints['max_duration_ms'] ?? null;
        if ($maxDuration !== null && $result->durationMs() > $maxDuration) {
            $errors[] = "Duration {$result->durationMs()}ms exceeds maximum {$maxDuration}ms";
        }

        // Validate memory
        $maxMemory = $this->performanceConstraints['max_memory_bytes'] ?? null;
        if ($maxMemory !== null) {
            $maxMemoryBytes = is_string($maxMemory) ? self::parseMemoryString($maxMemory) : $maxMemory;
            if ($result->netMemoryBytes() > $maxMemoryBytes) {
                $errors[] = "Memory usage {$result->netMemoryHuman()} exceeds maximum {$maxMemory}";
            }
        }

        // Validate N+1
        $maxN1 = $this->performanceConstraints['max_n1_candidates'] ?? null;
        $n1Threshold = $this->performanceConstraints['n1_threshold'] ?? 3;
        if ($maxN1 !== null && $result->n1Count($n1Threshold) > $maxN1) {
            $errors[] = "N+1 candidate count {$result->n1Count($n1Threshold)} exceeds maximum {$maxN1}";
        }

        if (! empty($errors)) {
            throw new ExpectationFailedException(
                "Performance constraints violated:\n- ".implode("\n- ", $errors)
            );
        }
    }

    /**
     * Parse memory string like "10M" or "512KB" to bytes.
     */
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
