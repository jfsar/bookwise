<?php

namespace App\Http\Controllers;

use App\Actions\Customer\CreateCustomer;
use App\Actions\Customer\UpdateCustomer;
use App\Actions\Organization\SwitchOrganization;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Account;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        if (! $organization) {
            return Inertia::render('Customers/Index', [
                'customers' => null,
                'accounts' => [],
                'filters' => $request->only(['search', 'status']),
            ]);
        }

        $this->authorize('viewAny', [Customer::class, $organization]);

        $customers = Customer::forOrganization($organization)
            ->with(['receivableAccount'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->string('search').'%';

                $query->where(function ($query) use ($term) {
                    $query->where('name', 'ilike', $term)
                        ->orWhere('customer_number', 'ilike', $term)
                        ->orWhere('email', 'ilike', $term);
                });
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->input('status'));
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Customer $customer) => $this->serialize($customer, $user))
            ->toArray();

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
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

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        if (! $organization) {
            return back()->with('error', 'No organization selected.');
        }

        $this->authorize('create', [Customer::class, $organization]);

        try {
            (new CreateCustomer)->handle($request->validated(), $organization, $user);

            return back()->with('success', 'Customer created successfully.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $organization = SwitchOrganization::current($request->user());

        if ($organization && $customer->organization_id !== $organization->id) {
            abort(404);
        }

        $this->authorize('update', $customer);

        try {
            (new UpdateCustomer)->handle($customer, $request->validated(), $request->user());

            return back()->with('success', 'Customer updated successfully.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Customer $customer, mixed $user): array
    {
        return [
            'id' => $customer->id,
            'ulid' => $customer->ulid,
            'customer_number' => $customer->customer_number,
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'tax_number' => $customer->tax_number,
            'payment_terms_days' => $customer->payment_terms_days,
            'credit_limit' => $customer->credit_limit,
            'receivable_account_id' => $customer->receivable_account_id,
            'receivable_account' => $customer->receivableAccount ? [
                'id' => $customer->receivableAccount->id,
                'code' => $customer->receivableAccount->code,
                'name' => $customer->receivableAccount->name,
            ] : null,
            'notes' => $customer->notes,
            'status' => $customer->status,
            'created_at' => $customer->created_at?->format('M d, Y'),
            'can' => [
                'update' => $user->can('update', $customer),
                'archive' => $user->can('archive', $customer),
            ],
        ];
    }
}
