<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Mateffy\Laraperf\Analysis\QueryNormalizer;
use Mateffy\Laraperf\QueryLogger;
use Mateffy\Laraperf\Storage\PerfStore;

it('registers all perf commands', function () {
    $commands = Artisan::all();

    expect($commands)->toHaveKey('perf:watch')
        ->and($commands)->toHaveKey('perf:stop')
        ->and($commands)->toHaveKey('perf:query')
        ->and($commands)->toHaveKey('perf:explain')
        ->and($commands)->toHaveKey('perf:clear');
});

it('does not attach DB::listen when no active session', function () {
    $perf_path = storage_path('perf');
    if (is_dir($perf_path)) {
        array_map('unlink', glob($perf_path.'/*.json'));
    }

    $store = app(PerfStore::class);
    expect($store->activeSession())->toBeNull();
});

it('detects an active session from disk', function () {
    $store = app(PerfStore::class);
    $store->writeSession('auto-attach-test', $store->emptySession('auto-attach-test'));

    // Verify that an active session is found on disk
    $active = $store->activeSession();
    expect($active)->not->toBeNull()
        ->and($active['session_id'])->toBe('auto-attach-test');

    // Clean up
    $store->finalizeSession('auto-attach-test');
});

it('query logger starts and stops correctly', function () {
    $store = app(PerfStore::class);
    $normalizer = app(QueryNormalizer::class);
    $logger = new QueryLogger($store, $normalizer);

    expect($logger->isActive())->toBeFalse();

    $store->writeSession('logger-test', $store->emptySession('logger-test'));
    $logger->start('logger-test');

    expect($logger->isActive())->toBeTrue();

    $logger->stop();

    expect($logger->isActive())->toBeFalse();
});

it('merges config from the package', function () {
    expect(config()->has('laraperf'))->toBeTrue();
    expect(config('laraperf.connection'))->not->toBeNull();
});
