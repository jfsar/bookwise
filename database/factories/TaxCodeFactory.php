<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Organization;
use App\Models\TaxCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxCode>
 */
class TaxCodeFactory extends Factory
{
    protected $model = TaxCode::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'code' => strtoupper(fake()->unique()->bothify('TX-??')),
            'name' => fake()->words(2, true),
            'type' => fake()->randomElement(['vat', 'percentage_tax', 'zero_rated', 'exempt']),
            'jurisdiction' => 'PH',
            'tax_account_id' => fn (array $attributes) => Account::factory()->create([
                'organization_id' => $attributes['organization_id'],
            ])->id,
            'is_active' => true,
        ];
    }
}
