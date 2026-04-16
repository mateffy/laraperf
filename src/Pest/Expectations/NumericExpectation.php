<?php

declare(strict_types=1);

namespace Mateffy\Laraperf\Pest\Expectations;

use Closure;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * Numeric expectation with fluent comparison methods.
 *
 * Used for duration, memory, and count assertions.
 */
class NumericExpectation
{
    protected float|int $value;

    protected string $label;

    protected Closure $describe;

    protected bool $isMemory;

    public function __construct(float|int $value, string $label, ?Closure $describe = null, bool $isMemory = false)
    {
        $this->value = $value;
        $this->label = $label;
        $this->describe = $describe ?? fn () => "{$label} is {$value}";
        $this->isMemory = $isMemory;
    }

    /**
     * Assert value equals expected.
     *
     * @throws ExpectationFailedException
     */
    public function toBe(float|int $expected): void
    {
        if ($this->value !== $expected) {
            throw new ExpectationFailedException(
                "Expected {$this->label} to be {$this->format($expected)}, got {$this->format($this->value)}"
            );
        }
    }

    /**
     * Assert value is less than expected.
     *
     * @throws ExpectationFailedException
     */
    public function toBeLessThan(float|int $expected): void
    {
        if ($this->value >= $expected) {
            throw new ExpectationFailedException(
                "Expected {$this->label} to be less than {$this->format($expected)}, got {$this->format($this->value)}"
            );
        }
    }

    /**
     * Assert value is less than or equal to expected.
     *
     * @throws ExpectationFailedException
     */
    public function toBeLessThanOrEqual(float|int $expected): void
    {
        if ($this->value > $expected) {
            throw new ExpectationFailedException(
                "Expected {$this->label} to be <= {$this->format($expected)}, got {$this->format($this->value)}"
            );
        }
    }

    /**
     * Assert value is greater than expected.
     *
     * @throws ExpectationFailedException
     */
    public function toBeGreaterThan(float|int $expected): void
    {
        if ($this->value <= $expected) {
            throw new ExpectationFailedException(
                "Expected {$this->label} to be greater than {$this->format($expected)}, got {$this->format($this->value)}"
            );
        }
    }

    /**
     * Assert value is greater than or equal to expected.
     *
     * @throws ExpectationFailedException
     */
    public function toBeGreaterThanOrEqual(float|int $expected): void
    {
        if ($this->value < $expected) {
            throw new ExpectationFailedException(
                "Expected {$this->label} to be >= {$this->format($expected)}, got {$this->format($this->value)}"
            );
        }
    }

    /**
     * Assert value is between min and max (inclusive).
     *
     * @throws ExpectationFailedException
     */
    public function toBeBetween(float|int $min, float|int $max): void
    {
        if ($this->value < $min || $this->value > $max) {
            throw new ExpectationFailedException(
                "Expected {$this->label} to be between {$this->format($min)} and {$this->format($max)}, got {$this->format($this->value)}"
            );
        }
    }

    /**
     * Assert value is approximately equal (within tolerance).
     *
     * @throws ExpectationFailedException
     */
    public function toBeApproximately(float|int $expected, float|int $tolerance): void
    {
        $diff = abs($this->value - $expected);

        if ($diff > $tolerance) {
            throw new ExpectationFailedException(
                "Expected {$this->label} to be approximately {$this->format($expected)} (±{$this->format($tolerance)}), got {$this->format($this->value)} (diff: {$this->format($diff)})"
            );
        }
    }

    /**
     * Get the raw value for custom assertions.
     */
    public function value(): float|int
    {
        return $this->value;
    }

    /**
     * Format value for display (with memory units if applicable).
     */
    protected function format(float|int $value): string
    {
        if (! $this->isMemory) {
            return (string) $value;
        }

        $bytes = (int) $value;
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, 2).' '.$units[$unitIndex];
    }
}
