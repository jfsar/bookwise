<?php

namespace App\Http\Controllers;

use App\Actions\BankReconciliation\FinalizeReconciliation;
use App\Actions\BankReconciliation\MatchBankTransaction;
use App\Actions\BankReconciliation\ReopenReconciliation;
use App\Actions\BankReconciliation\StartReconciliation;
use App\Actions\BankReconciliation\UnmatchBankTransaction;
use App\Actions\Organization\SwitchOrganization;
use App\Enums\JournalEntryStatus;
use App\Http\Requests\MatchBankTransactionRequest;
use App\Http\Requests\ReopenBankReconciliationRequest;
use App\Http\Requests\StoreBankReconciliationRequest;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankTransaction;
use App\Models\JournalEntryLine;
use App\Models\ReconciliationMatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BankReconciliationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        if (! $organization) {
            return Inertia::render('BankReconciliations/Index', [
                'reconciliations' => null,
                'bankAccounts' => [],
            ]);
        }

        $this->authorize('viewAny', [BankReconciliation::class, $organization]);

        $bankAccounts = BankAccount::forOrganization($organization)
            ->orderBy('bank_name')
            ->get()
            ->map(fn (BankAccount $bankAccount) => [
                'id' => $bankAccount->id,
                'bank_name' => $bankAccount->bank_name,
                'account_name' => $bankAccount->account_name,
            ])
            ->values();

        $bankAccountId = $request->filled('bank_account_id')
            ? (int) $request->input('bank_account_id')
            : null;

        $reconciliations = BankReconciliation::forOrganization($organization)
            ->with(['bankAccount', 'preparer', 'reconciler'])
            ->when($bankAccountId, fn ($query) => $query->where('bank_account_id', $bankAccountId))
            ->orderByDesc('statement_end_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (BankReconciliation $reconciliation) => $this->serialize($reconciliation, $user))
            ->toArray();

        return Inertia::render('BankReconciliations/Index', [
            'reconciliations' => $reconciliations,
            'bankAccounts' => $bankAccounts,
            'selectedBankAccountId' => $bankAccountId,
            'canManage' => $user->can('create', [BankReconciliation::class, $organization]),
        ]);
    }

    public function show(Request $request, BankReconciliation $bankReconciliation): Response
    {
        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        if (! $organization || $bankReconciliation->organization_id !== $organization->id) {
            abort(404);
        }

        $this->authorize('view', $bankReconciliation);

        $bankReconciliation->load(['bankAccount', 'currency', 'preparer', 'reconciler', 'matches.bankTransaction', 'matches.journalEntryLine']);

        $unmatchedTransactions = BankTransaction::forOrganization($organization)
            ->forBankAccount($bankReconciliation->bank_account_id)
            ->whereIn('reconciliation_status', ['unreconciled', 'matched'])
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get()
            ->map(fn (BankTransaction $bankTransaction) => [
                'id' => $bankTransaction->id,
                'transaction_date' => $bankTransaction->transaction_date?->format('Y-m-d'),
                'reference' => $bankTransaction->reference,
                'description' => $bankTransaction->description,
                'debit' => $bankTransaction->debit,
                'credit' => $bankTransaction->credit,
                'source' => $bankTransaction->source,
                'reconciliation_status' => $bankTransaction->reconciliation_status,
            ])
            ->values();

        $candidateLines = JournalEntryLine::where('organization_id', $organization->id)
            ->where('account_id', $bankReconciliation->bankAccount->account_id)
            ->whereHas('journalEntry', fn ($query) => $query->where('status', JournalEntryStatus::Posted->value))
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (JournalEntryLine $line) => [
                'id' => $line->id,
                'journal_entry_id' => $line->journal_entry_id,
                'description' => $line->description,
                'debit' => $line->debit,
                'credit' => $line->credit,
            ])
            ->values();

        return Inertia::render('BankReconciliations/Show', [
            'reconciliation' => $this->serialize($bankReconciliation, $user),
            'matches' => $bankReconciliation->matches
                ->map(fn (ReconciliationMatch $match) => [
                    'id' => $match->id,
                    'bank_transaction_id' => $match->bank_transaction_id,
                    'bank_transaction' => $match->bankTransaction ? [
                        'id' => $match->bankTransaction->id,
                        'transaction_date' => $match->bankTransaction->transaction_date?->format('Y-m-d'),
                        'description' => $match->bankTransaction->description,
                        'debit' => $match->bankTransaction->debit,
                        'credit' => $match->bankTransaction->credit,
                    ] : null,
                    'journal_entry_line_id' => $match->journal_entry_line_id,
                    'journal_entry_line' => $match->journalEntryLine ? [
                        'id' => $match->journalEntryLine->id,
                        'journal_entry_id' => $match->journalEntryLine->journal_entry_id,
                        'debit' => $match->journalEntryLine->debit,
                        'credit' => $match->journalEntryLine->credit,
                    ] : null,
                    'matched_amount' => $match->matched_amount,
                    'match_type' => $match->match_type,
                ])
                ->values(),
            'unmatchedTransactions' => $unmatchedTransactions,
            'candidateJournalLines' => $candidateLines,
            'can' => [
                'match' => $bankReconciliation->isOpen() && $user->can('update', $bankReconciliation),
                'finalize' => $bankReconciliation->isOpen() && $user->can('finalize', $bankReconciliation),
                'reopen' => $bankReconciliation->isFinalized() && $user->can('finalize', $bankReconciliation),
            ],
        ]);
    }

    public function store(StoreBankReconciliationRequest $request): RedirectResponse
    {
        $organization = SwitchOrganization::current($request->user());

        if (! $organization) {
            return back()->with('error', 'No organization selected.');
        }

        $this->authorize('create', [BankReconciliation::class, $organization]);

        try {
            $reconciliation = (new StartReconciliation)->handle($request->validated(), $organization, $request->user());

            return redirect()
                ->route('reconciliations.show', $reconciliation)
                ->with('success', 'Reconciliation started.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function match(MatchBankTransactionRequest $request, BankReconciliation $bankReconciliation): RedirectResponse
    {
        if (! $this->inCurrentOrganization($request, $bankReconciliation)) {
            abort(404);
        }

        $this->authorize('update', $bankReconciliation);

        try {
            (new MatchBankTransaction)->handle($bankReconciliation, $request->validated(), $request->user());

            return back()->with('success', 'Bank transaction matched.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroyMatch(
        Request $request,
        BankReconciliation $bankReconciliation,
        ReconciliationMatch $reconciliationMatch
    ): RedirectResponse {
        if (! $this->inCurrentOrganization($request, $bankReconciliation)) {
            abort(404);
        }

        if ($reconciliationMatch->reconciliation_id !== $bankReconciliation->id) {
            abort(404);
        }

        $this->authorize('update', $bankReconciliation);

        try {
            (new UnmatchBankTransaction)->handle($bankReconciliation, $reconciliationMatch, $request->user());

            return back()->with('success', 'Match removed.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function finalize(Request $request, BankReconciliation $bankReconciliation): RedirectResponse
    {
        if (! $this->inCurrentOrganization($request, $bankReconciliation)) {
            abort(404);
        }

        $this->authorize('finalize', $bankReconciliation);

        try {
            (new FinalizeReconciliation)->handle($bankReconciliation, $request->user());

            return back()->with('success', 'Reconciliation finalized.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reopen(ReopenBankReconciliationRequest $request, BankReconciliation $bankReconciliation): RedirectResponse
    {
        if (! $this->inCurrentOrganization($request, $bankReconciliation)) {
            abort(404);
        }

        $this->authorize('finalize', $bankReconciliation);

        try {
            (new ReopenReconciliation)->handle($bankReconciliation, $request->validated(), $request->user());

            return back()->with('success', 'Reconciliation reopened.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    private function inCurrentOrganization(Request $request, BankReconciliation $bankReconciliation): bool
    {
        $organization = SwitchOrganization::current($request->user());

        return $organization && $bankReconciliation->organization_id === $organization->id;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(BankReconciliation $reconciliation, mixed $user): array
    {
        return [
            'id' => $reconciliation->id,
            'bank_account_id' => $reconciliation->bank_account_id,
            'bank_account' => $reconciliation->bankAccount ? [
                'id' => $reconciliation->bankAccount->id,
                'bank_name' => $reconciliation->bankAccount->bank_name,
                'account_name' => $reconciliation->bankAccount->account_name,
                'masked_account_number' => $reconciliation->bankAccount->masked_account_number,
            ] : null,
            'statement_date' => $reconciliation->statement_date?->format('Y-m-d'),
            'statement_start_date' => $reconciliation->statement_start_date?->format('Y-m-d'),
            'statement_end_date' => $reconciliation->statement_end_date?->format('Y-m-d'),
            'opening_balance' => $reconciliation->opening_balance,
            'closing_balance' => $reconciliation->closing_balance,
            'reconciled_balance' => $reconciliation->reconciled_balance,
            'difference' => $reconciliation->difference,
            'status' => $reconciliation->status,
            'prepared_by' => $reconciliation->prepared_by,
            'preparer' => $reconciliation->preparer?->name,
            'reconciler' => $reconciliation->reconciler?->name,
            'reconciled_at' => $reconciliation->reconciled_at?->toDateTimeString(),
            'finalization_reason' => $reconciliation->finalization_reason,
            'can' => [
                'manage' => $user->can('update', $reconciliation),
                'finalize' => $user->can('finalize', $reconciliation),
            ],
        ];
    }
}
