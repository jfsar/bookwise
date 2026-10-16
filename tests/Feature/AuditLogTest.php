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

    public function test_audit_logs_capture_the_request_correlation_and_request_ids(): void
    {
        $this->get('/', ['X-Correlation-Id' => 'trace-42', 'X-Request-Id' => 'req-42']);

        $log = app(AuditLogService::class)->record($this->organization, $this->user->id, 'journal-entry.submitted', 'App\\Models\\JournalEntry', 1, [], ['status' => 'submitted']);

        $this->assertSame('trace-42', $log->correlation_id);
        $this->assertSame('req-42', $log->request_id);
    }

    public function test_audit_logs_leave_ids_null_outside_a_request(): void
    {
        $log = app(AuditLogService::class)->record($this->organization, $this->user->id, 'journal-entry.submitted', 'App\\Models\\JournalEntry', 1, [], ['status' => 'submitted']);

        $this->assertNull($log->correlation_id);
        $this->assertNull($log->request_id);
    }

    public function test_verify_empty_organization_is_valid(): void
    {
        $result = app(AuditLogService::class)->verify($this->organization);

        $this->assertTrue($result->valid);
        $this->assertSame(0, $result->checkedCount);
        $this->assertSame([], $result->failures);
    }

    public function test_verify_reports_valid_chain_and_count(): void
    {
        $service = app(AuditLogService::class);

        $service->record($this->organization, $this->user->id, 'journal-entry.submitted', 'App\\Models\\JournalEntry', 1, [], ['status' => 'submitted']);
        $service->record($this->organization, $this->user->id, 'journal-entry.approved', 'App\\Models\\JournalEntry', 1, ['status' => 'submitted'], ['status' => 'approved']);
        $service->record($this->organization, $this->user->id, 'journal-entry.posted', 'App\\Models\\JournalEntry', 1, ['status' => 'approved'], ['status' => 'posted']);

        $result = $service->verify($this->organization);

        $this->assertTrue($result->valid);
        $this->assertSame(3, $result->checkedCount);
        $this->assertSame([], $result->failures);
    }

    public function test_verify_detects_tampered_record_hash(): void
    {
        $service = app(AuditLogService::class);

        $service->record($this->organization, $this->user->id, 'journal-entry.submitted', 'App\\Models\\JournalEntry', 1, [], ['status' => 'submitted']);
        $log2 = $service->record($this->organization, $this->user->id, 'journal-entry.approved', 'App\\Models\\JournalEntry', 1, ['status' => 'submitted'], ['status' => 'approved']);
        $service->record($this->organization, $this->user->id, 'journal-entry.posted', 'App\\Models\\JournalEntry', 1, ['status' => 'approved'], ['status' => 'posted']);

        $log2->update(['action' => 'role.deleted']);

        $result = $service->verify($this->organization);

        $this->assertFalse($result->valid);
        $this->assertNotEmpty($result->failures);
        $this->assertSame(3, $result->checkedCount);
        $this->assertStringContainsString('record_hash', $result->failures[0]['reason'] ?? '');
    }

    public function test_verify_detects_broken_previous_hash_link(): void
    {
        $service = app(AuditLogService::class);

        $service->record($this->organization, $this->user->id, 'journal-entry.submitted', 'App\\Models\\JournalEntry', 1, [], ['status' => 'submitted']);
        $log2 = $service->record($this->organization, $this->user->id, 'journal-entry.approved', 'App\\Models\\JournalEntry', 1, ['status' => 'submitted'], ['status' => 'approved']);

        $log2->update(['previous_hash' => str_repeat('a', 64)]);

        $result = $service->verify($this->organization);

        $this->assertFalse($result->valid);
        $this->assertNotEmpty($result->failures);
        $this->assertStringContainsString('previous_hash', $result->failures[0]['reason']);
    }

    public function test_verify_detects_corrupted_genesis_hash(): void
    {
        $service = app(AuditLogService::class);

        $log1 = $service->record($this->organization, $this->user->id, 'journal-entry.submitted', 'App\\Models\\JournalEntry', 1, [], ['status' => 'submitted']);

        $log1->update(['previous_hash' => str_repeat('b', 64)]);

        $result = $service->verify($this->organization);

        $this->assertFalse($result->valid);
        $this->assertStringContainsString('previous_hash', $result->failures[0]['reason']);
    }

    public function test_verify_detects_deleted_record(): void
    {
        $service = app(AuditLogService::class);

        $service->record($this->organization, $this->user->id, 'journal-entry.submitted', 'App\\Models\\JournalEntry', 1, [], ['status' => 'submitted']);
        $log2 = $service->record($this->organization, $this->user->id, 'journal-entry.approved', 'App\\Models\\JournalEntry', 1, ['status' => 'submitted'], ['status' => 'approved']);
        $service->record($this->organization, $this->user->id, 'journal-entry.posted', 'App\\Models\\JournalEntry', 1, ['status' => 'approved'], ['status' => 'posted']);

        $log2->delete();

        $result = $service->verify($this->organization);

        $this->assertFalse($result->valid);
        $this->assertNotEmpty($result->failures);
        $this->assertStringContainsString('contiguous', $result->failures[0]['reason']);
    }

    public function test_verify_is_scoped_to_organization(): void
    {
        $service = app(AuditLogService::class);
        $otherOrg = Organization::factory()->create();

        $service->record($this->organization, $this->user->id, 'journal-entry.submitted', 'App\\Models\\JournalEntry', 1, [], ['status' => 'submitted']);
        $service->record($otherOrg, $this->user->id, 'journal-entry.submitted', 'App\\Models\\JournalEntry', 2, [], ['status' => 'submitted']);
        $service->record($otherOrg, $this->user->id, 'journal-entry.approved', 'App\\Models\\JournalEntry', 2, ['status' => 'submitted'], ['status' => 'approved']);

        AuditLog::where('organization_id', $this->organization->id)->first()->update(['action' => 'role.deleted']);

        $this->assertFalse($service->verify($this->organization)->valid);
        $this->assertTrue($service->verify($otherOrg)->valid);
    }
}
