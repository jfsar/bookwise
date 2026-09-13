import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';

import AppLayout from '@/Layouts/AppLayout';
import StatusBadge from '@/Components/StatusBadge';
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

export default function BalanceSheet() {
    const { reportType, currency, data, filters: initialFilters, accounts, journals, fiscalYears, accountingPeriods, recentExports } = usePage().props;

    const [filters, setFilters] = useState(initialFilters ?? {});
    const [exporting, setExporting] = useState(false);

    function applyFilters() {
        router.get('/reports/balance-sheet', filters, { preserveState: true, replace: true });
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
                title="Balance Sheet"
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
                <div className="mb-4 flex justify-end">
                    {data?.balanced === true ? (
                        <StatusBadge tone="success">Assets = Liabilities + Equity</StatusBadge>
                    ) : (
                        <StatusBadge tone="error">Out of balance</StatusBadge>
                    )}
                </div>

                <div className="grid gap-4">
                    <StatementSection title="Assets" trees={data?.sections?.assets} currency={currency} />
                    <StatementSection title="Liabilities" trees={data?.sections?.liabilities} currency={currency} />
                    <StatementSection title="Equity" trees={data?.sections?.equity} currency={currency} />

                    {data && (
                        <>
                            <MoneyRow label="Retained earnings (to date)" value={money(data.retained_earnings, currency)} muted />
                            <MoneyRow
                                label="Total Equity (incl. retained earnings)"
                                value={money(data.totals.equity, currency)}
                            />
                        </>
                    )}
                </div>
            </ReportShell>
        </>
    );
}

BalanceSheet.layout = (page) => <AppLayout>{page}</AppLayout>;