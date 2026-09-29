<?php

namespace App\Http\Controllers;

use App\Actions\BankTransaction\CreateBankTransaction;
use App\Actions\BankTransaction\UpdateBankTransaction;
use App\Actions\Organization\SwitchOrganization;
use App\Http\Requests\StoreBankTransactionRequest;
use App\Http\Requests\UpdateBankTransactionRequest;
use App\Models\BankAccount;
use App\Models\BankImportBatch;
use App\Models\BankTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BankTransactionController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        if (! $organization) {
            return Inertia::render('BankTransactions/Index', [
                'bankTransactions' => null,
                'bankAccounts' => [],
                'filters' => $request->only(['bank_account_id', 'search', 'reconciliation_status']),
            ]);
        }

        $this->authorize('viewAny', [BankTransaction::class, $organization]);

        $bankAccounts = BankAccount::forOrganization($organization)
            ->orderBy('bank_name')
            ->get()
            ->values()
            ->map(fn (BankAccount $bankAccount) => [
                'id' => $bankAccount->id,
                'bank_name' => $bankAccount->bank_name,
                'account_name' => $bankAccount->account_name,
                'masked_account_number' => $bankAccount->masked_account_number,
            ]);

        $bankAccountId = $request->filled('bank_account_id')
            ? (int) $request->input('bank_account_id')
            : ($bankAccounts->first()['id'] ?? null);

        $bankTransactions = BankTransaction::forOrganization($organization)
            ->with(['bankAccount', 'currency'])
            ->when($bankAccountId, fn ($query) => $query->forBankAccount($bankAccountId))
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->string('search').'%';

                $query->where(function ($query) use ($term) {
                    $query->where('reference', 'ilike', $term)
                        ->orWhere('description', 'ilike', $term)
                        ->orWhere('external_reference', 'ilike', $term);
                });
            })
            ->when(
                $request->filled('reconciliation_status')
                && in_array($request->input('reconciliation_status'), ['unreconciled', 'matched', 'reconciled']),
                fn ($query) => $query->where('reconciliation_status', $request->input('reconciliation_status'))
            )
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (BankTransaction $bankTransaction) => $this->serialize($bankTransaction, $user))
            ->toArray();

        return Inertia::render('BankTransactions/Index', [
            'bankTransactions' => $bankTransactions,
            'bankAccounts' => $bankAccounts,
            'selectedBankAccountId' => $bankAccountId,
            'filters' => $request->only(['bank_account_id', 'search', 'reconciliation_status']),
            'importBatches' => BankImportBatch::forOrganization($organization)
                ->with(['bankAccount'])
                ->orderByDesc('id')
                ->limit(5)
                ->get()
                ->map(fn (BankImportBatch $batch) => [
                    'id' => $batch->id,
                    'bank_account_id' => $batch->bank_account_id,
                    'bank_name' => $batch->bankAccount?->bank_name,
                    'file_name' => $batch->file_name,
                    'status' => $batch->status,
                    'rows_read' => $batch->rows_read,
                    'rows_imported' => $batch->rows_imported,
                    'rows_skipped' => $batch->rows_skipped,
                    'rows_failed' => $batch->rows_failed,
                    'created_at' => $batch->created_at?->toDateTimeString(),
                ]),
            'canImport' => $user->can('create', [BankImportBatch::class, $organization]),
        ]);
    }

    public function store(StoreBankTransactionRequest $request): RedirectResponse
    {
        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        if (! $organization) {
            return back()->with('error', 'No organization selected.');
        }

        $this->authorize('create', [BankTransaction::class, $organization]);

        try {
            (new CreateBankTransaction)->handle($request->validated(), $organization, $user);

            return back()->with('success', 'Bank transaction recorded successfully.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function update(UpdateBankTransactionRequest $request, BankTransaction $bankTransaction): RedirectResponse
    {
        $organization = SwitchOrganization::current($request->user());

        if ($organization && $bankTransaction->organization_id !== $organization->id) {
            abort(404);
        }

        $this->authorize('update', $bankTransaction);

        try {
            (new UpdateBankTransaction)->handle($bankTransaction, $request->validated(), $request->user());

            return back()->with('success', 'Bank transaction updated successfully.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(BankTransaction $bankTransaction, mixed $user): array
    {
        return [
            'id' => $bankTransaction->id,
            'bank_account_id' => $bankTransaction->bank_account_id,
            'bank_account' => $bankTransaction->bankAccount ? [
                'id' => $bankTransaction->bankAccount->id,
                'bank_name' => $bankTransaction->bankAccount->bank_name,
                'account_name' => $bankTransaction->bankAccount->account_name,
            ] : null,
            'transaction_date' => $bankTransaction->transaction_date?->format('Y-m-d'),
            'value_date' => $bankTransaction->value_date?->format('Y-m-d'),
            'reference' => $bankTransaction->reference,
            'description' => $bankTransaction->description,
            'debit' => $bankTransaction->debit,
            'credit' => $bankTransaction->credit,
            'currency' => $bankTransaction->currency ? [
                'id' => $bankTransaction->currency->id,
                'code' => $bankTransaction->currency->code,
            ] : null,
            'source' => $bankTransaction->source,
            'reconciliation_status' => $bankTransaction->reconciliation_status,
            'can' => [
                'update' => $bankTransaction->isEditable() && $user->can('update', $bankTransaction),
            ],
        ];
    }
}
