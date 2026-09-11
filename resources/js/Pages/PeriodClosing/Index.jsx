import { useEffect, useRef, useState } from 'react';
import { router, usePage } from '@inertiajs/react';

import AppLayout from '@/Layouts/AppLayout';
import PeriodForm from '@/Pages/PeriodClosing/PeriodForm';
import StatusBadge from '@/Components/StatusBadge';
import { Button } from '@/Components/ui/button';
import { Card } from '@/Components/ui/card';
import { NativeSelect } from '@/Components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

const statusKeys = { open: 'success', closing: 'warning', closed: 'error' };

export default function PeriodClosingIndex() {
    const { periods, fiscalYears, filters } = usePage().props;
    const [selectedFiscalYearId, setSelectedFiscalYearId] = useState(filters?.fiscal_year_id ?? '');
    const [target, setTarget] = useState(null);
    const firstRender = useRef(true);

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        router.get('/period-closing', { fiscal_year_id: selectedFiscalYearId || undefined }, { preserveState: true, replace: true });
    }, [selectedFiscalYearId]);

    return (
        <>
            <div>
                <h3 className="text-2xl font-display font-bold text-on-surface dark:text-on-surface">Period Closing</h3>
            </div>

            <div className="mt-4">
                <label
                    htmlFor="fiscalYear"
                    className="block text-sm font-medium text-on-surface-muted dark:text-on-surface-muted mb-1"
                >
                    Filter by Fiscal Year
                </label>
                <div className="w-64">
                    <NativeSelect
                        id="fiscalYear"
                        value={selectedFiscalYearId}
                        onChange={(event) => setSelectedFiscalYearId(event.target.value)}
                        className="w-full"
                    >
                        <option value="">All Fiscal Years</option>
                        {fiscalYears.map((fiscalYear) => (
                            <option key={fiscalYear.id} value={fiscalYear.id}>
                                {fiscalYear.name}
                            </option>
                        ))}
                    </NativeSelect>
                </div>
            </div>

            <Card className="mt-4 overflow-hidden">
                {periods?.length === 0 ? (
                    <div className="px-6 py-10 text-center text-sm text-on-surface-muted dark:text-on-surface-muted">
                        No accounting periods found.
                    </div>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Period</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Fiscal Year</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Dates</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Status</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {periods.map((period) => (
                                <TableRow key={period.id}>
                                    <TableCell className="font-display font-medium text-on-surface dark:text-on-surface">{period.name}</TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{period.fiscal_year?.name}</TableCell>
                                    <TableCell className="text-sm text-muted-foreground">
                                        {period.start_date} - {period.end_date}
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge tone={statusKeys[period.status] ?? 'success'}>
                                            {period.status[0].toUpperCase() + period.status.slice(1)}
                                        </StatusBadge>
                                    </TableCell>
                                    <TableCell>
                                        <Button
                                            variant={period.is_open ? 'destructive' : 'outline'}
                                            className={period.is_open ? '' : 'border-warning/30 text-warning hover:bg-warning/10 hover:text-warning'}
                                            size="sm"
                                            onClick={() => setTarget(period)}
                                        >
                                            {period.is_open ? 'Close' : 'Reopen'}
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </Card>

            <PeriodForm open={target !== null} period={target} onClose={() => setTarget(null)} />
        </>
    );
}

PeriodClosingIndex.layout = (page) => <AppLayout>{page}</AppLayout>;