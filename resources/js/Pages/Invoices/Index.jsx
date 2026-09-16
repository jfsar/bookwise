import { useEffect, useRef, useState } from 'react';
import { router, usePage } from '@inertiajs/react';

import CustomerInvoiceForm from '@/Pages/Invoices/CustomerInvoiceForm';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import StatusBadge from '@/Components/StatusBadge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Label } from '@/Components/ui/label';
import { NativeSelect, NativeSelectOption } from '@/Components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

export default function CustomerInvoicesIndex() {
    const { invoices, customers, accounts, currencies, taxCodes, baseCurrencyId } = usePage().props;
    const [status, setStatus] = useState('');

    const [showModal, setShowModal] = useState(false);
    const [editing, setEditing] = useState(null);

    const firstRender = useRef(true);

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;
            return;
        }

        const timeout = setTimeout(() => {
            router.get('/invoices', {
                status: status || undefined,
            }, { preserveState: true, replace: true });
        }, 300);

        return () => clearTimeout(timeout);
    }, [status]);

    return (
        <>
            <div className="flex items-center justify-between">
                <h3 className="text-2xl font-display font-bold text-on-surface dark:text-on-surface">Invoices</h3>
                <Button variant="default" onClick={() => { setEditing(null); setShowModal(true); }}>New Invoice</Button>
            </div>

            <Card className="mt-4">
                <CardContent className="p-6">
                    <div className="flex flex-wrap items-end gap-4">
                        <div className="w-64">
                            <Label className="text-sm text-muted-foreground">Status</Label>
                            <NativeSelect value={status} onChange={(event) => setStatus(event.target.value)} className="mt-1">
                                <NativeSelectOption value="">All Statuses</NativeSelectOption>
                                <NativeSelectOption value="draft">Draft</NativeSelectOption>
                                <NativeSelectOption value="issued">Issued</NativeSelectOption>
                                <NativeSelectOption value="posted">Posted</NativeSelectOption>
                                <NativeSelectOption value="partially_paid">Partially Paid</NativeSelectOption>
                                <NativeSelectOption value="paid">Paid</NativeSelectOption>
                                <NativeSelectOption value="voided">Voided</NativeSelectOption>
                            </NativeSelect>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card className="mt-4 overflow-hidden">
                {!invoices || invoices?.data?.length === 0 ? (
                    <div className="px-6 py-10 text-center text-sm text-muted-foreground">No invoices found. Create your first invoice to get started.</div>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Invoice #</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Customer</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Invoice Date</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Due Date</TableHead>
                                <TableHead className="text-right text-xs uppercase tracking-wider text-muted-foreground">Total</TableHead>
                                <TableHead className="text-right text-xs uppercase tracking-wider text-muted-foreground">Balance Due</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Status</TableHead>
                                <TableHead className="text-right text-xs uppercase tracking-wider text-muted-foreground">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {invoices.data.map((invoice) => (
                                <TableRow key={invoice.id}>
                                    <TableCell className="font-display font-medium text-on-surface">{invoice.invoice_number}</TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{invoice.customer?.name}</TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{invoice.invoice_date}</TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{invoice.due_date}</TableCell>
                                    <TableCell className="text-right text-sm font-display font-medium text-on-surface">{Number(invoice.total_amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}</TableCell>
                                    <TableCell className="text-right text-sm text-muted-foreground">{Number(invoice.balance_due).toLocaleString(undefined, { minimumFractionDigits: 2 })}</TableCell>
                                    <TableCell>
                                        <StatusBadge tone={invoice.status === 'draft' ? 'default' : invoice.status === 'posted' ? 'success' : invoice.status === 'paid' || invoice.status === 'partially_paid' ? 'success' : invoice.status === 'voided' ? 'error' : 'warning'}>
                                            {invoice.status.replace('_', ' ')}
                                        </StatusBadge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-2">
                                            {invoice.can?.post && (
                                                <Button size="sm" variant="secondary" onClick={() => router.post(`/invoices/${invoice.id}/post`)}>
                                                    Post
                                                </Button>
                                            )}
                                            {invoice.can?.void && (
                                                <Button size="sm" variant="outline" className="text-warning" onClick={() => window.confirm('Void this invoice? This cannot be undone.') && router.post(`/invoices/${invoice.id}/void`, { reason: 'Voided by user.' })}>
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

            <Pagination paginator={invoices} />

            <CustomerInvoiceForm
                open={showModal}
                onClose={() => setShowModal(false)}
                invoice={editing}
                customers={customers ?? []}
                accounts={accounts ?? []}
                currencies={currencies ?? []}
                taxCodes={taxCodes ?? []}
                baseCurrencyId={baseCurrencyId}
            />
        </>
    );
}

CustomerInvoicesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
