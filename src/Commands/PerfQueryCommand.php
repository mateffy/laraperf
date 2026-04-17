<?php

declare(strict_types=1);

namespace Mateffy\Laraperf\Commands;

use Illuminate\Console\Command;
use Mateffy\Laraperf\Analysis\N1Detector;
use Mateffy\Laraperf\Storage\PerfStore;

/**
 * Read a captured perf session and output structured analysis.
 *
 * OUTPUT FLAGS (combine freely)
 * ─────────────────────────────
 * --summary       aggregate stats: total queries, total time, slowest, N+1 count
 * --slow=N        queries above N milliseconds
 * --n1=N          probable N+1 patterns where same query repeats ≥ N times per batch
 *
 * When no output flags are given, all three are included (summary, slow≥100ms, n1≥3).
 * All output is JSON by default, making it trivially parseable by LLM agents.
 */
class PerfQueryCommand extends Command
{
    protected $signature = 'perf:query
        {--session=last    : Session ID to read. Use "last" for the most recent completed session.}
        {--summary         : Show aggregate session stats.}
        {--slow=            : Show queries slower than this threshold (ms).}
        {--n1=              : Show N+1 candidates where same query repeats ≥ N times per batch.}
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
        $wantsSummary = (bool) $this->option('summary');
        $wantsSlow = $this->option('slow') !== null;
        $wantsN1 = $this->option('n1') !== null;
        $defaultMode = ! $wantsSummary && ! $wantsSlow && ! $wantsN1;

        if ($defaultMode) {
            $wantsSummary = true;
            $wantsSlow = true;
            $wantsN1 = true;
        }

        $parts = [];

        if ($wantsSummary) {
            $parts['summary'] = $this->buildSummary($session, $queries);
        }

        if ($wantsSlow) {
            $threshold = $this->option('slow') !== null
                ? (float) $this->option('slow')
                : 100.0;
            $parts['slow'] = $this->buildSlow($queries, $threshold);
        }

        if ($wantsN1) {
            $n1Threshold = $this->option('n1') !== null
                ? (int) $this->option('n1')
                : N1Detector::DEFAULT_THRESHOLD;
            $parts['n1'] = $this->buildN1($queries, $n1Threshold);
        }

        $singleKey = count($parts) === 1 ? array_key_first($parts) : null;
        /** @var array<string, array<string, mixed>> $output */
        $output = $singleKey ? $parts[$singleKey] : $parts;

        if ((string) $this->option('format') === 'table') {
            $tableType = $singleKey ?? 'combined';
            $this->renderTable($output, (string) $tableType);
        } else {
            $this->line(json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
        }

        return self::SUCCESS;
    }

    // =========================================================================
    // Output builders
    // =========================================================================

    /**
     * @param  array<string, mixed>  $session
     * @param  array<int, array<string, mixed>>  $queries
     * @return array<string, mixed>
     */
    protected function buildSummary(array $session, array $queries): array
    {
        $total_time = collect($queries)->sum('time_ms');
        $slowest = collect($queries)->sortByDesc('time_ms')->first();
        $n1_candidates = $this->n1_detector->detect($queries);

        $unique_hashes = collect($queries)->pluck('hash')->unique()->count();
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
            'n1_candidates' => array_slice(is_array($n1_candidates) ? $n1_candidates : $n1_candidates->all(), 0, 5),
            'slow_query_count_100ms' => collect($queries)->filter(fn (array $q) => ($q['time_ms'] ?? 0) >= 100)->count(),
            'slow_query_count_500ms' => collect($queries)->filter(fn (array $q) => ($q['time_ms'] ?? 0) >= 500)->count(),
            'connections' => collect($queries)->pluck('connection')->unique()->values()->all(),
            'operations' => collect($queries)
                ->groupBy('operation')
                ->map->count()
                ->sortDesc()
                ->all(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $queries
     * @return array<string, mixed>
     */
    protected function buildSlow(array $queries, float $threshold): array
    {
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

    /**
     * @param  array<int, array<string, mixed>>  $queries
     * @return array<string, mixed>
     */
    protected function buildN1(array $queries, int $threshold): array
    {
        $filtered = $this->applyConnectionAndOperationFilters($queries);
        $candidates = $this->n1_detector->detect($filtered, $threshold);
        $candidatesArray = is_array($candidates) ? $candidates : $candidates->all();
        $limit = (int) $this->option('limit');

        return [
            'type' => 'n1',
            'threshold' => $threshold,
            'candidate_count' => count($candidatesArray),
            'candidates' => array_slice($candidatesArray, 0, $limit),
        ];
    }

    // =========================================================================
    // Filtering helpers
    // =========================================================================

    /**
     * @param  array<int, array<string, mixed>>  $queries
     * @return array<int, array<string, mixed>>
     */
    protected function applyFiltersAndLimit(array $queries, string $sortBy = 'time_ms'): array
    {
        $filtered = $this->applyConnectionAndOperationFilters($queries);

        $batch = $this->option('batch');

        if ($batch && is_string($batch)) {
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

    /**
     * @param  array<int, array<string, mixed>>  $queries
     * @return array<int, array<string, mixed>>
     */
    protected function applyConnectionAndOperationFilters(array $queries): array
    {
        $connection = $this->option('connection');
        $operation = $this->option('operation');

        return collect($queries)
            ->when($connection, fn ($c) => $c->filter(fn (array $q) => ($q['connection'] ?? '') === $connection))
            ->when($operation, fn ($c) => $c->filter(fn (array $q) => strtoupper((string) ($q['operation'] ?? '')) === strtoupper((string) $operation)))
            ->all();
    }

    // =========================================================================
    // Session resolution
    // =========================================================================

    /**
     * Resolve the session data, merging tracker metadata (tag, status)
     * into the data file contents.
     *
     * @return array<string, mixed>|null
     */
    protected function resolveSession(): ?array
    {
        $id = (string) ($this->option('session') ?? 'last');

        if ($id === 'last') {
            $data = $this->store->latestSession();
        } else {
            $data = $this->store->readSession($id);
        }

        if (! $data) {
            return null;
        }

        // The tracker only exists for the currently active session.
        // If present, merge its tag/status into the data.
        $tracker = $this->store->readTracker();
        if ($tracker && ($tracker['session_id'] ?? null) === $data['session_id']) {
            $data['tag'] = $data['tag'] ?? $tracker['tag'] ?? null;
            $data['status'] = $tracker['status'];
        } else {
            $data['status'] = $data['finished_at'] ? 'completed' : 'active';
        }

        return $data;
    }

    // =========================================================================
    // Table renderer (human-readable fallback)
    // =========================================================================

    /**
     * @param  array<string, mixed>  $output
     */
    protected function renderTable(array $output, string $type): void
    {
        if ($type === 'n1') {
            $rows = array_map(fn (array $c) => [
                $c['count'],
                number_format((float) ($c['total_time_ms'] ?? 0), 1).'ms',
                $c['table'] ?? '?',
                $c['operation'] ?? '',
                substr((string) ($c['normalized_sql'] ?? ''), 0, 80),
            ], $output['candidates'] ?? []);

            $this->table(['Count', 'Total ms', 'Table', 'Op', 'Normalized SQL'], $rows);

            return;
        }

        if ($type === 'slow') {
            $rows = array_map(fn (array $q) => [
                number_format((float) ($q['time_ms'] ?? 0), 2).'ms',
                $q['connection'] ?? '',
                $q['operation'] ?? '',
                $q['table'] ?? '',
                substr((string) ($q['raw_sql'] ?? $q['sql'] ?? ''), 0, 100),
                isset($q['source'][0]) ? ($q['source'][0]['file'] ?? '').':'.($q['source'][0]['line'] ?? '') : '',
            ], $output['queries'] ?? []);

            $this->table(['Time', 'Conn', 'Op', 'Table', 'SQL (truncated)', 'Source'], $rows);

            return;
        }

        if ($type === 'combined') {
            $this->renderCombinedTable($output);

            return;
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $output
     */
    protected function renderCombinedTable(array $output): void
    {
        if (isset($output['summary'])) {
            $s = $output['summary'];
            $this->info("Session: {$s['session_id']}  Queries: {$s['total_queries']}  Time: {$s['total_time_ms']}ms  N+1 candidates: {$s['n1_candidate_count']}");
        }

        if (isset($output['slow'])) {
            $this->info("\nSlow queries (≥{$output['slow']['threshold_ms']}ms):");
            $this->renderTable($output['slow'], 'slow');
        }

        if (isset($output['n1'])) {
            $this->info("\nN+1 candidates (≥{$output['n1']['threshold']} repeats):");
            $this->renderTable($output['n1'], 'n1');
        }
    }
}
