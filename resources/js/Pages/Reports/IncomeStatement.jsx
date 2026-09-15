import AppLayout from '@/Layouts/AppLayout';
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

export default function IncomeStatement() {
    const { currency, data, ...report } = useReportFilters('/reports/income-statement');

    return (
        <>
            <ReportShell title="Income Statement" {...report}>
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