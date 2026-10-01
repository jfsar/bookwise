import { useEffect, useRef, useState } from 'react';
import { router, usePage } from '@inertiajs/react';

import AppLayout from '@/Layouts/AppLayout';
import RecurringJournalForm from '@/Pages/RecurringJournals/RecurringJournalForm';
import Pagination from '@/Components/Pagination';
import StatusBadge from '@/Components/StatusBadge';
import { Button } from '@/Components/ui/button';
import { Card } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { NativeSelect, NativeSelectOption } from '@/Components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

const statusTones = {
    active: 'success',
    paused: 'warning',
    completed: 'default',
};

export default function RecurringJournalsIndex() {
    const { recurringJournals, journals, accounts, frequencies, statuses, filters, canCreate } = usePage().props;
    const [search, setSearch] = useState(filters?.search ?? '');
    const [status, setStatus] = useState(filters?.status ?? '');
    const [showModal, setShowModal] = useState(false);
    const [editing, setEditing] = useState(null);
    const firstRender = useRef(true);

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        const timeout = setTimeout(() => {
            router.get(
                '/recurring-journals',
                { search: search || undefined, status: status || undefined },
                { preserveState: true, replace: true },
            );
        }, 300);

        return () => clearTimeout(timeout);
    }, [search, status]);

    function openCreate() {
        setEditing(null);
        setShowModal(true);
    }

    function openEdit(recurringJournal) {
        setEditing(recurringJournal);
        setShowModal(true);
    }

    function runNow(recurringJournal) {
        router.post(`/recurring-journals/${recurringJournal.id}/run`, {}, { preserveScroll: true });
    }

    function togglePause(recurringJournal) {
        const verb = recurringJournal.can.pause ? 'pause' : 'resume';

        router.post(`/recurring-journals/${recurringJournal.id}/${verb}`, {}, { preserveScroll: true });
    }

    function destroy(recurringJournal) {
        if (!window.confirm(`Delete "${recurringJournal.name}"?`)) {
            return;
        }

        router.delete(`/recurring-journals/${recurringJournal.id}`, { preserveScroll: true });
    }

    return (
        <>
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-2xl font-display font-bold text-on-surface dark:text-on-surface">
                        Recurring Journals
                    </h3>
                    <p className="text-sm text-muted-foreground">
                        Draft entries are generated on schedule and still follow the journal approval workflow.
                    </p>
                </div>
                {canCreate && (
                    <Button variant="default" onClick={openCreate}>
                        New Recurring Journal
                    </Button>
                )}
            </div>

            <div className="mt-4 flex gap-3">
                <div className="w-64">
                    <Input
                        type="text"
                        placeholder="Search recurring journals..."
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                    />
                </div>
                <div className="w-40">
                    <NativeSelect value={status ?? ''} onChange={(event) => setStatus(event.target.value)} className="w-full">
                        <NativeSelectOption value="">All statuses</NativeSelectOption>
                        {statuses.map((option) => (
                            <NativeSelectOption key={option.value} value={option.value}>
                                {option.label}
                            </NativeSelectOption>
                        ))}
                    </NativeSelect>
                </div>
            </div>

            <Card className="mt-4 overflow-hidden">
                {!recurringJournals?.data?.length ? (
                    <div className="px-6 py-10 text-center text-sm text-on-surface-muted dark:text-on-surface-muted">
                        No recurring journals found.
                    </div>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">
                                    Name
                                </TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">
                                    Journal
                                </TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">
                                    Schedule
                                </TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">
                                    Next Run
                                </TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">
                                    Amount
                                </TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">
                                    Runs
                                </TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">
                                    Status
                                </TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">
                                    Actions
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {recurringJournals.data.map((recurringJournal) => (
                                <TableRow key={recurringJournal.id}>
                                    <TableCell>
                                        <span className="font-display font-medium text-on-surface dark:text-on-surface">
                                            {recurringJournal.name}
                                        </span>
                                        {recurringJournal.auto_submit && (
                                            <span className="mt-1 block text-xs text-muted-foreground">
                                                Auto-submits generated entries
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-sm text-muted-foreground">
                                        {recurringJournal.journal
                                            ? `${recurringJournal.journal.code} - ${recurringJournal.journal.name}`
                                            : '—'}
                                    </TableCell>
                                    <TableCell className="text-sm text-muted-foreground">
                                        {recurringJournal.frequency_label}
                                        <span className="mt-1 block text-xs">
                                            {recurringJournal.start_date}
                                            {recurringJournal.end_date ? ` → ${recurringJournal.end_date}` : ' → open ended'}
                                        </span>
                                    </TableCell>
                                    <TableCell className="text-sm text-muted-foreground">
                                        {recurringJournal.status === 'completed' ? '—' : recurringJournal.next_run_date}
                                    </TableCell>
                                    <TableCell className="font-display text-sm text-on-surface dark:text-on-surface">
                                        {Number(recurringJournal.total_debit).toLocaleString(undefined, {
                                            minimumFractionDigits: 2,
                                        })}
                                    </TableCell>
                                    <TableCell className="text-sm text-muted-foreground">
                                        {recurringJournal.run_count}
                                        <span className="mt-1 block text-xs">
                                            {recurringJournal.last_run_at ? `Last ${recurringJournal.last_run_at}` : 'Never run'}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge tone={statusTones[recurringJournal.status] ?? 'default'}>
                                            {recurringJournal.status_label}
                                        </StatusBadge>
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex flex-wrap gap-2">
                                            {recurringJournal.can.run && (
                                                <Button variant="outline" size="sm" onClick={() => runNow(recurringJournal)}>
                                                    Run now
                                                </Button>
                                            )}
                                            {(recurringJournal.can.pause || recurringJournal.can.resume) && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => togglePause(recurringJournal)}
                                                >
                                                    {recurringJournal.can.pause ? 'Pause' : 'Resume'}
                                                </Button>
                                            )}
                                            {recurringJournal.can.update && (
                                                <Button variant="secondary" size="sm" onClick={() => openEdit(recurringJournal)}>
                                                    Edit
                                                </Button>
                                            )}
                                            {recurringJournal.can.delete && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="text-destructive"
                                                    onClick={() => destroy(recurringJournal)}
                                                >
                                                    Delete
                                                </Button>
                                            )}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </Card>

            <Pagination paginator={recurringJournals} />

            <RecurringJournalForm
                open={showModal}
                onClose={() => setShowModal(false)}
                recurringJournal={editing}
                journals={journals}
                accounts={accounts}
                frequencies={frequencies}
            />
        </>
    );
}

RecurringJournalsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;