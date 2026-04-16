<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use function Mateffy\Laraperf\Testing\measure;

// Setup: Create tables once using a static flag
$tablesCreated = false;

beforeEach(function () use (&$tablesCreated) {
    if (! $tablesCreated) {
        Schema::create('perf_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('perf_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->string('title');
            $table->timestamps();
        });

        $tablesCreated = true;
    }
});

describe('measure() function', function () {
    it('captures query count and duration', function () {
        $perf = measure(function () {
            DB::table('perf_users')->where('id', 1)->first();
        });

        expect($perf->queryCount())->toBeGreaterThanOrEqual(1);
        expect($perf->durationMs())->toBeGreaterThan(0);
        expect($perf->result)->toBeNull();
    });

    it('captures the result value', function () {
        $perf = measure(fn () => ['key' => 'value']);

        expect($perf->result)->toBe(['key' => 'value']);
    });

    it('provides detailed query analysis', function () {
        $perf = measure(function () {
            DB::table('perf_users')->insert(['name' => 'Test']);
            DB::table('perf_users')->where('id', 1)->first();
        });

        expect($perf->queryCount())->toBe(2);
        expect($perf->queriesByOperation('INSERT'))->toHaveCount(1);
        expect($perf->queriesByOperation('SELECT'))->toHaveCount(1);
        expect($perf->tablesAccessed())->toContain('perf_users');
    });

    it('can access slow queries', function () {
        $perf = measure(function () {
            DB::table('perf_users')->insert(['name' => 'Slow Query Test']);
        });

        // Should have no slow queries (under 1000ms)
        expect($perf->slowQueries(1000))->toBeEmpty();
    });
});

describe('PerformanceResult', function () {
    it('provides memory metrics', function () {
        $perf = measure(function () {
            // Use some memory - allocate enough to ensure measurable increase
            $data = range(1, 100000);
            $result = collect($data)->map(fn ($i) => $i * 2)->toArray();

            // Keep result in memory
            return $result;
        });

        expect($perf->durationMs())->toBeGreaterThan(0);
        // Memory metrics are available - just verify they don't throw
        expect($perf->netMemoryBytes())->toBeGreaterThanOrEqual(0);
        expect($perf->peakMemoryBytes())->toBeGreaterThan(0);
    });

    it('provides timeline data', function () {
        $perf = measure(function () {
            DB::table('perf_users')->insert(['name' => 'Timeline Test']);
        });

        $timeline = $perf->timeline;
        expect($timeline)->toHaveCount(2); // start and end
        expect($timeline[0]['label'])->toBe('start');
        expect($timeline[1]['label'])->toBe('end');
    });

    it('provides summary', function () {
        $perf = measure(function () {
            DB::table('perf_users')->insert(['name' => 'Summary Test']);
        });

        $summary = $perf->summary();
        expect($summary)->toHaveKeys([
            'duration_ms',
            'memory_peak',
            'memory_net',
            'query_count',
            'query_time_ms',
            'n1_candidates',
            'tables_accessed',
            'had_exception',
        ]);
    });
});

describe('manual capture', function () {
    it('can start and stop capture manually', function () {
        // Do some work
        DB::table('perf_users')->insert(['name' => 'Before Capture']);

        // Start manual capture
        $capture = \Mateffy\Laraperf\Testing\capture();

        // Only this is measured
        DB::table('perf_users')->insert(['name' => 'During Capture']);

        // Stop and get results
        $perf = $capture->stop();

        expect($perf->queryCount())->toBe(1);
    });
});
