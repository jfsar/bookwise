<?php

namespace Tests\Feature;

use App\Actions\JournalEntry\ApproveJournalEntry;
use App\Actions\JournalEntry\CancelJournalEntry;
use App\Actions\JournalEntry\CreateJournalEntry;
use App\Actions\JournalEntry\PostJournalEntry;
use App\Actions\JournalEntry\ReverseJournalEntry;
use App\Actions\JournalEntry\SubmitJournalEntry;
use App\Enums\JournalEntryStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalEntryTest extends TestCase
{
    use RefreshDatabase;

    private function createEntry(Organization $org, User $creator, ?array $lines = null)
    {
        $journal = Journal::factory()->create(['organization_id' => $org->id]);
        $currency = Currency::factory()->create();
        $fiscalYear = FiscalYear::factory()->create(['organization_id' => $org->id]);
        $period = AccountingPeriod::factory()->create([
            'organization_id' => $org->id,
            'fiscal_year_id' => $fiscalYear->id,
        ]);

        $cash = Account::factory()->create(['organization_id' => $org->id]);
        $revenue = Account::factory()->create(['organization_id' => $org->id]);

        $lines ??= [
            ['account_id' => $cash->id, 'debit' => 500, 'credit' => 0, 'description' => null],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 500, 'description' => null],
        ];

        $entry = (new CreateJournalEntry)->handle([
            'journal_id' => $journal->id,
            'currency_id' => $currency->id,
            'fiscal_year_id' => $fiscalYear->id,
            'accounting_period_id' => $period->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Test entry',
            'lines' => $lines,
        ], $org, $creator);

        return $entry;
    }

    public function test_create_balanced_journal_entry(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();

        $entry = $this->createEntry($org, $user);

        $this->assertEquals(JournalEntryStatus::Draft, $entry->status);
        $this->assertTrue($entry->isBalanced());
        $this->assertSame(2, $entry->lines()->count());
        $this->assertSame(500.0, $entry->totalDebit());
        $this->assertSame(500.0, $entry->totalCredit());
    }

    public function test_submit_to_approved_to_posted_flow(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $approver = User::factory()->create();
        $poster = User::factory()->create();

        $entry = $this->createEntry($org, $user);

        (new SubmitJournalEntry)->handle($entry, $user);
        $this->assertEquals(JournalEntryStatus::Submitted, $entry->fresh()->status);

        (new ApproveJournalEntry)->handle($entry, $approver);
        $this->assertEquals(JournalEntryStatus::Approved, $entry->fresh()->status);

        (new PostJournalEntry)->handle($entry, $poster);
        $this->assertEquals(JournalEntryStatus::Posted, $entry->fresh()->status);
    }

    public function test_maker_checker_prevents_self_approval(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();

        $entry = $this->createEntry($org, $user);

        (new SubmitJournalEntry)->handle($entry, $user);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Maker-checker: The approver cannot be the same person who submitted the entry.');

        (new ApproveJournalEntry)->handle($entry, $user);
    }

    public function test_cannot_submit_unbalanced_entry(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();

        $journal = Journal::factory()->create(['organization_id' => $org->id]);
        $currency = Currency::factory()->create();
        $fiscalYear = FiscalYear::factory()->create(['organization_id' => $org->id]);
        $period = AccountingPeriod::factory()->create([
            'organization_id' => $org->id,
            'fiscal_year_id' => $fiscalYear->id,
        ]);
        $cash = Account::factory()->create(['organization_id' => $org->id]);
        $revenue = Account::factory()->create(['organization_id' => $org->id]);

        $entry = (new CreateJournalEntry)->handle([
            'journal_id' => $journal->id,
            'currency_id' => $currency->id,
            'fiscal_year_id' => $fiscalYear->id,
            'accounting_period_id' => $period->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Unbalanced entry',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 500, 'credit' => 0, 'description' => null],
                ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 400, 'description' => null],
            ],
        ], $org, $user);

        $this->assertFalse($entry->isBalanced());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot submit an unbalanced entry. Debits must equal credits.');

        (new SubmitJournalEntry)->handle($entry, $user);
    }

    public function test_reversal_creates_reversing_entry(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $approver = User::factory()->create();
        $poster = User::factory()->create();
        $reverser = User::factory()->create();

        $entry = $this->createEntry($org, $user);

        (new SubmitJournalEntry)->handle($entry, $user);
        (new ApproveJournalEntry)->handle($entry, $approver);
        (new PostJournalEntry)->handle($entry, $poster);

        $reversal = (new ReverseJournalEntry)->handle($entry, $reverser, 'Test reversal');

        $this->assertEquals(JournalEntryStatus::Reversed, $entry->fresh()->status);
        $this->assertEquals($entry->id, $reversal->reversal_of_id);
        $this->assertEquals(JournalEntryStatus::Draft, $reversal->status);
        $this->assertTrue($reversal->isBalanced());
        $this->assertSame(2, $reversal->lines()->count());
    }

    public function test_cannot_post_to_closed_period(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $approver = User::factory()->create();

        $entry = $this->createEntry($org, $user);

        (new SubmitJournalEntry)->handle($entry, $user);
        (new ApproveJournalEntry)->handle($entry, $approver);

        $entry->accountingPeriod->update(['status' => 'closed']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot post to a closed accounting period.');

        (new PostJournalEntry)->handle($entry, $user);
    }

    public function test_cancel_draft_entry(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();

        $entry = $this->createEntry($org, $user);

        (new CancelJournalEntry)->handle($entry, $user, 'Incorrect entry');

        $this->assertEquals(JournalEntryStatus::Cancelled, $entry->fresh()->status);
        $this->assertEquals('Incorrect entry', $entry->fresh()->rejection_reason);
    }
}
