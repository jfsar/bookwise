<?php

namespace App\Reports;

use App\Enums\ReportType;
use App\Models\Organization;

class ReportFactory
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public static function make(ReportType $type, Organization $organization, array $filters = []): ReportQuery
    {
        return match ($type) {
            ReportType::GeneralLedger => new GeneralLedgerReport($organization, $filters),
            ReportType::TrialBalance => new TrialBalanceReport($organization, $filters),
            ReportType::IncomeStatement => new IncomeStatementReport($organization, $filters),
            ReportType::BalanceSheet => new BalanceSheetReport($organization, $filters),
            ReportType::CashFlow => new CashFlowReport($organization, $filters),
        };
    }
}
