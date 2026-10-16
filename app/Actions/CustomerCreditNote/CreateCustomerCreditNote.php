<?php

namespace App\Actions\CustomerCreditNote;

use App\Actions\Action;
use App\Enums\CustomerCreditNoteStatus;
use App\Enums\CustomerInvoiceStatus;
use App\Models\Customer;
use App\Models\CustomerCreditNote;
use App\Models\CustomerInvoice;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class CreateCustomerCreditNote
{
    use Action;

    public function handle(array $data, Organization $organization, User $creator): CustomerCreditNote
    {
        try {
            return $this->persist($data, $organization, $creator);
        } catch (QueryException $e) {
            if ($this->isIdempotencyConflict($e, $organization, $data)) {
                $existing = $this->findByIdempotencyKey($organization, $data['idempotency_key']);

                if ($existing) {
                    return $existing;
                }
            }

            throw $e;
        }
    }

    protected function persist(array $data, Organization $organization, User $creator): CustomerCreditNote
    {
        return DB::transaction(function () use ($data, $organization, $creator) {
            if (! empty($data['idempotency_key'])) {
                $existing = $this->findByIdempotencyKey($organization, $data['idempotency_key']);

                if ($existing) {
                    return $existing;
                }
            }

            $customer = Customer::where('id', $data['customer_id'])
                ->where('organization_id', $organization->id)
                ->first();

            if (! $customer) {
                throw new \RuntimeException('The customer must belong to the same organization.');
            }

            if (! $customer->isActive()) {
                throw new \RuntimeException('The customer must be active.');
            }

            $invoice = CustomerInvoice::where('id', $data['invoice_id'])
                ->where('organization_id', $organization->id)
                ->first();

            if (! $invoice) {
                throw new \RuntimeException('The invoice must belong to the same organization.');
            }

            if ($invoice->customer_id !== $customer->id) {
                throw new \RuntimeException('The invoice must belong to the same customer.');
            }

            if (! in_array($invoice->status, [CustomerInvoiceStatus::Posted, CustomerInvoiceStatus::PartiallyPaid], true)) {
                throw new \RuntimeException('Only posted invoices can be credited.');
            }

            $subtotal = round((float) $data['subtotal'], 4);
            $taxAmount = round((float) $data['tax_amount'], 4);
            $totalAmount = round($subtotal + $taxAmount, 4);

            if ($totalAmount <= 0) {
                throw new \RuntimeException('The credit note total must be greater than zero.');
            }

            $claimed = $this->creditNotesForInvoice($invoice)
                ->where('status', '!=', CustomerCreditNoteStatus::Voided)
                ->sum('total_amount');

            $available = round((float) $invoice->balance_due - (float) $claimed, 4);

            if ($totalAmount > $available + 0.0001) {
                throw new \RuntimeException('The credit note total must not exceed the outstanding balance of the invoice.');
            }

            $creditNote = CustomerCreditNote::create([
                'organization_id' => $organization->id,
                'customer_id' => $customer->id,
                'invoice_id' => $invoice->id,
                'credit_note_number' => $this->generateCreditNoteNumber($organization),
                'date' => Carbon::parse($data['date'])->toDateString(),
                'currency_id' => $invoice->currency_id,
                'exchange_rate' => (float) $invoice->exchange_rate,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'reason' => $data['reason'],
                'status' => CustomerCreditNoteStatus::Draft,
                'idempotency_key' => $this->nullable($data['idempotency_key'] ?? null),
                'created_by' => $creator->id,
            ]);

            app(AuditLogService::class)->record(
                $organization,
                $creator->id,
                'customer-credit-note.created',
                CustomerCreditNote::class,
                $creditNote->id,
                null,
                $creditNote->only(['credit_note_number', 'invoice_id', 'customer_id', 'total_amount', 'status'])
            );

            return $creditNote->fresh();
        });
    }

    protected function creditNotesForInvoice(CustomerInvoice $invoice)
    {
        return CustomerCreditNote::forOrganization($invoice->organization)
            ->where('invoice_id', $invoice->id);
    }

    protected function generateCreditNoteNumber(Organization $organization): string
    {
        DB::table('organizations')
            ->where('id', $organization->id)
            ->increment('customer_credit_note_number_sequence');

        $sequence = DB::table('organizations')
            ->where('id', $organization->id)
            ->value('customer_credit_note_number_sequence');

        return 'CN-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    protected function findByIdempotencyKey(Organization $organization, string $key): ?CustomerCreditNote
    {
        return CustomerCreditNote::forOrganization($organization)
            ->where('idempotency_key', $key)
            ->first();
    }

    protected function isIdempotencyConflict(QueryException $e, Organization $organization, array $data): bool
    {
        if (empty($data['idempotency_key']) || $e->getCode() !== '23505') {
            return false;
        }

        return str_contains($e->getMessage(), 'customer_credit_notes_organization_id_idempotency_key_unique');
    }

    protected function nullable(mixed $value): ?string
    {
        return $value === '' ? null : $value;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'invoice_id' => ['required', 'exists:customer_invoices,id'],
            'date' => ['required', 'date'],
            'subtotal' => ['required', 'numeric', 'gte:0'],
            'tax_amount' => ['required', 'numeric', 'gte:0'],
            'reason' => ['required', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ];
    }
}
