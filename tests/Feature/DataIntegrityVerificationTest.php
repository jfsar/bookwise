<?php

namespace Tests\Feature;

use App\Models\JournalEntry;
use App\Models\Organization;
use App\Services\AuditLogService;
use App\Services\DataIntegrityVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DataIntegrityVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_healthy_database_passes_verification(): void
    {
        Organization::factory()->create();
        JournalEntry::factory()->posted()->create();

        $report = app(DataIntegrityVerifier::class)->verify();

        $this->assertTrue($report->passed(), json_encode($report->failures()));
        $this->assertArrayHasKey('migrations', $report->checks);
        $this->assertArrayHasKey('audit_chain', $report->checks);
        $this->assertArrayHasKey('posted_entries_balanced', $report->checks);
        $this->assertArrayHasKey('trial_balance', $report->checks);
    }

    public function test_command_reports_success_on_a_healthy_database(): void
    {
        $this->artisan('db:verify-restore')->assertSuccessful();
    }

    public function test_a_tampered_audit_chain_fails_verification(): void
    {
        $organization = Organization::factory()->create();
        $log = app(AuditLogService::class)->record(
            $organization,
            null,
            'journal-entry.posted',
            'App\\Models\\JournalEntry',
            1,
            [],
            ['status' => 'posted'],
        );

        DB::table('audit_logs')->where('id', $log->id)->update(['action' => 'tampered']);

        $report = app(DataIntegrityVerifier::class)->verify();

        $this->assertFalse($report->passed());
        $this->assertArrayHasKey('audit_chain', $report->failures());
        $this->artisan('db:verify-restore')->assertFailed();
    }

    public function test_pending_migrations_fail_verification(): void
    {
        $latest = DB::table('migrations')->orderByDesc('id')->value('migration');
        DB::table('migrations')->where('migration', $latest)->delete();

        $report = app(DataIntegrityVerifier::class)->verify();

        $this->assertFalse($report->passed());
        $this->assertArrayHasKey('migrations', $report->failures());
    }

    public function test_unbalanced_posted_entries_fail_verification(): void
    {
        $entry = JournalEntry::factory()->posted()->create();

        DB::table('journal_entries')->where('id', $entry->id)->update([
            'total_debit' => 5,
            'total_credit' => 10,
        ]);

        $report = app(DataIntegrityVerifier::class)->verify();

        $this->assertFalse($report->passed());
        $this->assertArrayHasKey('posted_entries_balanced', $report->failures());
    }
}
