import { useEffect, useRef, useState } from 'react';
import { router, usePage } from '@inertiajs/react';

import CustomerCreditNoteForm from '@/Pages/CreditNotes/CustomerCreditNoteForm';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import StatusBadge from '@/Components/StatusBadge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { NativeSelect, NativeSelectOption } from '@/Components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

export default function CustomerCreditNotesIndex() {
    const { creditNotes, customers, invoices } = usePage().props;
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
            router.get('/credit-notes', {
                status: status || undefined,
                search: search || undefined,
            }, { preserveState: true, replace: true });
        }, 300);

        return () => clearTimeout(timeout);
    }, [status, search]);

    return (
        <>
            <div className="flex items-center justify-between">
                <h3 className="text-2xl font-display font-bold text-on-surface dark:text-on-surface">Customer Credit Notes</h3>
                <Button variant="default" onClick={() => setShowModal(true)}>New Credit Note</Button>
            </div>

            <Card className="mt-4">
                <CardContent className="p-6">
                    <div className="flex flex-wrap items-end gap-4">
                        <div className="w-64">
                            <Label className="text-sm text-muted-foreground">Search</Label>
                            <Input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Credit note # or customer..." className="mt-1" />
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
                {!creditNotes || creditNotes?.data?.length === 0 ? (
                    <div className="px-6 py-10 text-center text-sm text-muted-foreground">No credit notes found. Create your first credit note to get started.</div>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Credit Note #</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Customer</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Invoice</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Date</TableHead>
                                <TableHead className="text-right text-xs uppercase tracking-wider text-muted-foreground">Total</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Status</TableHead>
                                <TableHead className="text-right text-xs uppercase tracking-wider text-muted-foreground">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {creditNotes.data.map((creditNote) => (
                                <TableRow key={creditNote.id}>
                                    <TableCell className="font-display font-medium text-on-surface">{creditNote.credit_note_number}</TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{creditNote.customer?.name}</TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{creditNote.invoice?.invoice_number ?? '—'}</TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{creditNote.date}</TableCell>
                                    <TableCell className="text-right text-sm font-display font-medium text-on-surface">
                                        {Number(creditNote.total_amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge tone={creditNote.status === 'draft' ? 'default' : creditNote.status === 'posted' ? 'success' : creditNote.status === 'voided' ? 'error' : 'warning'}>
                                            {creditNote.status.replace('_', ' ')}
                                        </StatusBadge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-2">
                                            {creditNote.can?.post && (
                                                <Button size="sm" variant="secondary" onClick={() => router.post(`/credit-notes/${creditNote.id}/post`)}>
                                                    Post
                                                </Button>
                                            )}
                                            {creditNote.can?.void && (
                                                <Button size="sm" variant="outline" className="text-warning" onClick={() => window.confirm('Void this credit note? This cannot be undone.') && router.post(`/credit-notes/${creditNote.id}/void`, { reason: 'Voided by user.' })}>
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

            <Pagination paginator={creditNotes} />

            <CustomerCreditNoteForm
                open={showModal}
                onClose={() => setShowModal(false)}
                customers={customers ?? []}
                invoices={invoices ?? []}
            />
        </>
    );
}

CustomerCreditNotesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;