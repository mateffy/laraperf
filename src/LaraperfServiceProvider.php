<?php

declare(strict_types=1);

namespace Mateffy\Laraperf;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Mateffy\Laraperf\Analysis\ExplainRunner;
use Mateffy\Laraperf\Analysis\N1Detector;
use Mateffy\Laraperf\Analysis\QueryNormalizer;
use Mateffy\Laraperf\Commands\PerfClearCommand;
use Mateffy\Laraperf\Commands\PerfExplainCommand;
use Mateffy\Laraperf\Commands\PerfQueryCommand;
use Mateffy\Laraperf\Commands\PerfStopCommand;
use Mateffy\Laraperf\Commands\PerfWatchCommand;
use Mateffy\Laraperf\Commands\PerfWorkerCommand;
use Mateffy\Laraperf\Storage\PerfStore;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Registers laraperf package services and commands.
 *
 * PHP-FPM / Herd interception strategy
 * ─────────────────────────────────────
 * Under standard PHP-FPM each web request is a separate process. The background
 * worker spawned by `perf:watch` (detached) cannot intercept those requests'
 * DB queries. Instead, the ServiceProvider checks on every boot whether an
 * active session exists on disk. If it does, it attaches DB::listen() to the
 * current process — meaning every request made while the watcher is "alive"
 * will append its queries to the shared session JSON.
 *
 * This is intentionally cheap: a glob + json_decode check. When no session is
 * active (the normal case) there is zero overhead beyond disk I/O for the glob.
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
                PerfWorkerCommand::class,
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
        // its own batch_id. The shared session ID is read from disk.
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
        // Attach the DB listener to the current process if a session is active.
        $this->maybeAttachListener();
    }

    // =========================================================================

    /**
     * Check if an active session is on disk and, if so, attach DB::listen()
     * for this PHP-FPM request or CLI process.
     */
    protected function maybeAttachListener(): void
    {
        $store = $this->app->make(PerfStore::class);

        $session = $store->activeSession();

        if (! $session) {
            return;
        }

        $session_id = $session['session_id'];

        $logger = $this->app->make(QueryLogger::class);
        $logger->start($session_id);
    }
}
