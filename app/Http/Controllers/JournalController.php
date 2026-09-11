<?php

namespace App\Http\Controllers;

use App\Actions\Journal\CreateJournal;
use App\Actions\Organization\SwitchOrganization;
use App\Http\Requests\StoreJournalRequest;
use App\Models\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JournalController extends Controller
{
    public function index(Request $request): Response
    {
        $organization = SwitchOrganization::current($request->user());

        $paginator = $organization
            ? Journal::forOrganization($organization)
                ->when($request->filled('search'), function ($query) use ($request) {
                    $term = '%'.$request->string('search').'%';

                    $query->where(function ($query) use ($term) {
                        $query->where('name', 'ilike', $term)->orWhere('code', 'ilike', $term);
                    });
                })
                ->orderBy('code')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (Journal $journal) => $this->serialize($journal))
                ->toArray()
            : null;

        return Inertia::render('Journals/Index', [
            'journals' => $paginator,
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(StoreJournalRequest $request): RedirectResponse
    {
        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        if (! $organization) {
            return back()->with('error', 'No organization selected.');
        }

        (new CreateJournal)->run($request->validated(), $organization, $user);

        return back()->with('success', 'Journal created successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Journal $journal): array
    {
        return [
            'id' => $journal->id,
            'code' => $journal->code,
            'name' => $journal->name,
            'description' => $journal->description,
            'type' => $journal->type,
            'is_active' => $journal->is_active,
        ];
    }
}
