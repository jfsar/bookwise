<?php

namespace App\Http\Livewire\JournalEntry;

use App\Actions\JournalEntry\CreateJournalEntry;
use App\Actions\Organization\SwitchOrganization;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\Journal;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class JournalEntryForm extends Component
{
    public ?int $journalId = null;

    public ?int $currencyId = null;

    public ?int $fiscalYearId = null;

    public ?int $accountingPeriodId = null;

    public string $entryDate = '';

    public string $description = '';

    public string $reference = '';

    public array $lines = [];

    public bool $showForm = false;

    public function mount(): void
    {
        $this->lines = [
            ['account_id' => '', 'debit' => '', 'credit' => '', 'description' => ''],
            ['account_id' => '', 'debit' => '', 'credit' => '', 'description' => ''],
        ];
    }

    protected array $rules = [
        'journalId' => ['required'],
        'currencyId' => ['required'],
        'fiscalYearId' => ['required'],
        'accountingPeriodId' => ['required'],
        'entryDate' => ['required', 'date'],
        'description' => ['required', 'string'],
        'reference' => ['nullable', 'string', 'max:100'],
        'lines' => ['required', 'array', 'min:2'],
        'lines.*.account_id' => ['required'],
        'lines.*.debit' => ['nullable', 'numeric', 'gte:0'],
        'lines.*.credit' => ['nullable', 'numeric', 'gte:0'],
    ];

    public function toggleForm(): void
    {
        $this->showForm = ! $this->showForm;
        if (! $this->showForm) {
            $this->resetForm();
        }
    }

    public function addLine(): void
    {
        $this->lines[] = ['account_id' => '', 'debit' => '', 'credit' => '', 'description' => ''];
    }

    public function removeLine(int $index): void
    {
        if (count($this->lines) > 2) {
            unset($this->lines[$index]);
            $this->lines = array_values($this->lines);
        }
    }

    public function totalDebit(): float
    {
        return (float) collect($this->lines)->sum('debit');
    }

    public function totalCredit(): float
    {
        return (float) collect($this->lines)->sum('credit');
    }

    public function isBalanced(): bool
    {
        return $this->totalDebit() > 0
            && $this->totalDebit() === $this->totalCredit();
    }

    public function createEntry(CreateJournalEntry $createEntry): void
    {
        $this->validate();

        if (! $this->isBalanced()) {
            session()->flash('error', 'Debit and credit totals must be equal.');

            return;
        }

        $organization = SwitchOrganization::current(Auth::user());
        $user = Auth::user();

        if (! $organization) {
            session()->flash('error', 'No organization selected.');

            return;
        }

        $lines = collect($this->lines)->map(fn ($line) => [
            'account_id' => $line['account_id'],
            'debit' => $line['debit'] ?: 0,
            'credit' => $line['credit'] ?: 0,
            'description' => $line['description'] ?? null,
        ])->toArray();

        $createEntry->run([
            'journal_id' => $this->journalId,
            'currency_id' => $this->currencyId,
            'fiscal_year_id' => $this->fiscalYearId,
            'accounting_period_id' => $this->accountingPeriodId,
            'entry_date' => $this->entryDate,
            'description' => $this->description,
            'reference' => $this->reference,
            'lines' => $lines,
        ], $organization, $user);

        $this->resetForm();
        $this->showForm = false;
        $this->dispatch('entry-created');
        session()->flash('success', 'Journal entry created successfully.');
    }

    protected function resetForm(): void
    {
        $this->journalId = null;
        $this->currencyId = null;
        $this->fiscalYearId = null;
        $this->accountingPeriodId = null;
        $this->entryDate = '';
        $this->description = '';
        $this->reference = '';
        $this->lines = [
            ['account_id' => '', 'debit' => '', 'credit' => '', 'description' => ''],
            ['account_id' => '', 'debit' => '', 'credit' => '', 'description' => ''],
        ];
        $this->resetValidation();
    }

    public function getJournalsProperty()
    {
        $organization = SwitchOrganization::current(Auth::user());

        return $organization ? Journal::forOrganization($organization)->active()->get() : collect();
    }

    public function getCurrenciesProperty()
    {
        return Currency::all();
    }

    public function getFiscalYearsProperty()
    {
        $organization = SwitchOrganization::current(Auth::user());

        return $organization ? FiscalYear::forOrganization($organization)->open()->get() : collect();
    }

    public function getAccountsProperty()
    {
        $organization = SwitchOrganization::current(Auth::user());

        return $organization ? Account::forOrganization($organization)->active()->posting()->get() : collect();
    }

    public function updatedFiscalYearId(): void
    {
        $this->accountingPeriodId = null;
    }

    public function getPeriodsProperty()
    {
        if (! $this->fiscalYearId) {
            return collect();
        }

        return AccountingPeriod::where('fiscal_year_id', $this->fiscalYearId)->open()->get();
    }

    public function render()
    {
        return view('livewire.journal-entry.journal-entry-form');
    }
}
