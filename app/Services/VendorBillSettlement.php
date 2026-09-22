<?php

namespace App\Services;

use App\Enums\VendorBillStatus;
use App\Enums\VendorPaymentStatus;
use App\Models\User;
use App\Models\VendorBill;
use App\Models\VendorPayment;
use App\Models\VendorPaymentAllocation;

/**
 * Derives the paid amount, balance due and status of every bill a payment is
 * allocated against. Only allocations on posted payments count (SRS 7.29/7.30),
 * so voiding a payment automatically restores the affected bills by re-running
 * this reconciliation.
 */
class VendorBillSettlement
{
    public function reconcileForPayment(VendorPayment $payment, User $actor): void
    {
        $billIds = VendorPaymentAllocation::forOrganization($payment->organization)
            ->where('payment_id', $payment->id)
            ->pluck('bill_id');

        if ($billIds->isEmpty()) {
            return;
        }

        $bills = VendorBill::forOrganization($payment->organization)
            ->whereIn('id', $billIds)
            ->lockForUpdate()
            ->orderBy('id')
            ->get();

        foreach ($bills as $bill) {
            $allocated = round((float) VendorPaymentAllocation::forOrganization($payment->organization)
                ->where('bill_id', $bill->id)
                ->whereHas('payment', fn ($query) => $query->where('status', VendorPaymentStatus::Posted))
                ->sum('amount'), 4);

            $paid = min((float) $bill->total_amount, $allocated);
            $balanceDue = round((float) $bill->total_amount - $paid, 4);

            $status = $balanceDue <= 0.0001
                ? VendorBillStatus::Paid
                : ($paid > 0 ? VendorBillStatus::PartiallyPaid : VendorBillStatus::Posted);

            $previous = [
                'status' => $bill->status->value,
                'paid_amount' => (float) $bill->paid_amount,
                'balance_due' => (float) $bill->balance_due,
            ];

            $bill->update([
                'paid_amount' => round($paid, 4),
                'balance_due' => $balanceDue,
                'status' => $status,
                'updated_by' => $actor->id,
            ]);

            app(AuditLogService::class)->record(
                $payment->organization,
                $actor->id,
                'vendor-bill.allocated',
                VendorBill::class,
                $bill->id,
                $previous,
                ['status' => $status->value, 'paid_amount' => $paid, 'balance_due' => $balanceDue]
            );
        }
    }
}
