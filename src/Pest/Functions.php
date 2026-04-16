<?php

declare(strict_types=1);

namespace Mateffy\Laraperf\Pest;

use Closure;
use Mateffy\Laraperf\Testing\PerformanceResult;
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

    return (bool) $test->hasPerformanceData();
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

use Pest\PendingCalls\TestCall;

/**
 * Extend Pest's test() with performance constraint methods.
 *
 * These methods work by setting constraints that are validated in afterEach.
 */
if (method_exists(TestCall::class, 'extend')) {
    TestCall::extend('maxQueryCount', function (int|Closure $limit) {
        if ($limit instanceof Closure) {
            $limit = $limit();
        }

        /** @var TestCall $testCall */
        $testCall = $this;
        $data = $testCall->data ?? [];
        $existing = is_array($data) && array_key_exists('_perf_constraints', $data) ? $data['_perf_constraints'] : [];

        return $testCall->with(['_perf_constraints' => array_merge($existing, ['max_queries' => $limit])]);
    });

    TestCall::extend('maxQueryDuration', function (float|int|Closure $ms) {
        if ($ms instanceof Closure) {
            $ms = $ms();
        }

        /** @var TestCall $testCall */
        $testCall = $this;
        $data = $testCall->data ?? [];
        $existing = is_array($data) && array_key_exists('_perf_constraints', $data) ? $data['_perf_constraints'] : [];

        return $testCall->with(['_perf_constraints' => array_merge($existing, ['max_query_duration_ms' => $ms])]);
    });

    TestCall::extend('maxTotalDuration', function (float|int|Closure $ms) {
        if ($ms instanceof Closure) {
            $ms = $ms();
        }

        /** @var TestCall $testCall */
        $testCall = $this;
        $data = $testCall->data ?? [];
        $existing = is_array($data) && array_key_exists('_perf_constraints', $data) ? $data['_perf_constraints'] : [];

        return $testCall->with(['_perf_constraints' => array_merge($existing, ['max_duration_ms' => $ms])]);
    });

    // Alternative naming (alias)
    TestCall::extend('maxDuration', function (float|int|Closure $ms) {
        if ($ms instanceof Closure) {
            $ms = $ms();
        }

        return $this->maxTotalDuration($ms);
    });

    TestCall::extend('maxMemory', function (string|int|Closure $limit) {
        if ($limit instanceof Closure) {
            $limit = $limit();
        }

        /** @var TestCall $testCall */
        $testCall = $this;
        $data = $testCall->data ?? [];
        $existing = is_array($data) && array_key_exists('_perf_constraints', $data) ? $data['_perf_constraints'] : [];

        return $testCall->with(['_perf_constraints' => array_merge($existing, ['max_memory_bytes' => $limit])]);
    });

    TestCall::extend('maxN1Candidates', function (int|Closure $limit, int $threshold = 3) {
        if ($limit instanceof Closure) {
            $limit = $limit();
        }

        /** @var TestCall $testCall */
        $testCall = $this;
        $data = $testCall->data ?? [];
        $existing = is_array($data) && array_key_exists('_perf_constraints', $data) ? $data['_perf_constraints'] : [];

        return $testCall->with(['_perf_constraints' => array_merge($existing, ['max_n1_candidates' => $limit, 'n1_threshold' => $threshold])]);
    });

    TestCall::extend('noN1Patterns', function (int $threshold = 3) {
        return $this->maxN1Candidates(0, $threshold);
    });
}
