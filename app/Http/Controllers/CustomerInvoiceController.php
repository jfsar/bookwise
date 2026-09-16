<?php

namespace App\Http\Controllers;

use App\Actions\CustomerInvoice\CreateCustomerInvoice;
use App\Actions\CustomerInvoice\PostCustomerInvoice;
use App\Actions\CustomerInvoice\VoidCustomerInvoice;
use App\Actions\Organization\SwitchOrganization;
use App\Http\Requests\StoreCustomerInvoiceRequest;
use App\Http\Requests\VoidCustomerInvoiceRequest;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\TaxCode;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerInvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        if (! $organization) {
            return Inertia::render('Invoices/Index', [
                'invoices' => null,
                'customers' => [],
                'accounts' => [],
                'currencies' => [],
                'taxCodes' => [],
                'filters' => $request->only(['search', 'status']),
            ]);
        }

        $this->authorize('viewAny', [CustomerInvoice::class, $organization]);

        $invoices = CustomerInvoice::forOrganization($organization)
            ->with(['customer', 'currency'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->string('search').'%';

                $query->where(function ($query) use ($term) {
                    $query->where('invoice_number', 'ilike', $term)
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
            ->through(fn (CustomerInvoice $invoice) => $this->serialize($invoice, $user))
            ->toArray();

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices,
            'customers' => Customer::forOrganization($organization)
                ->active()
                ->orderBy('name')
                ->get()
                ->values()
                ->map(fn (Customer $customer) => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'customer_number' => $customer->customer_number,
                    'receivable_account_id' => $customer->receivable_account_id,
                    'payment_terms_days' => $customer->payment_terms_days,
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

    public function store(StoreCustomerInvoiceRequest $request): RedirectResponse
    {
        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        if (! $organization) {
            return back()->with('error', 'No organization selected.');
        }

        $this->authorize('create', [CustomerInvoice::class, $organization]);

        $data = $request->validated();

        try {
            (new CreateCustomerInvoice)->handle($data, $organization, $user);
        } catch (QueryException $e) {
            logger()->error('Failed to create customer invoice.', ['organization_id' => $organization->id, 'exception' => $e]);

            return back()->with('error', 'Unable to create the invoice. Please try again.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Invoice draft created successfully.');
    }

    public function post(Request $request, CustomerInvoice $customerInvoice): RedirectResponse
    {
        $organization = SwitchOrganization::current($request->user());

        if (! $organization) {
            return back()->with('error', 'No organization selected.');
        }

        if ($customerInvoice->organization_id !== $organization->id) {
            abort(404);
        }

        $this->authorize('post', $customerInvoice);

        try {
            (new PostCustomerInvoice)->handle($customerInvoice, $request->user());

            return back()->with('success', 'Invoice posted to ledger.');
        } catch (QueryException $e) {
            logger()->error('Failed to post customer invoice.', ['invoice_id' => $customerInvoice->id, 'exception' => $e]);

            return back()->with('error', 'Unable to post the invoice. Please try again.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function void(VoidCustomerInvoiceRequest $request, CustomerInvoice $customerInvoice): RedirectResponse
    {
        $organization = SwitchOrganization::current($request->user());

        if (! $organization) {
            return back()->with('error', 'No organization selected.');
        }

        if ($customerInvoice->organization_id !== $organization->id) {
            abort(404);
        }

        $this->authorize('void', $customerInvoice);

        try {
            (new VoidCustomerInvoice)->handle($customerInvoice, $request->user(), $request->input('reason'));

            return back()->with('success', 'Invoice voided.');
        } catch (QueryException $e) {
            logger()->error('Failed to void customer invoice.', ['invoice_id' => $customerInvoice->id, 'exception' => $e]);

            return back()->with('error', 'Unable to void the invoice. Please try again.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(CustomerInvoice $invoice, User $user): array
    {
        return [
            'id' => $invoice->id,
            'ulid' => $invoice->ulid,
            'invoice_number' => $invoice->invoice_number,
            'invoice_date' => $invoice->invoice_date?->format('M d, Y'),
            'due_date' => $invoice->due_date?->format('M d, Y'),
            'customer' => $invoice->customer ? [
                'id' => $invoice->customer->id,
                'name' => $invoice->customer->name,
            ] : null,
            'subtotal' => $invoice->subtotal,
            'discount_amount' => $invoice->discount_amount,
            'tax_amount' => $invoice->tax_amount,
            'total_amount' => $invoice->total_amount,
            'paid_amount' => $invoice->paid_amount,
            'balance_due' => $invoice->balance_due,
            'currency' => $invoice->currency ? ['code' => $invoice->currency->code] : null,
            'status' => $invoice->status->value,
            'journal_entry_id' => $invoice->journal_entry_id,
            'created_at' => $invoice->created_at?->format('M d, Y'),
            'can' => [
                'post' => $user->can('post', $invoice),
                'void' => $user->can('void', $invoice),
            ],
        ];
    }
}
