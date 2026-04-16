<?php

declare(strict_types=1);

namespace Mateffy\Laraperf\Testing;

use Illuminate\Support\Collection;
use Mateffy\Laraperf\Analysis\N1Detector;
use Throwable;

/**
 * Immutable data transfer object containing comprehensive performance metrics.
 *
 * This class captures everything useful for performance analysis:
 * - Execution timing (total duration, timeline events)
 * - Memory usage (peak, net increase)
 * - Query analysis (count, duration, breakdowns, N+1 detection)
 * - Full result from the measured operation
 *
 * Designed to be testable, serializable, and usable in any context
 * (tests, CLI, tinker, production monitoring).
 */
class PerformanceResult
{
    /**
     * Timeline events with timestamps for detailed analysis.
     *
     * @var array<int, array{label: string, timestamp: float, memory: int, metadata: array}>
     */
    public readonly array $timeline;

    /**
     * @param  mixed  $result  The actual return value from the measured callback
     * @param  float  $startTime  Microtime when capture started
     * @param  float  $endTime  Microtime when capture ended
     * @param  int  $startMemory  Memory usage at start (bytes)
     * @param  int  $peakMemory  Peak memory usage during capture (bytes)
     * @param  Collection<int, QueryRecord>  $queries  All captured queries
     * @param  ?Throwable  $exception  Any exception thrown during execution
     * @param  array  $timeline  Sequential timeline events with metadata
     * @param  string  $sessionId  Unique identifier for this capture session
     */
    public function __construct(
        public readonly mixed $result,
        public readonly float $startTime,
        public readonly float $endTime,
        public readonly int $startMemory,
        public readonly int $peakMemory,
        public readonly Collection $queries,
        public readonly ?Throwable $exception = null,
        array $timeline = [],
        public readonly string $sessionId = '',
    ) {
        $this->timeline = $this->normalizeTimeline($timeline, $startTime, $endTime);
    }

    // -------------------------------------------------------------------------
    // Basic Metrics
    // -------------------------------------------------------------------------

    /**
     * Total execution duration in milliseconds.
     */
    public function durationMs(): float
    {
        return round(($this->endTime - $this->startTime) * 1000, 3);
    }

    /**
     * Total execution duration in seconds.
     */
    public function durationSeconds(): float
    {
        return $this->endTime - $this->startTime;
    }

    /**
     * Peak memory usage during execution (bytes).
     */
    public function peakMemoryBytes(): int
    {
        return $this->peakMemory;
    }

    /**
     * Net memory increase during execution (bytes).
     * This is usually more useful than peak for isolated operations.
     */
    public function netMemoryBytes(): int
    {
        return $this->peakMemory - $this->startMemory;
    }

    /**
     * Human-readable memory string (e.g., "2.4 MB").
     */
    public function peakMemoryHuman(): string
    {
        return $this->formatBytes($this->peakMemory);
    }

    /**
     * Human-readable net memory increase.
     */
    public function netMemoryHuman(): string
    {
        return $this->formatBytes($this->netMemoryBytes());
    }

    // -------------------------------------------------------------------------
    // Query Analysis
    // -------------------------------------------------------------------------

    /**
     * Total number of queries executed.
     */
    public function queryCount(): int
    {
        return $this->queries->count();
    }

    /**
     * Total time spent in all queries (milliseconds).
     */
    public function totalQueryTimeMs(): float
    {
        return $this->queries->sum('time_ms');
    }

    /**
     * Average query time (milliseconds).
     */
    public function averageQueryTimeMs(): float
    {
        $count = $this->queryCount();

        return $count > 0 ? round($this->totalQueryTimeMs() / $count, 3) : 0;
    }

    /**
     * Slowest single query time (milliseconds).
     */
    public function slowestQueryTimeMs(): float
    {
        return $this->queries->max('time_ms') ?? 0;
    }

    /**
     * Get queries slower than a threshold.
     *
     * @return Collection<int, QueryRecord>
     */
    public function slowQueries(float $thresholdMs = 100): Collection
    {
        return $this->queries->where('time_ms', '>', $thresholdMs);
    }

    /**
     * Get queries by table name.
     *
     * @return Collection<int, QueryRecord>
     */
    public function queriesByTable(string $table): Collection
    {
        return $this->queries->where('table', $table);
    }

    /**
     * Get queries by operation type (SELECT, INSERT, etc).
     *
     * @return Collection<int, QueryRecord>
     */
    public function queriesByOperation(string $operation): Collection
    {
        return $this->queries->where('operation', strtoupper($operation));
    }

    /**
     * Get queries by connection name.
     *
     * @return Collection<int, QueryRecord>
     */
    public function queriesByConnection(string $connection): Collection
    {
        return $this->queries->where('connection', $connection);
    }

    /**
     * Get unique table names that were queried.
     *
     * @return array<string>
     */
    public function tablesAccessed(): array
    {
        return $this->queries->pluck('table')->unique()->filter()->values()->toArray();
    }

    // -------------------------------------------------------------------------
    // N+1 Detection
    // -------------------------------------------------------------------------

    /**
     * Detect N+1 query patterns with configurable threshold.
     *
     * @return Collection<int, N1Candidate>
     */
    public function n1Candidates(int $threshold = 3): Collection
    {
        $detector = new N1Detector;

        return $detector->detect($this->queries, $threshold);
    }

    /**
     * Count of N+1 query patterns detected.
     */
    public function n1Count(int $threshold = 3): int
    {
        return $this->n1Candidates($threshold)->count();
    }

    /**
     * Check if any N+1 patterns were detected.
     */
    public function hasN1Patterns(int $threshold = 3): bool
    {
        return $this->n1Count($threshold) > 0;
    }

    // -------------------------------------------------------------------------
    // Timeline Analysis
    // -------------------------------------------------------------------------

    /**
     * Get events between two timestamps.
     *
     * @return array<int, array{label: string, timestamp: float, memory: int, metadata: array}>
     */
    public function timelineBetween(float $start, float $end): array
    {
        return array_filter($this->timeline, fn ($event) => $event['timestamp'] >= $start && $event['timestamp'] <= $end
        );
    }

    /**
     * Get memory delta between two timeline events.
     */
    public function memoryDelta(string $fromLabel, string $toLabel): ?int
    {
        $from = $this->findTimelineEvent($fromLabel);
        $to = $this->findTimelineEvent($toLabel);

        if ($from === null || $to === null) {
            return null;
        }

        return $to['memory'] - $from['memory'];
    }

    /**
     * Get duration between two timeline events.
     */
    public function durationBetween(string $fromLabel, string $toLabel): ?float
    {
        $from = $this->findTimelineEvent($fromLabel);
        $to = $this->findTimelineEvent($toLabel);

        if ($from === null || $to === null) {
            return null;
        }

        return round(($to['timestamp'] - $from['timestamp']) * 1000, 3);
    }

    // -------------------------------------------------------------------------
    // Utility
    // -------------------------------------------------------------------------

    /**
     * Summarize results for quick human review.
     */
    public function summary(): array
    {
        return [
            'duration_ms' => $this->durationMs(),
            'memory_peak' => $this->peakMemoryHuman(),
            'memory_net' => $this->netMemoryHuman(),
            'query_count' => $this->queryCount(),
            'query_time_ms' => $this->totalQueryTimeMs(),
            'n1_candidates' => $this->n1Count(),
            'tables_accessed' => $this->tablesAccessed(),
            'had_exception' => $this->exception !== null,
        ];
    }

    /**
     * Convert to JSON-serializable array.
     */
    public function toArray(): array
    {
        return [
            'session_id' => $this->sessionId,
            'duration_ms' => $this->durationMs(),
            'duration_seconds' => $this->durationSeconds(),
            'memory' => [
                'peak_bytes' => $this->peakMemoryBytes(),
                'peak_human' => $this->peakMemoryHuman(),
                'net_bytes' => $this->netMemoryBytes(),
                'net_human' => $this->netMemoryHuman(),
            ],
            'queries' => [
                'count' => $this->queryCount(),
                'total_time_ms' => $this->totalQueryTimeMs(),
                'average_time_ms' => $this->averageQueryTimeMs(),
                'slowest_time_ms' => $this->slowestQueryTimeMs(),
            ],
            'n1' => [
                'count' => $this->n1Count(),
                'candidates' => $this->n1Candidates()->toArray(),
            ],
            'tables' => $this->tablesAccessed(),
            'timeline' => $this->timeline,
            'exception' => $this->exception ? [
                'class' => get_class($this->exception),
                'message' => $this->exception->getMessage(),
            ] : null,
        ];
    }

    /**
     * Export to JSON string.
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    private function normalizeTimeline(array $timeline, float $startTime, float $endTime): array
    {
        // Ensure start and end events exist
        $events = $timeline;

        if (empty($events) || $events[0]['label'] !== 'start') {
            array_unshift($events, [
                'label' => 'start',
                'timestamp' => $startTime,
                'memory' => $this->startMemory,
                'metadata' => [],
            ]);
        }

        $last = end($events);
        if ($last === false || $last['label'] !== 'end') {
            $events[] = [
                'label' => 'end',
                'timestamp' => $endTime,
                'memory' => $this->peakMemory,
                'metadata' => [],
            ];
        }

        return array_values($events);
    }

    private function findTimelineEvent(string $label): ?array
    {
        foreach ($this->timeline as $event) {
            if ($event['label'] === $label) {
                return $event;
            }
        }

        return null;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, 2).' '.$units[$unitIndex];
    }
}
