<?php

namespace App\Actions\Customer;

use App\Actions\Action;
use App\Models\Account;
use App\Models\Customer;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class UpdateCustomer
{
    use Action;

    public function handle(Customer $customer, array $data, User $actor): Customer
    {
        return DB::transaction(function () use ($customer, $data, $actor) {
            $account = Account::findOrFail($data['receivable_account_id']);

            if ($account->organization_id !== $customer->organization_id) {
                throw new \RuntimeException('The receivable account must belong to the same organization.');
            }

            if (! $account->canReceiveJournalLines()) {
                throw new \RuntimeException('The receivable account must be an active posting account.');
            }

            $oldValues = $customer->only([
                'name', 'email', 'phone', 'tax_number', 'payment_terms_days',
                'credit_limit', 'receivable_account_id', 'notes', 'status',
            ]);

            $customer->update([
                'name' => $data['name'],
                'email' => $this->nullable($data['email'] ?? null),
                'phone' => $this->nullable($data['phone'] ?? null),
                'tax_number' => $this->nullable($data['tax_number'] ?? null),
                'payment_terms_days' => $data['payment_terms_days'],
                'credit_limit' => $this->nullable($data['credit_limit'] ?? null),
                'receivable_account_id' => $account->id,
                'notes' => $this->nullable($data['notes'] ?? null),
                'status' => $data['status'],
                'updated_by' => $actor->id,
            ]);

            app(AuditLogService::class)->record(
                $customer->organization,
                $actor->id,
                'customer.updated',
                Customer::class,
                $customer->id,
                $oldValues,
                $customer->only([
                    'name', 'email', 'phone', 'tax_number', 'payment_terms_days',
                    'credit_limit', 'receivable_account_id', 'notes', 'status',
                ])
            );

            return $customer->fresh();
        });
    }

    protected function nullable(mixed $value): ?string
    {
        return $value === '' ? null : $value;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'payment_terms_days' => ['required', 'integer', 'min:0'],
            'credit_limit' => ['nullable', 'numeric', 'gte:0'],
            'receivable_account_id' => ['required', 'exists:accounts,id'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive,archived'],
        ];
    }
}
