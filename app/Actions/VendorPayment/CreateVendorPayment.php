<?php

namespace App\Actions\VendorPayment;

use App\Actions\Action;
use App\Enums\VendorPaymentStatus;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayment;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class CreateVendorPayment
{
    use Action;

    public function handle(array $data, Organization $organization, User $creator): VendorPayment
    {
        return DB::transaction(function () use ($data, $organization, $creator) {
            if (! empty($data['idempotency_key'])) {
                $existing = $this->findByIdempotencyKey($organization, $data['idempotency_key']);

                if ($existing) {
                    return $existing;
                }
            }

            $vendor = Vendor::where('id', $data['vendor_id'])
                ->where('organization_id', $organization->id)
                ->first();

            if (! $vendor) {
                throw new \RuntimeException('The vendor must belong to the same organization.');
            }

            if (! $vendor->isActive()) {
                throw new \RuntimeException('The vendor must be active.');
            }

            $currency = Currency::findOrFail($data['currency_id']);
            $exchangeRate = (float) ($data['exchange_rate'] ?? 1);

            if ($currency->id !== $organization->base_currency_id && ! $exchangeRate) {
                throw new \RuntimeException('An exchange rate is required when the payment currency differs from the base currency.');
            }

            $cashAccount = Account::findOrFail($data['cash_account_id']);

            if ($cashAccount->organization_id !== $organization->id) {
                throw new \RuntimeException('The cash account must belong to the same organization.');
            }

            if (! $cashAccount->canReceiveJournalLines()) {
                throw new \RuntimeException('The cash account must be an active posting account.');
            }

            try {
                $payment = VendorPayment::create([
                    'organization_id' => $organization->id,
                    'vendor_id' => $vendor->id,
                    'payment_number' => $this->generatePaymentNumber($organization),
                    'payment_date' => Carbon::parse($data['payment_date'])->toDateString(),
                    'currency_id' => $currency->id,
                    'exchange_rate' => $exchangeRate,
                    'base_currency_id' => $organization->base_currency_id,
                    'amount' => round((float) $data['amount'], 4),
                    'payment_method' => $data['payment_method'],
                    'cash_account_id' => $cashAccount->id,
                    'reference' => $this->nullable($data['reference'] ?? null),
                    'status' => VendorPaymentStatus::Draft,
                    'notes' => $this->nullable($data['notes'] ?? null),
                    'idempotency_key' => $this->nullable($data['idempotency_key'] ?? null),
                    'created_by' => $creator->id,
                ]);
            } catch (QueryException $e) {
                if ($this->isIdempotencyConflict($e, $organization, $data)) {
                    return $this->findByIdempotencyKey($organization, $data['idempotency_key']);
                }

                throw $e;
            }

            app(AuditLogService::class)->record(
                $organization,
                $creator->id,
                'vendor-payment.created',
                VendorPayment::class,
                $payment->id,
                null,
                $payment->only(['payment_number', 'vendor_id', 'amount', 'status'])
            );

            return $payment->fresh();
        });
    }

    protected function generatePaymentNumber(Organization $organization): string
    {
        DB::table('organizations')
            ->where('id', $organization->id)
            ->increment('vendor_payment_number_sequence');

        $sequence = DB::table('organizations')
            ->where('id', $organization->id)
            ->value('vendor_payment_number_sequence');

        return 'VPAY-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    protected function findByIdempotencyKey(Organization $organization, string $key): ?VendorPayment
    {
        return VendorPayment::forOrganization($organization)
            ->where('idempotency_key', $key)
            ->first();
    }

    protected function isIdempotencyConflict(QueryException $e, Organization $organization, array $data): bool
    {
        if (empty($data['idempotency_key']) || $e->getCode() !== '23505') {
            return false;
        }

        return str_contains($e->getMessage(), 'vendor_payments_organization_id_idempotency_key_unique');
    }

    protected function nullable(mixed $value): ?string
    {
        return $value === '' ? null : $value;
    }

    public function rules(): array
    {
        return [
            'vendor_id' => ['required', 'exists:vendors,id'],
            'payment_date' => ['required', 'date'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', 'string', 'max:50'],
            'cash_account_id' => ['required', 'exists:accounts,id'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ];
    }
}
