<?php

declare(strict_types=1);

use Mateffy\Laraperf\Testing\PerformanceCapture;
use Mateffy\Laraperf\Tests\TestCase;

uses(TestCase::class)
    ->in(__DIR__)
    ->beforeEach(function () {
        // Store reference to current test for expect()->performance() access
        $GLOBALS['_laraperf_current_test'] = $this;

        // Store constraints set via test()->maxQueryCount() etc
        $this->performanceConstraints = $this->performanceConstraints ?? [];

        // Start capture for this test
        $this->performanceCapture = new PerformanceCapture;
        $this->performanceCapture->start();
    })
    ->afterEach(function () {
        // Stop the capture if it's still active
        if ($this->performanceCapture !== null && $this->performanceCapture->isActive()) {
            $this->performanceResult = $this->performanceCapture->stop();
        }

        // Clear the global reference
        $GLOBALS['_laraperf_current_test'] = null;

        // Validate any declarative constraints set via test()->maxQueryCount() etc
        if (method_exists($this, 'validatePerformanceConstraints')) {
            $this->validatePerformanceConstraints();
        }
    });
