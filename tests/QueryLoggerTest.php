<?php

declare(strict_types=1);

use Mateffy\Laraperf\Analysis\QueryNormalizer;
use Mateffy\Laraperf\QueryLogger;

it('starts and stops capturing', function () {
    $store = $this->store;
    $normalizer = new QueryNormalizer;
    $logger = new QueryLogger($store, $normalizer);

    expect($logger->isActive())->toBeFalse();

    $store->writeSession('test-session', $store->emptySession('test-session'));
    $logger->start('test-session');

    expect($logger->isActive())->toBeTrue();

    $logger->stop();

    expect($logger->isActive())->toBeFalse();
});

it('captures queries fired via DB::listen', function () {
    $store = $this->store;
    $normalizer = new QueryNormalizer;
    $logger = new QueryLogger($store, $normalizer);

    $store->writeSession('capture-test', $store->emptySession('capture-test'));
    $logger->start('capture-test');

    // Fire a query via Eloquent/query builder
    DB::connection('testing')->statement('CREATE TABLE capture_test (id INTEGER PRIMARY KEY, name TEXT)');
    DB::connection('testing')->table('capture_test')->insert(['name' => 'foo']);
    DB::connection('testing')->table('capture_test')->insert(['name' => 'bar']);
    DB::connection('testing')->select('SELECT * FROM capture_test');

    $session = $store->readSession('capture-test');

    expect($session)->not->toBeNull()
        ->and($session['query_count'])->toBeGreaterThanOrEqual(3);

    // Find the SELECT query in the captured queries
    $selectQueries = array_filter($session['queries'], fn (array $q) => ($q['operation'] ?? '') === 'SELECT');
    expect($selectQueries)->not->toBeEmpty();

    $logger->stop();
});

it('does not capture queries when inactive', function () {
    $store = $this->store;
    $normalizer = new QueryNormalizer;
    $logger = new QueryLogger($store, $normalizer);

    // Logger is NOT started — queries should not be captured
    $store->writeSession('inactive-test', $store->emptySession('inactive-test'));

    DB::connection('testing')->statement('CREATE TABLE inactive_test (id INTEGER PRIMARY KEY)');
    DB::connection('testing')->select('SELECT * FROM inactive_test');

    $session = $store->readSession('inactive-test');
    expect($session['query_count'])->toBe(0);
});

it('filters stack traces to app frames', function () {
    $store = $this->store;
    $normalizer = new QueryNormalizer;
    $logger = new QueryLogger($store, $normalizer);

    $store->writeSession('trace-test', $store->emptySession('trace-test'));
    $logger->start('trace-test');

    DB::connection('testing')->statement('CREATE TABLE trace_test (id INTEGER PRIMARY KEY)');
    DB::connection('testing')->select('SELECT * FROM trace_test');

    $session = $store->readSession('trace-test');

    // The source array may contain app/ frames or be empty in test context
    // (since this test runs from vendor/orchestra). The key thing is that
    // vendor/ and packages/perf/ frames are never included.
    foreach ($session['queries'] as $query) {
        foreach ($query['source'] ?? [] as $frame) {
            expect($frame['file'])->not->toContain('/vendor/')
                ->and($frame['file'])->not->toContain('/packages/perf/')
                ->and($frame['file'])->not->toContain('/packages/laraperf/');
        }
    }

    $logger->stop();
});

it('rotates batch ID', function () {
    $store = $this->store;
    $normalizer = new QueryNormalizer;
    $logger = new QueryLogger($store, $normalizer);

    $store->writeSession('batch-test', $store->emptySession('batch-test'));
    $logger->start('batch-test');

    // Capture the first batch ID via reflection
    $ref = new ReflectionProperty($logger, 'batch_id');
    $batch1 = $ref->getValue($logger);

    $logger->rotateBatch();

    $batch2 = $ref->getValue($logger);

    // The batch IDs are different
    expect($batch1)->not->toBe($batch2);

    $logger->stop();
});
