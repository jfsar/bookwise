<?php

namespace App\Actions\VendorDebitNote;

use App\Actions\Action;
use App\Actions\Concerns\PostsJournalEntries;
use App\Actions\JournalEntry\PostJournalEntry;
use App\Enums\JournalEntryStatus;
use App\Enums\VendorBillStatus;
use App\Enums\VendorDebitNoteStatus;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use App\Models\VendorBill;
use App\Models\VendorDebitNote;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PostVendorDebitNote
{
    use Action, PostsJournalEntries;

    public function handle(VendorDebitNote $debitNote, User $poster): VendorDebitNote
    {
        return DB::transaction(function () use ($debitNote, $poster) {
            $debitNote = VendorDebitNote::where('id', $debitNote->id)->lockForUpdate()->first();

            if (! $debitNote->status->isPostable()) {
                throw new \RuntimeException('Only draft debit notes can be posted.');
            }

            if ($debitNote->created_by === $poster->id) {
                throw new \RuntimeException('Maker-checker: The final poster cannot be the same person who created the debit note.');
            }

            $bill = VendorBill::where('id', $debitNote->bill_id)->lockForUpdate()->first();

            if (! $bill) {
                throw new \RuntimeException('A posted bill is required before the debit note can be posted.');
            }

            if (! in_array($bill->status, [VendorBillStatus::Posted, VendorBillStatus::PartiallyPaid], true)) {
                throw new \RuntimeException('Only posted bills can be debited.');
            }

            $claimed = VendorDebitNote::forOrganization($debitNote->organization)
                ->where('bill_id', $bill->id)
                ->where('status', VendorDebitNoteStatus::Posted)
                ->sum('total_amount');

            $available = round((float) $bill->balance_due - (float) $claimed, 4);

            if ((float) $debitNote->total_amount > $available + 0.0001) {
                throw new \RuntimeException('The debit note total must not exceed the outstanding balance of the bill.');
            }

            $debitNote->load(['vendor.payableAccount', 'bill.lines.expenseAccount', 'bill.lines.taxCode.taxAccount']);

            $organization = $debitNote->organization;
            $journal = $this->resolveJournal($organization);
            [$fiscalYear, $period] = $this->resolvePeriod($organization, Carbon::parse($debitNote->date), 'debit note date');

            $entryNumber = $this->generateEntryNumber($organization, $journal, Carbon::parse($debitNote->date));
            $exchangeRate = (float) $debitNote->exchange_rate;
            $total = (float) $debitNote->total_amount;

            $entry = JournalEntry::create([
                'organization_id' => $organization->id,
                'journal_id' => $journal->id,
                'fiscal_year_id' => $fiscalYear->id,
                'accounting_period_id' => $period->id,
                'entry_number' => $entryNumber,
                'entry_date' => $debitNote->date,
                'description' => "Vendor Debit Note {$debitNote->debit_note_number}",
                'reference' => $debitNote->debit_note_number,
                'currency_id' => $debitNote->currency_id,
                'base_currency_id' => $organization->base_currency_id,
                'exchange_rate' => $exchangeRate,
                'total_debit' => $total,
                'total_credit' => $total,
                'status' => JournalEntryStatus::Approved,
                'approved_by' => $poster->id,
                'approved_at' => now(),
                'idempotency_key' => $debitNote->idempotency_key,
                'created_by' => $debitNote->created_by,
                'updated_by' => $poster->id,
            ]);

            foreach ($this->buildJournalLines($debitNote) as $line) {
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
                    'currency_id' => $debitNote->currency_id,
                    'currency_amount' => $amount,
                    'base_amount' => round($amount * $exchangeRate, 4),
                    'exchange_rate' => $exchangeRate,
                    'reference' => $debitNote->debit_note_number,
                ]);
            }

            $postedEntry = (new PostJournalEntry)->handle($entry, $poster);

            $debitNote->update([
                'journal_entry_id' => $postedEntry->id,
                'status' => VendorDebitNoteStatus::Posted,
                'posted_at' => now(),
            ]);

            app(AuditLogService::class)->record(
                $organization,
                $poster->id,
                'vendor-debit-note.posted',
                VendorDebitNote::class,
                $debitNote->id,
                ['status' => 'draft'],
                ['status' => 'posted', 'journal_entry_id' => $postedEntry->id]
            );

            return $debitNote->fresh()->load(['journalEntry']);
        });
    }

    /**
     * Build the posting lines: DR accounts payable for the total, CR the
     * reference bill's expense and tax accounts proportionally. The bill's
     * payable is reduced by the debit note (symmetric to SRS §7.27, applied to
     * payables); the credits always equal the debits.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildJournalLines(VendorDebitNote $debitNote): array
    {
        $bill = $debitNote->bill;
        $lines = [];
        $lineNumber = 1;
        $total = (float) $debitNote->total_amount;

        $lines[] = [
            'account' => $debitNote->vendor->payableAccount,
            'side' => 'debit',
            'amount' => $total,
            'line_number' => $lineNumber++,
            'description' => 'Accounts payable',
        ];

        $subtotal = (float) $debitNote->subtotal;
        $taxAmount = (float) $debitNote->tax_amount;

        if ($subtotal > 0) {
            $expenseLines = $bill->lines
                ->map(fn ($line) => [
                    'account' => $line->expenseAccount,
                    'net' => round((float) $line->quantity * (float) $line->unit_price - (float) $line->discount_amount, 4),
                    'description' => $line->description,
                ])
                ->filter(fn (array $line) => $line['net'] > 0)
                ->values();

            if ($expenseLines->isEmpty()) {
                throw new \RuntimeException('The reference bill has no expense to offset.');
            }

            $netTotal = (float) $expenseLines->sum('net');
            $allocated = 0.0;
            $lastIndex = $expenseLines->count() - 1;

            foreach ($expenseLines as $index => $line) {
                $amount = $index === $lastIndex
                    ? round($subtotal - $allocated, 4)
                    : round($subtotal * $line['net'] / $netTotal, 4);

                $allocated = round($allocated + $amount, 4);

                $lines[] = [
                    'account' => $line['account'],
                    'side' => 'credit',
                    'amount' => $amount,
                    'line_number' => $lineNumber++,
                    'description' => $line['description'],
                ];
            }
        }

        if ($taxAmount > 0) {
            $taxLines = $bill->lines
                ->filter(fn ($line) => (float) $line->tax_amount > 0 && $line->taxCode && $line->taxCode->taxAccount)
                ->map(fn ($line) => [
                    'account' => $line->taxCode->taxAccount,
                    'tax' => (float) $line->tax_amount,
                    'description' => $line->taxCode->name,
                ])
                ->values();

            if ($taxLines->isEmpty()) {
                throw new \RuntimeException('The reference bill has no tax to offset.');
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
                    'side' => 'credit',
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
