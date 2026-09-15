import AppLayout from '@/Layouts/AppLayout';
import ReportShell from '@/Pages/Reports/ReportShell';
import useReportFilters from '@/Pages/Reports/useReportFilters';
import { Card } from '@/Components/ui/card';
import { Table, TableBody, TableCell, TableFooter, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

function formatMoney(value, currency = 'PHP') {
    return Number(value ?? 0).toLocaleString(undefined, {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

export default function GeneralLedger() {
    const { currency, data, ...report } = useReportFilters('/reports/general-ledger');

    return (
        <>
            <ReportShell title="General Ledger" {...report}>
                <Card className="overflow-hidden">
                    {data?.rows?.length === 0 || !data ? (
                        <div className="px-6 py-10 text-center text-sm text-on-surface-muted dark:text-on-surface-muted">
                            No general ledger records found. Adjust filters or select a date range.
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Entry #</TableHead>
                                    <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Date</TableHead>
                                    <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Reference</TableHead>
                                    <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Description</TableHead>
                                    <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Journal</TableHead>
                                    <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Account</TableHead>
                                    <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Debit</TableHead>
                                    <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Credit</TableHead>
                                    <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Running Balance</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {data.rows.map((row) => (
                                    <TableRow key={row.line_id}>
                                        <TableCell className="font-medium text-primary dark:text-primary">{row.entry_number}</TableCell>
                                        <TableCell className="text-sm text-muted-foreground">{row.entry_date}</TableCell>
                                        <TableCell className="text-sm text-muted-foreground">{row.reference ?? '—'}</TableCell>
                                        <TableCell className="max-w-xs truncate text-sm text-muted-foreground">{row.description ?? '—'}</TableCell>
                                        <TableCell className="text-sm text-muted-foreground">{row.journal_code ?? '—'}</TableCell>
                                        <TableCell className="text-sm">
                                            <span className="text-muted-foreground">{row.account.code}</span> — {row.account.name}
                                        </TableCell>
                                        <TableCell className="text-right text-sm tabular-nums">
                                            {row.debit > 0 ? formatMoney(row.debit, currency) : '—'}
                                        </TableCell>
                                        <TableCell className="text-right text-sm tabular-nums">
                                            {row.credit > 0 ? formatMoney(row.credit, currency) : '—'}
                                        </TableCell>
                                        <TableCell className="text-right text-sm font-medium tabular-nums">
                                            {formatMoney(row.running_balance, currency)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                            <TableFooter>
                                <TableRow className="hover:bg-transparent">
                                    <TableCell colSpan={6} className="text-right text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                        Totals
                                    </TableCell>
                                    <TableCell className="text-right text-sm font-semibold tabular-nums">
                                        {formatMoney(data.totals.debit, currency)}
                                    </TableCell>
                                    <TableCell className="text-right text-sm font-semibold tabular-nums">
                                        {formatMoney(data.totals.credit, currency)}
                                    </TableCell>
                                    <TableCell />
                                </TableRow>
                            </TableFooter>
                        </Table>
                    )}
                </Card>
            </ReportShell>
        </>
    );
}

GeneralLedger.layout = (page) => <AppLayout>{page}</AppLayout>;