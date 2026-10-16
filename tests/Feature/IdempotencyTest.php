<?php

namespace Tests\Feature;

use App\Enums\IdempotencyStatus;
use App\Exceptions\IdempotencyConflictException;
use App\Exceptions\IdempotencyInProgressException;
use App\Models\IdempotencyKey;
use App\Models\Organization;
use App\Models\User;
use App\Services\IdempotencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class IdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private function fingerprint(string $operationType, array $requestData): string
    {
        return hash('sha256', json_encode([
            'operation' => $operationType,
            'request' => $requestData,
        ], JSON_THROW_ON_ERROR));
    }

    public function test_same_key_and_request_executes_once_and_replays_the_result(): void
    {
        $organization = Organization::factory()->create();
        $service = app(IdempotencyService::class);
        $calls = 0;

        $first = $service->run($organization, 'key-1', 'test.operation', ['amount' => 100], function () use (&$calls) {
            $calls++;

            return User::factory()->create();
        });

        $second = $service->run($organization, 'key-1', 'test.operation', ['amount' => 100], function () use (&$calls) {
            $calls++;

            return User::factory()->create();
        });

        $this->assertSame(1, $calls);
        $this->assertFalse($first->replayed);
        $this->assertTrue($second->replayed);
        $this->assertSame($first->resourceId, $second->resourceId);
        $this->assertSame(User::class, $second->resourceType);
        $this->assertDatabaseCount('idempotency_keys', 1);
    }

    public function test_same_key_with_a_different_request_is_rejected(): void
    {
        $organization = Organization::factory()->create();
        $service = app(IdempotencyService::class);

        $service->run($organization, 'key-2', 'test.operation', ['amount' => 100], fn () => User::factory()->create());

        $this->expectException(IdempotencyConflictException::class);

        $service->run($organization, 'key-2', 'test.operation', ['amount' => 200], fn () => User::factory()->create());
    }

    public function test_integral_floats_fingerprint_the_same_as_integers(): void
    {
        $organization = Organization::factory()->create();
        $service = app(IdempotencyService::class);
        $calls = 0;

        $first = $service->run($organization, 'key-float', 'test.operation', ['amount' => 100], function () use (&$calls) {
            $calls++;

            return User::factory()->create();
        });

        $second = $service->run($organization, 'key-float', 'test.operation', ['amount' => 100.0], function () use (&$calls) {
            $calls++;

            return User::factory()->create();
        });

        $this->assertSame(1, $calls);
        $this->assertFalse($first->replayed);
        $this->assertTrue($second->replayed);
        $this->assertSame($first->resourceId, $second->resourceId);
    }

    public function test_negative_zero_fingerprints_the_same_as_zero(): void
    {
        $organization = Organization::factory()->create();
        $service = app(IdempotencyService::class);
        $calls = 0;

        $first = $service->run($organization, 'key-negative-zero', 'test.operation', ['amount' => 0], function () use (&$calls) {
            $calls++;

            return User::factory()->create();
        });

        $second = $service->run($organization, 'key-negative-zero', 'test.operation', ['amount' => -0.0], function () use (&$calls) {
            $calls++;

            return User::factory()->create();
        });

        $this->assertSame(1, $calls);
        $this->assertTrue($second->replayed);
        $this->assertSame($first->resourceId, $second->resourceId);
    }

    public function test_nested_integral_floats_fingerprint_like_integers(): void
    {
        $organization = Organization::factory()->create();
        $service = app(IdempotencyService::class);
        $calls = 0;

        $first = $service->run($organization, 'key-nested', 'test.operation', [
            'lines' => [['amount' => 50.0], ['amount' => 25]],
        ], function () use (&$calls) {
            $calls++;

            return User::factory()->create();
        });

        $second = $service->run($organization, 'key-nested', 'test.operation', [
            'lines' => [['amount' => 50], ['amount' => 25.0]],
        ], function () use (&$calls) {
            $calls++;

            return User::factory()->create();
        });

        $this->assertSame(1, $calls);
        $this->assertTrue($second->replayed);
        $this->assertSame($first->resourceId, $second->resourceId);
    }

    public function test_fractional_floats_remain_distinct_from_integers(): void
    {
        $organization = Organization::factory()->create();
        $service = app(IdempotencyService::class);

        $service->run($organization, 'key-fraction', 'test.operation', ['amount' => 100.5], fn () => User::factory()->create());

        $this->expectException(IdempotencyConflictException::class);

        $service->run($organization, 'key-fraction', 'test.operation', ['amount' => 100], fn () => User::factory()->create());
    }

    public function test_different_keys_create_separate_operations(): void
    {
        $organization = Organization::factory()->create();
        $service = app(IdempotencyService::class);

        $service->run($organization, 'key-3a', 'test.operation', ['amount' => 100], fn () => User::factory()->create());
        $service->run($organization, 'key-3b', 'test.operation', ['amount' => 100], fn () => User::factory()->create());

        $this->assertDatabaseCount('idempotency_keys', 2);
    }

    public function test_in_progress_key_is_rejected(): void
    {
        $organization = Organization::factory()->create();
        $service = app(IdempotencyService::class);

        IdempotencyKey::create([
            'organization_id' => $organization->id,
            'key' => 'key-4',
            'operation_type' => 'test.operation',
            'request_hash' => $this->fingerprint('test.operation', ['amount' => 100]),
            'status' => IdempotencyStatus::Pending,
        ]);

        $this->expectException(IdempotencyInProgressException::class);

        $service->run($organization, 'key-4', 'test.operation', ['amount' => 100], fn () => User::factory()->create());
    }

    public function test_failed_operation_can_be_retried_with_the_same_key(): void
    {
        $organization = Organization::factory()->create();
        $service = app(IdempotencyService::class);
        $attempts = 0;

        try {
            $service->run($organization, 'key-5', 'test.operation', ['amount' => 100], function () use (&$attempts) {
                $attempts++;

                throw new RuntimeException('boom');
            });
        } catch (RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $result = $service->run($organization, 'key-5', 'test.operation', ['amount' => 100], function () use (&$attempts) {
            $attempts++;

            return User::factory()->create();
        });

        $this->assertSame(2, $attempts);
        $this->assertFalse($result->replayed);
        $this->assertNotNull($result->resourceId);
    }

    public function test_expired_key_can_be_reused(): void
    {
        $organization = Organization::factory()->create();
        $service = app(IdempotencyService::class);

        IdempotencyKey::create([
            'organization_id' => $organization->id,
            'key' => 'key-6',
            'operation_type' => 'test.operation',
            'request_hash' => $this->fingerprint('test.operation', ['amount' => 100]),
            'status' => IdempotencyStatus::Completed,
            'expires_at' => now()->subMinute(),
        ]);

        $calls = 0;
        $result = $service->run($organization, 'key-6', 'test.operation', ['amount' => 100], function () use (&$calls) {
            $calls++;

            return User::factory()->create();
        });

        $this->assertSame(1, $calls);
        $this->assertFalse($result->replayed);
        $this->assertDatabaseCount('idempotency_keys', 1);
    }

    public function test_reservation_defaults_to_a_future_expiry(): void
    {
        $organization = Organization::factory()->create();
        $service = app(IdempotencyService::class);

        $service->run($organization, 'key-7', 'test.operation', ['amount' => 100], fn () => User::factory()->create());

        $record = IdempotencyKey::query()->where('key', 'key-7')->firstOrFail();

        $this->assertNotNull($record->expires_at);
        $this->assertTrue($record->expires_at->isAfter(now()->addHours(23)));
    }

    public function test_stale_pending_reservation_is_reclaimed(): void
    {
        $organization = Organization::factory()->create();
        $service = app(IdempotencyService::class);

        IdempotencyKey::create([
            'organization_id' => $organization->id,
            'key' => 'key-8',
            'operation_type' => 'test.operation',
            'request_hash' => $this->fingerprint('test.operation', ['amount' => 100]),
            'status' => IdempotencyStatus::Pending,
            'expires_at' => now()->subMinute(),
        ]);

        $calls = 0;
        $result = $service->run($organization, 'key-8', 'test.operation', ['amount' => 100], function () use (&$calls) {
            $calls++;

            return User::factory()->create();
        });

        $this->assertSame(1, $calls);
        $this->assertFalse($result->replayed);
        $this->assertSame(IdempotencyStatus::Completed, IdempotencyKey::query()->where('key', 'key-8')->firstOrFail()->status);
    }

    public function test_takeover_does_not_clobber_a_concurrent_completion(): void
    {
        $organization = Organization::factory()->create();
        $winner = User::factory()->create();
        $service = app(IdempotencyService::class);

        $record = IdempotencyKey::create([
            'organization_id' => $organization->id,
            'key' => 'key-9',
            'operation_type' => 'test.operation',
            'request_hash' => $this->fingerprint('test.operation', ['amount' => 100]),
            'status' => IdempotencyStatus::Failed,
        ]);

        // Simulate a concurrent worker completing the key between this request's
        // read and its takeover: the first read sees the stale snapshot, but the
        // persisted row is already Completed when the conditional update runs.
        $flipped = false;
        IdempotencyKey::retrieved(function () use ($record, $winner, &$flipped): void {
            if ($flipped) {
                return;
            }

            $flipped = true;

            DB::table('idempotency_keys')->where('id', $record->id)->update([
                'status' => IdempotencyStatus::Completed->value,
                'resource_type' => User::class,
                'resource_id' => $winner->id,
            ]);
        });

        $calls = 0;
        $result = $service->run($organization, 'key-9', 'test.operation', ['amount' => 100], function () use (&$calls) {
            $calls++;

            return User::factory()->create();
        });

        $this->assertSame(0, $calls);
        $this->assertTrue($result->replayed);
        $this->assertSame($winner->id, $result->resourceId);
    }

    public function test_expire_stale_command_marks_abandoned_reservations_expired(): void
    {
        $organization = Organization::factory()->create();

        $stale = IdempotencyKey::create([
            'organization_id' => $organization->id,
            'key' => 'key-10',
            'operation_type' => 'test.operation',
            'request_hash' => $this->fingerprint('test.operation', ['amount' => 100]),
            'status' => IdempotencyStatus::Pending,
            'expires_at' => now()->subMinute(),
        ]);

        $fresh = IdempotencyKey::create([
            'organization_id' => $organization->id,
            'key' => 'key-11',
            'operation_type' => 'test.operation',
            'request_hash' => $this->fingerprint('test.operation', ['amount' => 100]),
            'status' => IdempotencyStatus::Pending,
            'expires_at' => now()->addDay(),
        ]);

        $this->artisan('idempotency:expire-stale')->assertSuccessful();

        $this->assertSame(IdempotencyStatus::Expired, $stale->fresh()->status);
        $this->assertSame(IdempotencyStatus::Pending, $fresh->fresh()->status);
    }
}
