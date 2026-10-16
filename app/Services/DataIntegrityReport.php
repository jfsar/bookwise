<?php

namespace App\Services;

/**
 * Outcome of verifying a restored database's integrity (§0.31).
 *
 * @phpstan-type IntegrityCheck array{passed: bool, detail: string}
 */
class DataIntegrityReport
{
    /**
     * @param  array<string, array{passed: bool, detail: string}>  $checks
     */
    public function __construct(public readonly array $checks) {}

    public function passed(): bool
    {
        return $this->failures() === [];
    }

    /**
     * @return array<string, array{passed: bool, detail: string}>
     */
    public function failures(): array
    {
        return array_filter(
            $this->checks,
            static fn (array $check): bool => $check['passed'] === false,
        );
    }
}
