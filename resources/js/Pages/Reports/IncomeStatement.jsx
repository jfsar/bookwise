import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';

import AppLayout from '@/Layouts/AppLayout';
import MoneyRow from '@/Pages/Reports/StatementSummaryRow';
import StatementSection from '@/Pages/Reports/StatementSection';
import ReportShell from '@/Pages/Reports/ReportShell';

const money = (value, currency = 'PHP') =>
    Number(value ?? 0).toLocaleString(undefined, {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

export default function IncomeStatement() {
    const { reportType, currency, data, filters: initialFilters, accounts, journals, fiscalYears, accountingPeriods, recentExports } = usePage().props;

    const [filters, setFilters] = useState(initialFilters ?? {});
    const [exporting, setExporting] = useState(false);

    function applyFilters() {
        router.get('/reports/income-statement', filters, { preserveState: true, replace: true });
    }

    function handleExport() {
        setExporting(true);

        router.post('/reports/export', { report_type: reportType, ...filters }, {
            onFinish: () => setExporting(false),
        });
    }

    return (
        <>
            <ReportShell
                title="Income Statement"
                reportType={reportType}
                filters={filters}
                setFilters={setFilters}
                applyFilters={applyFilters}
                accounts={accounts}
                journals={journals}
                fiscalYears={fiscalYears}
                accountingPeriods={accountingPeriods}
                recentExports={recentExports}
                exporting={exporting}
                onExport={handleExport}
            >
                <div className="grid gap-4">
                    <StatementSection title="Revenue" trees={data?.sections?.revenue} currency={currency} />
                    <StatementSection title="Expenses" trees={data?.sections?.expenses} currency={currency} />

                    {data && (
                        <MoneyRow
                            label="Net Income"
                            value={money(data.totals.net_income, currency)}
                        />
                    )}
                </div>
            </ReportShell>
        </>
    );
}

IncomeStatement.layout = (page) => <AppLayout>{page}</AppLayout>;