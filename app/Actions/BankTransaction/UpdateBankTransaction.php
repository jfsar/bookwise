<?php

namespace App\Actions\BankTransaction;

use App\Actions\Action;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class UpdateBankTransaction
{
    use Action;

    public function handle(BankTransaction $bankTransaction, array $data, User $actor): BankTransaction
    {
        return DB::transaction(function () use ($bankTransaction, $data, $actor) {
            // Re-read under a row lock so the editability check and the audit
            // diff both see one committed version; without it a reconciliation
            // finalizing the row concurrently would be silently overwritten.
            $locked = BankTransaction::whereKey($bankTransaction->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isEditable()) {
                throw new \RuntimeException('Only unreconciled manual bank transactions can be edited.');
            }

            $bankAccount = BankAccount::findOrFail($data['bank_account_id']);

            if ($bankAccount->organization_id !== $locked->organization_id) {
                throw new \RuntimeException('The bank account must belong to the same organization.');
            }

            if (! $bankAccount->isActive()) {
                throw new \RuntimeException('The bank account must be active.');
            }

            $oldValues = $this->auditValues($locked);

            $locked->update([
                'bank_account_id' => $bankAccount->id,
                'transaction_date' => $data['transaction_date'],
                'value_date' => $this->nullable($data['value_date'] ?? null),
                'reference' => $this->nullable($data['reference'] ?? null),
                'description' => $this->nullable($data['description'] ?? null),
                'debit' => $data['direction'] === 'debit' ? $data['amount'] : 0,
                'credit' => $data['direction'] === 'credit' ? $data['amount'] : 0,
                'currency_id' => $bankAccount->currency_id,
            ]);

            app(AuditLogService::class)->record(
                $locked->organization,
                $actor->id,
                'bank-transaction.updated',
                BankTransaction::class,
                $locked->id,
                $oldValues,
                $this->auditValues($locked->fresh())
            );

            return $locked->fresh();
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
