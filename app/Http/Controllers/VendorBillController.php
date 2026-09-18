<?php

namespace App\Http\Controllers;

use App\Actions\Organization\SwitchOrganization;
use App\Actions\VendorBill\CreateVendorBill;
use App\Actions\VendorBill\PostVendorBill;
use App\Actions\VendorBill\VoidVendorBill;
use App\Http\Requests\StoreVendorBillRequest;
use App\Http\Requests\VoidVendorBillRequest;
use App\Models\Account;
use App\Models\Currency;
use App\Models\TaxCode;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorBill;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VendorBillController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        if (! $organization) {
            return Inertia::render('Bills/Index', [
                'bills' => null,
                'vendors' => [],
                'accounts' => [],
                'currencies' => [],
                'taxCodes' => [],
                'filters' => $request->only(['search', 'status']),
            ]);
        }

        $this->authorize('viewAny', [VendorBill::class, $organization]);

        $bills = VendorBill::forOrganization($organization)
            ->with(['vendor', 'currency'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->string('search').'%';

                $query->where(function ($query) use ($term) {
                    $query->where('bill_number', 'ilike', $term)
                        ->orWhereHas('vendor', function ($query) use ($term) {
                            $query->where('name', 'ilike', $term);
                        });
                });
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->input('status'));
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (VendorBill $bill) => $this->serialize($bill, $user))
            ->toArray();

        return Inertia::render('Bills/Index', [
            'bills' => $bills,
            'vendors' => Vendor::forOrganization($organization)
                ->active()
                ->orderBy('name')
                ->get()
                ->values()
                ->map(fn (Vendor $vendor) => [
                    'id' => $vendor->id,
                    'name' => $vendor->name,
                    'vendor_number' => $vendor->vendor_number,
                    'payable_account_id' => $vendor->payable_account_id,
                    'payment_terms_days' => $vendor->payment_terms_days,
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
            'currencies' => Currency::active()
                ->orderBy('code')
                ->get()
                ->values()
                ->map(fn (Currency $currency) => [
                    'id' => $currency->id,
                    'code' => $currency->code,
                    'name' => $currency->name,
                ]),
            'taxCodes' => TaxCode::forOrganization($organization)
                ->active()
                ->orderBy('code')
                ->get()
                ->values()
                ->map(fn (TaxCode $taxCode) => [
                    'id' => $taxCode->id,
                    'code' => $taxCode->code,
                    'name' => $taxCode->name,
                    'type' => $taxCode->type,
                ]),
            'baseCurrencyId' => $organization->base_currency_id,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function store(StoreVendorBillRequest $request): RedirectResponse
    {
        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        if (! $organization) {
            return back()->with('error', 'No organization selected.');
        }

        $this->authorize('create', [VendorBill::class, $organization]);

        $data = $request->validated();

        try {
            (new CreateVendorBill)->handle($data, $organization, $user);
        } catch (QueryException $e) {
            logger()->error('Failed to create vendor bill.', ['organization_id' => $organization->id, 'exception' => $e]);

            return back()->with('error', 'Unable to create the bill. Please try again.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Bill draft created successfully.');
    }

    public function post(Request $request, VendorBill $vendorBill): RedirectResponse
    {
        $organization = SwitchOrganization::current($request->user());

        if (! $organization) {
            return back()->with('error', 'No organization selected.');
        }

        if ($vendorBill->organization_id !== $organization->id) {
            abort(404);
        }

        $this->authorize('post', $vendorBill);

        try {
            (new PostVendorBill)->handle($vendorBill, $request->user());

            return back()->with('success', 'Bill posted to ledger.');
        } catch (QueryException $e) {
            logger()->error('Failed to post vendor bill.', ['bill_id' => $vendorBill->id, 'exception' => $e]);

            return back()->with('error', 'Unable to post the bill. Please try again.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function void(VoidVendorBillRequest $request, VendorBill $vendorBill): RedirectResponse
    {
        $organization = SwitchOrganization::current($request->user());

        if (! $organization) {
            return back()->with('error', 'No organization selected.');
        }

        if ($vendorBill->organization_id !== $organization->id) {
            abort(404);
        }

        $this->authorize('void', $vendorBill);

        try {
            (new VoidVendorBill)->handle($vendorBill, $request->user(), $request->input('reason'));

            return back()->with('success', 'Bill voided.');
        } catch (QueryException $e) {
            logger()->error('Failed to void vendor bill.', ['bill_id' => $vendorBill->id, 'exception' => $e]);

            return back()->with('error', 'Unable to void the bill. Please try again.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(VendorBill $bill, User $user): array
    {
        return [
            'id' => $bill->id,
            'ulid' => $bill->ulid,
            'bill_number' => $bill->bill_number,
            'vendor_reference' => $bill->vendor_reference,
            'bill_date' => $bill->bill_date?->format('M d, Y'),
            'due_date' => $bill->due_date?->format('M d, Y'),
            'vendor' => $bill->vendor ? [
                'id' => $bill->vendor->id,
                'name' => $bill->vendor->name,
            ] : null,
            'subtotal' => $bill->subtotal,
            'discount_amount' => $bill->discount_amount,
            'tax_amount' => $bill->tax_amount,
            'total_amount' => $bill->total_amount,
            'paid_amount' => $bill->paid_amount,
            'balance_due' => $bill->balance_due,
            'currency' => $bill->currency ? ['code' => $bill->currency->code] : null,
            'status' => $bill->status->value,
            'journal_entry_id' => $bill->journal_entry_id,
            'created_at' => $bill->created_at?->format('M d, Y'),
            'can' => [
                'post' => $user->can('post', $bill),
                'void' => $user->can('void', $bill),
            ],
        ];
    }
}
