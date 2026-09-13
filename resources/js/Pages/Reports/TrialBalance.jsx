import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';

import AppLayout from '@/Layouts/AppLayout';
import StatusBadge from '@/Components/StatusBadge';
import ReportShell from '@/Pages/Reports/ReportShell';
import { Card } from '@/Components/ui/card';
import { Table, TableBody, TableCell, TableFooter, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

const money = (value, currency = 'PHP') =>
    Number(value ?? 0).toLocaleString(undefined, {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

const columns = [
    ['Opening', 'opening_debit', 'opening_credit'],
    ['Period', 'period_debit', 'period_credit'],
    ['Closing', 'closing_debit', 'closing_credit'],
];

export default function TrialBalance() {
    const { reportType, currency, data, filters: initialFilters, accounts, journals, fiscalYears, accountingPeriods, recentExports } = usePage().props;

    const [filters, setFilters] = useState(initialFilters ?? {});
    const [exporting, setExporting] = useState(false);

    function applyFilters() {
        router.get('/reports/trial-balance', filters, { preserveState: true, replace: true });
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
                title="Trial Balance"
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
                <Card className="overflow-hidden">
                    <div className="flex items-center justify-between px-6 py-4">
                        <h4 className="font-display text-base font-semibold">Trial Balance</h4>
                        {data?.balanced === true ? (
                            <StatusBadge tone="success">Balanced</StatusBadge>
                        ) : (
                            <StatusBadge tone="error">Out of balance</StatusBadge>
                        )}
                    </div>

                    {data?.rows?.length === 0 || !data ? (
                        <div className="px-6 py-10 text-center text-sm text-on-surface-muted dark:text-on-surface-muted">
                            No accounts with activity found. Adjust filters or select a date range.
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Account Code</TableHead>
                                    <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Account Name</TableHead>
                                    {columns.map(([label]) => (
                                        <TableHead key={label} colSpan={2} className="text-center text-xs uppercase tracking-wider text-muted-foreground">
                                            {label}
                                        </TableHead>
                                    ))}
                                </TableRow>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead />
                                    <TableHead />
                                    {columns.flatMap(([label]) => [
                                        <TableHead key={`${label}d`} className="text-xs font-medium text-muted-foreground">
                                            Debit
                                        </TableHead>,
                                        <TableHead key={`${label}c`} className="text-xs font-medium text-muted-foreground">
                                            Credit
                                        </TableHead>,
                                    ])}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {data.rows.map((row) => (
                                    <TableRow key={row.account.id}>
                                        <TableCell className="font-medium text-primary dark:text-primary">{row.account.code}</TableCell>
                                        <TableCell className="text-sm text-muted-foreground">{row.account.name}</TableCell>
                                        {columns.flatMap(([, debitKey, creditKey]) => [
                                            <TableCell key={debitKey} className="text-right text-sm tabular-nums">
                                                {row[debitKey] > 0 ? money(row[debitKey], currency) : '—'}
                                            </TableCell>,
                                            <TableCell key={creditKey} className="text-right text-sm tabular-nums">
                                                {row[creditKey] > 0 ? money(row[creditKey], currency) : '—'}
                                            </TableCell>,
                                        ])}
                                    </TableRow>
                                ))}
                            </TableBody>
                            <TableFooter>
                                <TableRow className="hover:bg-transparent">
                                    <TableCell colSpan={2} className="text-right text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                        Totals
                                    </TableCell>
                                    {columns.flatMap(([, debitKey, creditKey]) => [
                                        <TableCell key={debitKey} className="text-right text-sm font-semibold tabular-nums">
                                            {money(data.totals[debitKey], currency)}
                                        </TableCell>,
                                        <TableCell key={creditKey} className="text-right text-sm font-semibold tabular-nums">
                                            {money(data.totals[creditKey], currency)}
                                        </TableCell>,
                                    ])}
                                </TableRow>
                            </TableFooter>
                        </Table>
                    )}
                </Card>
            </ReportShell>
        </>
    );
}

TrialBalance.layout = (page) => <AppLayout>{page}</AppLayout>;