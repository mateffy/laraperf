<?php

declare(strict_types=1);

namespace Mateffy\Laraperf\Testing;

use Closure;

/**
 * Universal performance measurement function.
 *
 * This function can be used in any context:
 * - Tests: $perf = measure(fn () => User::create([...]));
 * - Tinker: $perf = measure(fn () => HeavyJob::dispatch());
 * - CLI: $perf = measure(fn () => Artisan::call('migrate'));
 * - Production: $perf = measure(fn () => $service->process($data));
 *
 * Returns a PerformanceResult with complete metrics.
 *
 * @template T
 *
 * @param  Closure(): T  $callback  The operation to measure
 * @return PerformanceResult<T>
 */
function measure(Closure $callback): PerformanceResult
{
    $capture = new PerformanceCapture;

    return $capture->measure($callback);
}

/**
 * Start a performance capture session manually.
 *
 * Use this when you need more control over timing or want to add
 * custom timeline markers.
 */
function capture(): PerformanceCapture
{
    $capture = new PerformanceCapture;
    $capture->start();

    return $capture;
}

/**
 * Check if performance capture is currently active.
 */
function is_capturing(): bool
{
    return PerformanceSessionManager::isActive();
}

/**
 * Mark a point in the timeline (only works during active capture).
 *
 * @param  string  $label  Label for this timeline event
 * @param  array<string, mixed>  $metadata  Additional context data
 */
function timeline_mark(string $label, array $metadata = []): void
{
    $capture = PerformanceSessionManager::current();

    if ($capture !== null) {
        $capture->mark($label, $metadata);
    }
}
