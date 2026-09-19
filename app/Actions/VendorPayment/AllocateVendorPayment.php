<?php

namespace App\Actions\VendorPayment;

use App\Actions\Action;
use App\Enums\VendorBillStatus;
use App\Enums\VendorPaymentStatus;
use App\Models\User;
use App\Models\VendorBill;
use App\Models\VendorPayment;
use App\Models\VendorPaymentAllocation;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class AllocateVendorPayment
{
    use Action;

    /**
     * Allocate a draft payment against one or more posted bills, holding the
     * payment and bill row locks in deterministic order (SRS 0.9).
     *
     * @param  array<int, array{bill_id: int, amount: float}>  $allocations
     */
    public function handle(VendorPayment $payment, array $allocations, User $allocator): VendorPayment
    {
        if (empty($allocations)) {
            throw new \RuntimeException('At least one allocation is required.');
        }

        return DB::transaction(function () use ($payment, $allocations, $allocator) {
            $payment = VendorPayment::where('id', $payment->id)->lockForUpdate()->firstOrFail();

            if (! $payment->isDraft()) {
                throw new \RuntimeException('Only draft payments can be allocated.');
            }

            $organization = $payment->organization;
            $merged = $this->mergeByBill($allocations);
            $requested = round(array_sum($merged), 4);

            if ($requested > $payment->remainingAllocatable()) {
                throw new \RuntimeException("Payment {$payment->payment_number} cannot be allocated beyond its available amount.");
            }

            $billIds = array_keys($merged);
            sort($billIds);

            $bills = VendorBill::query()
                ->whereIn('id', $billIds)
                ->lockForUpdate()
                ->orderBy('id')
                ->get()
                ->keyBy('id');

            $pendingAllocatedByBill = VendorPaymentAllocation::query()
                ->join('vendor_payments', 'vendor_payments.id', '=', 'vendor_payment_allocations.payment_id')
                ->whereIn('vendor_payment_allocations.bill_id', $billIds)
                ->where('vendor_payments.status', VendorPaymentStatus::Draft)
                ->groupBy('vendor_payment_allocations.bill_id')
                ->selectRaw('vendor_payment_allocations.bill_id, SUM(vendor_payment_allocations.amount) as total')
                ->pluck('total', 'bill_id');

            foreach ($merged as $billId => $amount) {
                $bill = $bills->get($billId);

                if (! $bill || $bill->organization_id !== $organization->id || $bill->vendor_id !== $payment->vendor_id) {
                    throw new \RuntimeException('The bill must belong to the same organization and vendor.');
                }

                if (! in_array($bill->status, [VendorBillStatus::Posted, VendorBillStatus::PartiallyPaid], true)) {
                    throw new \RuntimeException('Only posted bills can be allocated.');
                }

                if ($bill->currency_id !== $payment->currency_id) {
                    throw new \RuntimeException('The payment and the bill must use the same currency.');
                }

                $available = round((float) $bill->balance_due - (float) ($pendingAllocatedByBill[$billId] ?? 0), 4);

                if ($amount > $available) {
                    throw new \RuntimeException("Bill {$bill->bill_number} cannot be allocated beyond its outstanding balance.");
                }
            }

            $before = $payment->totalAllocated();

            foreach ($merged as $billId => $amount) {
                VendorPaymentAllocation::create([
                    'organization_id' => $organization->id,
                    'payment_id' => $payment->id,
                    'bill_id' => $billId,
                    'amount' => $amount,
                    'allocated_at' => now(),
                    'created_by' => $allocator->id,
                ]);
            }

            app(AuditLogService::class)->record(
                $organization,
                $allocator->id,
                'vendor-payment.allocated',
                VendorPayment::class,
                $payment->id,
                ['allocated' => $before],
                ['allocated' => round($before + $requested, 4)]
            );

            return $payment->fresh()->load('allocations.bill');
        });
    }

    /**
     * Merge repeated bill rows and reject non-positive amounts before locking.
     *
     * @param  array<int, array{bill_id: int, amount: float}>  $allocations
     * @return array<int, float>
     */
    protected function mergeByBill(array $allocations): array
    {
        $merged = [];

        foreach ($allocations as $allocation) {
            $billId = (int) $allocation['bill_id'];
            $amount = round((float) $allocation['amount'], 4);

            if ($amount <= 0) {
                throw new \RuntimeException('Allocation amounts must be greater than zero.');
            }

            $merged[$billId] = round(($merged[$billId] ?? 0) + $amount, 4);
        }

        return $merged;
    }

    public function rules(): array
    {
        return [
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.bill_id' => ['required', 'integer', 'exists:vendor_bills,id'],
            'allocations.*.amount' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
