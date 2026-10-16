<?php

namespace App\Actions\VendorDebitNote;

use App\Actions\Action;
use App\Enums\VendorBillStatus;
use App\Enums\VendorDebitNoteStatus;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Models\VendorDebitNote;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class CreateVendorDebitNote
{
    use Action;

    public function handle(array $data, Organization $organization, User $creator): VendorDebitNote
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

    protected function persist(array $data, Organization $organization, User $creator): VendorDebitNote
    {
        return DB::transaction(function () use ($data, $organization, $creator) {
            if (! empty($data['idempotency_key'])) {
                $existing = $this->findByIdempotencyKey($organization, $data['idempotency_key']);

                if ($existing) {
                    return $existing;
                }
            }

            $vendor = Vendor::where('id', $data['vendor_id'])
                ->where('organization_id', $organization->id)
                ->first();

            if (! $vendor) {
                throw new \RuntimeException('The vendor must belong to the same organization.');
            }

            if (! $vendor->isActive()) {
                throw new \RuntimeException('The vendor must be active.');
            }

            $bill = VendorBill::where('id', $data['bill_id'])
                ->where('organization_id', $organization->id)
                ->first();

            if (! $bill) {
                throw new \RuntimeException('The bill must belong to the same organization.');
            }

            if ($bill->vendor_id !== $vendor->id) {
                throw new \RuntimeException('The bill must belong to the same vendor.');
            }

            if (! in_array($bill->status, [VendorBillStatus::Posted, VendorBillStatus::PartiallyPaid], true)) {
                throw new \RuntimeException('Only posted bills can be debited.');
            }

            $subtotal = round((float) $data['subtotal'], 4);
            $taxAmount = round((float) $data['tax_amount'], 4);
            $totalAmount = round($subtotal + $taxAmount, 4);

            if ($totalAmount <= 0) {
                throw new \RuntimeException('The debit note total must be greater than zero.');
            }

            $claimed = $this->debitNotesForBill($bill)
                ->where('status', '!=', VendorDebitNoteStatus::Voided)
                ->sum('total_amount');

            $available = round((float) $bill->balance_due - (float) $claimed, 4);

            if ($totalAmount > $available + 0.0001) {
                throw new \RuntimeException('The debit note total must not exceed the outstanding balance of the bill.');
            }

            $debitNote = VendorDebitNote::create([
                'organization_id' => $organization->id,
                'vendor_id' => $vendor->id,
                'bill_id' => $bill->id,
                'debit_note_number' => $this->generateDebitNoteNumber($organization),
                'date' => Carbon::parse($data['date'])->toDateString(),
                'currency_id' => $bill->currency_id,
                'exchange_rate' => (float) $bill->exchange_rate,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'reason' => $data['reason'],
                'status' => VendorDebitNoteStatus::Draft,
                'idempotency_key' => $this->nullable($data['idempotency_key'] ?? null),
                'created_by' => $creator->id,
            ]);

            app(AuditLogService::class)->record(
                $organization,
                $creator->id,
                'vendor-debit-note.created',
                VendorDebitNote::class,
                $debitNote->id,
                null,
                $debitNote->only(['debit_note_number', 'bill_id', 'vendor_id', 'total_amount', 'status'])
            );

            return $debitNote->fresh();
        });
    }

    protected function debitNotesForBill(VendorBill $bill)
    {
        return VendorDebitNote::forOrganization($bill->organization)
            ->where('bill_id', $bill->id);
    }

    protected function generateDebitNoteNumber(Organization $organization): string
    {
        DB::table('organizations')
            ->where('id', $organization->id)
            ->increment('vendor_debit_note_number_sequence');

        $sequence = DB::table('organizations')
            ->where('id', $organization->id)
            ->value('vendor_debit_note_number_sequence');

        return 'DBN-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    protected function findByIdempotencyKey(Organization $organization, string $key): ?VendorDebitNote
    {
        return VendorDebitNote::forOrganization($organization)
            ->where('idempotency_key', $key)
            ->first();
    }

    protected function isIdempotencyConflict(QueryException $e, Organization $organization, array $data): bool
    {
        if (empty($data['idempotency_key']) || $e->getCode() !== '23505') {
            return false;
        }

        return str_contains($e->getMessage(), 'vendor_debit_notes_organization_id_idempotency_key_unique');
    }

    protected function nullable(mixed $value): ?string
    {
        return $value === '' ? null : $value;
    }

    public function rules(): array
    {
        return [
            'vendor_id' => ['required', 'exists:vendors,id'],
            'bill_id' => ['required', 'exists:vendor_bills,id'],
            'date' => ['required', 'date'],
            'subtotal' => ['required', 'numeric', 'gte:0'],
            'tax_amount' => ['required', 'numeric', 'gte:0'],
            'reason' => ['required', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ];
    }
}
