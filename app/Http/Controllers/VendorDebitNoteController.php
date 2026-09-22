<?php

namespace App\Http\Controllers;

use App\Actions\Organization\SwitchOrganization;
use App\Actions\VendorDebitNote\CreateVendorDebitNote;
use App\Actions\VendorDebitNote\PostVendorDebitNote;
use App\Actions\VendorDebitNote\VoidVendorDebitNote;
use App\Enums\VendorBillStatus;
use App\Enums\VendorDebitNoteStatus;
use App\Http\Requests\StoreVendorDebitNoteRequest;
use App\Http\Requests\VoidVendorDebitNoteRequest;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Models\VendorDebitNote;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VendorDebitNoteController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        if (! $organization) {
            return Inertia::render('VendorDebitNotes/Index', [
                'vendorDebitNotes' => null,
                'vendors' => [],
                'bills' => [],
                'filters' => $request->only(['search', 'status']),
            ]);
        }

        $this->authorize('viewAny', [VendorDebitNote::class, $organization]);

        $vendorDebitNotes = VendorDebitNote::forOrganization($organization)
            ->with(['vendor', 'bill', 'currency'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->string('search').'%';

                $query->where(function ($query) use ($term) {
                    $query->where('debit_note_number', 'ilike', $term)
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
            ->through(fn (VendorDebitNote $debitNote) => $this->serialize($debitNote, $user))
            ->toArray();

        return Inertia::render('VendorDebitNotes/Index', [
            'vendorDebitNotes' => $vendorDebitNotes,
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
            'bills' => VendorBill::forOrganization($organization)
                ->with('currency')
                ->whereIn('status', [VendorBillStatus::Posted, VendorBillStatus::PartiallyPaid])
                ->orderBy('bill_number')
                ->get()
                ->values()
                ->map(fn (VendorBill $bill) => [
                    'id' => $bill->id,
                    'bill_number' => $bill->bill_number,
                    'vendor_id' => $bill->vendor_id,
                    'balance_due' => (float) $bill->balance_due,
                    'currency' => $bill->currency ? ['code' => $bill->currency->code] : null,
                ]),
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function store(StoreVendorDebitNoteRequest $request): RedirectResponse
    {
        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        if (! $organization) {
            return back()->with('error', 'No organization selected.');
        }

        $this->authorize('create', [VendorDebitNote::class, $organization]);

        try {
            (new CreateVendorDebitNote)->handle($request->validated(), $organization, $user);
        } catch (QueryException $e) {
            logger()->error('Failed to create vendor debit note.', ['organization_id' => $organization->id, 'exception' => $e]);

            return back()->with('error', 'Unable to create the debit note. Please try again.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Debit note draft created successfully.');
    }

    public function post(Request $request, VendorDebitNote $vendorDebitNote): RedirectResponse
    {
        $organization = SwitchOrganization::current($request->user());

        if (! $organization) {
            return back()->with('error', 'No organization selected.');
        }

        if ($vendorDebitNote->organization_id !== $organization->id) {
            abort(404);
        }

        $this->authorize('post', $vendorDebitNote);

        try {
            (new PostVendorDebitNote)->handle($vendorDebitNote, $request->user());

            return back()->with('success', 'Debit note posted to ledger.');
        } catch (QueryException $e) {
            logger()->error('Failed to post vendor debit note.', ['debit_note_id' => $vendorDebitNote->id, 'exception' => $e]);

            return back()->with('error', 'Unable to post the debit note. Please try again.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function void(VoidVendorDebitNoteRequest $request, VendorDebitNote $vendorDebitNote): RedirectResponse
    {
        $organization = SwitchOrganization::current($request->user());

        if (! $organization) {
            return back()->with('error', 'No organization selected.');
        }

        if ($vendorDebitNote->organization_id !== $organization->id) {
            abort(404);
        }

        $this->authorize('void', $vendorDebitNote);

        try {
            (new VoidVendorDebitNote)->handle($vendorDebitNote, $request->user(), $request->input('reason'));

            return back()->with('success', 'Debit note voided.');
        } catch (QueryException $e) {
            logger()->error('Failed to void vendor debit note.', ['debit_note_id' => $vendorDebitNote->id, 'exception' => $e]);

            return back()->with('error', 'Unable to void the debit note. Please try again.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(VendorDebitNote $debitNote, User $user): array
    {
        return [
            'id' => $debitNote->id,
            'ulid' => $debitNote->ulid,
            'debit_note_number' => $debitNote->debit_note_number,
            'date' => $debitNote->date?->format('M d, Y'),
            'vendor' => $debitNote->vendor ? [
                'id' => $debitNote->vendor->id,
                'name' => $debitNote->vendor->name,
            ] : null,
            'bill' => $debitNote->bill ? [
                'id' => $debitNote->bill->id,
                'bill_number' => $debitNote->bill->bill_number,
            ] : null,
            'subtotal' => $debitNote->subtotal,
            'tax_amount' => $debitNote->tax_amount,
            'total_amount' => $debitNote->total_amount,
            'reason' => $debitNote->reason,
            'currency' => $debitNote->currency ? ['code' => $debitNote->currency->code] : null,
            'status' => $debitNote->status->value,
            'journal_entry_id' => $debitNote->journal_entry_id,
            'posted_at' => $debitNote->posted_at?->format('M d, Y'),
            'created_at' => $debitNote->created_at?->format('M d, Y'),
            'can' => [
                'post' => $debitNote->status === VendorDebitNoteStatus::Draft && $user->can('post', $debitNote),
                'void' => $debitNote->status === VendorDebitNoteStatus::Draft && $user->can('void', $debitNote),
            ],
        ];
    }
}
