<?php

namespace App\Services;

/**
 * Outcome of an idempotent operation: either a fresh execution or a replay of
 * the recorded result for a repeated key.
 */
class IdempotencyResult
{
    /**
     * @param  array<string, mixed>|null  $responsePayload
     */
    public function __construct(
        public readonly bool $replayed,
        public readonly ?string $resourceType,
        public readonly ?int $resourceId,
        public readonly ?int $responseCode,
        public readonly ?array $responsePayload,
    ) {}
}
