<?php

namespace App\Actions\CustomerInvoice;

use App\Actions\Action;
use App\Enums\CustomerInvoiceStatus;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\CustomerInvoiceLine;
use App\Models\Organization;
use App\Models\TaxCode;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class CreateCustomerInvoice
{
    use Action;

    public function handle(array $data, Organization $organization, User $creator): CustomerInvoice
    {
        return DB::transaction(function () use ($data, $organization, $creator) {
            if (! empty($data['idempotency_key'])) {
                $existing = $this->findByIdempotencyKey($organization, $data['idempotency_key']);

                if ($existing) {
                    return $existing;
                }
            }

            $customer = Customer::where('id', $data['customer_id'])
                ->where('organization_id', $organization->id)
                ->first();

            if (! $customer) {
                throw new \RuntimeException('The customer must belong to the same organization.');
            }

            if (! $customer->isActive()) {
                throw new \RuntimeException('The customer must be active.');
            }

            $currency = Currency::findOrFail($data['currency_id']);
            $exchangeRate = (float) ($data['exchange_rate'] ?? 1);

            if ($currency->id !== $organization->base_currency_id && ! $exchangeRate) {
                throw new \RuntimeException('An exchange rate is required when the invoice currency differs from the base currency.');
            }

            $invoiceDate = Carbon::parse($data['invoice_date']);
            $subtotal = 0.0;
            $discountAmount = 0.0;
            $taxAmount = 0.0;

            $lines = collect($data['lines'])
                ->map(function (array $lineData) use ($organization, $invoiceDate, &$subtotal, &$discountAmount, &$taxAmount, $currency): array {
                    $account = Account::findOrFail($lineData['revenue_account_id']);

                    if ($account->organization_id !== $organization->id) {
                        throw new \RuntimeException('The revenue account must belong to the same organization.');
                    }

                    if (! $account->canReceiveJournalLines()) {
                        throw new \RuntimeException('The revenue account must be an active posting account.');
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
                            ->where('effective_from', '<=', $invoiceDate)
                            ->where(function ($query) use ($invoiceDate) {
                                $query->whereNull('effective_to')->orWhere('effective_to', '>=', $invoiceDate);
                            })
                            ->orderByDesc('effective_from')
                            ->first();

                        if (! $taxRate) {
                            throw new \RuntimeException("No effective tax rate found for {$taxCode->code} on {$invoiceDate->toDateString()}.");
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
                        'revenue_account_id' => $account->id,
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
                $invoice = CustomerInvoice::create([
                    'organization_id' => $organization->id,
                    'customer_id' => $customer->id,
                    'invoice_number' => $this->generateInvoiceNumber($organization),
                    'invoice_date' => $invoiceDate->toDateString(),
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
                    'status' => CustomerInvoiceStatus::Draft,
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
                CustomerInvoiceLine::create([...$line, 'invoice_id' => $invoice->id, 'line_number' => $index + 1]);
            }

            app(AuditLogService::class)->record(
                $organization,
                $creator->id,
                'customer-invoice.created',
                CustomerInvoice::class,
                $invoice->id,
                null,
                $invoice->only(['invoice_number', 'customer_id', 'total_amount', 'status'])
            );

            return $invoice->fresh()->load('lines');
        });
    }

    protected function generateInvoiceNumber(Organization $organization): string
    {
        DB::table('organizations')
            ->where('id', $organization->id)
            ->increment('customer_invoice_number_sequence');

        $sequence = DB::table('organizations')
            ->where('id', $organization->id)
            ->value('customer_invoice_number_sequence');

        return 'INV-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    protected function findByIdempotencyKey(Organization $organization, string $key): ?CustomerInvoice
    {
        return CustomerInvoice::forOrganization($organization)
            ->where('idempotency_key', $key)
            ->with('lines')
            ->first();
    }

    protected function isIdempotencyConflict(QueryException $e, Organization $organization, array $data): bool
    {
        if (empty($data['idempotency_key']) || $e->getCode() !== '23505') {
            return false;
        }

        return str_contains($e->getMessage(), 'customer_invoices_organization_id_idempotency_key_unique');
    }

    protected function nullable(mixed $value): ?string
    {
        return $value === '' ? null : $value;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:invoice_date'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'gt:0'],
            'lines.*.discount_amount' => ['nullable', 'numeric', 'gte:0'],
            'lines.*.revenue_account_id' => ['required', 'exists:accounts,id'],
            'lines.*.tax_code_id' => ['nullable', 'exists:tax_codes,id'],
        ];
    }
}
