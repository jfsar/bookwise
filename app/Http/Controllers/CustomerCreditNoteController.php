<?php

namespace App\Http\Controllers;

use App\Actions\CustomerCreditNote\CreateCustomerCreditNote;
use App\Actions\CustomerCreditNote\PostCustomerCreditNote;
use App\Actions\CustomerCreditNote\VoidCustomerCreditNote;
use App\Actions\Organization\SwitchOrganization;
use App\Enums\CustomerCreditNoteStatus;
use App\Enums\CustomerInvoiceStatus;
use App\Http\Requests\StoreCustomerCreditNoteRequest;
use App\Http\Requests\VoidCustomerCreditNoteRequest;
use App\Models\Customer;
use App\Models\CustomerCreditNote;
use App\Models\CustomerInvoice;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerCreditNoteController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        if (! $organization) {
            return Inertia::render('CreditNotes/Index', [
                'creditNotes' => null,
                'customers' => [],
                'invoices' => [],
                'filters' => $request->only(['search', 'status']),
            ]);
        }

        $this->authorize('viewAny', [CustomerCreditNote::class, $organization]);

        $creditNotes = CustomerCreditNote::forOrganization($organization)
            ->with(['customer', 'invoice', 'currency'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->string('search').'%';

                $query->where(function ($query) use ($term) {
                    $query->where('credit_note_number', 'ilike', $term)
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
            ->through(fn (CustomerCreditNote $creditNote) => $this->serialize($creditNote, $user))
            ->toArray();

        return Inertia::render('CreditNotes/Index', [
            'creditNotes' => $creditNotes,
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
            'invoices' => CustomerInvoice::forOrganization($organization)
                ->with('currency')
                ->whereIn('status', [CustomerInvoiceStatus::Posted, CustomerInvoiceStatus::PartiallyPaid])
                ->orderBy('invoice_number')
                ->get()
                ->values()
                ->map(fn (CustomerInvoice $invoice) => [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'customer_id' => $invoice->customer_id,
                    'balance_due' => (float) $invoice->balance_due,
                    'currency' => $invoice->currency ? ['code' => $invoice->currency->code] : null,
                ]),
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function store(StoreCustomerCreditNoteRequest $request): RedirectResponse
    {
        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        if (! $organization) {
            return back()->with('error', 'No organization selected.');
        }

        $this->authorize('create', [CustomerCreditNote::class, $organization]);

        try {
            (new CreateCustomerCreditNote)->handle($request->validated(), $organization, $user);
        } catch (QueryException $e) {
            logger()->error('Failed to create customer credit note.', ['organization_id' => $organization->id, 'exception' => $e]);

            return back()->with('error', 'Unable to create the credit note. Please try again.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Credit note draft created successfully.');
    }

    public function post(Request $request, CustomerCreditNote $customerCreditNote): RedirectResponse
    {
        $organization = SwitchOrganization::current($request->user());

        if (! $organization) {
            return back()->with('error', 'No organization selected.');
        }

        if ($customerCreditNote->organization_id !== $organization->id) {
            abort(404);
        }

        $this->authorize('post', $customerCreditNote);

        try {
            (new PostCustomerCreditNote)->handle($customerCreditNote, $request->user());

            return back()->with('success', 'Credit note posted to ledger.');
        } catch (QueryException $e) {
            logger()->error('Failed to post customer credit note.', ['credit_note_id' => $customerCreditNote->id, 'exception' => $e]);

            return back()->with('error', 'Unable to post the credit note. Please try again.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function void(VoidCustomerCreditNoteRequest $request, CustomerCreditNote $customerCreditNote): RedirectResponse
    {
        $organization = SwitchOrganization::current($request->user());

        if (! $organization) {
            return back()->with('error', 'No organization selected.');
        }

        if ($customerCreditNote->organization_id !== $organization->id) {
            abort(404);
        }

        $this->authorize('void', $customerCreditNote);

        try {
            (new VoidCustomerCreditNote)->handle($customerCreditNote, $request->user(), $request->input('reason'));

            return back()->with('success', 'Credit note voided.');
        } catch (QueryException $e) {
            logger()->error('Failed to void customer credit note.', ['credit_note_id' => $customerCreditNote->id, 'exception' => $e]);

            return back()->with('error', 'Unable to void the credit note. Please try again.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(CustomerCreditNote $creditNote, User $user): array
    {
        return [
            'id' => $creditNote->id,
            'ulid' => $creditNote->ulid,
            'credit_note_number' => $creditNote->credit_note_number,
            'date' => $creditNote->date?->format('M d, Y'),
            'customer' => $creditNote->customer ? [
                'id' => $creditNote->customer->id,
                'name' => $creditNote->customer->name,
            ] : null,
            'invoice' => $creditNote->invoice ? [
                'id' => $creditNote->invoice->id,
                'invoice_number' => $creditNote->invoice->invoice_number,
            ] : null,
            'subtotal' => $creditNote->subtotal,
            'tax_amount' => $creditNote->tax_amount,
            'total_amount' => $creditNote->total_amount,
            'reason' => $creditNote->reason,
            'currency' => $creditNote->currency ? ['code' => $creditNote->currency->code] : null,
            'status' => $creditNote->status->value,
            'journal_entry_id' => $creditNote->journal_entry_id,
            'posted_at' => $creditNote->posted_at?->format('M d, Y'),
            'created_at' => $creditNote->created_at?->format('M d, Y'),
            'can' => [
                'post' => $creditNote->status === CustomerCreditNoteStatus::Draft && $user->can('post', $creditNote),
                'void' => $creditNote->status === CustomerCreditNoteStatus::Draft && $user->can('void', $creditNote),
            ],
        ];
    }
}
