<?php

namespace App\Services;

use App\Enums\IdempotencyStatus;
use App\Exceptions\IdempotencyConflictException;
use App\Exceptions\IdempotencyInProgressException;
use App\Models\IdempotencyKey;
use App\Models\Organization;
use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Throwable;

/**
 * Shared idempotency store for critical operations (§0.26).
 *
 * A key identifies one logical operation, not one HTTP request. A retry with the
 * same key and request fingerprint replays the recorded result instead of
 * re-running the operation; a reused key with a different fingerprint is
 * rejected. Reservation is a single atomic insert, so concurrent duplicates
 * cannot both proceed.
 */
class IdempotencyService
{
    /**
     * Run $operation exactly once per (organization, key, operation type).
     *
     * @param  array<string, mixed>  $requestData
     */
    public function run(
        Organization $organization,
        string $key,
        string $operationType,
        array $requestData,
        Closure $operation,
        ?DateTimeInterface $expiresAt = null,
    ): IdempotencyResult {
        $expiresAt ??= now()->addDay();

        $fingerprint = $this->fingerprint($operationType, $requestData);

        $reservation = $this->reserve($organization, $key, $operationType, $fingerprint, $expiresAt);

        if ($reservation instanceof IdempotencyResult) {
            return $reservation;
        }

        try {
            $result = $operation();
        } catch (Throwable $e) {
            $reservation->forceFill(['status' => IdempotencyStatus::Failed])->save();

            throw $e;
        }

        [$resourceType, $resourceId, $responseCode, $responsePayload] = $this->describe($result);

        $reservation->forceFill([
            'status' => IdempotencyStatus::Completed,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'response_code' => $responseCode,
            'response_payload' => $responsePayload,
        ])->save();

        return new IdempotencyResult(false, $resourceType, $resourceId, $responseCode, $responsePayload);
    }

    /**
     * Atomically claim the key. Returns the pending record to execute against,
     * or a replayed result when the key was already completed.
     */
    protected function reserve(
        Organization $organization,
        string $key,
        string $operationType,
        string $fingerprint,
        ?DateTimeInterface $expiresAt,
    ): IdempotencyKey|IdempotencyResult {
        $inserted = IdempotencyKey::query()->insertOrIgnore([
            'organization_id' => $organization->id,
            'key' => $key,
            'operation_type' => $operationType,
            'request_hash' => $fingerprint,
            'status' => IdempotencyStatus::Pending->value,
            'expires_at' => $expiresAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $record = IdempotencyKey::query()
            ->where('organization_id', $organization->id)
            ->where('key', $key)
            ->where('operation_type', $operationType)
            ->first();

        if (! $record) {
            throw new RuntimeException('Failed to reserve the idempotency key.');
        }

        if ($inserted === 1) {
            return $record;
        }

        if ($record->expires_at !== null && $record->expires_at->isPast()) {
            return $this->takeOver($record, $key, $fingerprint, $expiresAt);
        }

        if ($record->request_hash !== $fingerprint) {
            throw new IdempotencyConflictException(
                "Idempotency key [{$key}] was already used with a different request."
            );
        }

        return match ($record->status) {
            IdempotencyStatus::Completed => new IdempotencyResult(
                true,
                $record->resource_type,
                $record->resource_id,
                $record->response_code,
                $record->response_payload,
            ),
            IdempotencyStatus::Pending => throw new IdempotencyInProgressException(
                "A request with idempotency key [{$key}] is already in progress."
            ),
            default => $this->takeOver($record, $key, $fingerprint, $expiresAt),
        };
    }

    /**
     * Atomically claim a failed, expired, or stale reservation for a fresh
     * attempt. Only one concurrent caller can win the conditional update; any
     * loser observes the winner's current state instead of double-executing.
     */
    protected function takeOver(
        IdempotencyKey $record,
        string $key,
        string $fingerprint,
        ?DateTimeInterface $expiresAt,
    ): IdempotencyKey|IdempotencyResult {
        $claimed = IdempotencyKey::query()
            ->whereKey($record->getKey())
            ->where(function ($query) {
                $query->whereIn('status', [
                    IdempotencyStatus::Failed->value,
                    IdempotencyStatus::Expired->value,
                ])->orWhere(function ($query) {
                    $query->whereNotNull('expires_at')->where('expires_at', '<', now());
                });
            })
            ->update([
                'status' => IdempotencyStatus::Pending->value,
                'request_hash' => $fingerprint,
                'resource_type' => null,
                'resource_id' => null,
                'response_code' => null,
                'response_payload' => null,
                'expires_at' => $expiresAt,
                'updated_at' => now(),
            ]);

        $record->refresh();

        if ($claimed === 1) {
            return $record;
        }

        if ($record->request_hash !== $fingerprint) {
            throw new IdempotencyConflictException(
                "Idempotency key [{$key}] was already used with a different request."
            );
        }

        return match ($record->status) {
            IdempotencyStatus::Completed => new IdempotencyResult(
                true,
                $record->resource_type,
                $record->resource_id,
                $record->response_code,
                $record->response_payload,
            ),
            default => throw new IdempotencyInProgressException(
                "A request with idempotency key [{$key}] is already in progress."
            ),
        };
    }

    /**
     * @return array{0: string|null, 1: int|null, 2: int|null, 3: array<string, mixed>|null}
     */
    protected function describe(mixed $result): array
    {
        if ($result instanceof Model) {
            return [$result::class, (int) $result->getKey(), null, null];
        }

        if (is_array($result)) {
            return [null, null, null, $result];
        }

        return [null, null, null, null];
    }

    /**
     * @param  array<string, mixed>  $requestData
     */
    protected function fingerprint(string $operationType, array $requestData): string
    {
        return hash('sha256', json_encode([
            'operation' => $operationType,
            'request' => $this->canonicalize($requestData),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Sort keys recursively so logically identical requests fingerprint equal.
     * Integral floats are normalized to integers so `100` and `100.0` — the same
     * amount arriving as JSON number or decoded float — share one fingerprint.
     *
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    protected function canonicalize(array $value): array
    {
        ksort($value, SORT_STRING);

        foreach ($value as &$item) {
            if (is_array($item)) {
                $item = $this->canonicalize($item);

                continue;
            }

            if ($this->isIntegralFloat($item)) {
                $item = (int) $item;
            }
        }
        unset($item);

        return $value;
    }

    private function isIntegralFloat(mixed $value): bool
    {
        return is_float($value)
            && is_finite($value)
            && floor($value) === $value
            && $value <= PHP_INT_MAX
            && $value >= PHP_INT_MIN;
    }
}
