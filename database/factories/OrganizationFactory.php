<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'display_name' => $name,
            'code' => strtoupper(fake()->unique()->bothify('???###')),
            'country_code' => 'US',
            'base_currency_id' => Currency::factory(),
            'timezone' => 'UTC',
            'fiscal_year_start_month' => 1,
            'date_format' => 'Y-m-d',
            'decimal_places' => 2,
            'status' => 'active',
            'created_by' => User::factory(),
        ];
    }
}
