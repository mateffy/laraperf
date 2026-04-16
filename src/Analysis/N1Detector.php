<?php

declare(strict_types=1);

namespace Mateffy\Laraperf\Analysis;

use Illuminate\Support\Collection;
use Mateffy\Laraperf\Testing\QueryRecord;

/**
 * Detects N+1 query patterns from a flat list of captured queries.
 *
 * A pattern is flagged as a probable N+1 when the same normalized SQL
 * template appears more than a configurable threshold number of times
 * within the same request batch. The threshold default of 3 avoids false
 * positives from legitimate pagination or small repeated lookups.
 */
class N1Detector
{
    /** Queries appearing this many or more times within one batch are flagged. */
    public const DEFAULT_THRESHOLD = 3;

    public function __construct(
        protected ?QueryNormalizer $normalizer = null,
    ) {
        $this->normalizer = $normalizer ?? new QueryNormalizer;
    }

    /**
     * Analyse a flat array of query records and return N+1 candidates.
     *
     * Supports both array and QueryRecord objects.
     *
     * @param  array<int, array<string, mixed>>|Collection<int, QueryRecord>  $queries
     * @param  int  $threshold  Queries appearing this many times are flagged as N+1
     * @return array<int, array<string, mixed>>|Collection<int, N1Candidate>
     */
    public function detect(array|Collection $queries, int $threshold = self::DEFAULT_THRESHOLD): array|Collection
    {
        // Handle QueryRecord collection
        if ($queries instanceof Collection && $queries->first() instanceof QueryRecord) {
            return $this->detectFromQueryRecords($queries, $threshold);
        }

        // Handle array format (original behavior for backward compatibility)
        return $this->detectFromArrays($queries, $threshold);
    }

    /**
     * Detect N+1 from QueryRecord objects.
     *
     * @param  Collection<int, QueryRecord>  $queries
     * @param  int  $threshold  Queries appearing this many times are flagged as N+1
     * @return Collection<int, N1Candidate>
     */
    protected function detectFromQueryRecords(Collection $queries, int $threshold = self::DEFAULT_THRESHOLD): Collection
    {
        $groups = [];

        foreach ($queries as $query) {
            $hash = $this->normalizer->hash($query->sql);
            $key = $query->batch_id.'::'.$hash;

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'hash' => $hash,
                    'batch_id' => $query->batch_id,
                    'table' => $query->table,
                    'normalized_sql' => $this->normalizer->normalize($query->sql),
                    'count' => 0,
                    'total_time_ms' => 0.0,
                    'examples' => [],
                ];
            }

            $groups[$key]['count']++;
            $groups[$key]['total_time_ms'] += $query->time_ms;

            // Keep a few examples
            if (count($groups[$key]['examples']) < 3) {
                $groups[$key]['examples'][] = $query->toArray();
            }
        }

        // Filter to groups exceeding threshold and create N1Candidate objects
        $candidates = [];

        foreach ($groups as $group) {
            if ($group['count'] >= $threshold) {
                $candidates[] = new N1Candidate(
                    hash: $group['hash'],
                    batchId: $group['batch_id'],
                    count: $group['count'],
                    totalTimeMs: round($group['total_time_ms'], 3),
                    table: $group['table'],
                    normalizedSql: $group['normalized_sql'],
                    avgTimeMs: round($group['total_time_ms'] / $group['count'], 3),
                    examples: $group['examples'],
                );
            }
        }

        // Sort by count descending
        usort($candidates, fn ($a, $b) => $b->count <=> $a->count);

        return collect($candidates);
    }

    /**
     * Original detect method for backward compatibility with arrays.
     *
     * @param  array<int, array<string, mixed>>  $queries
     * @param  int  $threshold  Queries appearing this many times are flagged as N+1
     * @return array<int, array<string, mixed>>
     */
    protected function detectFromArrays(array $queries, int $threshold = self::DEFAULT_THRESHOLD): array
    {
        // Group queries by (batch_id, normalized_hash)
        $groups = [];

        foreach ($queries as $query) {
            $hash = $this->normalizer->hash((string) ($query['sql'] ?? ''));
            $batch = (string) ($query['batch_id'] ?? 'unknown');
            $key = $batch.'::'.$hash;

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'hash' => $hash,
                    'normalized_sql' => $this->normalizer->normalize((string) ($query['sql'] ?? '')),
                    'table' => $this->normalizer->extractTable((string) ($query['sql'] ?? '')),
                    'operation' => $this->normalizer->extractOperation((string) ($query['sql'] ?? '')),
                    'count' => 0,
                    'total_time_ms' => 0.0,
                    'example_raw_sql' => (string) ($query['raw_sql'] ?? $query['sql'] ?? ''),
                    'example_source' => (array) ($query['source'] ?? []),
                    'batch_id' => $batch,
                    'instances' => [],
                ];
            }

            $groups[$key]['count']++;
            $groups[$key]['total_time_ms'] += (float) ($query['time_ms'] ?? 0);
            $groups[$key]['instances'][] = (string) ($query['raw_sql'] ?? $query['sql'] ?? '');
        }

        // Filter to groups that breach the threshold, compute averages
        $candidates = collect($groups)
            ->filter(fn (array $g) => $g['count'] >= $threshold)
            ->map(function (array $g) {
                $g['avg_time_ms'] = $g['count'] > 0
                    ? round($g['total_time_ms'] / $g['count'], 3)
                    : 0.0;
                $g['total_time_ms'] = round($g['total_time_ms'], 3);
                // Keep instances for context but cap to 5 examples to save output space
                $g['example_instances'] = array_slice($g['instances'], 0, 5);
                unset($g['instances']);

                return $g;
            })
            ->sortByDesc('count')
            ->values()
            ->all();

        return $candidates;
    }
}
