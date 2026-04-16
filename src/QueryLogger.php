<?php

declare(strict_types=1);

namespace Mateffy\Laraperf;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Mateffy\Laraperf\Analysis\QueryNormalizer;
use Mateffy\Laraperf\Storage\PerfStore;
use Mateffy\Laraperf\Testing\PerformanceSessionManager;
use Mateffy\Laraperf\Testing\QueryRecord;

/**
 * Listens to Illuminate\Database\Events\QueryExecuted and persists every
 * query to the active perf session file.
 *
 * Registration is intentionally lazy: the listener is only attached when
 * a session is explicitly started via start(). This means zero overhead
 * for normal production requests.
 *
 * Stack trace filtering strips vendor/framework frames so that the reported
 * source points into app/ or packages/ code — exactly what you need when
 * Filament generates dozens of "invisible" queries.
 */
class QueryLogger
{
    protected ?string $session_id = null;

    protected string $batch_id;

    protected bool $registered = false;

    /** Frames from these path fragments are excluded from stack traces. */
    /** @var list<string> */
    protected array $excluded_prefixes = [
        '/vendor/',
        '/packages/perf/',
    ];

    /** Frames from these path fragments are always included (override exclusions). */
    /** @var list<string> */
    protected array $included_prefixes = [
        '/app/',
        '/packages/',
    ];

    public function __construct(
        protected PerfStore $store,
        protected QueryNormalizer $normalizer,
    ) {
        $this->batch_id = $this->newBatchId();
    }

    // -------------------------------------------------------------------------
    // Lifecycle
    // -------------------------------------------------------------------------

    public function start(string $session_id): void
    {
        $this->session_id = $session_id;

        if (! $this->registered) {
            DB::listen(function (QueryExecuted $event) {
                $this->handleQuery($event);
            });

            $this->registered = true;
        }
    }

    public function stop(): void
    {
        $this->session_id = null;
    }

    public function isActive(): bool
    {
        return $this->session_id !== null;
    }

    // -------------------------------------------------------------------------
    // Handler
    // -------------------------------------------------------------------------

    protected function handleQuery(QueryExecuted $event): void
    {
        // Build the query record
        $raw_sql = $event->toRawSql();
        $source = $this->captureSource();

        $record = [
            'sql' => $event->sql,
            'raw_sql' => $raw_sql,
            'bindings' => $event->bindings,
            'time_ms' => round((float) $event->time, 3),
            'connection' => $event->connectionName,
            'driver' => $event->connection->getDriverName(),
            'operation' => $this->normalizer->extractOperation($event->sql),
            'table' => $this->normalizer->extractTable($event->sql),
            'hash' => $this->normalizer->hash($event->sql),
            'batch_id' => $this->batch_id,
            'source' => $source,
            'captured_at' => now()->toIso8601String(),
        ];

        // Route to file-based session (existing CLI behavior)
        if ($this->session_id) {
            $this->store->appendQuery($this->session_id, $record);
        }

        // NEW: Route to active testing sessions
        // This enables the measure() and Pest integration
        if (PerformanceSessionManager::isActive()) {
            $queryRecord = new QueryRecord(
                sql: $record['sql'],
                rawSql: $record['raw_sql'],
                bindings: $record['bindings'],
                time_ms: $record['time_ms'],
                connection: $record['connection'],
                driver: $record['driver'],
                operation: $record['operation'],
                table: $record['table'],
                hash: $record['hash'],
                batch_id: $record['batch_id'],
                source: $record['source'],
                captured_at: $record['captured_at'],
            );

            PerformanceSessionManager::routeQuery($queryRecord);
        }
    }

    // -------------------------------------------------------------------------
    // Batch management
    //
    // Each PHP-FPM request / CLI invocation gets its own batch_id so that
    // N+1 detection can group queries correctly by request context.
    // -------------------------------------------------------------------------

    public function rotateBatch(): void
    {
        $this->batch_id = $this->newBatchId();
    }

    protected function newBatchId(): string
    {
        return substr(md5(uniqid((string) microtime(true), true)), 0, 12);
    }

    // -------------------------------------------------------------------------
    // Stack trace capture
    // -------------------------------------------------------------------------

    /**
     * Capture a filtered stack trace pointing to application code.
     *
     * @return array<int, array{file: string, line: int, function: string, class: string|null}>
     */
    protected function captureSource(): array
    {
        $frames = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 30);
        $base = base_path();

        $app_frames = [];

        foreach ($frames as $frame) {
            $file = $frame['file'] ?? '';

            if (! $file) {
                continue;
            }

            // Make path relative to base so it's readable without the full server path
            $relative = str_replace($base, '', $file);

            // Skip vendor / laraperf-package frames
            $excluded = false;

            foreach ($this->excluded_prefixes as $prefix) {
                if (str_contains($relative, (string) $prefix)) {
                    $excluded = true;
                    break;
                }
            }

            if ($excluded) {
                // But allow if it matches an explicitly included prefix
                $included = false;

                foreach ($this->included_prefixes as $prefix) {
                    if (str_contains($relative, (string) $prefix)) {
                        $included = true;
                        break;
                    }
                }

                if (! $included) {
                    continue;
                }
            }

            $app_frames[] = [
                'file' => $relative,
                'line' => (int) ($frame['line'] ?? 0),
                'function' => $frame['function'],
                'class' => $frame['class'] ?? null,
            ];

            if (count($app_frames) >= 5) {
                break;
            }
        }

        return $app_frames;
    }
}
