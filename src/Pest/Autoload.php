<?php

declare(strict_types=1);

use Mateffy\Laraperf\Pest\Expectations\PerformanceExpectation;
use Mateffy\Laraperf\Pest\PerformanceTestingTrait;
use Mateffy\Laraperf\Testing\PerformanceResult;
use Pest\Plugin;
use PHPUnit\Framework\TestCase;

// Register the trait that adds $this->performance() methods in test closures
Plugin::uses(PerformanceTestingTrait::class);

// Load Pest-specific functions
require_once __DIR__.'/Functions.php';

// Store reference to current test for access in expectations
$GLOBALS['_laraperf_current_test'] = null;

// Register custom expect()->performance() expectation
// This is deferred until first use, so it's safe to register always
expect()->extend('performance', function () {
    /** @var TestCase|null $test */
    $test = $GLOBALS['_laraperf_current_test'] ?? test();

    // Try to get performance result from the test
    if ($test instanceof TestCase && method_exists($test, 'performance')) {
        try {
            $result = $test->performance();

            if ($result instanceof PerformanceResult) {
                return new PerformanceExpectation($result);
            }
        } catch (RuntimeException) {
            // No performance data available from ->performance() yet
        }
    }

    // Try to get from performanceResult property
    if ($test instanceof TestCase && property_exists($test, 'performanceResult') && $test->performanceResult !== null) {
        if ($test->performanceResult instanceof PerformanceResult) {
            return new PerformanceExpectation($test->performanceResult);
        }
    }

    // If there's an active capture in progress, stop it and get results
    if ($test instanceof TestCase && property_exists($test, 'performanceCapture') && $test->performanceCapture !== null && method_exists($test->performanceCapture, 'isActive') && $test->performanceCapture->isActive()) {
        $result = $test->performanceCapture->stop();
        if ($result instanceof PerformanceResult) {
            $test->performanceResult = $result;

            return new PerformanceExpectation($result);
        }
    }

    throw new RuntimeException(
        'No performance data available. '.
        'Make sure to run tests with performance hooks enabled, '.
        'or use measure() to capture performance manually.'
    );
});
