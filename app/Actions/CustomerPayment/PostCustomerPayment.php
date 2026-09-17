<?php

namespace App\Actions\CustomerPayment;

use App\Actions\Action;
use App\Actions\JournalEntry\PostJournalEntry;
use App\Enums\CustomerInvoiceStatus;
use App\Enums\CustomerPaymentStatus;
use App\Enums\JournalEntryStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\CustomerInvoice;
use App\Models\CustomerPayment;
use App\Models\CustomerPaymentAllocation;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PostCustomerPayment
{
    use Action;

    public function handle(CustomerPayment $payment, User $poster): CustomerPayment
    {
        return DB::transaction(function () use ($payment, $poster) {
            $payment = CustomerPayment::where('id', $payment->id)->lockForUpdate()->first();

            if (! $payment->status->isPostable()) {
                throw new \RuntimeException('Only draft payments can be posted.');
            }

            if ($payment->created_by === $poster->id) {
                throw new \RuntimeException('Maker-checker: The final poster cannot be the same person who created the payment.');
            }

            $cashAccount = $payment->cashAccount;

            if (! $cashAccount) {
                throw new \RuntimeException('A cash or bank account is required before the payment can be posted.');
            }

            $organization = $payment->organization;
            $journal = $this->resolveJournal($organization);
            [$fiscalYear, $period] = $this->resolvePeriod($organization, $payment->payment_date);

            $entryNumber = $this->generateEntryNumber($organization, $journal, $payment->payment_date);
            $exchangeRate = (float) $payment->exchange_rate;
            $amount = (float) $payment->amount;

            $entry = JournalEntry::create([
                'organization_id' => $organization->id,
                'journal_id' => $journal->id,
                'fiscal_year_id' => $fiscalYear->id,
                'accounting_period_id' => $period->id,
                'entry_number' => $entryNumber,
                'entry_date' => $payment->payment_date,
                'description' => "Customer Payment {$payment->payment_number}",
                'reference' => $payment->payment_number,
                'currency_id' => $payment->currency_id,
                'base_currency_id' => $payment->base_currency_id,
                'exchange_rate' => $exchangeRate,
                'total_debit' => $amount,
                'total_credit' => $amount,
                'status' => JournalEntryStatus::Approved,
                'approved_by' => $poster->id,
                'approved_at' => now(),
                'idempotency_key' => $payment->idempotency_key,
                'created_by' => $payment->created_by,
                'updated_by' => $poster->id,
            ]);

            $this->createJournalLines($payment, $entry, $cashAccount, $amount, $exchangeRate);

            $postedEntry = (new PostJournalEntry)->handle($entry, $poster);

            $payment->update([
                'journal_entry_id' => $postedEntry->id,
                'status' => CustomerPaymentStatus::Posted,
                'posted_at' => now(),
            ]);

            $this->reconcileAllocations($payment, $poster);

            app(AuditLogService::class)->record(
                $organization,
                $poster->id,
                'customer-payment.posted',
                CustomerPayment::class,
                $payment->id,
                ['status' => 'draft'],
                ['status' => 'posted', 'journal_entry_id' => $postedEntry->id]
            );

            return $payment->fresh()->load(['journalEntry']);
        });
    }

    /**
     * When a payment posts, settle every invoice it is allocated against: paid
     * amount and balance are derived from posted allocations (SRS INV-010/INV-012)
     * and the invoice flips to partially paid or paid.
     */
    protected function reconcileAllocations(CustomerPayment $payment, User $poster): void
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
                : ($paid > 0 ? CustomerInvoiceStatus::PartiallyPaid : $invoice->status);

            $previous = [
                'status' => $invoice->status->value,
                'paid_amount' => (float) $invoice->paid_amount,
                'balance_due' => (float) $invoice->balance_due,
            ];

            $invoice->update([
                'paid_amount' => round($paid, 4),
                'balance_due' => $balanceDue,
                'status' => $status,
                'updated_by' => $poster->id,
            ]);

            app(AuditLogService::class)->record(
                $payment->organization,
                $poster->id,
                'customer-invoice.allocated',
                CustomerInvoice::class,
                $invoice->id,
                $previous,
                ['status' => $status->value, 'paid_amount' => $paid, 'balance_due' => $balanceDue]
            );
        }
    }

    protected function createJournalLines(
        CustomerPayment $payment,
        JournalEntry $entry,
        Account $cashAccount,
        float $amount,
        float $exchangeRate,
    ): void {
        $receivableAccount = $payment->customer->receivableAccount;

        $lines = [
            [
                'account_id' => $cashAccount->id,
                'line_number' => 1,
                'description' => 'Cash received from customer',
                'debit' => $amount,
                'credit' => 0.0,
            ],
            [
                'account_id' => $receivableAccount->id,
                'line_number' => 2,
                'description' => 'Customer payment applied to receivable',
                'debit' => 0.0,
                'credit' => $amount,
            ],
        ];

        foreach ($lines as $line) {
            JournalEntryLine::create([
                'organization_id' => $payment->organization_id,
                'journal_entry_id' => $entry->id,
                'account_id' => $line['account_id'],
                'line_number' => $line['line_number'],
                'description' => $line['description'],
                'debit' => $line['debit'],
                'credit' => $line['credit'],
                'currency_id' => $payment->currency_id,
                'currency_amount' => $amount,
                'base_amount' => round($amount * $exchangeRate, 4),
                'exchange_rate' => $exchangeRate,
                'reference' => $payment->payment_number,
            ]);
        }
    }

    protected function resolveJournal(Organization $organization): Journal
    {
        $journal = Journal::forOrganization($organization)
            ->active()
            ->where('code', 'GJ')
            ->first();

        if (! $journal) {
            $journal = Journal::forOrganization($organization)->active()->first();
        }

        if (! $journal) {
            throw new \RuntimeException('No active journal exists for this organization.');
        }

        return $journal;
    }

    /**
     * @return array{0: FiscalYear, 1: AccountingPeriod}
     */
    protected function resolvePeriod(Organization $organization, Carbon $paymentDate): array
    {
        $fiscalYear = FiscalYear::forOrganization($organization)
            ->open()
            ->where('start_date', '<=', $paymentDate)
            ->where('end_date', '>=', $paymentDate)
            ->first();

        if (! $fiscalYear) {
            throw new \RuntimeException('No open fiscal year covers the payment date.');
        }

        $period = AccountingPeriod::forOrganization($organization)
            ->open()
            ->where('fiscal_year_id', $fiscalYear->id)
            ->where('start_date', '<=', $paymentDate)
            ->where('end_date', '>=', $paymentDate)
            ->first();

        if (! $period) {
            throw new \RuntimeException('No open accounting period covers the payment date.');
        }

        return [$fiscalYear, $period];
    }

    protected function generateEntryNumber(Organization $organization, Journal $journal, Carbon $paymentDate): string
    {
        $sequence = JournalEntry::where('organization_id', $organization->id)
            ->where('journal_id', $journal->id)
            ->whereYear('entry_date', $paymentDate->year)
            ->count() + 1;

        return "{$journal->code}-{$paymentDate->year}-".str_pad($sequence, 6, '0', STR_PAD_LEFT);
    }

    public function rules(): array
    {
        return [];
    }
}
