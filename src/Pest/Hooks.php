<?php

declare(strict_types=1);

namespace Mateffy\Laraperf\Pest;

use Mateffy\Laraperf\Testing\PerformanceCapture;

/**
 * Global hooks for automatic performance testing integration.
 *
 * Registers beforeEach and afterEach hooks that:
 * - Start an isolated performance capture session for each test
 * - Stop the capture after test execution
 * - Validate declarative constraints (test()->maxQueryCount() etc)
 * - Store results for access via expect()->performance()
 */
class Hooks
{
    /**
     * Register global hooks with Pest.
     *
     * Called automatically when Pest loads the plugin.
     */
    public static function register(): void
    {
        // Start performance capture before each test
        beforeEach(function () {
            // Store constraints set via test()->maxQueryCount() etc
            $this->performanceConstraints = $this->performanceConstraints ?? [];

            // Start capture for this test
            $this->performanceCapture = new PerformanceCapture;
            $this->performanceCapture->start();
        });

        // Stop capture and validate constraints after each test
        afterEach(function () {
            // Stop the capture if it's still active
            if ($this->performanceCapture !== null && $this->performanceCapture->isActive()) {
                $this->performanceResult = $this->performanceCapture->stop();
            }

            // Validate any declarative constraints set via test()->maxQueryCount() etc
            if (method_exists($this, 'validatePerformanceConstraints')) {
                $this->validatePerformanceConstraints();
            }
        });
    }
}
