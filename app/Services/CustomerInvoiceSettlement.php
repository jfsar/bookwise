<?php

namespace App\Services;

use App\Enums\CustomerInvoiceStatus;
use App\Enums\CustomerPaymentStatus;
use App\Models\CustomerInvoice;
use App\Models\CustomerPayment;
use App\Models\CustomerPaymentAllocation;
use App\Models\User;

/**
 * Derives the paid amount, balance due and status of every invoice a payment
 * is allocated against. Only allocations on posted payments count (SRS
 * INV-010/INV-012), so voiding a payment automatically restores the affected
 * invoices by re-running this reconciliation.
 */
class CustomerInvoiceSettlement
{
    public function reconcileForPayment(CustomerPayment $payment, User $actor): void
    {
        $invoiceIds = CustomerPaymentAllocation::forOrganization($payment->organization)
            ->where('payment_id', $payment->id)
            ->pluck('invoice_id');

        if ($invoiceIds->isEmpty()) {
            return;
        }

        $invoices = CustomerInvoice::forOrganization($payment->organization)
            ->whereIn('id', $invoiceIds)
            ->get();

        foreach ($invoices as $invoice) {
            $allocated = round((float) CustomerPaymentAllocation::forOrganization($payment->organization)
                ->where('invoice_id', $invoice->id)
                ->whereHas('payment', fn ($query) => $query->where('status', CustomerPaymentStatus::Posted))
                ->sum('amount'), 4);

            $paid = min((float) $invoice->total_amount, $allocated);
            $balanceDue = round((float) $invoice->total_amount - $paid, 4);

            $status = $balanceDue <= 0.0001
                ? CustomerInvoiceStatus::Paid
                : ($paid > 0 ? CustomerInvoiceStatus::PartiallyPaid : CustomerInvoiceStatus::Posted);

            $previous = [
                'status' => $invoice->status->value,
                'paid_amount' => (float) $invoice->paid_amount,
                'balance_due' => (float) $invoice->balance_due,
            ];

            $invoice->update([
                'paid_amount' => round($paid, 4),
                'balance_due' => $balanceDue,
                'status' => $status,
                'updated_by' => $actor->id,
            ]);

            app(AuditLogService::class)->record(
                $payment->organization,
                $actor->id,
                'customer-invoice.allocated',
                CustomerInvoice::class,
                $invoice->id,
                $previous,
                ['status' => $status->value, 'paid_amount' => $paid, 'balance_due' => $balanceDue]
            );
        }
    }
}
