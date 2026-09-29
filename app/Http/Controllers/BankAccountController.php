<?php

namespace App\Http\Controllers;

use App\Actions\BankAccount\CreateBankAccount;
use App\Actions\BankAccount\UpdateBankAccount;
use App\Actions\Organization\SwitchOrganization;
use App\Http\Requests\StoreBankAccountRequest;
use App\Http\Requests\UpdateBankAccountRequest;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BankAccountController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        if (! $organization) {
            return Inertia::render('BankAccounts/Index', [
                'bankAccounts' => null,
                'accounts' => [],
                'currencies' => [],
                'filters' => $request->only(['search', 'status']),
            ]);
        }

        $this->authorize('viewAny', [BankAccount::class, $organization]);

        $bankAccounts = BankAccount::forOrganization($organization)
            ->with(['account', 'currency'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->string('search').'%';

                $query->where(function ($query) use ($term) {
                    $query->where('bank_name', 'ilike', $term)
                        ->orWhere('account_name', 'ilike', $term)
                        ->orWhere('last4', 'ilike', $term);
                });
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->input('status'));
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (BankAccount $bankAccount) => $this->serialize($bankAccount, $user))
            ->toArray();

        return Inertia::render('BankAccounts/Index', [
            'bankAccounts' => $bankAccounts,
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
            'currencies' => Currency::where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name'])
                ->values(),
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function store(StoreBankAccountRequest $request): RedirectResponse
    {
        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        if (! $organization) {
            return back()->with('error', 'No organization selected.');
        }

        $this->authorize('create', [BankAccount::class, $organization]);

        try {
            (new CreateBankAccount)->handle($request->validated(), $organization, $user);

            return back()->with('success', 'Bank account created successfully.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function update(UpdateBankAccountRequest $request, BankAccount $bankAccount): RedirectResponse
    {
        $organization = SwitchOrganization::current($request->user());

        if ($organization && $bankAccount->organization_id !== $organization->id) {
            abort(404);
        }

        $this->authorize('update', $bankAccount);

        try {
            (new UpdateBankAccount)->handle($bankAccount, $request->validated(), $request->user());

            return back()->with('success', 'Bank account updated successfully.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(BankAccount $bankAccount, mixed $user): array
    {
        return [
            'id' => $bankAccount->id,
            'ulid' => $bankAccount->ulid,
            'bank_name' => $bankAccount->bank_name,
            'account_name' => $bankAccount->account_name,
            'masked_account_number' => $bankAccount->masked_account_number,
            'last4' => $bankAccount->last4,
            'balance' => $bankAccount->opening_balance,
            'account_id' => $bankAccount->account_id,
            'account' => $bankAccount->account ? [
                'id' => $bankAccount->account->id,
                'code' => $bankAccount->account->code,
                'name' => $bankAccount->account->name,
            ] : null,
            'currency_id' => $bankAccount->currency_id,
            'currency' => $bankAccount->currency ? [
                'id' => $bankAccount->currency->id,
                'code' => $bankAccount->currency->code,
            ] : null,
            'status' => $bankAccount->status,
            'created_at' => $bankAccount->created_at?->format('M d, Y'),
            'can' => [
                'update' => $user->can('update', $bankAccount),
                'archive' => $user->can('archive', $bankAccount),
            ],
        ];
    }
}
