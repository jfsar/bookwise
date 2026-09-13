import { useEffect, useRef, useState } from 'react';
import { router, usePage } from '@inertiajs/react';

import AppLayout from '@/Layouts/AppLayout';
import ConfirmEntryModal from '@/Pages/JournalEntries/ConfirmEntryModal';
import JournalEntryForm from '@/Pages/JournalEntries/JournalEntryForm';
import Pagination from '@/Components/Pagination';
import StatusBadge from '@/Components/StatusBadge';
import { Button } from '@/Components/ui/button';
import { Card } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { NativeSelect, NativeSelectOption } from '@/Components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

const statusColors = {
    draft: 'default',
    submitted: 'warning',
    approved: 'info',
    posted: 'success',
    reversed: 'error',
    cancelled: 'default',
    rejected: 'error',
};

function statusLabel(status) {
    return status[0].toUpperCase() + status.slice(1);
}

export default function JournalEntriesIndex() {
    const { entries, journals, currencies, fiscalYears, accountingPeriods, accounts, filters } = usePage().props;

    const [search, setSearch] = useState(filters?.search ?? '');
    const [status, setStatus] = useState(filters?.status ?? '');
    const [showModal, setShowModal] = useState(false);
    const [confirm, setConfirm] = useState({ open: false, entry: null, mode: 'reverse' });

    const firstRender = useRef(true);

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        const timeout = setTimeout(() => {
            router.get('/journal-entries', {
                search: search || undefined,
                status: status || undefined,
            }, { preserveState: true, replace: true });
        }, 300);

        return () => clearTimeout(timeout);
    }, [search, status]);

    function openReverse(entry) {
        setConfirm({ open: true, entry, mode: 'reverse' });
    }

    function openReject(entry) {
        setConfirm({ open: true, entry, mode: 'reject' });
    }

    function openCancel(entry) {
        setConfirm({ open: true, entry, mode: 'cancel' });
    }

    return (
        <>
            <div className="flex items-center justify-between">
                <h3 className="text-2xl font-display font-bold text-on-surface dark:text-on-surface">Journal Entries</h3>
                <Button variant="default" onClick={() => setShowModal(true)}>
                    New Journal Entry
                </Button>
            </div>

            <div className="mt-4 flex items-center gap-4">
                <div className="w-64">
                    <Input type="text" placeholder="Search entries..." value={search} onChange={(event) => setSearch(event.target.value)} />
                </div>
                <div className="w-48">
                    <NativeSelect value={status} onChange={(event) => setStatus(event.target.value)} className="w-full">
                        <NativeSelectOption value="">All Statuses</NativeSelectOption>
                        <NativeSelectOption value="draft">Draft</NativeSelectOption>
                        <NativeSelectOption value="submitted">Submitted</NativeSelectOption>
                        <NativeSelectOption value="approved">Approved</NativeSelectOption>
                        <NativeSelectOption value="posted">Posted</NativeSelectOption>
                        <NativeSelectOption value="reversed">Reversed</NativeSelectOption>
                    </NativeSelect>
                </div>
            </div>

            <Card className="mt-4 overflow-hidden">
                {entries?.data?.length === 0 || !entries ? (
                    <div className="px-6 py-10 text-center text-sm text-on-surface-muted dark:text-on-surface-muted">
                        No journal entries found.
                    </div>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Entry #</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Date</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Journal</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Description</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Status</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Period</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {entries.data.map((entry) => (
                                <TableRow key={entry.id}>
                                    <TableCell className="font-display font-medium text-primary dark:text-primary">{entry.entry_number}</TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{entry.entry_date}</TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{entry.journal?.name}</TableCell>
                                    <TableCell className="text-sm text-muted-foreground max-w-xs truncate">
                                        {entry.description ?? '—'}
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge tone={statusColors[entry.status] ?? 'default'}>
                                            {statusLabel(entry.status)}
                                        </StatusBadge>
                                    </TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{entry.accounting_period?.name}</TableCell>
                                    <TableCell>
                                        <div className="flex items-center gap-2">
                                            {entry.can.submit && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    className="border-warning/30 text-warning hover:bg-warning/10 hover:text-warning"
                                                    onClick={() => router.post(`/journal-entries/${entry.id}/submit`)}
                                                >
                                                    Submit
                                                </Button>
                                            )}
                                            {entry.can.approve && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    className="border-success/30 text-success hover:bg-success/10 hover:text-success"
                                                    onClick={() => router.post(`/journal-entries/${entry.id}/approve`)}
                                                >
                                                    Approve
                                                </Button>
                                            )}
                                            {entry.can.reject && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    className="border-destructive/30 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                    onClick={() => openReject(entry)}
                                                >
                                                    Reject
                                                </Button>
                                            )}
                                            {entry.can.post && (
                                                <Button size="sm" variant="default" onClick={() => router.post(`/journal-entries/${entry.id}/post`)}>
                                                    Post
                                                </Button>
                                            )}
                                            {entry.can.reverse && (
                                                <Button size="sm" variant="destructive" onClick={() => openReverse(entry)}>
                                                    Reverse
                                                </Button>
                                            )}
                                            {entry.can.cancel && (
                                                <Button size="sm" variant="secondary" onClick={() => openCancel(entry)}>
                                                    Cancel
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

            <Pagination paginator={entries} />

            <JournalEntryForm
                open={showModal}
                onClose={() => setShowModal(false)}
                journals={journals}
                currencies={currencies}
                fiscalYears={fiscalYears}
                accountingPeriods={accountingPeriods}
                accounts={accounts}
            />

            <ConfirmEntryModal
                open={confirm.open}
                mode={confirm.mode}
                entry={confirm.entry}
                onClose={() => setConfirm({ open: false, entry: null, mode: 'reverse' })}
            />
        </>
    );
}

JournalEntriesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;