<?php

namespace Database\Factories;

use App\Models\TaxCode;
use App\Models\TaxRate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<TaxRate>
 */
class TaxRateFactory extends Factory
{
    protected $model = TaxRate::class;

    public function definition(): array
    {
        return [
            'tax_code_id' => TaxCode::factory(),
            'organization_id' => fn (array $attributes) => TaxCode::find($attributes['tax_code_id'])->organization_id,
            'rate' => 12,
            'rate_type' => 'standard',
            'effective_from' => Carbon::create(2000, 1, 1)->toDateString(),
            'effective_to' => null,
        ];
    }
}
