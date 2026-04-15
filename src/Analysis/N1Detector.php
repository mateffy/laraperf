<?php

declare(strict_types=1);

namespace Mateffy\Laraperf\Analysis;

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
        protected QueryNormalizer $normalizer = new QueryNormalizer,
    ) {}

    /**
     * Analyse a flat array of query records and return N+1 candidates.
     *
     * @param  array<int, array<string, mixed>>  $queries
     * @return array<int, array<string, mixed>>
     */
    public function detect(array $queries, int $threshold = self::DEFAULT_THRESHOLD): array
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
