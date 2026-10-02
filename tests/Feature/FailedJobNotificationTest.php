<?php

namespace Tests\Feature;

use App\Contracts\OrganizationScopedJob;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Notifications\JobFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class FailedJobNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(Organization $organization): User
    {
        $user = User::factory()->create();
        $user->organizations()->attach($organization->id, ['membership_status' => 'active']);

        $role = Role::create([
            'name' => 'Administrator',
            'slug' => 'admin-'.Str::lower(Str::random(10)),
            'is_global' => false,
        ]);
        $role->permissions()->attach(Permission::firstOrCreate(['name' => 'manage-settings'])->id);
        $user->roles()->attach($role->id, ['organization_id' => $organization->id]);

        return $user;
    }

    private function runFailingJob(int $organizationId): void
    {
        try {
            FailingTenantJob::dispatch($organizationId);
        } catch (RuntimeException $exception) {
            $this->assertSame('Export failed', $exception->getMessage());

            return;
        }

        $this->fail('The queued job was expected to fail.');
    }

    public function test_failed_tenant_job_notifies_organization_administrators(): void
    {
        $organization = Organization::factory()->create();
        $administrator = $this->administrator($organization);

        $this->runFailingJob($organization->id);

        $notification = $administrator->notifications()->where('type', JobFailed::class)->sole();

        $this->assertSame($organization->id, $notification->organization_id);
        $this->assertNull($notification->read_at);
        $this->assertSame('FailingTenantJob', $notification->data['job_name']);
        $this->assertSame('Export failed', $notification->data['exception_message']);
    }

    public function test_failed_job_for_a_missing_organization_is_not_broadcast(): void
    {
        $organization = Organization::factory()->create();
        $administrator = $this->administrator($organization);

        $this->runFailingJob($organization->id + 10_000);

        $this->assertSame(0, $administrator->notifications()->count());
    }

    public function test_failed_job_does_not_reach_users_without_settings_permission(): void
    {
        $organization = Organization::factory()->create();
        $administrator = $this->administrator($organization);

        $staff = User::factory()->create();
        $staff->organizations()->attach($organization->id, ['membership_status' => 'active']);

        $this->runFailingJob($organization->id);

        $this->assertSame(1, $administrator->notifications()->where('type', JobFailed::class)->count());
        $this->assertSame(0, $staff->notifications()->count());
    }
}

/**
 * Minimal tenant-scoped job used to exercise failed job attribution.
 */
class FailingTenantJob implements OrganizationScopedJob, ShouldQueue
{
    use Queueable;

    public function __construct(public int $tenantOrganizationId) {}

    public function organizationId(): int
    {
        return $this->tenantOrganizationId;
    }

    public function handle(): void
    {
        throw new RuntimeException('Export failed');
    }
}
