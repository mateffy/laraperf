<?php

declare(strict_types=1);

namespace Mateffy\Laraperf\Commands;

use Illuminate\Console\Command;
use Mateffy\Laraperf\Analysis\N1Detector;
use Mateffy\Laraperf\Storage\PerfStore;

/**
 * Read a captured perf session and output structured analysis.
 *
 * OUTPUT TYPES
 * ────────────
 * all     — every captured query, sorted by time descending
 * slow    — queries above --threshold (default 100ms)
 * n1      — probable N+1 patterns detected across request batches
 * summary — aggregate stats: total queries, total time, slowest, N+1 count
 *
 * All output is JSON by default, making it trivially parseable by LLM agents.
 */
class PerfQueryCommand extends Command
{
    protected $signature = 'perf:query
        {--session=last    : Session ID to read. Use "last" for the most recent completed session.}
        {--type=summary    : Output type: all | slow | n1 | summary}
        {--threshold=100   : Slow query threshold in milliseconds (used by "slow" type).}
        {--limit=50        : Maximum number of records to return.}
        {--batch=          : Filter to a specific request batch ID.}
        {--connection=     : Filter to a specific DB connection name.}
        {--operation=      : Filter to a SQL operation (SELECT, INSERT, UPDATE, DELETE).}
        {--format=json     : Output format: json | table}';

    protected $description = 'Read a captured perf session and output query analysis as JSON.';

    public function __construct(
        protected PerfStore $store,
        protected N1Detector $n1_detector,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $session = $this->resolveSession();

        if (! $session) {
            $this->error('No session found. Run `php artisan perf:watch` first.');

            return self::FAILURE;
        }

        $queries = $session['queries'] ?? [];
        $type = $this->option('type');
        $format = $this->option('format');

        $output = match ($type) {
            'all' => $this->outputAll($queries),
            'slow' => $this->outputSlow($queries),
            'n1' => $this->outputN1($queries),
            'summary' => $this->outputSummary($session, $queries),
            default => $this->outputSummary($session, $queries),
        };

        if ($format === 'table' && $type !== 'summary') {
            $this->renderTable($output, $type);
        } else {
            $this->line(json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        return self::SUCCESS;
    }

    // =========================================================================
    // Output builders
    // =========================================================================

    protected function outputAll(array $queries): array
    {
        return [
            'type' => 'all',
            'count' => count($queries),
            'queries' => $this->applyFiltersAndLimit($queries, sortBy: 'time_ms'),
        ];
    }

    protected function outputSlow(array $queries): array
    {
        $threshold = (float) $this->option('threshold');

        $slow = collect($queries)
            ->filter(fn (array $q) => ($q['time_ms'] ?? 0) >= $threshold)
            ->all();

        return [
            'type' => 'slow',
            'threshold_ms' => $threshold,
            'count' => count($slow),
            'queries' => $this->applyFiltersAndLimit($slow, sortBy: 'time_ms'),
        ];
    }

    protected function outputN1(array $queries): array
    {
        $filtered = $this->applyConnectionAndOperationFilters($queries);
        $candidates = $this->n1_detector->detect($filtered);
        $limit = (int) $this->option('limit');

        return [
            'type' => 'n1',
            'candidate_count' => count($candidates),
            'candidates' => array_slice($candidates, 0, $limit),
        ];
    }

    protected function outputSummary(array $session, array $queries): array
    {
        $total_time = collect($queries)->sum('time_ms');
        $slowest = collect($queries)->sortByDesc('time_ms')->first();
        $n1_candidates = $this->n1_detector->detect($queries);

        // Group by normalized hash for unique query count
        $unique_hashes = collect($queries)->pluck('hash')->unique()->count();

        // Batch stats
        $batches = collect($queries)->groupBy('batch_id');

        return [
            'type' => 'summary',
            'session_id' => $session['session_id'],
            'session_tag' => $session['tag'] ?? null,
            'status' => $session['status'],
            'started_at' => $session['started_at'],
            'finished_at' => $session['finished_at'] ?? null,
            'total_queries' => count($queries),
            'unique_query_templates' => $unique_hashes,
            'total_time_ms' => round((float) $total_time, 3),
            'avg_time_ms' => count($queries) > 0
                ? round((float) $total_time / count($queries), 3)
                : 0,
            'slowest_query_ms' => $slowest ? round((float) ($slowest['time_ms'] ?? 0), 3) : null,
            'slowest_query_sql' => $slowest['raw_sql'] ?? null,
            'slowest_query_source' => $slowest['source'][0] ?? null,
            'request_batch_count' => $batches->count(),
            'n1_candidate_count' => count($n1_candidates),
            'n1_candidates' => array_slice($n1_candidates, 0, 5),
            'slow_query_count_100ms' => collect($queries)->filter(fn ($q) => ($q['time_ms'] ?? 0) >= 100)->count(),
            'slow_query_count_500ms' => collect($queries)->filter(fn ($q) => ($q['time_ms'] ?? 0) >= 500)->count(),
            'connections' => collect($queries)->pluck('connection')->unique()->values()->all(),
            'operations' => collect($queries)
                ->groupBy('operation')
                ->map->count()
                ->sortDesc()
                ->all(),
        ];
    }

    // =========================================================================
    // Filtering helpers
    // =========================================================================

    protected function applyFiltersAndLimit(array $queries, string $sortBy = 'time_ms'): array
    {
        $filtered = $this->applyConnectionAndOperationFilters($queries);

        // Optional batch filter
        $batch = $this->option('batch');

        if ($batch) {
            $filtered = collect($filtered)
                ->filter(fn (array $q) => ($q['batch_id'] ?? '') === $batch)
                ->all();
        }

        $limit = (int) $this->option('limit');

        return collect($filtered)
            ->sortByDesc($sortBy)
            ->values()
            ->take($limit)
            ->all();
    }

    protected function applyConnectionAndOperationFilters(array $queries): array
    {
        $connection = $this->option('connection');
        $operation = $this->option('operation');

        return collect($queries)
            ->when($connection, fn ($c) => $c->filter(fn ($q) => ($q['connection'] ?? '') === $connection))
            ->when($operation, fn ($c) => $c->filter(fn ($q) => strtoupper($q['operation'] ?? '') === strtoupper($operation)))
            ->all();
    }

    // =========================================================================
    // Session resolution
    // =========================================================================

    protected function resolveSession(): ?array
    {
        $id = $this->option('session') ?? 'last';

        if ($id === 'last') {
            return $this->store->latestSession();
        }

        return $this->store->readSession($id);
    }

    // =========================================================================
    // Table renderer (human-readable fallback)
    // =========================================================================

    protected function renderTable(array $output, string $type): void
    {
        if ($type === 'n1') {
            $rows = array_map(fn (array $c) => [
                $c['count'],
                number_format($c['total_time_ms'], 1).'ms',
                $c['table'] ?? '?',
                $c['operation'],
                substr($c['normalized_sql'], 0, 80),
            ], $output['candidates'] ?? []);

            $this->table(['Count', 'Total ms', 'Table', 'Op', 'Normalized SQL'], $rows);

            return;
        }

        $rows = array_map(fn (array $q) => [
            number_format($q['time_ms'] ?? 0, 2).'ms',
            $q['connection'] ?? '',
            $q['operation'] ?? '',
            $q['table'] ?? '',
            substr($q['raw_sql'] ?? $q['sql'] ?? '', 0, 100),
            isset($q['source'][0]) ? ($q['source'][0]['file'] ?? '').':'.($q['source'][0]['line'] ?? '') : '',
        ], $output['queries'] ?? []);

        $this->table(['Time', 'Conn', 'Op', 'Table', 'SQL (truncated)', 'Source'], $rows);
    }
}
