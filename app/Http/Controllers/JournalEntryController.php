<?php

namespace App\Http\Controllers;

use App\Actions\JournalEntry\ApproveJournalEntry;
use App\Actions\JournalEntry\CancelJournalEntry;
use App\Actions\JournalEntry\CreateJournalEntry;
use App\Actions\JournalEntry\PostJournalEntry;
use App\Actions\JournalEntry\ReverseJournalEntry;
use App\Actions\JournalEntry\SubmitJournalEntry;
use App\Actions\Organization\SwitchOrganization;
use App\Http\Requests\CancelJournalEntryRequest;
use App\Http\Requests\ReverseJournalEntryRequest;
use App\Http\Requests\StoreJournalEntryRequest;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\JournalEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JournalEntryController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        if (! $organization) {
            return Inertia::render('JournalEntries/Index', [
                'entries' => null,
                'journals' => [],
                'currencies' => [],
                'fiscalYears' => [],
                'accountingPeriods' => [],
                'accounts' => [],
                'filters' => $request->only(['search', 'status']),
            ]);
        }

        $entries = JournalEntry::forOrganization($organization)
            ->with(['journal', 'currency', 'accountingPeriod'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->string('search').'%';

                $query->where(function ($query) use ($term) {
                    $query->where('entry_number', 'ilike', $term)->orWhere('description', 'ilike', $term);
                });
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->input('status'));
            })
            ->orderByDesc('entry_date')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (JournalEntry $entry) => $this->serialize($entry, $user))
            ->toArray();

        return Inertia::render('JournalEntries/Index', [
            'entries' => $entries,
            'journals' => Journal::forOrganization($organization)
                ->active()
                ->orderBy('code')
                ->get()
                ->values()
                ->map(fn ($journal) => [
                    'id' => $journal->id,
                    'code' => $journal->code,
                    'name' => $journal->name,
                ]),
            'currencies' => Currency::active()
                ->orderBy('code')
                ->get()
                ->values()
                ->map(fn ($currency) => [
                    'id' => $currency->id,
                    'code' => $currency->code,
                    'name' => $currency->name,
                ]),
            'fiscalYears' => FiscalYear::forOrganization($organization)
                ->open()
                ->orderByDesc('start_date')
                ->get()
                ->values()
                ->map(fn ($fiscalYear) => [
                    'id' => $fiscalYear->id,
                    'name' => $fiscalYear->name,
                ]),
            'accountingPeriods' => AccountingPeriod::forOrganization($organization)
                ->open()
                ->with('fiscalYear')
                ->orderBy('start_date')
                ->get()
                ->values()
                ->map(fn ($period) => [
                    'id' => $period->id,
                    'name' => $period->name,
                    'fiscal_year_id' => $period->fiscal_year_id,
                ]),
            'accounts' => Account::forOrganization($organization)
                ->active()
                ->posting()
                ->orderBy('code')
                ->get()
                ->values()
                ->map(fn ($account) => [
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                ]),
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function store(StoreJournalEntryRequest $request): RedirectResponse
    {
        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        if (! $organization) {
            return back()->with('error', 'No organization selected.');
        }

        $data = $request->validated();

        $totalDebit = collect($data['lines'])->sum(fn ($line) => (float) ($line['debit'] ?? 0));
        $totalCredit = collect($data['lines'])->sum(fn ($line) => (float) ($line['credit'] ?? 0));

        if ($totalDebit === 0.0 || abs($totalDebit - $totalCredit) >= 0.005) {
            return back()->with('error', 'Debit and credit totals must be equal and greater than zero.');
        }

        $lines = collect($data['lines'])
            ->map(fn ($line) => [
                'account_id' => $line['account_id'],
                'description' => $line['description'] ?? null,
                'debit' => ($line['debit'] ?? null) !== null && $line['debit'] !== '' ? $line['debit'] : 0,
                'credit' => ($line['credit'] ?? null) !== null && $line['credit'] !== '' ? $line['credit'] : 0,
            ])
            ->all();

        (new CreateJournalEntry)->run([
            'journal_id' => $data['journal_id'],
            'currency_id' => $data['currency_id'],
            'fiscal_year_id' => $data['fiscal_year_id'],
            'accounting_period_id' => $data['accounting_period_id'],
            'entry_date' => $data['entry_date'],
            'description' => $data['description'],
            'reference' => $data['reference'] ?? null,
            'lines' => $lines,
        ], $organization, $user);

        return back()->with('success', 'Journal entry created successfully.');
    }

    public function submit(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        $this->authorize('submit', $journalEntry);

        try {
            (new SubmitJournalEntry)->handle($journalEntry, $request->user());

            return back()->with('success', 'Entry submitted for approval.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function approve(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        $this->authorize('approve', $journalEntry);

        try {
            (new ApproveJournalEntry)->handle($journalEntry, $request->user());

            return back()->with('success', 'Entry approved.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function post(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        $this->authorize('post', $journalEntry);

        try {
            (new PostJournalEntry)->handle($journalEntry, $request->user());

            return back()->with('success', 'Entry posted to ledger.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reverse(ReverseJournalEntryRequest $request, JournalEntry $journalEntry): RedirectResponse
    {
        $this->authorize('reverse', $journalEntry);

        try {
            $reversal = (new ReverseJournalEntry)->handle($journalEntry, $request->user(), $request->input('reason'));

            return back()->with('success', "Entry reversed. Reversal entry: {$reversal->entry_number}");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cancel(CancelJournalEntryRequest $request, JournalEntry $journalEntry): RedirectResponse
    {
        $this->authorize('cancel', $journalEntry);

        try {
            (new CancelJournalEntry)->handle($journalEntry, $request->user(), $request->input('reason'));

            return back()->with('success', 'Entry cancelled.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(JournalEntry $entry, mixed $user): array
    {
        return [
            'id' => $entry->id,
            'entry_number' => $entry->entry_number,
            'description' => $entry->description,
            'reference' => $entry->reference,
            'status' => $entry->status->value,
            'entry_date' => $entry->entry_date?->format('M d, Y'),
            'journal' => $entry->journal ? ['id' => $entry->journal->id, 'code' => $entry->journal->code, 'name' => $entry->journal->name] : null,
            'currency' => $entry->currency ? ['id' => $entry->currency->id, 'code' => $entry->currency->code] : null,
            'accounting_period' => $entry->accountingPeriod ? ['id' => $entry->accountingPeriod->id, 'name' => $entry->accountingPeriod->name] : null,
            'can' => [
                'submit' => $user->can('submit', $entry),
                'approve' => $user->can('approve', $entry),
                'post' => $user->can('post', $entry),
                'reverse' => $user->can('reverse', $entry),
                'cancel' => $user->can('cancel', $entry),
            ],
        ];
    }
}
