<?php

namespace App\Actions\CustomerPayment;

use App\Actions\Action;
use App\Enums\CustomerInvoiceStatus;
use App\Enums\CustomerPaymentStatus;
use App\Models\CustomerInvoice;
use App\Models\CustomerPayment;
use App\Models\CustomerPaymentAllocation;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\OperationalMetrics;
use Illuminate\Support\Facades\DB;

class AllocateCustomerPayment
{
    use Action;

    /**
     * Allocate a draft payment against one or more posted invoices, holding the
     * payment and invoice row locks in deterministic order (SRS 0.9).
     *
     * @param  array<int, array{invoice_id: int, amount: float}>  $allocations
     */
    public function handle(CustomerPayment $payment, array $allocations, User $allocator): CustomerPayment
    {
        if (empty($allocations)) {
            throw new \RuntimeException('At least one allocation is required.');
        }

        return DB::transaction(function () use ($payment, $allocations, $allocator) {
            $payment = CustomerPayment::where('id', $payment->id)->lockForUpdate()->firstOrFail();

            if (! $payment->isDraft()) {
                throw new \RuntimeException('Only draft payments can be allocated.');
            }

            $organization = $payment->organization;
            $merged = $this->mergeByInvoice($allocations);
            $requested = round(array_sum($merged), 4);

            if ($requested > $payment->remainingAllocatable()) {
                app(OperationalMetrics::class)->increment('payment_allocation_conflict_total', ['resource' => 'payment']);
                throw new \RuntimeException("Payment {$payment->payment_number} cannot be allocated beyond its available amount.");
            }

            $invoiceIds = array_keys($merged);
            sort($invoiceIds);

            $invoices = CustomerInvoice::query()
                ->whereIn('id', $invoiceIds)
                ->lockForUpdate()
                ->orderBy('id')
                ->get()
                ->keyBy('id');

            $pendingAllocatedByInvoice = CustomerPaymentAllocation::query()
                ->join('customer_payments', 'customer_payments.id', '=', 'customer_payment_allocations.payment_id')
                ->whereIn('customer_payment_allocations.invoice_id', $invoiceIds)
                ->where('customer_payments.status', CustomerPaymentStatus::Draft)
                ->groupBy('customer_payment_allocations.invoice_id')
                ->selectRaw('customer_payment_allocations.invoice_id, SUM(customer_payment_allocations.amount) as total')
                ->pluck('total', 'invoice_id');

            foreach ($merged as $invoiceId => $amount) {
                $invoice = $invoices->get($invoiceId);

                if (! $invoice || $invoice->organization_id !== $organization->id || $invoice->customer_id !== $payment->customer_id) {
                    throw new \RuntimeException('The invoice must belong to the same organization and customer.');
                }

                if (! in_array($invoice->status, [CustomerInvoiceStatus::Posted, CustomerInvoiceStatus::PartiallyPaid], true)) {
                    throw new \RuntimeException('Only posted invoices can be allocated.');
                }

                if ($invoice->currency_id !== $payment->currency_id) {
                    throw new \RuntimeException('The payment and the invoice must use the same currency.');
                }

                $available = round((float) $invoice->balance_due - (float) ($pendingAllocatedByInvoice[$invoiceId] ?? 0), 4);

                if ($amount > $available) {
                    app(OperationalMetrics::class)->increment('payment_allocation_conflict_total', ['resource' => 'invoice']);
                    throw new \RuntimeException("Invoice {$invoice->invoice_number} cannot be allocated beyond its outstanding balance.");
                }
            }

            $before = $payment->totalAllocated();

            foreach ($merged as $invoiceId => $amount) {
                CustomerPaymentAllocation::create([
                    'organization_id' => $organization->id,
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoiceId,
                    'amount' => $amount,
                    'allocated_at' => now(),
                    'created_by' => $allocator->id,
                ]);
            }

            app(AuditLogService::class)->record(
                $organization,
                $allocator->id,
                'customer-payment.allocated',
                CustomerPayment::class,
                $payment->id,
                ['allocated' => $before],
                ['allocated' => round($before + $requested, 4)]
            );

            return $payment->fresh()->load('allocations.invoice');
        });
    }

    /**
     * Merge repeated invoice rows and reject non-positive amounts before locking.
     *
     * @param  array<int, array{invoice_id: int, amount: float}>  $allocations
     * @return array<int, float>
     */
    protected function mergeByInvoice(array $allocations): array
    {
        $merged = [];

        foreach ($allocations as $allocation) {
            $invoiceId = (int) $allocation['invoice_id'];
            $amount = round((float) $allocation['amount'], 4);

            if ($amount <= 0) {
                throw new \RuntimeException('Allocation amounts must be greater than zero.');
            }

            $merged[$invoiceId] = round(($merged[$invoiceId] ?? 0) + $amount, 4);
        }

        return $merged;
    }

    public function rules(): array
    {
        return [
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.invoice_id' => ['required', 'integer', 'exists:customer_invoices,id'],
            'allocations.*.amount' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
