<?php

namespace Database\Factories;

use App\Enums\VendorDebitNoteStatus;
use App\Models\Account;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Models\VendorBillLine;
use App\Models\VendorDebitNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorDebitNote>
 */
class VendorDebitNoteFactory extends Factory
{
    protected $model = VendorDebitNote::class;

    public function definition(): array
    {
        return [
            'bill_id' => fn () => $this->defaultBill()->id,
            'organization_id' => fn (array $attributes) => VendorBill::find($attributes['bill_id'])?->organization_id,
            'vendor_id' => fn (array $attributes) => VendorBill::find($attributes['bill_id'])?->vendor_id,
            'debit_note_number' => strtoupper(fake()->unique()->bothify('DBN-??###')),
            'date' => now()->toDateString(),
            'currency_id' => fn (array $attributes) => VendorBill::find($attributes['bill_id'])?->currency_id,
            'exchange_rate' => fn (array $attributes) => (float) (VendorBill::find($attributes['bill_id'])?->exchange_rate ?? 1),
            'subtotal' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
            'reason' => fake()->sentence(),
            'journal_entry_id' => null,
            'status' => VendorDebitNoteStatus::Draft,
            'posted_at' => null,
            'idempotency_key' => null,
            'created_by' => User::factory(),
        ];
    }

    protected function defaultBill(): VendorBill
    {
        $organization = Organization::factory()->create();
        $payable = Account::factory()->create(['organization_id' => $organization->id]);
        $vendor = Vendor::factory()->create([
            'organization_id' => $organization->id,
            'payable_account_id' => $payable->id,
        ]);

        $bill = VendorBill::factory()->posted()->create([
            'organization_id' => $organization->id,
            'vendor_id' => $vendor->id,
            'currency_id' => $organization->base_currency_id,
            'base_currency_id' => $organization->base_currency_id,
            'subtotal' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
            'paid_amount' => 0,
            'balance_due' => 0,
        ]);

        VendorBillLine::create([
            'organization_id' => $organization->id,
            'bill_id' => $bill->id,
            'line_number' => 1,
            'description' => 'Factory service',
            'quantity' => 1,
            'unit_price' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'line_total' => 0,
            'expense_account_id' => Account::factory()->create(['organization_id' => $organization->id])->id,
            'tax_code_id' => null,
            'currency_id' => $organization->base_currency_id,
        ]);

        return $bill;
    }

    public function posted(): static
    {
        return $this->state(fn () => [
            'status' => VendorDebitNoteStatus::Posted,
            'posted_at' => now(),
        ]);
    }

    public function voided(): static
    {
        return $this->state(fn () => [
            'status' => VendorDebitNoteStatus::Voided,
        ]);
    }
}
