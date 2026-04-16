<?php

declare(strict_types=1);

use Mateffy\Laraperf\Pest\Expectations\PerformanceExpectation;
use Mateffy\Laraperf\Pest\PerformanceTestingTrait;
use Pest\Plugin;

// Register the trait that adds $this->performance() methods in test closures
Plugin::uses(PerformanceTestingTrait::class);

// Load Pest-specific functions
require_once __DIR__.'/Functions.php';

// Store reference to current test for access in expectations
$GLOBALS['_laraperf_current_test'] = null;

// Register custom expect()->performance() expectation
// This is deferred until first use, so it's safe to register always
expect()->extend('performance', function () {
    // Get the current test case from the global if available
    // The hooks set this when running
    $test = $GLOBALS['_laraperf_current_test'] ?? test();

    // Try to get performance result from the test
    if (method_exists($test, 'performance')) {
        try {
            $result = $test->performance();

            return new PerformanceExpectation($result);
        } catch (RuntimeException) {
            // No performance data available from ->performance() yet
        }
    }

    // Try to get from performanceResult property
    if (isset($test->performanceResult) && $test->performanceResult !== null) {
        return new PerformanceExpectation($test->performanceResult);
    }

    // If there's an active capture in progress, stop it and get results
    if (isset($test->performanceCapture) && $test->performanceCapture !== null && $test->performanceCapture->isActive()) {
        $result = $test->performanceCapture->stop();
        $test->performanceResult = $result;

        return new PerformanceExpectation($result);
    }

    throw new RuntimeException(
        'No performance data available. '.
        'Make sure to run tests with performance hooks enabled, '.
        'or use measure() to capture performance manually.'
    );
});
