<?php

namespace App\Http\Controllers;

use App\Actions\Organization\SwitchOrganization;
use App\Actions\RecurringJournal\CreateRecurringJournal;
use App\Actions\RecurringJournal\DeleteRecurringJournal;
use App\Actions\RecurringJournal\GenerateRecurringJournal;
use App\Actions\RecurringJournal\PauseRecurringJournal;
use App\Actions\RecurringJournal\ResumeRecurringJournal;
use App\Actions\RecurringJournal\UpdateRecurringJournal;
use App\Enums\RecurringJournalFrequency;
use App\Enums\RecurringJournalStatus;
use App\Http\Requests\StoreRecurringJournalRequest;
use App\Http\Requests\UpdateRecurringJournalRequest;
use App\Models\Account;
use App\Models\Journal;
use App\Models\RecurringJournal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecurringJournalController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        if (! $organization) {
            return Inertia::render('RecurringJournals/Index', [
                'recurringJournals' => null,
                'journals' => [],
                'accounts' => [],
                'frequencies' => $this->frequencies(),
                'statuses' => $this->statuses(),
                'canCreate' => false,
                'filters' => $request->only(['search', 'status']),
            ]);
        }

        $this->authorize('viewAny', [RecurringJournal::class, $organization]);

        $recurringJournals = RecurringJournal::forOrganization($organization)
            ->with(['journal', 'lines.account'])
            ->withCount('executions')
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->string('search').'%';

                $query->where('name', 'ilike', $term);
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->input('status'));
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (RecurringJournal $recurringJournal) => $this->serialize($recurringJournal, $user))
            ->toArray();

        return Inertia::render('RecurringJournals/Index', [
            'recurringJournals' => $recurringJournals,
            'journals' => Journal::forOrganization($organization)
                ->active()
                ->orderBy('code')
                ->get()
                ->values()
                ->map(fn (Journal $journal) => [
                    'id' => $journal->id,
                    'code' => $journal->code,
                    'name' => $journal->name,
                ]),
            'accounts' => Account::forOrganization($organization)
                ->active()
                ->posting()
                ->orderBy('code')
                ->get()
                ->values()
                ->map(fn (Account $account) => [
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                ]),
            'frequencies' => $this->frequencies(),
            'statuses' => $this->statuses(),
            'canCreate' => $user->can('create', [RecurringJournal::class, $organization]),
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function store(StoreRecurringJournalRequest $request): RedirectResponse
    {
        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        if (! $organization) {
            return back()->with('error', 'No organization selected.');
        }

        $this->authorize('create', [RecurringJournal::class, $organization]);

        try {
            (new CreateRecurringJournal)->handle($request->validated(), $organization, $user);

            return back()->with('success', 'Recurring journal created successfully.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function update(UpdateRecurringJournalRequest $request, RecurringJournal $recurringJournal): RedirectResponse
    {
        $this->ensureSameOrganization($request, $recurringJournal);
        $this->authorize('update', $recurringJournal);

        try {
            (new UpdateRecurringJournal)->handle($recurringJournal, $request->validated(), $request->user());

            return back()->with('success', 'Recurring journal updated successfully.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy(Request $request, RecurringJournal $recurringJournal): RedirectResponse
    {
        $this->ensureSameOrganization($request, $recurringJournal);
        $this->authorize('delete', $recurringJournal);

        try {
            (new DeleteRecurringJournal)->handle($recurringJournal, $request->user());

            return back()->with('success', 'Recurring journal deleted successfully.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function pause(Request $request, RecurringJournal $recurringJournal): RedirectResponse
    {
        $this->ensureSameOrganization($request, $recurringJournal);
        $this->authorize('pause', $recurringJournal);

        try {
            (new PauseRecurringJournal)->handle($recurringJournal, $request->user());

            return back()->with('success', 'Recurring journal paused.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function resume(Request $request, RecurringJournal $recurringJournal): RedirectResponse
    {
        $this->ensureSameOrganization($request, $recurringJournal);
        $this->authorize('resume', $recurringJournal);

        try {
            (new ResumeRecurringJournal)->handle($recurringJournal, $request->user());

            return back()->with('success', 'Recurring journal resumed.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function run(Request $request, RecurringJournal $recurringJournal): RedirectResponse
    {
        $this->ensureSameOrganization($request, $recurringJournal);
        $this->authorize('run', $recurringJournal);

        try {
            $execution = (new GenerateRecurringJournal)->handle($recurringJournal);

            if (! $execution->isCompleted()) {
                return back()->with('error', 'This occurrence is already being processed.');
            }

            $entry = $execution->journalEntry;

            return back()->with('success', "Generated draft entry {$entry->entry_number} for {$execution->scheduled_run_date->toDateString()}.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    private function ensureSameOrganization(Request $request, RecurringJournal $recurringJournal): void
    {
        $organization = SwitchOrganization::current($request->user());

        if (! $organization || $recurringJournal->organization_id !== $organization->id) {
            abort(404);
        }
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function frequencies(): array
    {
        return collect(RecurringJournalFrequency::cases())
            ->map(fn (RecurringJournalFrequency $frequency) => [
                'value' => $frequency->value,
                'label' => $frequency->label(),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(RecurringJournal $recurringJournal, mixed $user): array
    {
        return [
            'id' => $recurringJournal->id,
            'ulid' => $recurringJournal->ulid,
            'name' => $recurringJournal->name,
            'description' => $recurringJournal->description,
            'journal_id' => $recurringJournal->journal_id,
            'journal' => $recurringJournal->journal ? [
                'id' => $recurringJournal->journal->id,
                'code' => $recurringJournal->journal->code,
                'name' => $recurringJournal->journal->name,
            ] : null,
            'frequency' => $recurringJournal->frequency->value,
            'frequency_label' => $recurringJournal->frequency->label(),
            'start_date' => $recurringJournal->start_date->toDateString(),
            'end_date' => $recurringJournal->end_date?->toDateString(),
            'next_run_date' => $recurringJournal->next_run_date->toDateString(),
            'status' => $recurringJournal->status->value,
            'status_label' => ucfirst($recurringJournal->status->value),
            'auto_submit' => $recurringJournal->auto_submit,
            'run_count' => $recurringJournal->run_count,
            'executions_count' => $recurringJournal->executions_count,
            'last_run_at' => $recurringJournal->last_run_at?->format('M d, Y H:i'),
            'total_debit' => (float) $recurringJournal->lines->sum('debit'),
            'total_credit' => (float) $recurringJournal->lines->sum('credit'),
            'lines' => $recurringJournal->lines->map(fn ($line) => [
                'line_number' => $line->line_number,
                'account_id' => $line->account_id,
                'account_code' => $line->account?->code,
                'account_name' => $line->account?->name,
                'description' => $line->description,
                'debit' => (float) $line->debit,
                'credit' => (float) $line->credit,
            ])->values(),
            'can' => [
                'update' => $user->can('update', $recurringJournal),
                'delete' => $user->can('delete', $recurringJournal),
                'pause' => $user->can('pause', $recurringJournal),
                'resume' => $user->can('resume', $recurringJournal),
                'run' => $user->can('run', $recurringJournal),
            ],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function statuses(): array
    {
        return collect(RecurringJournalStatus::cases())
            ->map(fn (RecurringJournalStatus $status) => [
                'value' => $status->value,
                'label' => ucfirst($status->value),
            ])
            ->all();
    }
}
