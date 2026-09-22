<?php

namespace App\Enums;

enum ReportType: string
{
    case GeneralLedger = 'general-ledger';
    case TrialBalance = 'trial-balance';
    case IncomeStatement = 'income-statement';
    case BalanceSheet = 'balance-sheet';
    case CashFlow = 'cash-flow';
    case ArAging = 'ar-aging';
    case ApAging = 'ap-aging';

    public function label(): string
    {
        return match ($this) {
            self::GeneralLedger => 'General Ledger',
            self::TrialBalance => 'Trial Balance',
            self::IncomeStatement => 'Income Statement',
            self::BalanceSheet => 'Balance Sheet',
            self::CashFlow => 'Cash Flow Statement',
            self::ArAging => 'AR Aging',
            self::ApAging => 'AP Aging',
        };
    }
}
