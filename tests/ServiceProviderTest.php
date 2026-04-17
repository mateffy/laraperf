<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Mateffy\Laraperf\Analysis\QueryNormalizer;
use Mateffy\Laraperf\LaraperfServiceProvider;
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
    $store = app(PerfStore::class);
    expect($store->activeTracker())->toBeNull();
});

it('detects an active tracker from disk', function () {
    $store = app(PerfStore::class);
    $store->writeTracker($store->emptyTracker('auto-attach-test'));

    $active = $store->activeTracker();
    expect($active)->not->toBeNull()
        ->and($active['session_id'])->toBe('auto-attach-test');

    $store->removeTracker();
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

it('is enabled by default in testing environment', function () {
    config(['laraperf.enabled' => null]);
    $provider = new LaraperfServiceProvider(app());
    $method = new ReflectionMethod($provider, 'isEnabled');
    $method->setAccessible(true);

    expect($method->invoke($provider))->toBeTrue();
});

it('disables runtime interception when PERF_ENABLE is false', function () {
    config(['laraperf.enabled' => false]);
    $provider = new LaraperfServiceProvider(app());
    $method = new ReflectionMethod($provider, 'isEnabled');
    $method->setAccessible(true);

    expect($method->invoke($provider))->toBeFalse();
});

it('forces enable when PERF_ENABLE is true regardless of environment', function () {
    config(['laraperf.enabled' => true]);
    $provider = new LaraperfServiceProvider(app());
    $method = new ReflectionMethod($provider, 'isEnabled');
    $method->setAccessible(true);

    expect($method->invoke($provider))->toBeTrue();
});
