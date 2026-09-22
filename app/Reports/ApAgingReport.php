<?php

namespace App\Reports;

use App\Enums\VendorBillStatus;
use App\Enums\VendorDebitNoteStatus;
use App\Models\VendorBill;
use App\Models\VendorDebitNote;
use Carbon\Carbon;

/**
 * Accounts payable aging report (§67 / §7.26 applied to payables).
 *
 * Buckets the outstanding balance of posted vendor bills by how long each bill
 * has been past its due date as of the report date: Current, 1-30, 31-60, 61-90,
 * and 90+ days. Only posted bills still carrying a balance are included; the
 * report is derived from vendor bills rather than the ledger, so amounts are
 * converted to the organization's base currency using each bill's exchange rate.
 */
final class ApAgingReport extends ReportQuery
{
    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $asOf = $this->asOf();

        $bills = VendorBill::forOrganization($this->organization)
            ->with('vendor')
            ->whereIn('status', [VendorBillStatus::Posted, VendorBillStatus::PartiallyPaid])
            ->where('balance_due', '>', 0)
            ->orderBy('vendor_id')
            ->orderBy('due_date')
            ->get();

        if ($bills->isEmpty()) {
            return $this->emptyResult($asOf);
        }

        $debitedByBill = VendorDebitNote::query()
            ->where('organization_id', $this->organization->id)
            ->whereIn('bill_id', $bills->pluck('id'))
            ->where('status', VendorDebitNoteStatus::Posted)
            ->groupBy('bill_id')
            ->selectRaw('bill_id, SUM(total_amount) as total')
            ->pluck('total', 'bill_id');

        $rows = [];
        $totals = $this->zeroTotals();

        foreach ($bills->groupBy('vendor_id') as $vendorBills) {
            $vendor = $vendorBills->first()->vendor;
            $bucket = $this->zeroTotals();

            foreach ($vendorBills as $bill) {
                $netDue = round((float) $bill->balance_due - (float) ($debitedByBill[$bill->id] ?? 0), 4);

                if ($netDue <= 0.0001) {
                    continue;
                }

                $index = $this->bucketFor($bill->due_date, $asOf);
                $amount = $this->baseBalance($bill, $netDue);

                $bucket[$index] += $amount;
                $totals[$index] += $amount;
            }

            $bucket['total'] = $this->round($bucket['current'] + $bucket['days_1_30'] + $bucket['days_31_60'] + $bucket['days_61_90'] + $bucket['days_90_plus']);

            if ($bucket['total'] <= 0.0001) {
                continue;
            }

            $bucket = array_map(fn (float $value): float => $this->round($value), $bucket);

            $rows[] = [
                'vendor' => [
                    'id' => (int) $vendor->id,
                    'number' => $vendor->vendor_number,
                    'name' => $vendor->name,
                ],
                ...$bucket,
            ];
        }

        $totals['total'] = $this->round($totals['current'] + $totals['days_1_30'] + $totals['days_31_60'] + $totals['days_61_90'] + $totals['days_90_plus']);
        $totals = array_map(fn (float $value): float => $this->round($value), $totals);

        return [
            'rows' => $rows,
            'totals' => $totals,
            'as_of' => $asOf->toDateString(),
        ];
    }

    protected function asOf(): Carbon
    {
        return $this->dateFilter('as_of') ?? now();
    }

    /**
     * Outstanding balance converted to the organization's base currency.
     */
    protected function baseBalance(VendorBill $bill, float $balanceDue): float
    {
        return $balanceDue * (float) $bill->exchange_rate;
    }

    /**
     * Bucket key for a bill given its due date and the report date.
     */
    protected function bucketFor(Carbon $dueDate, Carbon $asOf): string
    {
        $daysOverdue = (int) round(($asOf->startOfDay()->getTimestamp() - $dueDate->startOfDay()->getTimestamp()) / 86400);

        if ($daysOverdue <= 0) {
            return 'current';
        }

        if ($daysOverdue <= 30) {
            return 'days_1_30';
        }

        if ($daysOverdue <= 60) {
            return 'days_31_60';
        }

        if ($daysOverdue <= 90) {
            return 'days_61_90';
        }

        return 'days_90_plus';
    }

    /**
     * @return array{current: float, days_1_30: float, days_31_60: float, days_61_90: float, days_90_plus: float, total: float}
     */
    protected function zeroTotals(): array
    {
        return [
            'current' => 0.0,
            'days_1_30' => 0.0,
            'days_31_60' => 0.0,
            'days_61_90' => 0.0,
            'days_90_plus' => 0.0,
            'total' => 0.0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyResult(Carbon $asOf): array
    {
        return [
            'rows' => [],
            'totals' => $this->zeroTotals(),
            'as_of' => $asOf->toDateString(),
        ];
    }
}
