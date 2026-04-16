<?php

declare(strict_types=1);

namespace Mateffy\Laraperf\Pest;

use Closure;
use Mateffy\Laraperf\Testing\PerformanceResult;
use Pest\PendingCalls\TestCall;
use PHPUnit\Framework\TestCase;

/**
 * Get the current test's performance result.
 *
 * Shortcut function for accessing $this->performance() in tests.
 *
 * @throws \RuntimeException if no performance data available
 */
function perf(): PerformanceResult
{
    /** @var TestCase $test */
    $test = test();

    if (! method_exists($test, 'performance')) {
        throw new \RuntimeException('Performance testing trait not registered');
    }

    return $test->performance();
}

/**
 * Check if performance data is available for the current test.
 */
function has_perf(): bool
{
    /** @var TestCase $test */
    $test = test();

    if (! method_exists($test, 'hasPerformanceData')) {
        return false;
    }

    return $test->hasPerformanceData();
}

/**
 * Measure a callback and return performance results.
 *
 * This is the Pest-friendly wrapper around the measure() function,
 * ensuring proper integration with the test context.
 *
 * @template T
 *
 * @param  Closure(): T  $callback
 * @return PerformanceResult<T>
 */
function measure(Closure $callback): PerformanceResult
{
    /** @var TestCase $test */
    $test = test();

    if (method_exists($test, 'measurePerformance')) {
        return $test->measurePerformance($callback);
    }

    // Fallback to global measure function
    return \Mateffy\Laraperf\Testing\measure($callback);
}

// -------------------------------------------------------------------------
// TestCall Extensions
// -------------------------------------------------------------------------

/**
 * Extend Pest's test() with performance constraint methods.
 *
 * These methods work by setting constraints that are validated in afterEach.
 */
if (method_exists(TestCall::class, 'extend')) {
    // Maximum number of queries
    TestCall::extend('maxQueryCount', function (int|Closure $limit) {
        return $this->with(['_perf_constraints' => array_merge(
            $this->data['_perf_constraints'] ?? [],
            ['max_queries' => $limit]
        )]);
    });

    // Maximum duration for any single query (ms)
    TestCall::extend('maxQueryDuration', function (float|Closure $ms) {
        return $this->with(['_perf_constraints' => array_merge(
            $this->data['_perf_constraints'] ?? [],
            ['max_query_duration_ms' => $ms]
        )]);
    });

    // Maximum total test duration (ms)
    TestCall::extend('maxTotalDuration', function (float|Closure $ms) {
        return $this->with(['_perf_constraints' => array_merge(
            $this->data['_perf_constraints'] ?? [],
            ['max_duration_ms' => $ms]
        )]);
    });

    // Alternative naming (alias)
    TestCall::extend('maxDuration', function (float|Closure $ms) {
        return $this->maxTotalDuration($ms);
    });

    // Maximum memory usage (string like "10M" or bytes)
    TestCall::extend('maxMemory', function (string|int|Closure $limit) {
        return $this->with(['_perf_constraints' => array_merge(
            $this->data['_perf_constraints'] ?? [],
            ['max_memory_bytes' => $limit]
        )]);
    });

    // Maximum N+1 candidate count
    TestCall::extend('maxN1Candidates', function (int|Closure $limit, int $threshold = 3) {
        return $this->with(['_perf_constraints' => array_merge(
            $this->data['_perf_constraints'] ?? [],
            ['max_n1_candidates' => $limit, 'n1_threshold' => $threshold]
        )]);
    });

    // Require zero N+1 patterns (convenience method)
    TestCall::extend('noN1Patterns', function (int $threshold = 3) {
        return $this->maxN1Candidates(0, $threshold);
    });
}
