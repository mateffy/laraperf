<?php

declare(strict_types=1);

namespace Mateffy\Laraperf;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Mateffy\Laraperf\Analysis\ExplainRunner;
use Mateffy\Laraperf\Analysis\N1Detector;
use Mateffy\Laraperf\Analysis\QueryNormalizer;
use Mateffy\Laraperf\Commands\PerfClearCommand;
use Mateffy\Laraperf\Commands\PerfExplainCommand;
use Mateffy\Laraperf\Commands\PerfQueryCommand;
use Mateffy\Laraperf\Commands\PerfStopCommand;
use Mateffy\Laraperf\Commands\PerfWatchCommand;
use Mateffy\Laraperf\Storage\PerfStore;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Registers laraperf package services and commands.
 *
 * PHP-FPM interception strategy
 * ─────────────────────────────
 * Under PHP-FPM each web request is a separate process. The tracker file
 * (storage/perf/tracker.json) acts as the cross-process flag: on every
 * boot this ServiceProvider reads it to decide whether to attach DB::listen().
 *
 * The data file (storage/perf/<id>.json) holds the actual queries and is
 * only read/written when appending queries or running analysis commands.
 * It is never glob'd or read during the boot check.
 *
 * Sessions auto-expire based on started_at + duration_seconds, so no
 * background worker process is needed.
 */
class LaraperfServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laraperf')
            ->hasConfigFile()
            ->hasCommands([
                PerfWatchCommand::class,
                PerfStopCommand::class,
                PerfQueryCommand::class,
                PerfExplainCommand::class,
                PerfClearCommand::class,
            ]);
    }

    public function registeringPackage(): void
    {
        $this->app->singleton(PerfStore::class);
        $this->app->singleton(QueryNormalizer::class);
        $this->app->singleton(N1Detector::class);
        $this->app->singleton(ExplainRunner::class);

        // QueryLogger is request-scoped (not a singleton) so each request gets
        // its own batch_id. The shared session ID is read from the tracker.
        $this->app->bind(QueryLogger::class, function ($app) {
            /** @var Application $app */
            return new QueryLogger(
                store: $app->make(PerfStore::class),
                normalizer: $app->make(QueryNormalizer::class),
            );
        });
    }

    public function packageBooted(): void
    {
        $this->maybeAttachListener();
    }

    // =========================================================================

    /**
     * Check if an active, non-expired tracker exists and, if so,
     * attach DB::listen() for this PHP-FPM request or CLI process.
     * The tracker is a tiny file — no large JSON is read at boot.
     */
    protected function maybeAttachListener(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        // Check the app instance cache first — avoids the tracker file
        // read on every request when no session is active.
        if ($this->app->has('laraperf.active_session')) {
            /** @var array<string, mixed>|false $cached */
            $cached = $this->app->make('laraperf.active_session');

            if ($cached === false) {
                return;
            }

            /** @var string $session_id */
            $session_id = $cached['session_id'];
        } else {
            $store = $this->app->make(PerfStore::class);

            $tracker = $store->activeTracker();

            if (! $tracker) {
                $this->app->instance('laraperf.active_session', false);

                return;
            }

            /** @var string $session_id */
            $session_id = $tracker['session_id'];

            // Prime the PerfStore's in-memory data cache so that
            // appendQuery() never reads from disk for the first query.
            $data = $store->readSession((string) $session_id);
            if ($data) {
                $store->cacheSession($data);
            }

            $this->app->instance('laraperf.active_session', $tracker);
        }

        $logger = $this->app->make(QueryLogger::class);
        $logger->start($session_id);
    }

    /**
     * Determine whether runtime interception is enabled.
     *
     * - PERF_ENABLE=false  → always disabled (zero overhead, no glob)
     * - PERF_ENABLE=true   → always enabled
     * - null (unset)       → enabled in local/testing, disabled in production
     */
    protected function isEnabled(): bool
    {
        $env = config('laraperf.enabled');

        if ($env === false) {
            return false;
        }

        if ($env === true) {
            return true;
        }

        // Unset — default to the application environment
        return in_array($this->app->environment(), ['local', 'testing'], true);
    }
}
