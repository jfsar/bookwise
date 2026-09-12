<?php

namespace App\Http\Controllers;

use App\Actions\Account\CreateAccount;
use App\Actions\Account\UpdateAccount;
use App\Actions\Organization\SwitchOrganization;
use App\Enums\CashFlowSection;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\Account;
use App\Models\AccountType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(Request $request): Response
    {
        $organization = SwitchOrganization::current($request->user());

        $paginator = $organization
            ? Account::forOrganization($organization)
                ->with(['accountType', 'parent'])
                ->when($request->filled('search'), function ($query) use ($request) {
                    $term = '%'.$request->string('search').'%';

                    $query->where(function ($query) use ($term) {
                        $query->where('code', 'ilike', $term)->orWhere('name', 'ilike', $term);
                    });
                })
                ->when($request->filled('type'), function ($query) use ($request) {
                    $query->where('account_type_id', $request->input('type'));
                })
                ->when(
                    $request->filled('status') && $request->input('status') !== 'inactive',
                    fn ($query) => $query->active()
                )
                ->when(
                    $request->filled('status') && $request->input('status') === 'inactive',
                    fn ($query) => $query->where('is_active', false)
                )
                ->when(! $request->filled('status'), fn ($query) => $query->active())
                ->orderBy('code')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (Account $account) => $this->serialize($account))
                ->toArray()
            : null;

        return Inertia::render('Accounts/Index', [
            'accounts' => $paginator,
            'accountTypes' => AccountType::orderBy('sort_order')
                ->get()
                ->values()
                ->map(fn ($type) => ['id' => $type->id, 'name' => $type->name]),
            'parentAccounts' => $organization
                ? Account::forOrganization($organization)
                    ->active()
                    ->orderBy('code')
                    ->get()
                    ->values()
                    ->map(fn ($account) => [
                        'id' => $account->id,
                        'code' => $account->code,
                        'name' => $account->name,
                    ])
                : [],
            'filters' => $request->only(['search', 'type', 'status']),
            'cashFlowSections' => collect(CashFlowSection::cases())
                ->map(fn (CashFlowSection $section) => [
                    'value' => $section->value,
                    'label' => $section->label(),
                ])
                ->all(),
        ]);
    }

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        if (! $organization) {
            return back()->with('error', 'No organization selected.');
        }

        (new CreateAccount)->handle($request->validated(), $organization, $user);

        return back()->with('success', 'Account created successfully.');
    }

    public function update(UpdateAccountRequest $request, Account $account): RedirectResponse
    {
        (new UpdateAccount)->handle($request->validated() + ['updated_by' => $request->user()->id], $account);

        return back()->with('success', 'Account updated successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Account $account): array
    {
        return [
            'id' => $account->id,
            'code' => $account->code,
            'name' => $account->name,
            'description' => $account->description,
            'account_type_id' => $account->account_type_id,
            'account_type' => $account->accountType ? ['id' => $account->accountType->id, 'name' => $account->accountType->name] : null,
            'parent_id' => $account->parent_id,
            'parent' => $account->parent ? ['id' => $account->parent->id, 'code' => $account->parent->code] : null,
            'normal_balance' => $account->normal_balance->value,
            'cash_flow_section' => $account->cash_flow_section?->value,
            'is_cash_account' => $account->is_cash_account,
            'is_posting_account' => $account->is_posting_account,
            'is_active' => $account->is_active,
            'allow_manual_posting' => $account->allow_manual_posting,
        ];
    }
}
