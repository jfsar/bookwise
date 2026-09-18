<?php

namespace App\Actions\VendorBill;

use App\Actions\Action;
use App\Enums\VendorBillStatus;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Organization;
use App\Models\TaxCode;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Models\VendorBillLine;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class CreateVendorBill
{
    use Action;

    public function handle(array $data, Organization $organization, User $creator): VendorBill
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
                throw new \RuntimeException('An exchange rate is required when the bill currency differs from the base currency.');
            }

            $billDate = Carbon::parse($data['bill_date']);
            $subtotal = 0.0;
            $discountAmount = 0.0;
            $taxAmount = 0.0;

            $lines = collect($data['lines'])
                ->map(function (array $lineData) use ($organization, $billDate, &$subtotal, &$discountAmount, &$taxAmount, $currency): array {
                    $account = Account::findOrFail($lineData['expense_account_id']);

                    if ($account->organization_id !== $organization->id) {
                        throw new \RuntimeException('The expense account must belong to the same organization.');
                    }

                    if (! $account->canReceiveJournalLines()) {
                        throw new \RuntimeException('The expense account must be an active posting account.');
                    }

                    $appliedRate = null;
                    if (! empty($lineData['tax_code_id'])) {
                        $taxCode = TaxCode::where('id', $lineData['tax_code_id'])
                            ->where('organization_id', $organization->id)
                            ->first();

                        if (! $taxCode) {
                            throw new \RuntimeException('The tax code must belong to the same organization.');
                        }

                        $taxRate = TaxRate::where('tax_code_id', $taxCode->id)
                            ->where('organization_id', $organization->id)
                            ->where('effective_from', '<=', $billDate)
                            ->where(function ($query) use ($billDate) {
                                $query->whereNull('effective_to')->orWhere('effective_to', '>=', $billDate);
                            })
                            ->orderByDesc('effective_from')
                            ->first();

                        if (! $taxRate) {
                            throw new \RuntimeException("No effective tax rate found for {$taxCode->code} on {$billDate->toDateString()}.");
                        }

                        $appliedRate = (float) $taxRate->rate;
                    }

                    $quantity = (float) $lineData['quantity'];
                    $unitPrice = (float) $lineData['unit_price'];
                    $discount = (float) ($lineData['discount_amount'] ?? 0);

                    $extended = round($quantity * $unitPrice, 4);
                    $net = round($extended - $discount, 4);
                    $lineTax = $appliedRate !== null ? round($net * $appliedRate / 100, 4) : 0.0;
                    $lineTotal = round($net + $lineTax, 4);

                    $subtotal += $extended;
                    $discountAmount += $discount;
                    $taxAmount += $lineTax;

                    return [
                        'organization_id' => $organization->id,
                        'description' => $lineData['description'],
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'discount_amount' => $discount,
                        'tax_amount' => $lineTax,
                        'line_total' => $lineTotal,
                        'expense_account_id' => $account->id,
                        'tax_code_id' => $lineData['tax_code_id'] ?? null,
                        'currency_id' => $currency->id,
                        'applied_tax_rate' => $appliedRate,
                    ];
                })
                ->all();

            $subtotal = round($subtotal, 4);
            $discountAmount = round($discountAmount, 4);
            $taxAmount = round($taxAmount, 4);
            $totalAmount = round($subtotal - $discountAmount + $taxAmount, 4);

            try {
                $bill = VendorBill::create([
                    'organization_id' => $organization->id,
                    'vendor_id' => $vendor->id,
                    'bill_number' => $this->generateBillNumber($organization),
                    'vendor_reference' => $this->nullable($data['vendor_reference'] ?? null),
                    'bill_date' => $billDate->toDateString(),
                    'due_date' => $data['due_date'],
                    'currency_id' => $currency->id,
                    'exchange_rate' => $exchangeRate,
                    'base_currency_id' => $organization->base_currency_id,
                    'subtotal' => $subtotal,
                    'discount_amount' => $discountAmount,
                    'tax_amount' => $taxAmount,
                    'total_amount' => $totalAmount,
                    'paid_amount' => 0,
                    'balance_due' => $totalAmount,
                    'status' => VendorBillStatus::Draft,
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

            foreach ($lines as $index => $line) {
                VendorBillLine::create([...$line, 'bill_id' => $bill->id, 'line_number' => $index + 1]);
            }

            app(AuditLogService::class)->record(
                $organization,
                $creator->id,
                'vendor-bill.created',
                VendorBill::class,
                $bill->id,
                null,
                $bill->only(['bill_number', 'vendor_id', 'total_amount', 'status'])
            );

            return $bill->fresh()->load('lines', 'vendor');
        });
    }

    protected function generateBillNumber(Organization $organization): string
    {
        DB::table('organizations')
            ->where('id', $organization->id)
            ->increment('vendor_bill_number_sequence');

        $sequence = DB::table('organizations')
            ->where('id', $organization->id)
            ->value('vendor_bill_number_sequence');

        return 'BILL-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    protected function findByIdempotencyKey(Organization $organization, string $key): ?VendorBill
    {
        return VendorBill::forOrganization($organization)
            ->where('idempotency_key', $key)
            ->with('lines', 'vendor')
            ->first();
    }

    protected function isIdempotencyConflict(QueryException $e, Organization $organization, array $data): bool
    {
        if (empty($data['idempotency_key']) || $e->getCode() !== '23505') {
            return false;
        }

        return str_contains($e->getMessage(), 'vendor_bills_organization_id_idempotency_key_unique');
    }

    protected function nullable(mixed $value): ?string
    {
        return $value === '' ? null : $value;
    }

    public function rules(): array
    {
        return [
            'vendor_id' => ['required', 'exists:vendors,id'],
            'vendor_reference' => ['nullable', 'string', 'max:100'],
            'bill_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:bill_date'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'gt:0'],
            'lines.*.discount_amount' => ['nullable', 'numeric', 'gte:0'],
            'lines.*.expense_account_id' => ['required', 'exists:accounts,id'],
            'lines.*.tax_code_id' => ['nullable', 'exists:tax_codes,id'],
        ];
    }
}
