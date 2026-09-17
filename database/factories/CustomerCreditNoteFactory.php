<?php

namespace Database\Factories;

use App\Enums\CustomerCreditNoteStatus;
use App\Models\Account;
use App\Models\Customer;
use App\Models\CustomerCreditNote;
use App\Models\CustomerInvoice;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerCreditNote>
 */
class CustomerCreditNoteFactory extends Factory
{
    protected $model = CustomerCreditNote::class;

    public function definition(): array
    {
        return [
            'invoice_id' => fn () => $this->defaultInvoice()->id,
            'organization_id' => fn (array $attributes) => CustomerInvoice::find($attributes['invoice_id'])?->organization_id,
            'customer_id' => fn (array $attributes) => CustomerInvoice::find($attributes['invoice_id'])?->customer_id,
            'credit_note_number' => strtoupper(fake()->unique()->bothify('CN-??###')),
            'date' => now()->toDateString(),
            'currency_id' => fn (array $attributes) => CustomerInvoice::find($attributes['invoice_id'])?->currency_id,
            'exchange_rate' => fn (array $attributes) => (float) (CustomerInvoice::find($attributes['invoice_id'])?->exchange_rate ?? 1),
            'subtotal' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
            'reason' => fake()->sentence(),
            'journal_entry_id' => null,
            'status' => CustomerCreditNoteStatus::Draft,
            'posted_at' => null,
            'idempotency_key' => null,
            'created_by' => User::factory(),
        ];
    }

    protected function defaultInvoice(): CustomerInvoice
    {
        $organization = Organization::factory()->create();
        $receivable = Account::factory()->create(['organization_id' => $organization->id]);
        $customer = Customer::factory()->create([
            'organization_id' => $organization->id,
            'receivable_account_id' => $receivable->id,
        ]);

        return CustomerInvoice::factory()->posted()->create([
            'organization_id' => $organization->id,
            'customer_id' => $customer->id,
            'currency_id' => $organization->base_currency_id,
            'base_currency_id' => $organization->base_currency_id,
        ]);
    }

    public function posted(): static
    {
        return $this->state(fn () => [
            'status' => CustomerCreditNoteStatus::Posted,
            'posted_at' => now(),
        ]);
    }

    public function voided(): static
    {
        return $this->state(fn () => [
            'status' => CustomerCreditNoteStatus::Voided,
        ]);
    }
}
