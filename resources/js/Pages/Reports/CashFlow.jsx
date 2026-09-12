import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';

import AppLayout from '@/Layouts/AppLayout';
import MoneyRow from '@/Pages/Reports/StatementSummaryRow';
import ReportShell from '@/Pages/Reports/ReportShell';
import { Card } from '@/Components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

const money = (value, currency = 'PHP') =>
    Number(value ?? 0).toLocaleString(undefined, {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

const sections = [
    ['operating', 'Operating Activities'],
    ['investing', 'Investing Activities'],
    ['financing', 'Financing Activities'],
];

export default function CashFlow() {
    const { reportType, currency, data, filters: initialFilters, accounts, journals, fiscalYears, accountingPeriods, recentExports } = usePage().props;

    const [filters, setFilters] = useState(initialFilters ?? {});
    const [exporting, setExporting] = useState(false);

    function applyFilters() {
        router.get('/reports/cash-flow', filters, { preserveState: true, replace: true });
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
                title="Cash Flow Statement"
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
                    {sections.map(([key, label]) => {
                        const items = data?.sections?.[key] ?? [];
                        const total = data?.totals?.[key] ?? 0;

                        return (
                            <Card key={key} className="overflow-hidden">
                                <div className="px-6 py-4">
                                    <h4 className="font-display text-base font-semibold">{label}</h4>
                                </div>

                                {items.length === 0 ? (
                                    <div className="px-6 pb-6 text-sm text-on-surface-muted dark:text-on-surface-muted">
                                        No accounts classified under this section.
                                    </div>
                                ) : (
                                    <Table>
                                        <TableHeader>
                                            <TableRow className="hover:bg-transparent">
                                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Code</TableHead>
                                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Account</TableHead>
                                                <TableHead className="text-right text-xs uppercase tracking-wider text-muted-foreground">Amount</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {items.map((item) => (
                                                <TableRow key={item.account.id}>
                                                    <TableCell className="text-sm tabular-nums text-muted-foreground">{item.account.code}</TableCell>
                                                    <TableCell className="text-sm text-muted-foreground">{item.account.name}</TableCell>
                                                    <TableCell className="text-right text-sm font-medium tabular-nums">
                                                        {money(item.amount, currency)}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                )}

                                <div className="border-t px-6 py-3">
                                    <span className="text-sm font-semibold">Net cash — {label}: </span>
                                    <span className="text-sm font-bold tabular-nums">{money(total, currency)}</span>
                                </div>
                            </Card>
                        );
                    })}

                    {data && <MoneyRow label="Net change in cash accounts" value={money(data.net_cash_change, currency)} muted />}
                </div>
            </ReportShell>
        </>
    );
}

CashFlow.layout = (page) => <AppLayout>{page}</AppLayout>;