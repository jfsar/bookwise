<?php

namespace Tests\Feature;

use App\Actions\BankReconciliation\MaintainsReconciliationState;
use App\Actions\JournalEntry\ApproveJournalEntry;
use App\Actions\JournalEntry\CreateJournalEntry;
use App\Actions\JournalEntry\PostJournalEntry;
use App\Actions\JournalEntry\SubmitJournalEntry;
use App\Enums\JournalEntryStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankTransaction;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\JournalEntryLine;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\ReconciliationMatch;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class BankReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private const MANAGER_PERMISSIONS = [
        'view-bank-reconciliations',
        'manage-bank-reconciliations',
        'finalize-bank-reconciliations',
    ];

    private function attachRoleWithPermission(User $user, Organization $organization, string|array $permissionNames): User
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

        return $user;
    }

    private function bankSetup(): array
    {
        $org = Organization::factory()->create();
        $ledger = Account::factory()->create(['organization_id' => $org->id]);
        $bankAccount = BankAccount::factory()->create([
            'organization_id' => $org->id,
            'account_id' => $ledger->id,
            'created_by' => User::factory()->create()->id,
        ]);

        return [$org, $bankAccount, $ledger];
    }

    /**
     * A bank transaction on an exact date, for period-boundary assertions.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function bankTransactionOn(BankAccount $bankAccount, string $date, array $overrides = []): BankTransaction
    {
        return BankTransaction::factory()->debit(400)->create(array_merge([
            'organization_id' => $bankAccount->organization_id,
            'bank_account_id' => $bankAccount->id,
            'currency_id' => $bankAccount->currency_id,
            'transaction_date' => $date,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function storePayload(int $bankAccountId, array $overrides = []): array
    {
        return array_merge([
            'bank_account_id' => $bankAccountId,
            'statement_date' => '2026-01-31',
            'statement_start_date' => '2026-01-01',
            'statement_end_date' => '2026-01-31',
            'opening_balance' => 1000,
            'closing_balance' => 1000,
        ], $overrides);
    }

    private function startReconciliation(Organization $org, BankAccount $bankAccount, User $preparer, array $overrides = []): BankReconciliation
    {
        $this->actingAs($preparer)
            ->post('/bank-reconciliations', $this->storePayload($bankAccount->id, $overrides))
            ->assertRedirect()
            ->assertSessionHas('success');

        return BankReconciliation::where('prepared_by', $preparer->id)->orderByDesc('id')->firstOrFail();
    }

    private function finalize(BankReconciliation $reconciliation, User $actor)
    {
        $this->actingAs($actor);
        $this->confirmPassword();

        return $this->post("/bank-reconciliations/{$reconciliation->id}/finalize");
    }

    private function reopen(BankReconciliation $reconciliation, array $payload, User $actor)
    {
        $this->actingAs($actor);
        $this->confirmPassword();

        return $this->post("/bank-reconciliations/{$reconciliation->id}/reopen", $payload);
    }

    /**
     * Create a POSTED journal entry line on the bank account's ledger account so
     * reconciliation matches can point at authoritative ledger data.
     */
    private function postedLedgerLine(Organization $org, Account $ledger, Currency $currency, float $amount, ?string $entryDate = null): JournalEntryLine
    {
        $journal = Journal::factory()->create(['organization_id' => $org->id]);
        $fiscalYear = FiscalYear::factory()->create(['organization_id' => $org->id]);
        $period = AccountingPeriod::factory()->create([
            'organization_id' => $org->id,
            'fiscal_year_id' => $fiscalYear->id,
        ]);
        $counterpart = Account::factory()->create(['organization_id' => $org->id]);
        $creator = User::factory()->create();
        $submitter = User::factory()->create();
        $approver = User::factory()->create();
        $poster = User::factory()->create();

        $entry = (new CreateJournalEntry)->handle([
            'journal_id' => $journal->id,
            'currency_id' => $currency->id,
            'fiscal_year_id' => $fiscalYear->id,
            'accounting_period_id' => $period->id,
            'entry_date' => $entryDate ?? now()->toDateString(),
            'description' => 'Ledger side of the reconciliation',
            'lines' => [
                ['account_id' => $ledger->id, 'debit' => $amount, 'credit' => 0, 'description' => null],
                ['account_id' => $counterpart->id, 'debit' => 0, 'credit' => $amount, 'description' => null],
            ],
        ], $org, $creator);

        (new SubmitJournalEntry)->handle($entry, $submitter);
        (new ApproveJournalEntry)->handle($entry, $approver);
        (new PostJournalEntry)->handle($entry, $poster);

        $this->assertEquals(JournalEntryStatus::Posted, $entry->fresh()->status);

        return $entry->fresh()->lines()->where('account_id', $ledger->id)->firstOrFail();
    }

    /**
     * A DRAFT journal entry line on the bank account's ledger account.
     */
    private function draftLedgerLine(Organization $org, BankAccount $bankAccount, float $amount): JournalEntryLine
    {
        $journal = Journal::factory()->create(['organization_id' => $org->id]);
        $fiscalYear = FiscalYear::factory()->create(['organization_id' => $org->id]);
        $period = AccountingPeriod::factory()->create([
            'organization_id' => $org->id,
            'fiscal_year_id' => $fiscalYear->id,
        ]);
        $counterpart = Account::factory()->create(['organization_id' => $org->id]);

        $entry = (new CreateJournalEntry)->handle([
            'journal_id' => $journal->id,
            'currency_id' => $bankAccount->currency_id,
            'fiscal_year_id' => $fiscalYear->id,
            'accounting_period_id' => $period->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Draft ledger line',
            'lines' => [
                ['account_id' => $bankAccount->account_id, 'debit' => $amount, 'credit' => 0, 'description' => null],
                ['account_id' => $counterpart->id, 'debit' => 0, 'credit' => $amount, 'description' => null],
            ],
        ], $org, User::factory()->create());

        $this->assertEquals(JournalEntryStatus::Draft, $entry->fresh()->status);

        return $entry->fresh()->lines()->where('account_id', $bankAccount->account_id)->firstOrFail();
    }

    // ---------------------------------------------------------------- start

    public function test_index_requires_authentication(): void
    {
        $this->get('/bank-reconciliations')->assertRedirect(route('login'));
    }

    public function test_index_forbids_user_without_permission(): void
    {
        [$org] = $this->bankSetup();
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['membership_status' => 'active']);

        $this->actingAs($user)->get('/bank-reconciliations')->assertForbidden();
    }

    public function test_index_is_tenant_scoped(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        [$otherOrg, $otherBankAccount] = $this->bankSetup();
        $user = $this->attachRoleWithPermission(User::factory()->create(), $org, 'view-bank-reconciliations');

        BankReconciliation::factory()->create([
            'organization_id' => $org->id,
            'bank_account_id' => $bankAccount->id,
            'currency_id' => $bankAccount->currency_id,
            'prepared_by' => $user->id,
        ]);
        BankReconciliation::factory()->create([
            'organization_id' => $otherOrg->id,
            'bank_account_id' => $otherBankAccount->id,
            'currency_id' => $otherBankAccount->currency_id,
            'prepared_by' => $user->id,
        ]);

        $this->actingAs($user)->get('/bank-reconciliations')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('BankReconciliations/Index')
                ->has('reconciliations.data', 1));
    }

    public function test_show_returns_404_for_other_organization(): void
    {
        [$org] = $this->bankSetup();
        [$otherOrg, $otherBankAccount] = $this->bankSetup();
        $user = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);

        $reconciliation = BankReconciliation::factory()->create([
            'organization_id' => $otherOrg->id,
            'bank_account_id' => $otherBankAccount->id,
            'currency_id' => $otherBankAccount->currency_id,
        ]);

        $this->actingAs($user)->get("/bank-reconciliations/{$reconciliation->id}")->assertNotFound();
    }

    public function test_show_forbids_a_member_without_permission(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $preparer = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);
        $reconciliation = $this->startReconciliation($org, $bankAccount, $preparer);

        // Same organization, so the 404 tenant guard passes and the policy is
        // the only thing standing between this user and the statement.
        $outsider = User::factory()->create();
        $outsider->organizations()->attach($org->id, ['membership_status' => 'active']);

        $this->actingAs($outsider)->get("/bank-reconciliations/{$reconciliation->id}")->assertForbidden();
    }

    public function test_show_scopes_open_transactions_to_the_statement_period(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $user = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);
        $reconciliation = $this->startReconciliation($org, $bankAccount, $user);

        $cleared = $this->bankTransactionOn($bankAccount, '2026-01-15');
        $this->bankTransactionOn($bankAccount, '2025-12-20'); // cheque issued before the period
        $this->bankTransactionOn($bankAccount, '2026-02-03'); // deposit still in transit

        $this->actingAs($user)->get("/bank-reconciliations/{$reconciliation->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('BankReconciliations/Show')
                ->has('unmatchedTransactions', 1)
                ->where('unmatchedTransactions.0.id', $cleared->id)
                ->where('unmatchedTotal', 1)
                ->where('outOfPeriodMatchCount', 0)
                ->where('filters.includeOutOfPeriod', false));

        // The period is the default scope, not a hard filter: a timing
        // difference must stay one checkbox away.
        $this->actingAs($user)->get("/bank-reconciliations/{$reconciliation->id}?include_out_of_period=1")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('unmatchedTransactions', 3)
                ->where('unmatchedTotal', 3)
                ->where('filters.includeOutOfPeriod', true));
    }

    public function test_show_scopes_candidate_lines_to_the_statement_period(): void
    {
        [$org, $bankAccount, $ledger] = $this->bankSetup();
        $user = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);
        $reconciliation = $this->startReconciliation($org, $bankAccount, $user);

        $inPeriod = $this->postedLedgerLine($org, $ledger, $bankAccount->currency, 400, '2026-01-20');
        $this->postedLedgerLine($org, $ledger, $bankAccount->currency, 500, '2026-02-02');

        $this->actingAs($user)->get("/bank-reconciliations/{$reconciliation->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('candidateJournalLines', 1)
                ->where('candidateJournalLines.0.id', $inPeriod->id)
                ->where('candidateJournalLines.0.entry_date', '2026-01-20')
                // A line description is optional, so the entry it belongs to
                // has to be enough for a human to recognise the line.
                ->where('candidateJournalLines.0.description', 'Ledger side of the reconciliation')
                ->where('candidateLineTotal', 1));

        $this->actingAs($user)->get("/bank-reconciliations/{$reconciliation->id}?include_out_of_period=1")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('candidateJournalLines', 2)
                ->where('candidateLineTotal', 2));
    }

    public function test_show_search_narrows_both_picker_lists(): void
    {
        [$org, $bankAccount, $ledger] = $this->bankSetup();
        $user = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);
        $reconciliation = $this->startReconciliation($org, $bankAccount, $user);

        $payroll = $this->bankTransactionOn($bankAccount, '2026-01-10', [
            'description' => 'Monthly payroll run',
            'reference' => 'PR-4471',
        ]);
        $this->bankTransactionOn($bankAccount, '2026-01-11', [
            'description' => 'Office supplies',
            'reference' => 'INV-882',
        ]);

        $line = $this->postedLedgerLine($org, $ledger, $bankAccount->currency, 400, '2026-01-20');

        $this->actingAs($user)->get("/bank-reconciliations/{$reconciliation->id}?search=payroll")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('unmatchedTransactions', 1)
                ->where('unmatchedTransactions.0.id', $payroll->id)
                ->where('unmatchedTotal', 1)
                ->where('filters.search', 'payroll'));

        $this->actingAs($user)->get("/bank-reconciliations/{$reconciliation->id}?line_search=Ledger%20side")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('candidateJournalLines', 1)
                ->where('candidateJournalLines.0.id', $line->id)
                ->where('candidateLineTotal', 1)
                ->where('filters.lineSearch', 'Ledger side'));

        // A numeric search also resolves a journal entry number, which is how
        // an accountant refers to a posted entry.
        $this->actingAs($user)->get("/bank-reconciliations/{$reconciliation->id}?line_search={$line->journal_entry_id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('candidateJournalLines', 1)
                ->where('candidateJournalLines.0.id', $line->id));
    }

    public function test_show_truncates_the_open_transaction_picker_and_reports_the_real_total(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $user = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);
        $reconciliation = $this->startReconciliation($org, $bankAccount, $user);

        $limit = 200;
        BankTransaction::factory()->count($limit + 5)->debit(10)->create([
            'organization_id' => $org->id,
            'bank_account_id' => $bankAccount->id,
            'currency_id' => $bankAccount->currency_id,
            'transaction_date' => '2026-01-15',
        ]);

        // Silently hiding rows would make a reconciliation that looks complete
        // while rows were never offered, so the cap is reported, not hidden.
        $this->actingAs($user)->get("/bank-reconciliations/{$reconciliation->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('unmatchedTransactions', $limit)
                ->where('unmatchedTotal', $limit + 5));
    }

    public function test_show_flags_a_match_made_outside_the_statement_period(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $user = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);
        $reconciliation = $this->startReconciliation($org, $bankAccount, $user);

        // Written before the period, cleared inside it: the canonical timing
        // difference, which must stay matchable but must be visible.
        $bankTransaction = $this->bankTransactionOn($bankAccount, '2025-12-28');
        $ledgerLine = $this->postedLedgerLine($org, $bankAccount->account, $bankAccount->currency, 400, '2026-01-20');

        $this->actingAs($user)
            ->post("/bank-reconciliations/{$reconciliation->id}/matches", [
                'bank_transaction_id' => $bankTransaction->id,
                'journal_entry_line_id' => $ledgerLine->id,
                'matched_amount' => 400,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($user)->get("/bank-reconciliations/{$reconciliation->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('matches', 1)
                ->where('matches.0.out_of_period', true)
                ->where('outOfPeriodMatchCount', 1));

        $audit = AuditLog::where('action', 'bank-reconciliation.matched')->firstOrFail();
        $this->assertTrue($audit->new_values['out_of_period'] ?? null, 'the out-of-period match is recorded in the audit trail');
    }

    public function test_show_does_not_flag_a_match_inside_the_statement_period(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $user = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);
        $reconciliation = $this->startReconciliation($org, $bankAccount, $user);

        $bankTransaction = $this->bankTransactionOn($bankAccount, '2026-01-20');
        $ledgerLine = $this->postedLedgerLine($org, $bankAccount->account, $bankAccount->currency, 400, '2026-01-22');

        $this->actingAs($user)
            ->post("/bank-reconciliations/{$reconciliation->id}/matches", [
                'bank_transaction_id' => $bankTransaction->id,
                'journal_entry_line_id' => $ledgerLine->id,
                'matched_amount' => 400,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($user)->get("/bank-reconciliations/{$reconciliation->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('matches', 1)
                ->where('matches.0.out_of_period', false)
                ->where('outOfPeriodMatchCount', 0));

        $audit = AuditLog::where('action', 'bank-reconciliation.matched')->firstOrFail();
        $this->assertFalse($audit->new_values['out_of_period'] ?? null);
    }

    public function test_store_creates_in_progress_reconciliation_with_derived_difference(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $user = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);

        $this->actingAs($user)->post('/bank-reconciliations', $this->storePayload($bankAccount->id, [
            'opening_balance' => 1000,
            'closing_balance' => 1250.5,
        ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $reconciliation = BankReconciliation::firstOrFail();

        $this->assertSame($org->id, $reconciliation->organization_id);
        $this->assertSame($bankAccount->id, $reconciliation->bank_account_id);
        $this->assertSame($bankAccount->currency_id, $reconciliation->currency_id, 'currency is taken from the bank account (INV-005)');
        $this->assertSame('in_progress', $reconciliation->status);
        $this->assertSame($user->id, $reconciliation->prepared_by);
        $this->assertSame('1000.0000', $reconciliation->reconciled_balance);
        $this->assertSame('250.5000', $reconciliation->difference);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'action' => 'bank-reconciliation.started',
            'auditable_type' => BankReconciliation::class,
            'auditable_id' => $reconciliation->id,
        ]);
    }

    public function test_store_rejects_bank_account_from_another_organization(): void
    {
        [$org] = $this->bankSetup();
        [$otherOrg, $otherBankAccount] = $this->bankSetup();
        $user = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);

        $this->actingAs($user)->post('/bank-reconciliations', $this->storePayload($otherBankAccount->id))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('bank_reconciliations', 0);
    }

    public function test_store_rejects_inactive_bank_account(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $bankAccount->update(['status' => 'closed']);
        $user = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);

        $this->actingAs($user)->post('/bank-reconciliations', $this->storePayload($bankAccount->id))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('bank_reconciliations', 0);
    }

    public function test_store_rejects_a_second_open_reconciliation_for_the_account(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $user = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);

        $this->actingAs($user)->post('/bank-reconciliations', $this->storePayload($bankAccount->id))
            ->assertSessionHas('success');

        $this->actingAs($user)->post('/bank-reconciliations', $this->storePayload($bankAccount->id, [
            'statement_start_date' => '2026-02-01',
            'statement_end_date' => '2026-02-28',
            'statement_date' => '2026-02-28',
        ]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('bank_reconciliations', 1);
    }

    public function test_store_rejects_period_overlapping_a_finalized_reconciliation(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $user = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);

        BankReconciliation::factory()->finalized()->create([
            'organization_id' => $org->id,
            'bank_account_id' => $bankAccount->id,
            'currency_id' => $bankAccount->currency_id,
            'statement_start_date' => '2026-01-01',
            'statement_end_date' => '2026-01-31',
        ]);

        $this->actingAs($user)->post('/bank-reconciliations', $this->storePayload($bankAccount->id))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('bank_reconciliations', 1);
    }

    // ---------------------------------------------------------------- match

    public function test_match_creates_many_to_many_link_and_flips_transaction_to_matched(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $user = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);
        $reconciliation = $this->startReconciliation($org, $bankAccount, $user);

        $bankTransaction = BankTransaction::factory()->debit(400)->create([
            'bank_account_id' => $bankAccount->id,
            'organization_id' => $org->id,
        ]);

        $ledgerLine = $this->postedLedgerLine($org, $bankAccount->account, $bankAccount->currency, 400);

        $this->actingAs($user)
            ->post("/bank-reconciliations/{$reconciliation->id}/matches", [
                'bank_transaction_id' => $bankTransaction->id,
                'journal_entry_line_id' => $ledgerLine->id,
                'matched_amount' => 400,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $match = ReconciliationMatch::firstOrFail();

        $this->assertSame($reconciliation->id, $match->reconciliation_id);
        $this->assertSame($bankTransaction->id, $match->bank_transaction_id);
        $this->assertSame($ledgerLine->id, $match->journal_entry_line_id);
        $this->assertSame('400.0000', $match->matched_amount);
        $this->assertSame('exact', $match->match_type);
        $this->assertSame('matched', $bankTransaction->fresh()->reconciliation_status);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'action' => 'bank-reconciliation.matched',
            'auditable_type' => ReconciliationMatch::class,
            'auditable_id' => $match->id,
        ]);
    }

    public function test_match_supports_one_transaction_split_across_journal_lines(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $user = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);
        $reconciliation = $this->startReconciliation($org, $bankAccount, $user);

        $bankTransaction = BankTransaction::factory()->debit(1000)->create([
            'bank_account_id' => $bankAccount->id,
            'organization_id' => $org->id,
        ]);

        $first = $this->postedLedgerLine($org, $bankAccount->account, $bankAccount->currency, 600);
        $second = $this->postedLedgerLine($org, $bankAccount->account, $bankAccount->currency, 400);

        $this->actingAs($user)->post("/bank-reconciliations/{$reconciliation->id}/matches", [
            'bank_transaction_id' => $bankTransaction->id,
            'journal_entry_line_id' => $first->id,
            'matched_amount' => 600,
        ])->assertSessionHas('success');

        $this->actingAs($user)->post("/bank-reconciliations/{$reconciliation->id}/matches", [
            'bank_transaction_id' => $bankTransaction->id,
            'journal_entry_line_id' => $second->id,
            'matched_amount' => 400,
        ])->assertSessionHas('success');

        $matches = ReconciliationMatch::orderBy('id')->get();

        $this->assertCount(2, $matches);
        $this->assertSame('split', $matches[0]->match_type, 'the transaction is not fully consumed by the first line');
        $this->assertSame('exact', $matches[1]->match_type, 'the second line consumes what is left');
        $this->assertSame(1000.0, (float) $matches->sum('matched_amount'));
    }

    public function test_match_supports_many_transactions_combined_into_one_journal_line(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $user = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);
        $reconciliation = $this->startReconciliation($org, $bankAccount, $user);

        $ledgerLine = $this->postedLedgerLine($org, $bankAccount->account, $bankAccount->currency, 900);

        $first = BankTransaction::factory()->debit(500)->create([
            'bank_account_id' => $bankAccount->id,
            'organization_id' => $org->id,
        ]);
        $second = BankTransaction::factory()->debit(400)->create([
            'bank_account_id' => $bankAccount->id,
            'organization_id' => $org->id,
        ]);

        $this->actingAs($user)->post("/bank-reconciliations/{$reconciliation->id}/matches", [
            'bank_transaction_id' => $first->id,
            'journal_entry_line_id' => $ledgerLine->id,
            'matched_amount' => 500,
        ])->assertSessionHas('success');

        $this->actingAs($user)->post("/bank-reconciliations/{$reconciliation->id}/matches", [
            'bank_transaction_id' => $second->id,
            'journal_entry_line_id' => $ledgerLine->id,
            'matched_amount' => 400,
        ])->assertSessionHas('success');

        $matches = ReconciliationMatch::orderBy('id')->get();

        $this->assertCount(2, $matches);
        $this->assertSame('combined', $matches[0]->match_type, 'the transaction is consumed but the line still has room');
        $this->assertSame('exact', $matches[1]->match_type);
    }

    public function test_match_recomputes_reconciled_balance_and_difference(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $user = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);
        $reconciliation = $this->startReconciliation($org, $bankAccount, $user, [
            'opening_balance' => 1000,
            'closing_balance' => 1200,
        ]);

        $credit = BankTransaction::factory()->credit(500)->create([
            'bank_account_id' => $bankAccount->id,
            'organization_id' => $org->id,
        ]);
        $debit = BankTransaction::factory()->debit(300)->create([
            'bank_account_id' => $bankAccount->id,
            'organization_id' => $org->id,
        ]);

        $this->actingAs($user)->post("/bank-reconciliations/{$reconciliation->id}/matches", [
            'bank_transaction_id' => $credit->id,
            'matched_amount' => 500,
        ])->assertSessionHas('success');

        $this->assertSame('1500.0000', $reconciliation->fresh()->reconciled_balance);
        $this->assertSame('-300.0000', $reconciliation->fresh()->difference);

        $this->actingAs($user)->post("/bank-reconciliations/{$reconciliation->id}/matches", [
            'bank_transaction_id' => $debit->id,
            'matched_amount' => 300,
        ])->assertSessionHas('success');

        $this->assertSame('1200.0000', $reconciliation->fresh()->reconciled_balance);
        $this->assertSame('0.0000', $reconciliation->fresh()->difference);
    }

    public function test_match_rejects_amount_above_unmatched_transaction_remainder(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $user = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);
        $reconciliation = $this->startReconciliation($org, $bankAccount, $user);

        $bankTransaction = BankTransaction::factory()->debit(300)->create([
            'bank_account_id' => $bankAccount->id,
            'organization_id' => $org->id,
        ]);

        $this->actingAs($user)->post("/bank-reconciliations/{$reconciliation->id}/matches", [
            'bank_transaction_id' => $bankTransaction->id,
            'matched_amount' => 200,
        ])->assertSessionHas('success');

        $this->actingAs($user)->post("/bank-reconciliations/{$reconciliation->id}/matches", [
            'bank_transaction_id' => $bankTransaction->id,
            'matched_amount' => 150,
        ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(200.0, (float) ReconciliationMatch::sum('matched_amount'));
    }

    public function test_match_rejects_amount_above_unmatched_journal_line_remainder(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $user = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);
        $reconciliation = $this->startReconciliation($org, $bankAccount, $user);

        $ledgerLine = $this->postedLedgerLine($org, $bankAccount->account, $bankAccount->currency, 250);

        $tooBig = BankTransaction::factory()->debit(500)->create([
            'bank_account_id' => $bankAccount->id,
            'organization_id' => $org->id,
        ]);

        $this->actingAs($user)->post("/bank-reconciliations/{$reconciliation->id}/matches", [
            'bank_transaction_id' => $tooBig->id,
            'journal_entry_line_id' => $ledgerLine->id,
            'matched_amount' => 300,
        ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('reconciliation_matches', 0);
    }

    public function test_match_rejects_transaction_from_another_organization_and_account(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        [, $otherBankAccount] = $this->bankSetup();
        $user = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);
        $reconciliation = $this->startReconciliation($org, $bankAccount, $user);

        $foreign = BankTransaction::factory()->debit(100)->create([
            'bank_account_id' => $otherBankAccount->id,
        ]);

        $this->actingAs($user)->post("/bank-reconciliations/{$reconciliation->id}/matches", [
            'bank_transaction_id' => $foreign->id,
            'matched_amount' => 100,
        ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('reconciliation_matches', 0);
    }

    public function test_match_rejects_a_posted_line_on_another_ledger_account(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $user = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);
        $reconciliation = $this->startReconciliation($org, $bankAccount, $user);

        $bankTransaction = BankTransaction::factory()->debit(100)->create([
            'bank_account_id' => $bankAccount->id,
            'organization_id' => $org->id,
        ]);

        // A posted line on a DIFFERENT account of the same organization and
        // currency: reachable only by bypassing the controller's candidate list.
        $foreignAccount = Account::factory()->create(['organization_id' => $org->id]);
        $foreignLine = $this->postedLedgerLine($org, $foreignAccount, $bankAccount->currency, 100);

        $this->actingAs($user)->post("/bank-reconciliations/{$reconciliation->id}/matches", [
            'bank_transaction_id' => $bankTransaction->id,
            'journal_entry_line_id' => $foreignLine->id,
            'matched_amount' => 100,
        ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('reconciliation_matches', 0);
    }

    public function test_partial_match_moves_the_statement_by_the_matched_amount_only(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $user = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);

        // Opening 1000 with a 1000 debit transaction, of which only 600 is
        // reconciled: the statement closed at 400, so the difference is zero.
        $reconciliation = $this->startReconciliation($org, $bankAccount, $user, [
            'opening_balance' => 1000,
            'closing_balance' => 400,
        ]);

        $bankTransaction = BankTransaction::factory()->debit(1000)->create([
            'bank_account_id' => $bankAccount->id,
            'organization_id' => $org->id,
        ]);

        $this->actingAs($user)->post("/bank-reconciliations/{$reconciliation->id}/matches", [
            'bank_transaction_id' => $bankTransaction->id,
            'matched_amount' => 600,
        ])->assertSessionHas('success');

        $reconciliation->refresh();

        $this->assertEquals(400, (float) $reconciliation->reconciled_balance, 'only the matched 600 leaves the opening 1000');
        $this->assertEquals(0, (float) $reconciliation->difference);
        $this->assertTrue($reconciliation->isBalanced(), 'a partial match can still be finalized when the statement agrees');
    }

    public function test_match_rejects_unposted_journal_entry_line(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $user = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);
        $reconciliation = $this->startReconciliation($org, $bankAccount, $user);

        $bankTransaction = BankTransaction::factory()->debit(100)->create([
            'bank_account_id' => $bankAccount->id,
            'organization_id' => $org->id,
        ]);

        // A draft entry cannot be reconciled: the ledger side is not authoritative yet.
        $draftLine = $this->draftLedgerLine($org, $bankAccount, 100);

        $this->actingAs($user)->post("/bank-reconciliations/{$reconciliation->id}/matches", [
            'bank_transaction_id' => $bankTransaction->id,
            'journal_entry_line_id' => $draftLine->id,
            'matched_amount' => 100,
        ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('reconciliation_matches', 0);
    }

    public function test_match_forbids_user_without_manage_permission(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $preparer = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);
        $viewer = $this->attachRoleWithPermission(User::factory()->create(), $org, 'view-bank-reconciliations');
        $reconciliation = $this->startReconciliation($org, $bankAccount, $preparer);

        $bankTransaction = BankTransaction::factory()->debit(100)->create([
            'bank_account_id' => $bankAccount->id,
            'organization_id' => $org->id,
        ]);

        $this->actingAs($viewer)->post("/bank-reconciliations/{$reconciliation->id}/matches", [
            'bank_transaction_id' => $bankTransaction->id,
            'matched_amount' => 100,
        ])->assertForbidden();

        $this->assertDatabaseCount('reconciliation_matches', 0);
    }

    public function test_match_returns_404_for_other_organization_reconciliation(): void
    {
        [$org] = $this->bankSetup();
        [$otherOrg, $otherBankAccount] = $this->bankSetup();
        $user = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);

        $reconciliation = BankReconciliation::factory()->create([
            'organization_id' => $otherOrg->id,
            'bank_account_id' => $otherBankAccount->id,
            'currency_id' => $otherBankAccount->currency_id,
        ]);
        $bankTransaction = BankTransaction::factory()->debit(100)->create([
            'bank_account_id' => $otherBankAccount->id,
        ]);

        $this->actingAs($user)->post("/bank-reconciliations/{$reconciliation->id}/matches", [
            'bank_transaction_id' => $bankTransaction->id,
            'matched_amount' => 100,
        ])->assertNotFound();
    }

    public function test_database_rejects_a_cross_organization_match(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        [$otherOrg, $otherBankAccount] = $this->bankSetup();
        $user = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);
        $reconciliation = $this->startReconciliation($org, $bankAccount, $user);

        // INV-008 is a database invariant: the composite FK
        // (organization_id, reconciliation_id) must not accept a foreign reconciliation.
        $this->expectException(QueryException::class);

        ReconciliationMatch::create([
            'organization_id' => $org->id,
            'reconciliation_id' => $reconciliation->id,
            'bank_transaction_id' => BankTransaction::factory()->debit(100)->create([
                'bank_account_id' => $otherBankAccount->id,
            ])->id,
            'matched_amount' => 100,
            'match_type' => 'exact',
            'created_by' => $user->id,
        ]);
    }

    public function test_database_requires_an_organization_on_every_match(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $user = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);
        $reconciliation = $this->startReconciliation($org, $bankAccount, $user);
        $bankTransaction = BankTransaction::factory()->debit(100)->create([
            'bank_account_id' => $bankAccount->id,
            'organization_id' => $org->id,
        ]);

        // A NULL organization_id would skip all three composite tenant foreign
        // keys, so the column itself must refuse to be null.
        $this->expectException(QueryException::class);

        DB::table('reconciliation_matches')->insert([
            'organization_id' => null,
            'reconciliation_id' => $reconciliation->id,
            'bank_transaction_id' => $bankTransaction->id,
            'matched_amount' => 100,
            'match_type' => 'exact',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_database_rejects_non_positive_matched_amount(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $user = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);
        $reconciliation = $this->startReconciliation($org, $bankAccount, $user);
        $bankTransaction = BankTransaction::factory()->debit(100)->create([
            'bank_account_id' => $bankAccount->id,
            'organization_id' => $org->id,
        ]);

        $this->expectException(QueryException::class);

        ReconciliationMatch::create([
            'organization_id' => $org->id,
            'reconciliation_id' => $reconciliation->id,
            'bank_transaction_id' => $bankTransaction->id,
            'matched_amount' => 0,
            'match_type' => 'exact',
            'created_by' => $user->id,
        ]);
    }

    // -------------------------------------------------------------- unmatch

    public function test_unmatch_removes_link_recomputes_balance_and_resets_transaction(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $user = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);
        $reconciliation = $this->startReconciliation($org, $bankAccount, $user, [
            'opening_balance' => 1000,
            'closing_balance' => 1500,
        ]);

        $bankTransaction = BankTransaction::factory()->credit(500)->create([
            'bank_account_id' => $bankAccount->id,
            'organization_id' => $org->id,
        ]);

        $this->actingAs($user)->post("/bank-reconciliations/{$reconciliation->id}/matches", [
            'bank_transaction_id' => $bankTransaction->id,
            'matched_amount' => 500,
        ])->assertSessionHas('success');

        $match = ReconciliationMatch::firstOrFail();
        $this->assertSame('1500.0000', $reconciliation->fresh()->reconciled_balance);

        $this->actingAs($user)
            ->delete("/bank-reconciliations/{$reconciliation->id}/matches/{$match->id}")
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('reconciliation_matches', 0);
        $this->assertSame('unreconciled', $bankTransaction->fresh()->reconciliation_status);
        $this->assertSame('1000.0000', $reconciliation->fresh()->reconciled_balance);
        $this->assertSame('500.0000', $reconciliation->fresh()->difference);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'action' => 'bank-reconciliation.unmatched',
            'auditable_type' => ReconciliationMatch::class,
            'auditable_id' => $match->id,
        ]);
    }

    public function test_unmatch_keeps_transaction_matched_while_another_match_remains(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $user = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);
        $reconciliation = $this->startReconciliation($org, $bankAccount, $user);

        $bankTransaction = BankTransaction::factory()->debit(500)->create([
            'bank_account_id' => $bankAccount->id,
            'organization_id' => $org->id,
        ]);

        $this->actingAs($user)->post("/bank-reconciliations/{$reconciliation->id}/matches", [
            'bank_transaction_id' => $bankTransaction->id,
            'matched_amount' => 200,
        ])->assertSessionHas('success');
        $this->actingAs($user)->post("/bank-reconciliations/{$reconciliation->id}/matches", [
            'bank_transaction_id' => $bankTransaction->id,
            'matched_amount' => 300,
        ])->assertSessionHas('success');

        $first = ReconciliationMatch::orderBy('id')->firstOrFail();

        $this->actingAs($user)->delete("/bank-reconciliations/{$reconciliation->id}/matches/{$first->id}")
            ->assertSessionHas('success');

        $this->assertSame('matched', $bankTransaction->fresh()->reconciliation_status);
    }

    // ------------------------------------------------------------- finalize

    public function test_finalize_rejects_unresolved_difference(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $user = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);
        $preparer = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);
        $reconciliation = $this->startReconciliation($org, $bankAccount, $preparer, [
            'opening_balance' => 1000,
            'closing_balance' => 1500,
        ]);

        $this->finalize($reconciliation, $user)
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('in_progress', $reconciliation->fresh()->status);
    }

    public function test_finalize_locks_reconciliation_and_marks_transactions_reconciled(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $finalizer = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);
        $preparer = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);
        $reconciliation = $this->startReconciliation($org, $bankAccount, $preparer, [
            'opening_balance' => 1000,
            'closing_balance' => 1500,
        ]);

        $bankTransaction = BankTransaction::factory()->credit(500)->create([
            'bank_account_id' => $bankAccount->id,
            'organization_id' => $org->id,
        ]);

        $this->actingAs($finalizer)->post("/bank-reconciliations/{$reconciliation->id}/matches", [
            'bank_transaction_id' => $bankTransaction->id,
            'matched_amount' => 500,
        ])->assertSessionHas('success');

        $this->finalize($reconciliation, $finalizer)
            ->assertRedirect()
            ->assertSessionHas('success');

        $finalized = $reconciliation->fresh();

        $this->assertSame('finalized', $finalized->status);
        $this->assertSame($finalizer->id, $finalized->approved_by);
        $this->assertSame($finalizer->id, $finalized->reconciled_by);
        $this->assertNotNull($finalized->reconciled_at);
        $this->assertSame('reconciled', $bankTransaction->fresh()->reconciliation_status);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'action' => 'bank-reconciliation.finalized',
            'auditable_type' => BankReconciliation::class,
            'auditable_id' => $reconciliation->id,
        ]);
    }

    public function test_finalize_enforces_maker_checker_against_the_preparer(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $preparer = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);
        $reconciliation = $this->startReconciliation($org, $bankAccount, $preparer, [
            'opening_balance' => 1000,
            'closing_balance' => 1000,
        ]);

        $this->finalize($reconciliation, $preparer)
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('in_progress', $reconciliation->fresh()->status);
        $this->assertNull($reconciliation->fresh()->reconciled_at);
    }

    public function test_finalize_forbids_user_without_finalize_permission(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $preparer = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);
        $reconciliation = $this->startReconciliation($org, $bankAccount, $preparer, [
            'opening_balance' => 1000,
            'closing_balance' => 1000,
        ]);

        $managerOnly = $this->attachRoleWithPermission(User::factory()->create(), $org, 'manage-bank-reconciliations');

        $this->finalize($reconciliation, $managerOnly)->assertForbidden();

        $this->assertSame('in_progress', $reconciliation->fresh()->status);
    }

    public function test_finalized_reconciliation_rejects_further_matching(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $finalizer = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);
        $preparer = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);
        $reconciliation = $this->startReconciliation($org, $bankAccount, $preparer, [
            'opening_balance' => 1000,
            'closing_balance' => 1000,
        ]);

        $this->finalize($reconciliation, $finalizer)->assertSessionHas('success');

        $bankTransaction = BankTransaction::factory()->debit(100)->create([
            'bank_account_id' => $bankAccount->id,
            'organization_id' => $org->id,
        ]);

        $this->actingAs($finalizer)->post("/bank-reconciliations/{$reconciliation->id}/matches", [
            'bank_transaction_id' => $bankTransaction->id,
            'matched_amount' => 100,
        ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('reconciliation_matches', 0);
    }

    public function test_transaction_already_reconciled_elsewhere_cannot_be_matched_again(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $finalizer = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);
        $preparer = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);

        $bankTransaction = BankTransaction::factory()->credit(500)->imported()->create([
            'bank_account_id' => $bankAccount->id,
            'organization_id' => $org->id,
        ]);

        $first = $this->startReconciliation($org, $bankAccount, $preparer, [
            'opening_balance' => 0,
            'closing_balance' => 500,
        ]);
        $this->actingAs($finalizer)->post("/bank-reconciliations/{$first->id}/matches", [
            'bank_transaction_id' => $bankTransaction->id,
            'matched_amount' => 500,
        ])->assertSessionHas('success');
        $this->finalize($first, $finalizer)->assertSessionHas('success');

        $second = $this->startReconciliation($org, $bankAccount, $preparer, [
            'statement_start_date' => '2026-02-01',
            'statement_end_date' => '2026-02-28',
            'statement_date' => '2026-02-28',
            'opening_balance' => 500,
            'closing_balance' => 500,
        ]);

        $this->actingAs($finalizer)->post("/bank-reconciliations/{$second->id}/matches", [
            'bank_transaction_id' => $bankTransaction->id,
            'matched_amount' => 500,
        ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(1, ReconciliationMatch::count());
    }

    // --------------------------------------------------------------- reopen

    public function test_reopen_reopens_finalized_reconciliation_and_restores_matched_state(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $finalizer = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);
        $preparer = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);
        $reconciliation = $this->startReconciliation($org, $bankAccount, $preparer, [
            'opening_balance' => 0,
            'closing_balance' => 500,
        ]);

        $bankTransaction = BankTransaction::factory()->credit(500)->create([
            'bank_account_id' => $bankAccount->id,
            'organization_id' => $org->id,
        ]);

        $this->actingAs($finalizer)->post("/bank-reconciliations/{$reconciliation->id}/matches", [
            'bank_transaction_id' => $bankTransaction->id,
            'matched_amount' => 500,
        ])->assertSessionHas('success');
        $this->finalize($reconciliation, $finalizer)->assertSessionHas('success');

        $this->reopen($reconciliation, [
            'reason' => 'Duplicate statement line received from the bank',
        ], $finalizer)
            ->assertRedirect()
            ->assertSessionHas('success');

        $reopened = $reconciliation->fresh();

        $this->assertSame('reopened', $reopened->status);
        $this->assertSame('Duplicate statement line received from the bank', $reopened->finalization_reason);
        $this->assertNull($reopened->reconciled_at);
        $this->assertNull($reopened->reconciled_by);
        $this->assertNull($reopened->approved_by);
        $this->assertSame('matched', $bankTransaction->fresh()->reconciliation_status);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'action' => 'bank-reconciliation.reopened',
            'auditable_type' => BankReconciliation::class,
            'auditable_id' => $reconciliation->id,
        ]);
    }

    public function test_reopen_requires_a_reason(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $finalizer = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);
        $preparer = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);
        $reconciliation = $this->startReconciliation($org, $bankAccount, $preparer);

        $this->reopen($reconciliation, [], $finalizer)->assertSessionHasErrors('reason');

        $this->assertSame('in_progress', $reconciliation->fresh()->status);
    }

    public function test_reopen_rejects_a_reconciliation_that_is_not_finalized(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $user = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);
        $reconciliation = $this->startReconciliation($org, $bankAccount, $user);

        $this->reopen($reconciliation, ['reason' => 'Not actually finalized'], $user)
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('in_progress', $reconciliation->fresh()->status);
    }

    public function test_reopen_forbids_user_without_finalize_permission(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $managerOnly = $this->attachRoleWithPermission(User::factory()->create(), $org, 'manage-bank-reconciliations');
        $preparer = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);
        $reconciliation = $this->startReconciliation($org, $bankAccount, $preparer);

        BankReconciliation::whereKey($reconciliation->id)->update(['status' => 'finalized']);

        $this->reopen($reconciliation, ['reason' => 'Trying to bypass the control'], $managerOnly)
            ->assertForbidden();

        $this->assertSame('finalized', $reconciliation->fresh()->status);
    }

    // ------------------------------------------------------ derived + audit

    public function test_derived_balance_is_rebuildable_from_matches(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $user = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);
        $reconciliation = $this->startReconciliation($org, $bankAccount, $user, [
            'opening_balance' => 250,
            'closing_balance' => 400,
        ]);

        $credit = BankTransaction::factory()->credit(300)->create([
            'bank_account_id' => $bankAccount->id,
            'organization_id' => $org->id,
        ]);
        $debit = BankTransaction::factory()->debit(150)->create([
            'bank_account_id' => $bankAccount->id,
            'organization_id' => $org->id,
        ]);

        $this->actingAs($user)->post("/bank-reconciliations/{$reconciliation->id}/matches", [
            'bank_transaction_id' => $credit->id,
            'matched_amount' => 300,
        ])->assertSessionHas('success');
        $this->actingAs($user)->post("/bank-reconciliations/{$reconciliation->id}/matches", [
            'bank_transaction_id' => $debit->id,
            'matched_amount' => 150,
        ])->assertSessionHas('success');

        $stored = $reconciliation->fresh();

        $this->assertSame('400.0000', $stored->reconciled_balance);
        $this->assertSame('0.0000', $stored->difference);
        $this->assertTrue($stored->isBalanced());

        // Corrupting the derived column must be recoverable from the matches.
        BankReconciliation::whereKey($reconciliation->id)->update(['reconciled_balance' => 999999]);

        $this->assertSame('999999.0000', $reconciliation->fresh()->reconciled_balance);

        $rebuilt = $this->rebuild($reconciliation->id);

        $this->assertSame('400.0000', $rebuilt->reconciled_balance);
        $this->assertSame('0.0000', $rebuilt->difference);
    }

    public function test_audit_chain_remains_valid_across_the_reconciliation_lifecycle(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $finalizer = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);
        $preparer = $this->attachRoleWithPermission(User::factory()->create(), $org, self::MANAGER_PERMISSIONS);
        $reconciliation = $this->startReconciliation($org, $bankAccount, $preparer, [
            'opening_balance' => 0,
            'closing_balance' => 500,
        ]);

        $bankTransaction = BankTransaction::factory()->credit(500)->create([
            'bank_account_id' => $bankAccount->id,
            'organization_id' => $org->id,
        ]);

        $this->actingAs($finalizer)->post("/bank-reconciliations/{$reconciliation->id}/matches", [
            'bank_transaction_id' => $bankTransaction->id,
            'matched_amount' => 500,
        ])->assertSessionHas('success');
        $this->finalize($reconciliation, $finalizer)->assertSessionHas('success');

        $match = ReconciliationMatch::firstOrFail();
        $this->actingAs($finalizer)->delete("/bank-reconciliations/{$reconciliation->id}/matches/{$match->id}")
            ->assertSessionHas('error');
        $this->reopen($reconciliation, ['reason' => 'Statement correction'], $finalizer)
            ->assertSessionHas('success');

        $result = app(AuditLogService::class)->verify($org);

        $this->assertTrue($result->valid);
        $this->assertSame(4, $result->checkedCount);
    }

    private function rebuild(int $reconciliationId): BankReconciliation
    {
        $action = new class
        {
            use MaintainsReconciliationState;

            public function run(BankReconciliation $reconciliation): BankReconciliation
            {
                return $this->recompute($reconciliation);
            }
        };

        return $action->run(BankReconciliation::findOrFail($reconciliationId));
    }
}
