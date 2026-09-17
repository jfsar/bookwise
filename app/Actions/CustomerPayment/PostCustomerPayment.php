<?php

namespace App\Actions\CustomerPayment;

use App\Actions\Action;
use App\Actions\Concerns\PostsJournalEntries;
use App\Actions\JournalEntry\PostJournalEntry;
use App\Enums\CustomerPaymentStatus;
use App\Enums\JournalEntryStatus;
use App\Models\Account;
use App\Models\CustomerPayment;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\CustomerInvoiceSettlement;
use Illuminate\Support\Facades\DB;

class PostCustomerPayment
{
    use Action, PostsJournalEntries;

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
            [$fiscalYear, $period] = $this->resolvePeriod($organization, $payment->payment_date, 'payment date');

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

            $this->settleInvoices($payment, $poster);

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
     * amount and balance are derived from posted allocations (SRS INV-010/INV-012).
     */
    protected function settleInvoices(CustomerPayment $payment, User $poster): void
    {
        app(CustomerInvoiceSettlement::class)->reconcileForPayment($payment, $poster);
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

    public function rules(): array
    {
        return [];
    }
}
