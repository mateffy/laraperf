<?php

declare(strict_types=1);

namespace Mateffy\Laraperf\Commands;

use Illuminate\Console\Command;
use Mateffy\Laraperf\Storage\PerfStore;

/**
 * Stop one or all active perf sessions.
 *
 * Marks the tracker as completed and finalizes the data file.
 * PHP-FPM requests will stop capturing on the next boot.
 */
class PerfStopCommand extends Command
{
    protected $signature = 'perf:stop
        {--session= : Stop a specific session ID. Defaults to the active session.}';

    protected $description = 'Stop the active perf:watch session.';

    public function __construct(protected PerfStore $store)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $session_id = $this->option('session');

        if ($session_id) {
            $tracker = $this->store->readTracker($session_id);

            if (! $tracker) {
                $this->error("Session not found: {$session_id}");

                return self::FAILURE;
            }

            if ($tracker['status'] !== 'active') {
                $this->warn("Session {$session_id} is already {$tracker['status']}.");

                return self::SUCCESS;
            }

            $this->store->finalizeTracker($session_id);
            $this->store->finalizeSession($session_id);
            $this->store->removeTracker($session_id);
            $this->info("Stopped session={$session_id}");

            return self::SUCCESS;
        }

        $active = $this->store->activeTracker();

        if (! $active) {
            $this->info('No active perf sessions found.');

            return self::SUCCESS;
        }

        $session_id = $active['session_id'];
        $this->store->finalizeTracker($session_id);
        $this->store->finalizeSession($session_id);
        $this->store->removeTracker($session_id);
        $this->info("Stopped session={$session_id}");

        return self::SUCCESS;
    }
}
