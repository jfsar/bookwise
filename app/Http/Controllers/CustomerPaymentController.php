<?php

namespace App\Http\Controllers;

use App\Actions\CustomerPayment\CreateCustomerPayment;
use App\Actions\CustomerPayment\PostCustomerPayment;
use App\Actions\Organization\SwitchOrganization;
use App\Http\Requests\StoreCustomerPaymentRequest;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerPaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        if (! $organization) {
            return Inertia::render('Payments/Index', [
                'payments' => null,
                'customers' => [],
                'accounts' => [],
                'currencies' => [],
                'baseCurrencyId' => null,
                'filters' => $request->only(['search', 'status']),
            ]);
        }

        $this->authorize('viewAny', [CustomerPayment::class, $organization]);

        $payments = CustomerPayment::forOrganization($organization)
            ->with(['customer', 'currency', 'cashAccount'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->string('search').'%';

                $query->where(function ($query) use ($term) {
                    $query->where('payment_number', 'ilike', $term)
                        ->orWhereHas('customer', function ($query) use ($term) {
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
            ->through(fn (CustomerPayment $payment) => $this->serialize($payment, $user))
            ->toArray();

        return Inertia::render('Payments/Index', [
            'payments' => $payments,
            'customers' => Customer::forOrganization($organization)
                ->active()
                ->orderBy('name')
                ->get()
                ->values()
                ->map(fn (Customer $customer) => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'customer_number' => $customer->customer_number,
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

    public function store(StoreCustomerPaymentRequest $request): RedirectResponse
    {
        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        if (! $organization) {
            return back()->with('error', 'No organization selected.');
        }

        $this->authorize('create', [CustomerPayment::class, $organization]);

        $data = $request->validated();

        try {
            (new CreateCustomerPayment)->handle($data, $organization, $user);
        } catch (QueryException $e) {
            logger()->error('Failed to create customer payment.', ['organization_id' => $organization->id, 'exception' => $e]);

            return back()->with('error', 'Unable to create the payment. Please try again.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payment draft created successfully.');
    }

    public function post(Request $request, CustomerPayment $customerPayment): RedirectResponse
    {
        $organization = SwitchOrganization::current($request->user());

        if (! $organization) {
            return back()->with('error', 'No organization selected.');
        }

        if ($customerPayment->organization_id !== $organization->id) {
            abort(404);
        }

        $this->authorize('post', $customerPayment);

        try {
            (new PostCustomerPayment)->handle($customerPayment, $request->user());

            return back()->with('success', 'Payment posted to ledger.');
        } catch (QueryException $e) {
            logger()->error('Failed to post customer payment.', ['payment_id' => $customerPayment->id, 'exception' => $e]);

            return back()->with('error', 'Unable to post the payment. Please try again.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(CustomerPayment $payment, User $user): array
    {
        return [
            'id' => $payment->id,
            'ulid' => $payment->ulid,
            'payment_number' => $payment->payment_number,
            'payment_date' => $payment->payment_date?->format('M d, Y'),
            'customer' => $payment->customer ? [
                'id' => $payment->customer->id,
                'name' => $payment->customer->name,
            ] : null,
            'amount' => $payment->amount,
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
            ],
        ];
    }
}
