<?php

namespace App\Http\Controllers;

use App\Actions\Organization\SwitchOrganization;
use App\Actions\VendorPayment\CreateVendorPayment;
use App\Actions\VendorPayment\PostVendorPayment;
use App\Actions\VendorPayment\VoidVendorPayment;
use App\Http\Requests\StoreVendorPaymentRequest;
use App\Http\Requests\VoidVendorPaymentRequest;
use App\Models\Account;
use App\Models\Currency;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayment;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VendorPaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        if (! $organization) {
            return Inertia::render('VendorPayments/Index', [
                'payments' => null,
                'vendors' => [],
                'accounts' => [],
                'currencies' => [],
                'baseCurrencyId' => null,
                'filters' => $request->only(['search', 'status']),
            ]);
        }

        $this->authorize('viewAny', [VendorPayment::class, $organization]);

        $payments = VendorPayment::forOrganization($organization)
            ->with(['vendor', 'currency', 'cashAccount'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->string('search').'%';

                $query->where(function ($query) use ($term) {
                    $query->where('payment_number', 'ilike', $term)
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
            ->through(fn (VendorPayment $payment) => $this->serialize($payment, $user))
            ->toArray();

        return Inertia::render('VendorPayments/Index', [
            'payments' => $payments,
            'vendors' => Vendor::forOrganization($organization)
                ->active()
                ->orderBy('name')
                ->get()
                ->values()
                ->map(fn (Vendor $vendor) => [
                    'id' => $vendor->id,
                    'name' => $vendor->name,
                    'vendor_number' => $vendor->vendor_number,
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
            'baseCurrencyId' => $organization->base_currency_id,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function store(StoreVendorPaymentRequest $request): RedirectResponse
    {
        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        if (! $organization) {
            return back()->with('error', 'No organization selected.');
        }

        $this->authorize('create', [VendorPayment::class, $organization]);

        $data = $request->validated();

        try {
            (new CreateVendorPayment)->handle($data, $organization, $user);
        } catch (QueryException $e) {
            logger()->error('Failed to create vendor payment.', ['organization_id' => $organization->id, 'exception' => $e]);

            return back()->with('error', 'Unable to create the payment. Please try again.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payment draft created successfully.');
    }

    public function post(Request $request, VendorPayment $vendorPayment): RedirectResponse
    {
        $organization = SwitchOrganization::current($request->user());

        if (! $organization) {
            return back()->with('error', 'No organization selected.');
        }

        if ($vendorPayment->organization_id !== $organization->id) {
            abort(404);
        }

        $this->authorize('post', $vendorPayment);

        try {
            (new PostVendorPayment)->handle($vendorPayment, $request->user());

            return back()->with('success', 'Payment posted to ledger.');
        } catch (QueryException $e) {
            logger()->error('Failed to post vendor payment.', ['payment_id' => $vendorPayment->id, 'exception' => $e]);

            return back()->with('error', 'Unable to post the payment. Please try again.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function void(VoidVendorPaymentRequest $request, VendorPayment $vendorPayment): RedirectResponse
    {
        $organization = SwitchOrganization::current($request->user());

        if (! $organization) {
            return back()->with('error', 'No organization selected.');
        }

        if ($vendorPayment->organization_id !== $organization->id) {
            abort(404);
        }

        $this->authorize('void', $vendorPayment);

        try {
            (new VoidVendorPayment)->handle($vendorPayment, $request->user(), $request->input('reason'));

            return back()->with('success', 'Payment voided.');
        } catch (QueryException $e) {
            logger()->error('Failed to void vendor payment.', ['payment_id' => $vendorPayment->id, 'exception' => $e]);

            return back()->with('error', 'Unable to void the payment. Please try again.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(VendorPayment $payment, User $user): array
    {
        return [
            'id' => $payment->id,
            'ulid' => $payment->ulid,
            'payment_number' => $payment->payment_number,
            'payment_date' => $payment->payment_date?->format('M d, Y'),
            'vendor' => $payment->vendor ? [
                'id' => $payment->vendor->id,
                'name' => $payment->vendor->name,
            ] : null,
            'amount' => $payment->amount,
            'allocated' => $payment->totalAllocated(),
            'remaining' => $payment->remainingAllocatable(),
            'payment_method' => $payment->payment_method,
            'reference' => $payment->reference,
            'cash_account' => $payment->cashAccount ? [
                'id' => $payment->cashAccount->id,
                'code' => $payment->cashAccount->code,
                'name' => $payment->cashAccount->name,
            ] : null,
            'currency' => $payment->currency ? ['code' => $payment->currency->code] : null,
            'status' => $payment->status->value,
            'journal_entry_id' => $payment->journal_entry_id,
            'posted_at' => $payment->posted_at?->format('M d, Y'),
            'created_at' => $payment->created_at?->format('M d, Y'),
            'can' => [
                'post' => $user->can('post', $payment),
                'void' => ($payment->isDraft() || $payment->isPosted()) && $user->can('void', $payment),
            ],
        ];
    }
}
