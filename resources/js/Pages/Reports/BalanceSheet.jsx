import AppLayout from '@/Layouts/AppLayout';
import StatusBadge from '@/Components/StatusBadge';
import MoneyRow from '@/Pages/Reports/StatementSummaryRow';
import StatementSection from '@/Pages/Reports/StatementSection';
import ReportShell from '@/Pages/Reports/ReportShell';
import useReportFilters from '@/Pages/Reports/useReportFilters';

const money = (value, currency = 'PHP') =>
    Number(value ?? 0).toLocaleString(undefined, {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

export default function BalanceSheet() {
    const { currency, data, ...report } = useReportFilters('/reports/balance-sheet');

    return (
        <>
            <ReportShell title="Balance Sheet" {...report}>
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