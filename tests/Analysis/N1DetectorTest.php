<?php

declare(strict_types=1);

use Mateffy\Laraperf\Analysis\N1Detector;
use Mateffy\Laraperf\Analysis\QueryNormalizer;

it('detects N+1 pattern when same query appears above threshold', function () {
    $normalizer = new QueryNormalizer;
    $detector = new N1Detector($normalizer);

    $queries = [];
    for ($i = 0; $i < 10; $i++) {
        $queries[] = [
            'sql' => 'select * from "posts" where "user_id" = ? limit 1',
            'raw_sql' => "select * from \"posts\" where \"user_id\" = {$i} limit 1",
            'time_ms' => 1.2,
            'batch_id' => 'batch-001',
            'source' => [],
        ];
    }

    $candidates = $detector->detect($queries, threshold: 3);

    expect($candidates)->toHaveCount(1)
        ->and($candidates[0]['count'])->toBe(10)
        ->and($candidates[0]['table'])->toBe('posts')
        ->and($candidates[0]['operation'])->toBe('SELECT')
        ->and($candidates[0]['total_time_ms'])->toBe(12.0)
        ->and($candidates[0]['avg_time_ms'])->toBe(1.2);
});

it('does not flag queries below threshold', function () {
    $normalizer = new QueryNormalizer;
    $detector = new N1Detector($normalizer);

    // Only 2 occurrences of the same query — default threshold is 3
    $queries = [
        ['sql' => 'select * from "users" where "id" = ?', 'raw_sql' => 'select * from "users" where "id" = 1', 'time_ms' => 1.0, 'batch_id' => 'b1', 'source' => []],
        ['sql' => 'select * from "users" where "id" = ?', 'raw_sql' => 'select * from "users" where "id" = 2', 'time_ms' => 1.0, 'batch_id' => 'b1', 'source' => []],
    ];

    $candidates = $detector->detect($queries, threshold: 3);

    expect($candidates)->toHaveCount(0);
});

it('groups N+1 by batch_id — same query in different batches is not flagged', function () {
    $normalizer = new QueryNormalizer;
    $detector = new N1Detector($normalizer);

    // 2 occurrences in batch A, 2 in batch B — not N+1 when threshold is 3
    $queries = [
        ['sql' => 'select * from "users" where "id" = ?', 'raw_sql' => '...', 'time_ms' => 1.0, 'batch_id' => 'A', 'source' => []],
        ['sql' => 'select * from "users" where "id" = ?', 'raw_sql' => '...', 'time_ms' => 1.0, 'batch_id' => 'A', 'source' => []],
        ['sql' => 'select * from "users" where "id" = ?', 'raw_sql' => '...', 'time_ms' => 1.0, 'batch_id' => 'B', 'source' => []],
        ['sql' => 'select * from "users" where "id" = ?', 'raw_sql' => '...', 'time_ms' => 1.0, 'batch_id' => 'B', 'source' => []],
    ];

    $candidates = $detector->detect($queries, threshold: 3);
    expect($candidates)->toHaveCount(0);
});

it('flags N+1 separately per batch', function () {
    $normalizer = new QueryNormalizer;
    $detector = new N1Detector($normalizer);

    // 5 in batch A, 5 in batch B → 2 separate N+1 candidates
    $queries = [];
    for ($i = 0; $i < 5; $i++) {
        $queries[] = ['sql' => 'select * from "users" where "id" = ?', 'raw_sql' => '...', 'time_ms' => 1.0, 'batch_id' => 'A', 'source' => []];
    }
    for ($i = 0; $i < 5; $i++) {
        $queries[] = ['sql' => 'select * from "users" where "id" = ?', 'raw_sql' => '...', 'time_ms' => 1.0, 'batch_id' => 'B', 'source' => []];
    }

    $candidates = $detector->detect($queries, threshold: 3);
    expect($candidates)->toHaveCount(2);
});

it('detects multiple distinct N+1 patterns in the same batch', function () {
    $normalizer = new QueryNormalizer;
    $detector = new N1Detector($normalizer);

    $queries = [];
    for ($i = 0; $i < 5; $i++) {
        $queries[] = ['sql' => 'select * from "users" where "id" = ?', 'raw_sql' => '...', 'time_ms' => 1.0, 'batch_id' => 'A', 'source' => []];
    }
    for ($i = 0; $i < 4; $i++) {
        $queries[] = ['sql' => 'select * from "posts" where "user_id" = ?', 'raw_sql' => '...', 'time_ms' => 2.0, 'batch_id' => 'A', 'source' => []];
    }

    $candidates = $detector->detect($queries, threshold: 3);
    expect($candidates)->toHaveCount(2);
});

it('returns empty array for empty input', function () {
    $normalizer = new QueryNormalizer;
    $detector = new N1Detector($normalizer);

    expect($detector->detect([]))->toBe([]);
});

it('includes example_raw_sql and example_instance in candidates', function () {
    $normalizer = new QueryNormalizer;
    $detector = new N1Detector($normalizer);

    $queries = [];
    for ($i = 0; $i < 3; $i++) {
        $queries[] = [
            'sql' => 'select * from "users" where "id" = ?',
            'raw_sql' => "select * from \"users\" where \"id\" = {$i}",
            'time_ms' => 1.5,
            'batch_id' => 'batch-001',
            'source' => [['file' => '/app/User.php', 'line' => 10 + $i]],
        ];
    }

    $candidates = $detector->detect($queries, threshold: 3);

    expect($candidates)->toHaveCount(1)
        ->and($candidates[0]['example_raw_sql'])->toContain('select * from "users"')
        ->and($candidates[0]['example_source'][0]['file'])->toContain('User.php')
        ->and($candidates[0]['example_instances'])->toHaveCount(3);
});

it('caps example_instances to 5', function () {
    $normalizer = new QueryNormalizer;
    $detector = new N1Detector($normalizer);

    $queries = [];
    for ($i = 0; $i < 20; $i++) {
        $queries[] = [
            'sql' => 'select * from "users" where "id" = ?',
            'raw_sql' => "select * from \"users\" where \"id\" = {$i}",
            'time_ms' => 1.0,
            'batch_id' => 'batch-001',
            'source' => [],
        ];
    }

    $candidates = $detector->detect($queries, threshold: 3);
    expect($candidates[0]['example_instances'])->toHaveCount(5);
});

it('respects custom threshold', function () {
    $normalizer = new QueryNormalizer;
    $detector = new N1Detector($normalizer);

    $queries = [];
    for ($i = 0; $i < 5; $i++) {
        $queries[] = ['sql' => 'select * from "users" where "id" = ?', 'raw_sql' => '...', 'time_ms' => 1.0, 'batch_id' => 'A', 'source' => []];
    }

    // With threshold 5, 5 occurrences IS an N+1
    expect($detector->detect($queries, threshold: 5))->toHaveCount(1);
    // With threshold 6, 5 occurrences is NOT an N+1
    expect($detector->detect($queries, threshold: 6))->toHaveCount(0);
});
