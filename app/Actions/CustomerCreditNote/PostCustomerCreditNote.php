<?php

namespace App\Actions\CustomerCreditNote;

use App\Actions\Action;
use App\Actions\Concerns\PostsJournalEntries;
use App\Actions\JournalEntry\PostJournalEntry;
use App\Enums\CustomerCreditNoteStatus;
use App\Enums\CustomerInvoiceStatus;
use App\Enums\JournalEntryStatus;
use App\Models\CustomerCreditNote;
use App\Models\CustomerInvoice;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PostCustomerCreditNote
{
    use Action, PostsJournalEntries;

    public function handle(CustomerCreditNote $creditNote, User $poster): CustomerCreditNote
    {
        return DB::transaction(function () use ($creditNote, $poster) {
            $creditNote = CustomerCreditNote::where('id', $creditNote->id)->lockForUpdate()->first();

            if (! $creditNote->status->isPostable()) {
                throw new \RuntimeException('Only draft credit notes can be posted.');
            }

            if ($creditNote->created_by === $poster->id) {
                throw new \RuntimeException('Maker-checker: The final poster cannot be the same person who created the credit note.');
            }

            $invoice = CustomerInvoice::where('id', $creditNote->invoice_id)->lockForUpdate()->first();

            if (! $invoice) {
                throw new \RuntimeException('A posted invoice is required before the credit note can be posted.');
            }

            if (! in_array($invoice->status, [CustomerInvoiceStatus::Posted, CustomerInvoiceStatus::PartiallyPaid], true)) {
                throw new \RuntimeException('Only posted invoices can be credited.');
            }

            $claimed = CustomerCreditNote::forOrganization($creditNote->organization)
                ->where('invoice_id', $invoice->id)
                ->where('status', CustomerCreditNoteStatus::Posted)
                ->sum('total_amount');

            $available = round((float) $invoice->balance_due - (float) $claimed, 4);

            if ((float) $creditNote->total_amount > $available + 0.0001) {
                throw new \RuntimeException('The credit note total must not exceed the outstanding balance of the invoice.');
            }

            $creditNote->load(['customer.receivableAccount', 'invoice.lines.revenueAccount', 'invoice.lines.taxCode.taxAccount']);

            $organization = $creditNote->organization;
            $journal = $this->resolveJournal($organization);
            [$fiscalYear, $period] = $this->resolvePeriod($organization, Carbon::parse($creditNote->date), 'credit note date');

            $entryNumber = $this->generateEntryNumber($organization, $journal, Carbon::parse($creditNote->date));
            $exchangeRate = (float) $creditNote->exchange_rate;
            $total = (float) $creditNote->total_amount;

            $entry = JournalEntry::create([
                'organization_id' => $organization->id,
                'journal_id' => $journal->id,
                'fiscal_year_id' => $fiscalYear->id,
                'accounting_period_id' => $period->id,
                'entry_number' => $entryNumber,
                'entry_date' => $creditNote->date,
                'description' => "Customer Credit Note {$creditNote->credit_note_number}",
                'reference' => $creditNote->credit_note_number,
                'currency_id' => $creditNote->currency_id,
                'base_currency_id' => $organization->base_currency_id,
                'exchange_rate' => $exchangeRate,
                'total_debit' => $total,
                'total_credit' => $total,
                'status' => JournalEntryStatus::Approved,
                'approved_by' => $poster->id,
                'approved_at' => now(),
                'idempotency_key' => $creditNote->idempotency_key,
                'created_by' => $creditNote->created_by,
                'updated_by' => $poster->id,
            ]);

            foreach ($this->buildJournalLines($creditNote) as $line) {
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
                    'currency_id' => $creditNote->currency_id,
                    'currency_amount' => $amount,
                    'base_amount' => round($amount * $exchangeRate, 4),
                    'exchange_rate' => $exchangeRate,
                    'reference' => $creditNote->credit_note_number,
                ]);
            }

            $postedEntry = (new PostJournalEntry)->handle($entry, $poster);

            $creditNote->update([
                'journal_entry_id' => $postedEntry->id,
                'status' => CustomerCreditNoteStatus::Posted,
                'posted_at' => now(),
            ]);

            app(AuditLogService::class)->record(
                $organization,
                $poster->id,
                'customer-credit-note.posted',
                CustomerCreditNote::class,
                $creditNote->id,
                ['status' => 'draft'],
                ['status' => 'posted', 'journal_entry_id' => $postedEntry->id]
            );

            return $creditNote->fresh()->load(['journalEntry']);
        });
    }

    /**
     * Build the posting lines: DR the reference invoice's revenue and tax
     * accounts proportionally, CR accounts receivable for the total. The
     * invoice's receivable is reduced by the credit (SRS §7.27); the debits
     * always equal the credits.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildJournalLines(CustomerCreditNote $creditNote): array
    {
        $invoice = $creditNote->invoice;
        $lines = [];
        $lineNumber = 1;
        $total = (float) $creditNote->total_amount;

        $lines[] = [
            'account' => $creditNote->customer->receivableAccount,
            'side' => 'credit',
            'amount' => $total,
            'line_number' => $lineNumber++,
            'description' => 'Accounts receivable',
        ];

        $subtotal = (float) $creditNote->subtotal;
        $taxAmount = (float) $creditNote->tax_amount;

        if ($subtotal > 0) {
            $revenueLines = $invoice->lines
                ->map(fn ($line) => [
                    'account' => $line->revenueAccount,
                    'net' => round((float) $line->quantity * (float) $line->unit_price - (float) $line->discount_amount, 4),
                    'description' => $line->description,
                ])
                ->filter(fn (array $line) => $line['net'] > 0)
                ->values();

            if ($revenueLines->isEmpty()) {
                throw new \RuntimeException('The reference invoice has no revenue to offset.');
            }

            $netTotal = (float) $revenueLines->sum('net');
            $allocated = 0.0;
            $lastIndex = $revenueLines->count() - 1;

            foreach ($revenueLines as $index => $line) {
                $amount = $index === $lastIndex
                    ? round($subtotal - $allocated, 4)
                    : round($subtotal * $line['net'] / $netTotal, 4);

                $allocated = round($allocated + $amount, 4);

                $lines[] = [
                    'account' => $line['account'],
                    'side' => 'debit',
                    'amount' => $amount,
                    'line_number' => $lineNumber++,
                    'description' => $line['description'],
                ];
            }
        }

        if ($taxAmount > 0) {
            $taxLines = $invoice->lines
                ->filter(fn ($line) => (float) $line->tax_amount > 0 && $line->taxCode && $line->taxCode->taxAccount)
                ->map(fn ($line) => [
                    'account' => $line->taxCode->taxAccount,
                    'tax' => (float) $line->tax_amount,
                    'description' => $line->taxCode->name,
                ])
                ->values();

            if ($taxLines->isEmpty()) {
                throw new \RuntimeException('The reference invoice has no tax to offset.');
            }

            $taxTotal = (float) $taxLines->sum('tax');
            $allocated = 0.0;
            $lastIndex = $taxLines->count() - 1;

            foreach ($taxLines as $index => $line) {
                $amount = $index === $lastIndex
                    ? round($taxAmount - $allocated, 4)
                    : round($taxAmount * $line['tax'] / $taxTotal, 4);

                $allocated = round($allocated + $amount, 4);

                $lines[] = [
                    'account' => $line['account'],
                    'side' => 'debit',
                    'amount' => $amount,
                    'line_number' => $lineNumber++,
                    'description' => $line['description'],
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
