<?php

namespace App\Actions\BankTransaction;

use App\Actions\Action;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class CreateBankTransaction
{
    use Action;

    public function handle(array $data, Organization $organization, User $creator): BankTransaction
    {
        return DB::transaction(function () use ($data, $organization, $creator) {
            $bankAccount = BankAccount::findOrFail($data['bank_account_id']);

            if ($bankAccount->organization_id !== $organization->id) {
                throw new \RuntimeException('The bank account must belong to the same organization.');
            }

            if (! $bankAccount->isActive()) {
                throw new \RuntimeException('The bank account must be active.');
            }

            $bankTransaction = BankTransaction::create([
                'organization_id' => $organization->id,
                'bank_account_id' => $bankAccount->id,
                'transaction_date' => $data['transaction_date'],
                'value_date' => $this->nullable($data['value_date'] ?? null),
                'reference' => $this->nullable($data['reference'] ?? null),
                'description' => $this->nullable($data['description'] ?? null),
                'debit' => $data['direction'] === 'debit' ? $data['amount'] : 0,
                'credit' => $data['direction'] === 'credit' ? $data['amount'] : 0,
                'currency_id' => $bankAccount->currency_id,
                'source' => 'manual',
                'reconciliation_status' => 'unreconciled',
            ]);

            app(AuditLogService::class)->record(
                $organization,
                $creator->id,
                'bank-transaction.created',
                BankTransaction::class,
                $bankTransaction->id,
                null,
                $this->auditValues($bankTransaction)
            );

            return $bankTransaction;
        });
    }

    protected function nullable(mixed $value): ?string
    {
        return $value === '' ? null : $value;
    }

    /**
     * @return array<string, mixed>
     */
    protected function auditValues(BankTransaction $bankTransaction): array
    {
        return $bankTransaction->only([
            'bank_account_id', 'transaction_date', 'value_date', 'reference',
            'description', 'debit', 'credit', 'currency_id', 'source',
            'reconciliation_status',
        ]);
    }

    public function rules(): array
    {
        return [
            'bank_account_id' => ['required', 'exists:bank_accounts,id'],
            'transaction_date' => ['required', 'date'],
            'value_date' => ['nullable', 'date'],
            'direction' => ['required', 'in:debit,credit'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
