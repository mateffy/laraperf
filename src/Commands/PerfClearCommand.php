<?php

declare(strict_types=1);

namespace Mateffy\Laraperf\Commands;

use Illuminate\Console\Command;
use Mateffy\Laraperf\Storage\PerfStore;

/**
 * Wipe all stored perf sessions and trackers from storage/perf/.
 */
class PerfClearCommand extends Command
{
    protected $signature = 'perf:clear
        {--force : Skip the confirmation prompt.}';

    protected $description = 'Delete all stored perf sessions from storage/perf/.';

    public function __construct(protected PerfStore $store)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Delete all perf sessions?')) {
            $this->line('Aborted.');

            return self::SUCCESS;
        }

        $active = $this->store->activeTracker();

        if ($active) {
            $this->warn('Active session detected — stop it with `perf:stop` before clearing.');

            return self::FAILURE;
        }

        $this->store->clearAll();
        $this->info('All perf sessions cleared.');

        return self::SUCCESS;
    }
}
