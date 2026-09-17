import { useEffect, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';

import StatusBadge from '@/Components/StatusBadge';
import { Button } from '@/Components/ui/button';
import { Card } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { NativeSelect, NativeSelectOption } from '@/Components/ui/native-select';

const exportStatusTones = {
    queued: 'warning',
    generating: 'info',
    completed: 'success',
    failed: 'error',
};

const POLL_INTERVAL_MS = 10000;

export default function ReportShell({
    title,
    filters,
    setFilters,
    applyFilters,
    resetFilters,
    accounts,
    journals,
    fiscalYears,
    accountingPeriods,
    recentExports,
    exporting = false,
    hasRecords = false,
    onExport,
    showStandardFilters = true,
    showAsOf = false,
    children,
}) {
    const { errors = {} } = usePage().props;

    const filterFields = [
        ...(showStandardFilters ? [
            ['account_id', 'Account'],
            ['journal_id', 'Journal'],
            ['fiscal_year_id', 'Fiscal Year'],
            ['accounting_period_id', 'Accounting Period'],
            ['start_date', 'Start Date'],
            ['end_date', 'End Date'],
            ['reference', 'Reference'],
        ] : []),
        ...(showAsOf ? [['as_of', 'As Of']] : []),
    ];

    const [liveExports, setLiveExports] = useState(null);

    const exportItems = liveExports ?? recentExports ?? [];
    const hasPendingExport = exportItems.some(
        (item) => item.status === 'queued' || item.status === 'generating'
    );

    useEffect(() => {
        if (!hasPendingExport) {
            return;
        }

        let cancelled = false;

        async function refresh() {
            try {
                const response = await fetch('/reports/exports', {
                    headers: { Accept: 'application/json' },
                });

                if (!response.ok || cancelled) {
                    return;
                }

                const payload = await response.json();
                if (!cancelled) {
                    setLiveExports(payload.recentExports ?? []);
                }
            } catch {
                // Transient failures are retried on the next interval.
            }
        }

        refresh();
        const intervalId = setInterval(refresh, POLL_INTERVAL_MS);

        return () => {
            cancelled = true;
            clearInterval(intervalId);
        };
    }, [hasPendingExport]);

    const previousRecentExports = useRef(recentExports);

    useEffect(() => {
        if (previousRecentExports.current !== recentExports) {
            previousRecentExports.current = recentExports;
            setLiveExports(null);
        }
    }, [recentExports]);

    const filterErrors = filterFields.flatMap(([key, label]) =>
        errors[key] ? [{ key, label, message: errors[key] }] : []
    );

    return (
        <>
            <div className="flex items-center justify-between">
                <h3 className="text-2xl font-display font-bold text-on-surface dark:text-on-surface">{title}</h3>
                <div className="flex items-center gap-2">
                    {filters && (
                        <>
                            <Button variant="ghost" onClick={resetFilters}>
                                Reset
                            </Button>
                            <Button variant="secondary" onClick={applyFilters}>
                                Apply Filters
                            </Button>
                        </>
                    )}
                    <Button variant="default" disabled={exporting || !hasRecords} onClick={onExport}>
                        {exporting ? 'Exporting...' : 'Export CSV'}
                    </Button>
                </div>
            </div>

            {filters && (
                <Card className="mt-4 p-4">
                    {filterErrors.length > 0 && (
                        <div className="mb-3 rounded-md bg-error-container px-3 py-2 text-xs text-error">
                            {filterErrors.map(({ key, label, message }) => (
                                <p key={key}>
                                    {label}: {message}
                                </p>
                            ))}
                        </div>
                    )}

                    <div className="grid gap-4 lg:grid-cols-4">
                        {showAsOf && (
                            <div>
                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wider text-muted-foreground">As Of</label>
                                <Input
                                    type="date"
                                    value={filters.as_of ?? ''}
                                    onChange={(event) => setFilters({ ...filters, as_of: event.target.value || '' })}
                                />
                            </div>
                        )}

                        {showStandardFilters && (
                            <>
                                <div>
                                    <label className="mb-1 block text-xs font-semibold uppercase tracking-wider text-muted-foreground">Account</label>
                            <NativeSelect
                                value={filters.account_id ?? ''}
                                onChange={(event) => setFilters({ ...filters, account_id: event.target.value || '' })}
                                className="w-full"
                            >
                                <NativeSelectOption value="">All accounts</NativeSelectOption>
                                {accounts.map((account) => (
                                    <NativeSelectOption key={account.id} value={account.id}>
                                        {account.code} — {account.name}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                        </div>

                        <div>
                            <label className="mb-1 block text-xs font-semibold uppercase tracking-wider text-muted-foreground">Journal</label>
                            <NativeSelect
                                value={filters.journal_id ?? ''}
                                onChange={(event) => setFilters({ ...filters, journal_id: event.target.value || '' })}
                                className="w-full"
                            >
                                <NativeSelectOption value="">All journals</NativeSelectOption>
                                {journals.map((journal) => (
                                    <NativeSelectOption key={journal.id} value={journal.id}>
                                        {journal.code} — {journal.name}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                        </div>

                        <div>
                            <label className="mb-1 block text-xs font-semibold uppercase tracking-wider text-muted-foreground">Fiscal Year</label>
                            <NativeSelect
                                value={filters.fiscal_year_id ?? ''}
                                onChange={(event) => setFilters({ ...filters, fiscal_year_id: event.target.value || '' })}
                                className="w-full"
                            >
                                <NativeSelectOption value="">All fiscal years</NativeSelectOption>
                                {fiscalYears.map((fiscalYear) => (
                                    <NativeSelectOption key={fiscalYear.id} value={fiscalYear.id}>
                                        {fiscalYear.name}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                        </div>

                        <div>
                            <label className="mb-1 block text-xs font-semibold uppercase tracking-wider text-muted-foreground">Accounting Period</label>
                            <NativeSelect
                                value={filters.accounting_period_id ?? ''}
                                onChange={(event) => setFilters({ ...filters, accounting_period_id: event.target.value || '' })}
                                className="w-full"
                            >
                                <NativeSelectOption value="">All periods</NativeSelectOption>
                                {accountingPeriods.map((period) => (
                                    <NativeSelectOption key={period.id} value={period.id}>
                                        {period.name}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                        </div>

                        <div>
                            <label className="mb-1 block text-xs font-semibold uppercase tracking-wider text-muted-foreground">Start Date</label>
                            <Input
                                type="date"
                                value={filters.start_date ?? ''}
                                onChange={(event) => setFilters({ ...filters, start_date: event.target.value || '' })}
                            />
                        </div>

                        <div>
                            <label className="mb-1 block text-xs font-semibold uppercase tracking-wider text-muted-foreground">End Date</label>
                            <Input
                                type="date"
                                value={filters.end_date ?? ''}
                                onChange={(event) => setFilters({ ...filters, end_date: event.target.value || '' })}
                            />
                        </div>

                        <div>
                            <label className="mb-1 block text-xs font-semibold uppercase tracking-wider text-muted-foreground">Reference</label>
                            <Input
                                type="text"
                                placeholder="Reference text..."
                                value={filters.reference ?? ''}
                                onChange={(event) => setFilters({ ...filters, reference: event.target.value || '' })}
                            />
                        </div>
                            </>
                        )}
                    </div>

                    <div className="mt-4 flex flex-wrap items-center gap-2 border-t pt-3">
                        <span className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">My exports:</span>
                        {exportItems.length === 0 ? (
                            <span className="text-xs text-muted-foreground">None yet</span>
                        ) : (
                            exportItems.map((exportItem) => (
                                <span
                                    key={exportItem.id}
                                    className="inline-flex items-center gap-2 text-xs text-muted-foreground"
                                >
                                    <StatusBadge tone={exportStatusTones[exportItem.status] ?? 'default'}>
                                        {exportItem.status}
                                    </StatusBadge>
                                    {exportItem.download_url ? (
                                        <a href={exportItem.download_url} className="font-medium text-primary hover:underline">
                                            {exportItem.file_name}
                                        </a>
                                    ) : (
                                        <span>{exportItem.report_type}</span>
                                    )}
                                </span>
                            ))
                        )}
                    </div>
                </Card>
            )}

            <div className="mt-4">{children}</div>
        </>
    );
}