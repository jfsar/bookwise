<?php

namespace App\Actions\Vendor;

use App\Actions\Action;
use App\Models\Account;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class UpdateVendor
{
    use Action;

    public function handle(Vendor $vendor, array $data, User $actor): Vendor
    {
        return DB::transaction(function () use ($vendor, $data, $actor) {
            $account = Account::findOrFail($data['payable_account_id']);

            if ($account->organization_id !== $vendor->organization_id) {
                throw new \RuntimeException('The payable account must belong to the same organization.');
            }

            if (! $account->canReceiveJournalLines()) {
                throw new \RuntimeException('The payable account must be an active posting account.');
            }

            $oldValues = $vendor->only([
                'name', 'email', 'phone', 'tax_number', 'payment_terms_days',
                'payable_account_id', 'notes', 'status',
            ]);

            $vendor->update([
                'name' => $data['name'],
                'email' => $this->nullable($data['email'] ?? null),
                'phone' => $this->nullable($data['phone'] ?? null),
                'tax_number' => $this->nullable($data['tax_number'] ?? null),
                'payment_terms_days' => $data['payment_terms_days'],
                'payable_account_id' => $account->id,
                'notes' => $this->nullable($data['notes'] ?? null),
                'status' => $data['status'],
                'updated_by' => $actor->id,
            ]);

            app(AuditLogService::class)->record(
                $vendor->organization,
                $actor->id,
                'vendor.updated',
                Vendor::class,
                $vendor->id,
                $oldValues,
                $vendor->only([
                    'name', 'email', 'phone', 'tax_number', 'payment_terms_days',
                    'payable_account_id', 'notes', 'status',
                ])
            );

            return $vendor->fresh();
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
            'payable_account_id' => ['required', 'exists:accounts,id'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive,archived'],
        ];
    }
}
