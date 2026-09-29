<?php

namespace App\Actions\BankReconciliation;

use App\Actions\Action;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class StartReconciliation
{
    use Action;

    public function handle(array $data, Organization $organization, User $actor): BankReconciliation
    {
        return DB::transaction(function () use ($data, $organization, $actor) {
            // Locking the bank account serialises concurrent "start" requests
            // for the same account so two open reconciliations cannot be created.
            $bankAccount = BankAccount::whereKey($data['bank_account_id'])->lockForUpdate()->firstOrFail();

            if ($bankAccount->organization_id !== $organization->id) {
                throw new \RuntimeException('The bank account must belong to the same organization.');
            }

            if (! $bankAccount->isActive()) {
                throw new \RuntimeException('The bank account must be active.');
            }

            $existing = BankReconciliation::forOrganization($organization)
                ->where('bank_account_id', $bankAccount->id)
                ->where('status', '!=', 'finalized')
                ->exists();

            if ($existing) {
                throw new \RuntimeException('This bank account already has an open reconciliation.');
            }

            $overlapping = BankReconciliation::forOrganization($organization)
                ->where('bank_account_id', $bankAccount->id)
                ->where('status', 'finalized')
                ->whereDate('statement_end_date', '>=', $data['statement_start_date'])
                ->whereDate('statement_start_date', '<=', $data['statement_end_date'])
                ->exists();

            if ($overlapping) {
                throw new \RuntimeException('The statement period overlaps a finalized reconciliation.');
            }

            $reconciliation = BankReconciliation::create([
                'organization_id' => $organization->id,
                'bank_account_id' => $bankAccount->id,
                'currency_id' => $bankAccount->currency_id,
                'statement_date' => $data['statement_date'],
                'statement_start_date' => $data['statement_start_date'],
                'statement_end_date' => $data['statement_end_date'],
                'opening_balance' => $data['opening_balance'],
                'closing_balance' => $data['closing_balance'],
                'reconciled_balance' => $data['opening_balance'],
                'difference' => bcsub((string) $data['closing_balance'], (string) $data['opening_balance'], 4),
                'status' => 'in_progress',
                'prepared_by' => $actor->id,
            ]);

            app(AuditLogService::class)->record(
                $organization,
                $actor->id,
                'bank-reconciliation.started',
                BankReconciliation::class,
                $reconciliation->id,
                null,
                $this->auditValues($reconciliation)
            );

            return $reconciliation;
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function auditValues(BankReconciliation $reconciliation): array
    {
        return $reconciliation->only([
            'bank_account_id', 'currency_id', 'statement_date', 'statement_start_date',
            'statement_end_date', 'opening_balance', 'closing_balance', 'reconciled_balance',
            'difference', 'status', 'prepared_by',
        ]);
    }

    public function rules(): array
    {
        return [
            'bank_account_id' => ['required', 'exists:bank_accounts,id'],
            'statement_date' => ['required', 'date'],
            'statement_start_date' => ['required', 'date'],
            'statement_end_date' => ['required', 'date', 'after_or_equal:statement_start_date'],
            'opening_balance' => ['required', 'numeric', 'min:0'],
            'closing_balance' => ['required', 'numeric', 'min:0'],
        ];
    }
}
