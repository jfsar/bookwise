<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();

        $this->user = User::factory()->create([
            'name' => 'Auditor',
            'email' => 'auditor@example.com',
        ]);

        $this->user->organizations()->attach($this->organization->id, ['membership_status' => 'active']);

        session(['current_organization_id' => $this->organization->id]);
    }

    private function grantPermission(User $user, Organization $organization, string $permission): void
    {
        $role = Role::create([
            'name' => 'Audit Viewer',
            'slug' => 'audit-viewer-'.uniqid(),
            'is_global' => false,
        ]);
        $role->permissions()->attach(Permission::firstOrCreate(['name' => $permission])->id);

        $user->roles()->attach($role->id, ['organization_id' => $organization->id]);
    }

    public function test_index_forbids_user_without_permission(): void
    {
        $this->actingAs($this->user);

        $response = $this->get('/settings/audit-logs');

        $response->assertStatus(403);
    }

    public function test_index_allows_user_with_permission(): void
    {
        $this->grantPermission($this->user, $this->organization, 'view-audit-logs');
        $this->actingAs($this->user);

        $response = $this->get('/settings/audit-logs');

        $response->assertOk();
    }

    public function test_index_shows_org_audit_logs(): void
    {
        $this->grantPermission($this->user, $this->organization, 'view-audit-logs');
        $this->actingAs($this->user);

        AuditLog::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'action' => 'journal-entry.submitted',
            'auditable_type' => 'App\\Models\\JournalEntry',
            'auditable_id' => 1,
            'old_values' => [],
            'new_values' => [],
            'ip_address' => '127.0.0.1',
            'sequence_no' => 1,
            'previous_hash' => str_repeat('0', 64),
        ]);

        $response = $this->get('/settings/audit-logs');

        $response->assertOk();
    }

    public function test_hash_chain_links_records(): void
    {
        $service = app(AuditLogService::class);

        $log1 = $service->record($this->organization, $this->user->id, 'journal-entry.submitted', 'App\\Models\\JournalEntry', 1, [], ['status' => 'submitted']);
        $log2 = $service->record($this->organization, $this->user->id, 'journal-entry.approved', 'App\\Models\\JournalEntry', 1, ['status' => 'submitted'], ['status' => 'approved']);

        $this->assertEquals(1, $log1->sequence_no);
        $this->assertEquals(str_repeat('0', 64), $log1->previous_hash);

        $this->assertEquals(2, $log2->sequence_no);
        $this->assertEquals($log1->record_hash, $log2->previous_hash);
        $this->assertNotEquals($log1->record_hash, $log2->record_hash);
    }

    public function test_hash_chain_detects_tampering(): void
    {
        $service = app(AuditLogService::class);

        $service->record($this->organization, $this->user->id, 'journal-entry.submitted', 'App\\Models\\JournalEntry', 1, [], ['status' => 'submitted']);
        $log2 = $service->record($this->organization, $this->user->id, 'journal-entry.approved', 'App\\Models\\JournalEntry', 1, ['status' => 'submitted'], ['status' => 'approved']);

        $log2->update(['action' => 'role.deleted']);

        $log1 = AuditLog::where('organization_id', $this->organization->id)->where('sequence_no', 1)->first();
        $tampered = AuditLog::where('organization_id', $this->organization->id)->where('sequence_no', 2)->first();

        // Recompute the hash using its own (now tampered) action and the original created_at
        $tamperedPayload = json_encode([
            'organization_id' => $tampered->organization_id,
            'sequence_no' => $tampered->sequence_no,
            'action' => $tampered->action,
            'auditable_type' => $tampered->auditable_type,
            'auditable_id' => $tampered->auditable_id,
            'old_values' => $tampered->old_values,
            'new_values' => $tampered->new_values,
            'created_at' => $tampered->created_at->toIso8601String(),
        ], JSON_THROW_ON_ERROR);

        $recomputedHash = hash('sha256', $log1->record_hash.$tamperedPayload);

        $this->assertNotEquals($recomputedHash, $tampered->record_hash);
    }

    public function test_recording_via_actions_writes_audit_logs(): void
    {
        $this->grantPermission($this->user, $this->organization, 'create-journal-entries');
        $this->actingAs($this->user);

        $countBefore = AuditLog::count();

        app(AuditLogService::class)->record($this->organization, $this->user->id, 'journal-entry.submitted', 'App\\Models\\JournalEntry', 1, [], ['status' => 'submitted']);

        $this->assertEquals($countBefore + 1, AuditLog::count());
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'action' => 'journal-entry.submitted',
        ]);
    }
}
