<?php

namespace App\Actions\BankAccount;

use App\Actions\Action;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\BankAccountNumberService;
use Illuminate\Support\Facades\DB;

class CreateBankAccount
{
    use Action;

    public function handle(array $data, Organization $organization, User $creator): BankAccount
    {
        return DB::transaction(function () use ($data, $organization, $creator) {
            $account = Account::findOrFail($data['account_id']);

            if ($account->organization_id !== $organization->id) {
                throw new \RuntimeException('The ledger account must belong to the same organization.');
            }

            if (! $account->canReceiveJournalLines()) {
                throw new \RuntimeException('The ledger account must be an active posting account.');
            }

            $protected = app(BankAccountNumberService::class)->protect($data['account_number'] ?? null);

            $bankAccount = BankAccount::create([
                'organization_id' => $organization->id,
                'account_id' => $account->id,
                'currency_id' => $data['currency_id'],
                'bank_name' => $data['bank_name'],
                'account_name' => $data['account_name'],
                'iban' => $this->nullable($data['iban'] ?? null),
                'routing_number' => $this->nullable($data['routing_number'] ?? null),
                'masked_account_number' => $protected['masked_account_number'],
                'encrypted_account_number' => $protected['encrypted_account_number'],
                'last4' => $protected['last4'],
                'opening_balance' => $data['opening_balance'],
                'status' => $data['status'],
                'created_by' => $creator->id,
            ]);

            app(AuditLogService::class)->record(
                $organization,
                $creator->id,
                'bank-account.created',
                BankAccount::class,
                $bankAccount->id,
                null,
                $this->auditValues($bankAccount)
            );

            return $bankAccount;
        });
    }

    protected function nullable(mixed $value): ?string
    {
        return $value === '' ? null : $value;
    }

    /**
     * Audit-safe values: the full account number is never written to the
     * audit log (V1.1 §0.28).
     *
     * @return array<string, mixed>
     */
    protected function auditValues(BankAccount $bankAccount): array
    {
        return $bankAccount->only([
            'bank_name', 'account_name', 'masked_account_number', 'last4',
            'account_id', 'currency_id', 'opening_balance', 'status',
        ]);
    }

    public function rules(): array
    {
        return [
            'bank_name' => ['required', 'string', 'max:200'],
            'account_name' => ['required', 'string', 'max:200'],
            'account_number' => ['nullable', 'string', 'max:255'],
            'iban' => ['nullable', 'string', 'max:100'],
            'routing_number' => ['nullable', 'string', 'max:100'],
            'account_id' => ['required', 'exists:accounts,id'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'opening_balance' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:active,inactive,closed'],
        ];
    }
}
