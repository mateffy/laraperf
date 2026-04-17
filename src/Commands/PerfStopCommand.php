<?php

declare(strict_types=1);

namespace Mateffy\Laraperf\Commands;

use Illuminate\Console\Command;
use Mateffy\Laraperf\Storage\PerfStore;

/**
 * Stop the active perf session.
 *
 * Finalizes the data file and removes the tracker.
 * PHP-FPM requests will stop capturing immediately.
 */
class PerfStopCommand extends Command
{
    protected $signature = 'perf:stop';

    protected $description = 'Stop the active perf:watch session.';

    public function __construct(protected PerfStore $store)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $tracker = $this->store->activeTracker();

        if (! $tracker) {
            $this->info('No active perf session found.');

            return self::SUCCESS;
        }

        $session_id = $tracker['session_id'];
        $this->store->finalizeSession($session_id);
        $this->store->removeTracker();
        $this->info("Stopped session={$session_id}");

        return self::SUCCESS;
    }
}
