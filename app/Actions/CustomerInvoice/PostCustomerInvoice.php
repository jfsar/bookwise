<?php

namespace App\Actions\CustomerInvoice;

use App\Actions\Action;
use App\Actions\Concerns\PostsJournalEntries;
use App\Actions\JournalEntry\PostJournalEntry;
use App\Enums\CustomerInvoiceStatus;
use App\Enums\JournalEntryStatus;
use App\Models\CustomerInvoice;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class PostCustomerInvoice
{
    use Action, PostsJournalEntries;

    public function handle(CustomerInvoice $invoice, User $poster): CustomerInvoice
    {
        return DB::transaction(function () use ($invoice, $poster) {
            $invoice = CustomerInvoice::where('id', $invoice->id)->lockForUpdate()->first();

            if (! $invoice->status->isPostable()) {
                throw new \RuntimeException('Only draft invoices can be posted.');
            }

            if ($invoice->created_by === $poster->id) {
                throw new \RuntimeException('Maker-checker: The final poster cannot be the same person who created the invoice.');
            }

            $organization = $invoice->organization;
            $journal = $this->resolveJournal($organization);
            [$fiscalYear, $period] = $this->resolvePeriod($organization, $invoice->invoice_date, 'invoice date');

            $entryNumber = $this->generateEntryNumber($organization, $journal, $invoice->invoice_date);
            $exchangeRate = (float) $invoice->exchange_rate;

            $entry = JournalEntry::create([
                'organization_id' => $organization->id,
                'journal_id' => $journal->id,
                'fiscal_year_id' => $fiscalYear->id,
                'accounting_period_id' => $period->id,
                'entry_number' => $entryNumber,
                'entry_date' => $invoice->invoice_date,
                'description' => "Customer Invoice {$invoice->invoice_number}",
                'reference' => $invoice->invoice_number,
                'currency_id' => $invoice->currency_id,
                'base_currency_id' => $invoice->base_currency_id,
                'exchange_rate' => $exchangeRate,
                'total_debit' => (float) $invoice->total_amount,
                'total_credit' => (float) $invoice->total_amount,
                'status' => JournalEntryStatus::Approved,
                'approved_by' => $poster->id,
                'approved_at' => now(),
                'idempotency_key' => $invoice->idempotency_key,
                'created_by' => $invoice->created_by,
                'updated_by' => $poster->id,
            ]);

            $lines = $this->buildJournalLines($invoice, $entry, $exchangeRate);

            foreach ($lines as $line) {
                $account = $line['account'];
                $amount = (float) $line['amount'];
                $debit = $line['side'] === 'debit' ? $amount : 0.0;
                $credit = $line['side'] === 'credit' ? $amount : 0.0;

                JournalEntryLine::create([
                    'organization_id' => $organization->id,
                    'journal_entry_id' => $entry->id,
                    'account_id' => $account->id,
                    'line_number' => $line['line_number'],
                    'description' => $line['description'],
                    'debit' => $debit,
                    'credit' => $credit,
                    'currency_id' => $invoice->currency_id,
                    'currency_amount' => $amount,
                    'base_amount' => round($amount * $exchangeRate, 4),
                    'exchange_rate' => $exchangeRate,
                    'reference' => $invoice->invoice_number,
                ]);
            }

            $postedEntry = (new PostJournalEntry)->handle($entry, $poster);

            $invoice->update([
                'journal_entry_id' => $postedEntry->id,
                'status' => CustomerInvoiceStatus::Posted,
                'issued_at' => now(),
                'posted_at' => now(),
                'updated_by' => $poster->id,
            ]);

            app(AuditLogService::class)->record(
                $organization,
                $poster->id,
                'customer-invoice.posted',
                CustomerInvoice::class,
                $invoice->id,
                ['status' => 'draft'],
                ['status' => 'posted', 'journal_entry_id' => $postedEntry->id]
            );

            return $invoice->fresh()->load(['lines', 'journalEntry']);
        });
    }

    /**
     * Build the posting lines: DR A/R for the total, CR revenue per line net of
     * discount, CR tax payable per line. Debits always equal credits.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildJournalLines(CustomerInvoice $invoice, JournalEntry $entry, float $exchangeRate): array
    {
        $lines = [];
        $lineNumber = 1;

        $lines[] = [
            'account' => $invoice->customer->receivableAccount,
            'side' => 'debit',
            'amount' => (float) $invoice->total_amount,
            'line_number' => $lineNumber++,
            'description' => 'Accounts receivable',
        ];

        foreach ($invoice->lines as $invoiceLine) {
            $extended = round((float) $invoiceLine->quantity * (float) $invoiceLine->unit_price, 4);
            $netRevenue = round($extended - (float) $invoiceLine->discount_amount, 4);

            if ($netRevenue > 0) {
                $lines[] = [
                    'account' => $invoiceLine->revenueAccount,
                    'side' => 'credit',
                    'amount' => $netRevenue,
                    'line_number' => $lineNumber++,
                    'description' => $invoiceLine->description,
                ];
            }

            if ((float) $invoiceLine->tax_amount > 0 && $invoiceLine->taxCode) {
                $lines[] = [
                    'account' => $invoiceLine->taxCode->taxAccount,
                    'side' => 'credit',
                    'amount' => (float) $invoiceLine->tax_amount,
                    'line_number' => $lineNumber++,
                    'description' => $invoiceLine->taxCode->name,
                ];
            }
        }

        return $lines;
    }

    public function rules(): array
    {
        return [];
    }
}
