import { useState } from 'react';
import { usePage } from '@inertiajs/react';

import AppLayout from '@/Layouts/AppLayout';
import FiscalYearForm from '@/Pages/FiscalYears/FiscalYearForm';
import Pagination from '@/Components/Pagination';
import StatusBadge from '@/Components/StatusBadge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';

export default function FiscalYearsIndex() {
    const { fiscalYears } = usePage().props;
    const [showModal, setShowModal] = useState(false);

    if (!fiscalYears || fiscalYears.data?.length === 0) {
        return (
            <>
                <div className="flex items-center justify-between">
                    <h3 className="text-2xl font-display font-bold text-on-surface dark:text-on-surface">Fiscal Years</h3>
                    <Button variant="default" onClick={() => setShowModal(true)}>
                        New Fiscal Year
                    </Button>
                </div>

                <Card className="mt-4">
                    <CardContent>
                        <div className="py-8 text-center text-sm text-on-surface-muted dark:text-on-surface-muted">
                            No fiscal years found. Create one to get started.
                        </div>
                    </CardContent>
                </Card>

                <FiscalYearForm open={showModal} onClose={() => setShowModal(false)} />
            </>
        );
    }

    function statusColor(status) {
        const colors = { open: 'success', closed: 'error' };

        return colors[status] ?? 'default';
    }

    return (
        <>
            <div className="flex items-center justify-between">
                <h3 className="text-2xl font-display font-bold text-on-surface dark:text-on-surface">Fiscal Years</h3>
                <Button variant="default" onClick={() => setShowModal(true)}>
                    New Fiscal Year
                </Button>
            </div>

            <div className="mt-4 space-y-4">
                {fiscalYears.data.map((fiscalYear) => (
                    <Card key={fiscalYear.id}>
                        <CardContent>
                            <div className="flex items-center justify-between">
                                <div>
                                    <h4 className="font-display font-semibold text-lg text-on-surface dark:text-on-surface">
                                        {fiscalYear.name}
                                    </h4>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {fiscalYear.start_date} - {fiscalYear.end_date}
                                    </p>
                                </div>
                                <StatusBadge tone={statusColor(fiscalYear.status)}>
                                    {fiscalYear.status[0].toUpperCase() + fiscalYear.status.slice(1)}
                                </StatusBadge>
                            </div>

                            <h5 className="mt-5 text-sm font-medium text-on-surface-muted dark:text-on-surface-muted">Accounting Periods</h5>
                            <div className="mt-2 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                                {fiscalYear.periods.map((period) => (
                                    <div
                                        key={period.id}
                                        className={`rounded-lg bg-surface-low dark:bg-surface-low p-3 ${period.is_open ? '' : 'opacity-50'}`}
                                    >
                                        <div className="text-sm font-medium text-on-surface dark:text-on-surface">{period.name}</div>
                                        <div className="mt-2 flex items-center gap-2">
                                            <span
                                                className={`h-2 w-2 rounded-full ${
                                                    period.is_open ? 'bg-primary-light dark:bg-primary-light' : 'bg-on-surface-muted/30 dark:bg-on-surface-muted/30'
                                                }`}
                                            ></span>
                                            <span className="text-xs text-on-surface-muted dark:text-on-surface-muted">
                                                {period.start_date} - {period.end_date}
                                            </span>
                                        </div>
                                        <div className="mt-1 text-xs text-on-surface-muted dark:text-on-surface-muted">
                                            {period.status[0].toUpperCase() + period.status.slice(1)}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>

            <Pagination paginator={fiscalYears} />

            <FiscalYearForm open={showModal} onClose={() => setShowModal(false)} />
        </>
    );
}

FiscalYearsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;