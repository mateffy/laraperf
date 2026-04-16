<?php

declare(strict_types=1);

namespace Mateffy\Laraperf\Testing;

use Closure;
use Throwable;

/**
 * Active performance capture session for measuring a single operation.
 *
 * This class is responsible for:
 * - Starting/stopping the measurement timer
 * - Recording memory usage
 * - Capturing all database queries during execution
 * - Building the final PerformanceResult
 *
 * Works with both in-memory capture (for tests) and file-based sessions (for CLI).
 */
class PerformanceCapture
{
    protected string $sessionId;

    protected float $startTime;

    protected int $startMemory;

    /** @var array<int, QueryRecord> */
    protected array $queries = [];

    /** @var array<int, array{label: string, timestamp: float, memory: int, metadata: array}> */
    protected array $timeline = [];

    protected bool $active = false;

    public function __construct()
    {
        $this->sessionId = 'perf_'.uniqid('', true);
    }

    /**
     * Start capturing performance metrics.
     *
     * Registers this capture session with the global manager and attaches
     * database listeners if needed.
     */
    public function start(): self
    {
        if ($this->active) {
            throw new \RuntimeException('Performance capture already started');
        }

        $this->active = true;
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage(true);

        // Mark timeline start
        $this->timeline[] = [
            'label' => 'start',
            'timestamp' => $this->startTime,
            'memory' => $this->startMemory,
            'metadata' => [],
        ];

        // Register with global session manager
        PerformanceSessionManager::register($this->sessionId, $this);

        return $this;
    }

    /**
     * Stop capturing and return the results.
     *
     * @param  mixed  $result  The return value from the measured operation
     */
    public function stop(mixed $result = null, ?Throwable $exception = null): PerformanceResult
    {
        if (! $this->active) {
            throw new \RuntimeException('Performance capture not started');
        }

        $endTime = microtime(true);
        $peakMemory = memory_get_peak_usage(true);

        // Mark timeline end
        $this->timeline[] = [
            'label' => 'end',
            'timestamp' => $endTime,
            'memory' => $peakMemory,
            'metadata' => [],
        ];

        // Unregister from global manager
        PerformanceSessionManager::unregister($this->sessionId);

        $this->active = false;

        return new PerformanceResult(
            result: $result,
            startTime: $this->startTime,
            endTime: $endTime,
            startMemory: $this->startMemory,
            peakMemory: $peakMemory,
            queries: collect($this->queries),
            exception: $exception,
            timeline: $this->timeline,
            sessionId: $this->sessionId,
        );
    }

    /**
     * Record a query captured during execution.
     */
    public function recordQuery(QueryRecord $query): void
    {
        if (! $this->active) {
            return;
        }

        $this->queries[] = $query;
    }

    /**
     * Add a timeline event with a label.
     *
     * Useful for marking specific phases of an operation.
     */
    public function mark(string $label, array $metadata = []): void
    {
        if (! $this->active) {
            return;
        }

        $this->timeline[] = [
            'label' => $label,
            'timestamp' => microtime(true),
            'memory' => memory_get_usage(true),
            'metadata' => $metadata,
        ];
    }

    /**
     * Execute a callback and capture its performance.
     *
     * This is the main entry point for measuring operations.
     */
    public function measure(Closure $callback): PerformanceResult
    {
        $this->start();

        $result = null;
        $exception = null;

        try {
            $result = $callback();
        } catch (Throwable $e) {
            $exception = $e;
        }

        return $this->stop($result, $exception);
    }

    /**
     * Get the session ID for this capture.
     */
    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    /**
     * Check if capture is currently active.
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Get currently captured queries (for inspection during capture).
     *
     * @return array<int, QueryRecord>
     */
    public function getQueries(): array
    {
        return $this->queries;
    }

    /**
     * Get query count so far.
     */
    public function getQueryCount(): int
    {
        return count($this->queries);
    }
}
