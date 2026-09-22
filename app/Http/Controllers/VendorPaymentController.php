<?php

namespace App\Http\Controllers;

use App\Actions\Organization\SwitchOrganization;
use App\Actions\VendorPayment\AllocateVendorPayment;
use App\Actions\VendorPayment\CreateVendorPayment;
use App\Actions\VendorPayment\PostVendorPayment;
use App\Actions\VendorPayment\VoidVendorPayment;
use App\Enums\VendorBillStatus;
use App\Enums\VendorDebitNoteStatus;
use App\Enums\VendorPaymentStatus;
use App\Http\Requests\AllocateVendorPaymentRequest;
use App\Http\Requests\StoreVendorPaymentRequest;
use App\Http\Requests\VoidVendorPaymentRequest;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Models\VendorDebitNote;
use App\Models\VendorPayment;
use App\Models\VendorPaymentAllocation;
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
            ->with(['vendor', 'currency', 'cashAccount', 'allocations'])
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

    public function allocate(AllocateVendorPaymentRequest $request, VendorPayment $vendorPayment): RedirectResponse
    {
        $organization = SwitchOrganization::current($request->user());

        if (! $organization) {
            return back()->with('error', 'No organization selected.');
        }

        if ($vendorPayment->organization_id !== $organization->id) {
            abort(404);
        }

        $this->authorize('allocate', $vendorPayment);

        try {
            (new AllocateVendorPayment)->handle($vendorPayment, $request->validated('allocations'), $request->user());

            return back()->with('success', 'Payment allocation saved.');
        } catch (QueryException $e) {
            logger()->error('Failed to allocate vendor payment.', ['payment_id' => $vendorPayment->id, 'exception' => $e]);

            return back()->with('error', 'Unable to allocate the payment. Please try again.');
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
        $organization = $payment->organization;

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
                'allocate' => $payment->isDraft() && $user->can('allocate', $payment),
                'void' => ($payment->isDraft() || $payment->isPosted()) && $user->can('void', $payment),
            ],
            'allocable_bills' => $payment->isDraft() ? $this->allocableBills($payment, $organization) : [],
        ];
    }

    /**
     * Posted bills of the same vendor that still have an outstanding balance,
     * each capped by what this draft payment can still cover. Existing draft
     * allocations are already reflected so the UI never offers more than the
     * supported limits (SRS 7.29/7.31).
     *
     * @return array<int, array<string, mixed>>
     */
    private function allocableBills(VendorPayment $payment, Organization $organization): array
    {
        $bills = VendorBill::forOrganization($organization)
            ->where('vendor_id', $payment->vendor_id)
            ->whereIn('status', [VendorBillStatus::Posted, VendorBillStatus::PartiallyPaid])
            ->orderBy('due_date')
            ->get();

        if ($bills->isEmpty()) {
            return [];
        }

        $pendingByBill = VendorPaymentAllocation::query()
            ->join('vendor_payments', 'vendor_payments.id', '=', 'vendor_payment_allocations.payment_id')
            ->where('vendor_payment_allocations.organization_id', $organization->id)
            ->whereIn('vendor_payment_allocations.bill_id', $bills->pluck('id'))
            ->where('vendor_payments.status', VendorPaymentStatus::Draft)
            ->groupBy('vendor_payment_allocations.bill_id')
            ->selectRaw('vendor_payment_allocations.bill_id, SUM(vendor_payment_allocations.amount) as total')
            ->pluck('total', 'bill_id');

        $debitedByBill = VendorDebitNote::query()
            ->where('organization_id', $organization->id)
            ->whereIn('bill_id', $bills->pluck('id'))
            ->where('status', VendorDebitNoteStatus::Posted)
            ->groupBy('bill_id')
            ->selectRaw('bill_id, SUM(total_amount) as total')
            ->pluck('total', 'bill_id');

        return $bills
            ->map(function (VendorBill $bill) use ($payment, $pendingByBill, $debitedByBill) {
                $pending = (float) ($pendingByBill[$bill->id] ?? 0);
                $debited = (float) ($debitedByBill[$bill->id] ?? 0);
                $available = round((float) $bill->balance_due - $pending - $debited, 4);
                $remaining = round((float) $payment->amount - $payment->totalAllocated(), 4);

                return [
                    'id' => $bill->id,
                    'bill_number' => $bill->bill_number,
                    'due_date' => $bill->due_date?->format('M d, Y'),
                    'total_amount' => (float) $bill->total_amount,
                    'balance_due' => (float) $bill->balance_due,
                    'available' => max(0.0, min($available, $remaining)),
                    'status' => $bill->status->value,
                ];
            })
            ->filter(fn (array $bill) => $bill['available'] > 0.005)
            ->values()
            ->all();
    }
}
