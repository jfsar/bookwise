<?php

namespace App\Http\Controllers;

use App\Actions\Organization\SwitchOrganization;
use App\Actions\Vendor\CreateVendor;
use App\Actions\Vendor\UpdateVendor;
use App\Http\Requests\StoreVendorRequest;
use App\Http\Requests\UpdateVendorRequest;
use App\Models\Account;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VendorController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        if (! $organization) {
            return Inertia::render('Vendors/Index', [
                'vendors' => null,
                'accounts' => [],
                'filters' => $request->only(['search', 'status']),
            ]);
        }

        $this->authorize('viewAny', [Vendor::class, $organization]);

        $vendors = Vendor::forOrganization($organization)
            ->with(['payableAccount'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->string('search').'%';

                $query->where(function ($query) use ($term) {
                    $query->where('name', 'ilike', $term)
                        ->orWhere('vendor_number', 'ilike', $term)
                        ->orWhere('email', 'ilike', $term);
                });
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->input('status'));
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Vendor $vendor) => $this->serialize($vendor, $user))
            ->toArray();

        return Inertia::render('Vendors/Index', [
            'vendors' => $vendors,
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

    public function store(StoreVendorRequest $request): RedirectResponse
    {
        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        if (! $organization) {
            return back()->with('error', 'No organization selected.');
        }

        $this->authorize('create', [Vendor::class, $organization]);

        try {
            (new CreateVendor)->handle($request->validated(), $organization, $user);

            return back()->with('success', 'Vendor created successfully.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function update(UpdateVendorRequest $request, Vendor $vendor): RedirectResponse
    {
        $organization = SwitchOrganization::current($request->user());

        if ($organization && $vendor->organization_id !== $organization->id) {
            abort(404);
        }

        $this->authorize('update', $vendor);

        try {
            (new UpdateVendor)->handle($vendor, $request->validated(), $request->user());

            return back()->with('success', 'Vendor updated successfully.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Vendor $vendor, mixed $user): array
    {
        return [
            'id' => $vendor->id,
            'ulid' => $vendor->ulid,
            'vendor_number' => $vendor->vendor_number,
            'name' => $vendor->name,
            'email' => $vendor->email,
            'phone' => $vendor->phone,
            'tax_number' => $vendor->tax_number,
            'payment_terms_days' => $vendor->payment_terms_days,
            'payable_account_id' => $vendor->payable_account_id,
            'payable_account' => $vendor->payableAccount ? [
                'id' => $vendor->payableAccount->id,
                'code' => $vendor->payableAccount->code,
                'name' => $vendor->payableAccount->name,
            ] : null,
            'notes' => $vendor->notes,
            'status' => $vendor->status,
            'created_at' => $vendor->created_at?->format('M d, Y'),
            'can' => [
                'update' => $user->can('update', $vendor),
                'archive' => $user->can('archive', $vendor),
            ],
        ];
    }
}
