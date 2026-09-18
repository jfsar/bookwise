<?php

namespace App\Actions\VendorBill;

use App\Actions\Action;
use App\Actions\Concerns\PostsJournalEntries;
use App\Actions\JournalEntry\PostJournalEntry;
use App\Enums\JournalEntryStatus;
use App\Enums\VendorBillStatus;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use App\Models\VendorBill;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class PostVendorBill
{
    use Action, PostsJournalEntries;

    public function handle(VendorBill $bill, User $poster): VendorBill
    {
        return DB::transaction(function () use ($bill, $poster) {
            $bill = VendorBill::where('id', $bill->id)->lockForUpdate()->first();

            if (! $bill->status->isPostable()) {
                throw new \RuntimeException('Only draft bills can be posted.');
            }

            if ($bill->created_by === $poster->id) {
                throw new \RuntimeException('Maker-checker: The final poster cannot be the same person who created the bill.');
            }

            $organization = $bill->organization;
            $journal = $this->resolveJournal($organization);
            [$fiscalYear, $period] = $this->resolvePeriod($organization, $bill->bill_date, 'bill date');

            $entryNumber = $this->generateEntryNumber($organization, $journal, $bill->bill_date);
            $exchangeRate = (float) $bill->exchange_rate;

            $entry = JournalEntry::create([
                'organization_id' => $organization->id,
                'journal_id' => $journal->id,
                'fiscal_year_id' => $fiscalYear->id,
                'accounting_period_id' => $period->id,
                'entry_number' => $entryNumber,
                'entry_date' => $bill->bill_date,
                'description' => "Vendor Bill {$bill->bill_number}",
                'reference' => $bill->bill_number,
                'currency_id' => $bill->currency_id,
                'base_currency_id' => $bill->base_currency_id,
                'exchange_rate' => $exchangeRate,
                'total_debit' => (float) $bill->total_amount,
                'total_credit' => (float) $bill->total_amount,
                'status' => JournalEntryStatus::Approved,
                'approved_by' => $poster->id,
                'approved_at' => now(),
                'idempotency_key' => $bill->idempotency_key,
                'created_by' => $bill->created_by,
                'updated_by' => $poster->id,
            ]);

            $lines = $this->buildJournalLines($bill, $entry, $exchangeRate);

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
                    'currency_id' => $bill->currency_id,
                    'currency_amount' => $amount,
                    'base_amount' => round($amount * $exchangeRate, 4),
                    'exchange_rate' => $exchangeRate,
                    'reference' => $bill->bill_number,
                ]);
            }

            $postedEntry = (new PostJournalEntry)->handle($entry, $poster);

            $bill->update([
                'journal_entry_id' => $postedEntry->id,
                'status' => VendorBillStatus::Posted,
                'posted_at' => now(),
                'updated_by' => $poster->id,
            ]);

            app(AuditLogService::class)->record(
                $organization,
                $poster->id,
                'vendor-bill.posted',
                VendorBill::class,
                $bill->id,
                ['status' => 'draft'],
                ['status' => 'posted', 'journal_entry_id' => $postedEntry->id]
            );

            return $bill->fresh()->load(['lines', 'journalEntry', 'vendor']);
        });
    }

    /**
     * Build the posting lines: DR expense per line net of discount, DR tax
     * payable per line, CR accounts payable for the total. Debits always equal
     * credits.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildJournalLines(VendorBill $bill, JournalEntry $entry, float $exchangeRate): array
    {
        $lines = [];
        $lineNumber = 1;

        foreach ($bill->lines as $billLine) {
            $extended = round((float) $billLine->quantity * (float) $billLine->unit_price, 4);
            $netExpense = round($extended - (float) $billLine->discount_amount, 4);

            if ($netExpense > 0) {
                $lines[] = [
                    'account' => $billLine->expenseAccount,
                    'side' => 'debit',
                    'amount' => $netExpense,
                    'line_number' => $lineNumber++,
                    'description' => $billLine->description,
                ];
            }

            if ((float) $billLine->tax_amount > 0 && $billLine->taxCode) {
                $lines[] = [
                    'account' => $billLine->taxCode->taxAccount,
                    'side' => 'debit',
                    'amount' => (float) $billLine->tax_amount,
                    'line_number' => $lineNumber++,
                    'description' => $billLine->taxCode->name,
                ];
            }
        }

        $lines[] = [
            'account' => $bill->vendor->payableAccount,
            'side' => 'credit',
            'amount' => (float) $bill->total_amount,
            'line_number' => $lineNumber++,
            'description' => 'Accounts payable',
        ];

        return $lines;
    }

    public function rules(): array
    {
        return [];
    }
}
