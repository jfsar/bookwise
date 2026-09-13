<?php

namespace App\Services;

/**
 * Result of verifying a tamper-evident audit-log hash chain for one organization.
 *
 * @phpstan-type AuditChainFailure array{sequence_no: int|null, reason: string}
 */
class AuditLogVerificationResult
{
    /**
     * @param  array<int, array{sequence_no: int|null, reason: string}>  $failures
     */
    public function __construct(
        public readonly bool $valid,
        public readonly int $checkedCount,
        public readonly array $failures = [],
    ) {}
}
