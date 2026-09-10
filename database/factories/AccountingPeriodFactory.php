<?php

namespace Database\Factories;

use App\Enums\AccountingPeriodStatus;
use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Models\Organization;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountingPeriod>
 */
class AccountingPeriodFactory extends Factory
{
    protected $model = AccountingPeriod::class;

    public function definition(): array
    {
        $month = fake()->numberBetween(1, 12);
        $year = fake()->year();

        return [
            'fiscal_year_id' => FiscalYear::factory(),
            'organization_id' => fn (array $attributes) => FiscalYear::find($attributes['fiscal_year_id'])?->organization_id ?? Organization::factory(),
            'name' => Carbon::create()->month($month)->format('F').' '.$year,
            'period_number' => $month,
            'start_date' => Carbon::create($year, $month, 1),
            'end_date' => Carbon::create($year, $month, 1)->endOfMonth(),
            'status' => AccountingPeriodStatus::Open,
        ];
    }
}
