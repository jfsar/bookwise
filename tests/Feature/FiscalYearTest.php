<?php

namespace Tests\Feature;

use App\Actions\FiscalYear\CreateFiscalYear;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FiscalYearTest extends TestCase
{
    use RefreshDatabase;

    public function test_fiscal_year_creates_monthly_periods(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();

        $fiscalYear = (new CreateFiscalYear)->handle([
            'name' => 'FY 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ], $org, $user);

        $this->assertDatabaseHas('fiscal_years', ['id' => $fiscalYear->id]);

        $periods = $fiscalYear->periods()->orderBy('period_number')->get();
        $this->assertEquals(12, $periods->count());
        $this->assertEquals('January 2026', $periods->first()->name);
        $this->assertEquals('December 2026', $periods->last()->name);
    }

    public function test_fiscal_year_handles_final_partial_period(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();

        $fiscalYear = (new CreateFiscalYear)->handle([
            'name' => 'FY 2026-27',
            'start_date' => '2026-06-15',
            'end_date' => '2027-05-15',
        ], $org, $user);

        $this->assertDatabaseHas('fiscal_years', ['id' => $fiscalYear->id]);

        $periods = $fiscalYear->periods()->orderBy('period_number')->get();
        $this->assertEquals(12, $periods->count());
        $this->assertEquals('2026-06-01', $periods->first()->start_date->toDateString());
        $this->assertEquals('2027-05-15', $periods->last()->end_date->toDateString());
    }
}
