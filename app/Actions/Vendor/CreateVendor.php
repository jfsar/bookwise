<?php

namespace App\Actions\Vendor;

use App\Actions\Action;
use App\Models\Account;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class CreateVendor
{
    use Action;

    public function handle(array $data, Organization $organization, User $creator): Vendor
    {
        return DB::transaction(function () use ($data, $organization, $creator) {
            $account = Account::findOrFail($data['payable_account_id']);

            if ($account->organization_id !== $organization->id) {
                throw new \RuntimeException('The payable account must belong to the same organization.');
            }

            if (! $account->canReceiveJournalLines()) {
                throw new \RuntimeException('The payable account must be an active posting account.');
            }

            $vendor = Vendor::create([
                'organization_id' => $organization->id,
                'vendor_number' => $this->generateVendorNumber($organization),
                'name' => $data['name'],
                'email' => $this->nullable($data['email'] ?? null),
                'phone' => $this->nullable($data['phone'] ?? null),
                'tax_number' => $this->nullable($data['tax_number'] ?? null),
                'payment_terms_days' => $data['payment_terms_days'],
                'payable_account_id' => $account->id,
                'notes' => $this->nullable($data['notes'] ?? null),
                'status' => $data['status'] ?? 'active',
                'created_by' => $creator->id,
            ]);

            app(AuditLogService::class)->record(
                $organization,
                $creator->id,
                'vendor.created',
                Vendor::class,
                $vendor->id,
                null,
                $vendor->only(['vendor_number', 'name', 'payable_account_id', 'status'])
            );

            return $vendor;
        });
    }

    protected function generateVendorNumber(Organization $organization): string
    {
        DB::table('organizations')
            ->where('id', $organization->id)
            ->increment('vendor_number_sequence');

        $sequence = DB::table('organizations')
            ->where('id', $organization->id)
            ->value('vendor_number_sequence');

        return 'VEND-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
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
