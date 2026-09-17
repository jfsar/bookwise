import AppLayout from '@/Layouts/AppLayout';
import ReportShell from '@/Pages/Reports/ReportShell';
import useReportFilters from '@/Pages/Reports/useReportFilters';
import { Card } from '@/Components/ui/card';
import { Table, TableBody, TableCell, TableFooter, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

const money = (value, currency = 'PHP') =>
    Number(value ?? 0).toLocaleString(undefined, {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

const bucketColumns = [
    ['Current', 'current'],
    ['1-30 Days', 'days_1_30'],
    ['31-60 Days', 'days_31_60'],
    ['61-90 Days', 'days_61_90'],
    ['90+ Days', 'days_90_plus'],
];

export default function ArAging() {
    const { currency, data, ...report } = useReportFilters('/reports/ar-aging');

    return (
        <>
            <ReportShell title="AR Aging" showAsOf showStandardFilters={false} {...report}>
                <Card className="overflow-hidden">
                    <div className="flex items-center justify-between px-6 py-4">
                        <h4 className="font-display text-base font-semibold">AR Aging</h4>
                        {data?.as_of && (
                            <span className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                As of {data.as_of}
                            </span>
                        )}
                    </div>

                    {data?.rows?.length === 0 || !data ? (
                        <div className="px-6 py-10 text-center text-sm text-on-surface-muted dark:text-on-surface-muted">
                            No outstanding receivables found.
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Customer #</TableHead>
                                    <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Customer Name</TableHead>
                                    {bucketColumns.map(([label]) => (
                                        <TableHead key={label} className="text-right text-xs uppercase tracking-wider text-muted-foreground">
                                            {label}
                                        </TableHead>
                                    ))}
                                    <TableHead className="text-right text-xs uppercase tracking-wider text-muted-foreground">Total</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {data.rows.map((row) => (
                                    <TableRow key={row.customer.id}>
                                        <TableCell className="font-medium text-primary dark:text-primary">{row.customer.number}</TableCell>
                                        <TableCell className="text-sm text-muted-foreground">{row.customer.name}</TableCell>
                                        {bucketColumns.map(([, key]) => (
                                            <TableCell key={key} className="text-right text-sm tabular-nums">
                                                {row[key] > 0 ? money(row[key], currency) : '—'}
                                            </TableCell>
                                        ))}
                                        <TableCell className="text-right text-sm font-semibold tabular-nums">
                                            {money(row.total, currency)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                            <TableFooter>
                                <TableRow className="hover:bg-transparent">
                                    <TableCell colSpan={2} className="text-right text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                        Totals
                                    </TableCell>
                                    {bucketColumns.map(([, key]) => (
                                        <TableCell key={key} className="text-right text-sm font-semibold tabular-nums">
                                            {money(data.totals[key], currency)}
                                        </TableCell>
                                    ))}
                                    <TableCell className="text-right text-sm font-semibold tabular-nums">
                                        {money(data.totals.total, currency)}
                                    </TableCell>
                                </TableRow>
                            </TableFooter>
                        </Table>
                    )}
                </Card>
            </ReportShell>
        </>
    );
}

ArAging.layout = (page) => <AppLayout>{page}</AppLayout>;