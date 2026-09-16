<?php

namespace App\Actions\Customer;

use App\Actions\Action;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class CreateCustomer
{
    use Action;

    public function handle(array $data, Organization $organization, User $creator): Customer
    {
        return DB::transaction(function () use ($data, $organization, $creator) {
            $account = Account::findOrFail($data['receivable_account_id']);

            if ($account->organization_id !== $organization->id) {
                throw new \RuntimeException('The receivable account must belong to the same organization.');
            }

            if (! $account->canReceiveJournalLines()) {
                throw new \RuntimeException('The receivable account must be an active posting account.');
            }

            $customer = Customer::create([
                'organization_id' => $organization->id,
                'customer_number' => $this->generateCustomerNumber($organization),
                'name' => $data['name'],
                'email' => $this->nullable($data['email'] ?? null),
                'phone' => $this->nullable($data['phone'] ?? null),
                'tax_number' => $this->nullable($data['tax_number'] ?? null),
                'payment_terms_days' => $data['payment_terms_days'],
                'credit_limit' => $this->nullable($data['credit_limit'] ?? null),
                'receivable_account_id' => $account->id,
                'notes' => $this->nullable($data['notes'] ?? null),
                'status' => $data['status'] ?? 'active',
                'created_by' => $creator->id,
            ]);

            app(AuditLogService::class)->record(
                $organization,
                $creator->id,
                'customer.created',
                Customer::class,
                $customer->id,
                null,
                $customer->only(['customer_number', 'name', 'receivable_account_id', 'status'])
            );

            return $customer;
        });
    }

    protected function generateCustomerNumber(Organization $organization): string
    {
        $sequence = Customer::where('organization_id', $organization->id)->count() + 1;

        return 'CUST-'.str_pad($sequence, 6, '0', STR_PAD_LEFT);
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
