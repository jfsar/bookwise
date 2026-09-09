<?php

namespace Database\Factories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Currency>
 */
class CurrencyFactory extends Factory
{
    protected $model = Currency::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->currencyCode()),
            'name' => fake()->currencyCode(),
            'symbol' => '$',
            'decimal_places' => 2,
            'is_active' => true,
        ];
    }
}
