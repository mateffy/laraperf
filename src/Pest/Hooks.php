<?php

declare(strict_types=1);

namespace Mateffy\Laraperf\Pest;

use Mateffy\Laraperf\Testing\PerformanceCapture;
use Mateffy\Laraperf\Testing\PerformanceResult;
use PHPUnit\Framework\TestCase;

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
            /** @var TestCase $this */
            $this->performanceConstraints = [];

            // Start capture for this test
            $this->performanceCapture = new PerformanceCapture;
            $this->performanceCapture->start();
        });

        // Stop capture and validate constraints after each test
        afterEach(function () {
            /** @var TestCase $this */
            // Stop the capture if it's still active
            if (property_exists($this, 'performanceCapture') && $this->performanceCapture !== null && $this->performanceCapture instanceof PerformanceCapture && $this->performanceCapture->isActive()) {
                /** @var PerformanceResult $result */
                $result = $this->performanceCapture->stop();
                $this->performanceResult = $result;
            }

            // Validate any declarative constraints set via test()->maxQueryCount() etc
            if (method_exists($this, 'validatePerformanceConstraints')) {
                $this->validatePerformanceConstraints();
            }
        });
    }
}
