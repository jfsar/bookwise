import { useEffect, useRef, useState } from 'react';
import { router, usePage } from '@inertiajs/react';

import VendorDebitNoteForm from '@/Pages/VendorDebitNotes/VendorDebitNoteForm';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import StatusBadge from '@/Components/StatusBadge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { NativeSelect, NativeSelectOption } from '@/Components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

export default function VendorDebitNotesIndex() {
    const { vendorDebitNotes, vendors, bills } = usePage().props;
    const [status, setStatus] = useState('');
    const [search, setSearch] = useState('');

    const [showModal, setShowModal] = useState(false);

    const firstRender = useRef(true);

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;
            return;
        }

        const timeout = setTimeout(() => {
            router.get('/vendor-debit-notes', {
                status: status || undefined,
                search: search || undefined,
            }, { preserveState: true, replace: true });
        }, 300);

        return () => clearTimeout(timeout);
    }, [status, search]);

    return (
        <>
            <div className="flex items-center justify-between">
                <h3 className="text-2xl font-display font-bold text-on-surface dark:text-on-surface">Vendor Debit Notes</h3>
                <Button variant="default" onClick={() => setShowModal(true)}>New Debit Note</Button>
            </div>

            <Card className="mt-4">
                <CardContent className="p-6">
                    <div className="flex flex-wrap items-end gap-4">
                        <div className="w-64">
                            <Label className="text-sm text-muted-foreground">Search</Label>
                            <Input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Debit note # or vendor..." className="mt-1" />
                        </div>
                        <div className="w-64">
                            <Label className="text-sm text-muted-foreground">Status</Label>
                            <NativeSelect value={status} onChange={(event) => setStatus(event.target.value)} className="mt-1">
                                <NativeSelectOption value="">All Statuses</NativeSelectOption>
                                <NativeSelectOption value="draft">Draft</NativeSelectOption>
                                <NativeSelectOption value="posted">Posted</NativeSelectOption>
                                <NativeSelectOption value="used">Used</NativeSelectOption>
                                <NativeSelectOption value="voided">Voided</NativeSelectOption>
                            </NativeSelect>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card className="mt-4 overflow-hidden">
                {!vendorDebitNotes || vendorDebitNotes?.data?.length === 0 ? (
                    <div className="px-6 py-10 text-center text-sm text-muted-foreground">No debit notes found. Create your first debit note to get started.</div>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Debit Note #</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Vendor</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Bill</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Date</TableHead>
                                <TableHead className="text-right text-xs uppercase tracking-wider text-muted-foreground">Total</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Status</TableHead>
                                <TableHead className="text-right text-xs uppercase tracking-wider text-muted-foreground">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {vendorDebitNotes.data.map((debitNote) => (
                                <TableRow key={debitNote.id}>
                                    <TableCell className="font-display font-medium text-on-surface">{debitNote.debit_note_number}</TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{debitNote.vendor?.name}</TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{debitNote.bill?.bill_number ?? '—'}</TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{debitNote.date}</TableCell>
                                    <TableCell className="text-right text-sm font-display font-medium text-on-surface">
                                        {Number(debitNote.total_amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge tone={debitNote.status === 'draft' ? 'default' : debitNote.status === 'posted' ? 'success' : debitNote.status === 'voided' ? 'error' : 'warning'}>
                                            {debitNote.status.replace('_', ' ')}
                                        </StatusBadge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-2">
                                            {debitNote.can?.post && (
                                                <Button size="sm" variant="secondary" onClick={() => router.post(`/vendor-debit-notes/${debitNote.id}/post`)}>
                                                    Post
                                                </Button>
                                            )}
                                            {debitNote.can?.void && (
                                                <Button size="sm" variant="outline" className="text-warning" onClick={() => window.confirm('Void this debit note? This cannot be undone.') && router.post(`/vendor-debit-notes/${debitNote.id}/void`, { reason: 'Voided by user.' })}>
                                                    Void
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

            <Pagination paginator={vendorDebitNotes} />

            <VendorDebitNoteForm
                open={showModal}
                onClose={() => setShowModal(false)}
                vendors={vendors ?? []}
                bills={bills ?? []}
            />
        </>
    );
}

VendorDebitNotesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;