import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';

export default function useReportFilters(reportPath) {
    const {
        reportType,
        currency,
        data,
        filters: initialFilters,
        accounts,
        journals,
        fiscalYears,
        accountingPeriods,
        recentExports,
    } = usePage().props;

    const [filters, setFilters] = useState(initialFilters ?? {});
    const [exporting, setExporting] = useState(false);

    function applyFilters() {
        router.get(reportPath, filters, { preserveState: true, replace: true });
    }

    function resetFilters() {
        setFilters({});
        router.get(reportPath, undefined, { preserveState: true, replace: true });
    }

    function handleExport() {
        setExporting(true);

        router.post('/reports/export', { report_type: reportType, ...filters }, {
            onFinish: () => setExporting(false),
        });
    }

    return {
        currency,
        data,
        filters,
        setFilters,
        applyFilters,
        resetFilters,
        exporting,
        onExport: handleExport,
        accounts,
        journals,
        fiscalYears,
        accountingPeriods,
        recentExports,
    };
}