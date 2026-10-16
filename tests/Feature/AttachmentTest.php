<?php

namespace Tests\Feature;

use App\Contracts\FileScanner;
use App\Models\Attachment;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\FileScanResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    use RefreshDatabase;

    private function attachRoleWithPermission(User $user, Organization $organization, string|array $permissionNames): void
    {
        $user->organizations()->attach($organization->id, ['membership_status' => 'active']);

        $role = Role::create([
            'name' => 'Tester',
            'slug' => 'tester-'.uniqid(),
            'is_global' => false,
        ]);
        $role->permissions()->attach(
            collect($permissionNames)->map(fn (string $name) => Permission::firstOrCreate(['name' => $name])->id)
        );

        $user->roles()->attach($role->id, ['organization_id' => $organization->id]);
    }

    private function journalEntryFor(Organization $organization): JournalEntry
    {
        $currency = Currency::factory()->create();
        $journal = Journal::factory()->create(['organization_id' => $organization->id]);
        $fiscalYear = FiscalYear::factory()->create(['organization_id' => $organization->id]);

        return JournalEntry::factory()->create([
            'organization_id' => $organization->id,
            'journal_id' => $journal->id,
            'fiscal_year_id' => $fiscalYear->id,
            'currency_id' => $currency->id,
            'base_currency_id' => $currency->id,
        ]);
    }

    /**
     * @return array{0: Organization, 1: User, 2: JournalEntry}
     */
    private function uploadContext(string|array $permissions = ['view-attachments', 'create-attachments', 'delete-attachments']): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $organization, $permissions);

        return [$organization, $user, $this->journalEntryFor($organization)];
    }

    public function test_attachment_model_and_morph_relation(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $journalEntry = $this->journalEntryFor($org);

        $attachment = Attachment::factory()->create([
            'organization_id' => $org->id,
            'attachable_type' => JournalEntry::class,
            'attachable_id' => $journalEntry->id,
            'uploaded_by' => $user->id,
        ]);

        $this->assertInstanceOf(JournalEntry::class, $attachment->attachable);
        $this->assertTrue($journalEntry->attachments->contains($attachment));
    }

    public function test_store_requires_authentication(): void
    {
        $this->post('/attachments', [])->assertRedirect(route('login'));
    }

    public function test_store_forbids_user_without_permission(): void
    {
        Storage::fake('local');

        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($organization->id, ['membership_status' => 'active']);
        $entry = $this->journalEntryFor($organization);

        $this->actingAs($user)->post('/attachments', [
            'file' => UploadedFile::fake()->create('report.pdf', 50, 'application/pdf'),
            'attachable_type' => JournalEntry::class,
            'attachable_id' => $entry->id,
        ])->assertForbidden();

        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_store_uploads_file_stores_metadata_and_audit(): void
    {
        Storage::fake('local');

        [$organization, $user, $entry] = $this->uploadContext();

        $this->actingAs($user)->post('/attachments', [
            'file' => UploadedFile::fake()->create('report.pdf', 50, 'application/pdf'),
            'attachable_type' => JournalEntry::class,
            'attachable_id' => $entry->id,
        ])->assertRedirect();

        $attachment = Attachment::where('organization_id', $organization->id)->firstOrFail();

        $this->assertSame(JournalEntry::class, $attachment->attachable_type);
        $this->assertSame($entry->id, $attachment->attachable_id);
        $this->assertSame('report.pdf', $attachment->original_name);
        $this->assertSame('private', $attachment->visibility);
        $this->assertSame('clean', $attachment->scan_status);
        $this->assertSame($user->id, $attachment->uploaded_by);
        $this->assertStringStartsWith('sha256:', (string) $attachment->checksum);
        $this->assertSame(64, strlen((string) $attachment->content_hash));
        Storage::disk('local')->assertExists($attachment->path);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $organization->id,
            'action' => 'attachment.created',
            'auditable_type' => Attachment::class,
            'auditable_id' => $attachment->id,
        ]);
    }

    public function test_store_rejects_unsupported_attachable_type(): void
    {
        Storage::fake('local');

        [, $user, $entry] = $this->uploadContext();

        $this->actingAs($user)->post('/attachments', [
            'file' => UploadedFile::fake()->create('report.pdf', 50, 'application/pdf'),
            'attachable_type' => User::class,
            'attachable_id' => $entry->id,
        ])->assertSessionHasErrors('attachable_type');

        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_store_rejects_cross_organization_attachable(): void
    {
        Storage::fake('local');

        [$organization, $user] = $this->uploadContext();

        $otherOrganization = Organization::factory()->create();
        $foreignEntry = $this->journalEntryFor($otherOrganization);

        $this->actingAs($user)->post('/attachments', [
            'file' => UploadedFile::fake()->create('report.pdf', 50, 'application/pdf'),
            'attachable_type' => JournalEntry::class,
            'attachable_id' => $foreignEntry->id,
        ])->assertSessionHas('error', 'The attachment target does not belong to this organization.');

        $this->assertDatabaseCount('attachments', 0);
        $this->assertSame(0, count(Storage::disk('local')->allFiles()));
        $this->assertDatabaseMissing('audit_logs', [
            'organization_id' => $organization->id,
            'action' => 'attachment.created',
        ]);
    }

    public function test_store_rejects_disallowed_file_type(): void
    {
        Storage::fake('local');

        [, $user, $entry] = $this->uploadContext();

        $this->actingAs($user)->post('/attachments', [
            'file' => UploadedFile::fake()->create('payload.php', 10, 'application/x-php'),
            'attachable_type' => JournalEntry::class,
            'attachable_id' => $entry->id,
        ])->assertSessionHasErrors('file');

        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_store_rejects_oversized_file(): void
    {
        Storage::fake('local');

        [, $user, $entry] = $this->uploadContext();

        $this->actingAs($user)->post('/attachments', [
            'file' => UploadedFile::fake()->create('huge.pdf', 20480, 'application/pdf'),
            'attachable_type' => JournalEntry::class,
            'attachable_id' => $entry->id,
        ])->assertSessionHasErrors('file');

        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_store_rejects_malware_and_removes_the_stored_file(): void
    {
        Storage::fake('local');

        $this->app->bind(FileScanner::class, fn () => new class implements FileScanner
        {
            public function scan(UploadedFile $file): FileScanResult
            {
                return FileScanResult::infected('Malware signature detected.');
            }
        });

        [, $user, $entry] = $this->uploadContext();

        $this->actingAs($user)->post('/attachments', [
            'file' => UploadedFile::fake()->create('invoice.pdf', 50, 'application/pdf'),
            'attachable_type' => JournalEntry::class,
            'attachable_id' => $entry->id,
        ])->assertSessionHas('error', 'The attachment was rejected by the malware scanner.');

        $this->assertDatabaseCount('attachments', 0);
        $this->assertSame(0, count(Storage::disk('local')->allFiles()));
    }

    public function test_store_rejects_file_when_scanning_fails(): void
    {
        Storage::fake('local');

        $this->app->bind(FileScanner::class, fn () => new class implements FileScanner
        {
            public function scan(UploadedFile $file): FileScanResult
            {
                return FileScanResult::failed('Scanner unavailable.');
            }
        });

        [, $user, $entry] = $this->uploadContext();

        $this->actingAs($user)->post('/attachments', [
            'file' => UploadedFile::fake()->create('report.pdf', 50, 'application/pdf'),
            'attachable_type' => JournalEntry::class,
            'attachable_id' => $entry->id,
        ])->assertSessionHas('error', 'The attachment could not be scanned and was rejected.');

        $this->assertDatabaseCount('attachments', 0);
        $this->assertSame(0, count(Storage::disk('local')->allFiles()));
    }

    public function test_download_requires_authentication(): void
    {
        $attachment = Attachment::factory()->create();

        $this->get("/attachments/{$attachment->id}/download")->assertRedirect(route('login'));
    }

    public function test_download_forbids_cross_tenant_attachment(): void
    {
        Storage::fake('local');

        [, $user] = $this->uploadContext();

        $otherOrganization = Organization::factory()->create();
        $foreignEntry = $this->journalEntryFor($otherOrganization);
        $foreign = Attachment::factory()->create([
            'organization_id' => $otherOrganization->id,
            'attachable_type' => JournalEntry::class,
            'attachable_id' => $foreignEntry->id,
        ]);
        Storage::disk('local')->put($foreign->path, 'secret');

        $this->actingAs($user)
            ->get("/attachments/{$foreign->id}/download")
            ->assertNotFound();
    }

    public function test_download_is_denied_until_scan_is_clean(): void
    {
        Storage::fake('local');

        [$organization, $user, $entry] = $this->uploadContext(['view-attachments']);

        $attachment = Attachment::factory()->create([
            'organization_id' => $organization->id,
            'attachable_type' => JournalEntry::class,
            'attachable_id' => $entry->id,
            'scan_status' => 'pending',
        ]);
        Storage::disk('local')->put($attachment->path, 'pending');

        $this->actingAs($user)
            ->get("/attachments/{$attachment->id}/download")
            ->assertForbidden();
    }

    public function test_download_returns_file_and_records_access(): void
    {
        Storage::fake('local');

        [$organization, $user, $entry] = $this->uploadContext(['view-attachments']);

        $attachment = Attachment::factory()->create([
            'organization_id' => $organization->id,
            'attachable_type' => JournalEntry::class,
            'attachable_id' => $entry->id,
            'original_name' => 'statement.pdf',
            'scan_status' => 'clean',
        ]);
        Storage::disk('local')->put($attachment->path, 'pdf-bytes');

        $this->actingAs($user)
            ->get("/attachments/{$attachment->id}/download")
            ->assertOk()
            ->assertDownload('statement.pdf');

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $organization->id,
            'action' => 'attachment.accessed',
            'auditable_type' => Attachment::class,
            'auditable_id' => $attachment->id,
        ]);
    }

    public function test_destroy_removes_file_and_row_with_audit(): void
    {
        Storage::fake('local');

        [$organization, $user, $entry] = $this->uploadContext();

        $attachment = Attachment::factory()->create([
            'organization_id' => $organization->id,
            'attachable_type' => JournalEntry::class,
            'attachable_id' => $entry->id,
            'uploaded_by' => $user->id,
        ]);
        Storage::disk('local')->put($attachment->path, 'bytes');

        $this->actingAs($user)
            ->delete("/attachments/{$attachment->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
        Storage::disk('local')->assertMissing($attachment->path);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $organization->id,
            'action' => 'attachment.deleted',
            'auditable_type' => Attachment::class,
            'auditable_id' => $attachment->id,
        ]);
    }

    public function test_destroy_forbids_user_without_permission(): void
    {
        Storage::fake('local');

        [$organization, $user, $entry] = $this->uploadContext(['view-attachments']);

        $attachment = Attachment::factory()->create([
            'organization_id' => $organization->id,
            'attachable_type' => JournalEntry::class,
            'attachable_id' => $entry->id,
        ]);
        Storage::disk('local')->put($attachment->path, 'bytes');

        $this->actingAs($user)
            ->delete("/attachments/{$attachment->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('attachments', ['id' => $attachment->id]);
        Storage::disk('local')->assertExists($attachment->path);
    }

    public function test_destroy_forbids_cross_tenant_attachment(): void
    {
        Storage::fake('local');

        [, $user] = $this->uploadContext();

        $otherOrganization = Organization::factory()->create();
        $foreignEntry = $this->journalEntryFor($otherOrganization);
        $foreign = Attachment::factory()->create([
            'organization_id' => $otherOrganization->id,
            'attachable_type' => JournalEntry::class,
            'attachable_id' => $foreignEntry->id,
        ]);

        $this->actingAs($user)
            ->delete("/attachments/{$foreign->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('attachments', ['id' => $foreign->id]);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/attachments?attachable_type='.urlencode(JournalEntry::class).'&attachable_id=1')
            ->assertUnauthorized();
    }

    public function test_index_forbids_user_without_view_permission(): void
    {
        [, $user, $entry] = $this->uploadContext(['create-attachments']);

        $this->actingAs($user)
            ->getJson('/attachments?attachable_type='.urlencode(JournalEntry::class).'&attachable_id='.$entry->id)
            ->assertForbidden();
    }

    public function test_index_returns_attachments_for_the_attachable(): void
    {
        [$organization, $user, $entry] = $this->uploadContext(['view-attachments', 'delete-attachments']);

        $attachment = Attachment::factory()->create([
            'organization_id' => $organization->id,
            'attachable_type' => JournalEntry::class,
            'attachable_id' => $entry->id,
            'original_name' => 'receipt.pdf',
        ]);

        $this->actingAs($user)
            ->getJson('/attachments?attachable_type='.urlencode(JournalEntry::class).'&attachable_id='.$entry->id)
            ->assertOk()
            ->assertJsonCount(1, 'attachments')
            ->assertJsonPath('attachments.0.id', $attachment->id)
            ->assertJsonPath('attachments.0.original_name', 'receipt.pdf')
            ->assertJsonPath('attachments.0.scan_status', 'clean')
            ->assertJsonPath('attachments.0.can_delete', true)
            ->assertJsonPath('attachments.0.download_url', route('attachments.download', $attachment));
    }

    public function test_index_scopes_attachments_to_the_active_organization(): void
    {
        [, $user, $entry] = $this->uploadContext(['view-attachments']);

        $otherOrganization = Organization::factory()->create();
        $foreignEntry = $this->journalEntryFor($otherOrganization);
        Attachment::factory()->create([
            'organization_id' => $otherOrganization->id,
            'attachable_type' => JournalEntry::class,
            'attachable_id' => $foreignEntry->id,
        ]);

        $this->actingAs($user)
            ->getJson('/attachments?attachable_type='.urlencode(JournalEntry::class).'&attachable_id='.$entry->id)
            ->assertOk()
            ->assertJsonCount(0, 'attachments');
    }

    public function test_index_rejects_unsupported_attachable_type(): void
    {
        [, $user, $entry] = $this->uploadContext(['view-attachments']);

        $this->actingAs($user)
            ->getJson('/attachments?attachable_type='.urlencode(User::class).'&attachable_id='.$entry->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors('attachable_type');
    }

    public function test_index_returns_not_found_for_cross_organization_attachable(): void
    {
        [, $user] = $this->uploadContext(['view-attachments']);

        $otherOrganization = Organization::factory()->create();
        $foreignEntry = $this->journalEntryFor($otherOrganization);

        $this->actingAs($user)
            ->getJson('/attachments?attachable_type='.urlencode(JournalEntry::class).'&attachable_id='.$foreignEntry->id)
            ->assertNotFound();
    }

    public function test_store_returns_json_for_ajax_uploads(): void
    {
        Storage::fake('local');

        [, $user, $entry] = $this->uploadContext();

        $this->actingAs($user)
            ->post('/attachments', [
                'file' => UploadedFile::fake()->create('report.pdf', 50, 'application/pdf'),
                'attachable_type' => JournalEntry::class,
                'attachable_id' => $entry->id,
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('attachment.original_name', 'report.pdf')
            ->assertJsonPath('attachment.scan_status', 'clean');

        $this->assertDatabaseCount('attachments', 1);
    }

    public function test_store_returns_json_validation_errors_for_ajax_uploads(): void
    {
        Storage::fake('local');

        [, $user, $entry] = $this->uploadContext();

        $this->actingAs($user)
            ->post('/attachments', [
                'file' => UploadedFile::fake()->create('payload.php', 10, 'application/x-php'),
                'attachable_type' => JournalEntry::class,
                'attachable_id' => $entry->id,
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_destroy_returns_json_for_ajax_deletes(): void
    {
        Storage::fake('local');

        [$organization, $user, $entry] = $this->uploadContext();

        $attachment = Attachment::factory()->create([
            'organization_id' => $organization->id,
            'attachable_type' => JournalEntry::class,
            'attachable_id' => $entry->id,
        ]);
        Storage::disk('local')->put($attachment->path, 'bytes');

        $this->actingAs($user)
            ->deleteJson("/attachments/{$attachment->id}")
            ->assertOk()
            ->assertJsonPath('deleted', true);

        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
        Storage::disk('local')->assertMissing($attachment->path);
    }
}
