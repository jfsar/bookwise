<?php

namespace App\Actions\FiscalYear;

use App\Actions\Action;
use App\Enums\AccountingPeriodStatus;
use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Models\Organization;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CreateFiscalYear
{
    use Action;

    public function handle(array $data, Organization $organization, User $creator): FiscalYear
    {
        return DB::transaction(function () use ($data, $organization) {
            $startDate = Carbon::parse($data['start_date']);
            $endDate = Carbon::parse($data['end_date']);

            $fiscalYear = FiscalYear::create([
                'organization_id' => $organization->id,
                'name' => $data['name'],
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => 'open',
            ]);

            $this->generatePeriods($fiscalYear, $organization);

            return $fiscalYear;
        });
    }

    protected function generatePeriods(FiscalYear $fiscalYear, Organization $organization): void
    {
        $current = $fiscalYear->start_date->copy()->startOfMonth();
        $periodNumber = 1;

        while ($current->lte($fiscalYear->end_date)) {
            $periodEnd = $current->copy()->endOfMonth();

            if ($periodEnd->gt($fiscalYear->end_date)) {
                $periodEnd = $fiscalYear->end_date->copy();
            }

            AccountingPeriod::create([
                'fiscal_year_id' => $fiscalYear->id,
                'organization_id' => $organization->id,
                'name' => $current->format('F Y'),
                'period_number' => $periodNumber,
                'start_date' => $current->copy(),
                'end_date' => $periodEnd,
                'status' => AccountingPeriodStatus::Open,
            ]);

            $current->addMonth()->startOfMonth();
            $periodNumber++;
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ];
    }
}
