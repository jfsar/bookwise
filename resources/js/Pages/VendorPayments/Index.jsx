import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';

import VendorPaymentForm from '@/Pages/VendorPayments/VendorPaymentForm';
import VendorPaymentAllocationDialog from '@/Pages/VendorPayments/AllocationDialog';
import AppLayout from '@/Layouts/AppLayout';
import ConfirmVoidDialog from '@/Components/ConfirmVoidDialog';
import Pagination from '@/Components/Pagination';
import StatusBadge from '@/Components/StatusBadge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Label } from '@/Components/ui/label';
import { NativeSelect, NativeSelectOption } from '@/Components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

export default function VendorPaymentsIndex() {
    const { payments, vendors, accounts, currencies, baseCurrencyId } = usePage().props;
    const [status, setStatus] = useState('');

    const [showModal, setShowModal] = useState(false);
    const [allocatingPayment, setAllocatingPayment] = useState(null);
    const [voidingPayment, setVoidingPayment] = useState(null);

    useEffect(() => {
        if (!status) {
            return;
        }

        const timeout = setTimeout(() => {
            router.get('/vendor-payments', {
                status: status || undefined,
            }, { preserveState: true, replace: true });
        }, 300);

        return () => clearTimeout(timeout);
    }, [status]);

    return (
        <>
            <div className="flex items-center justify-between">
                <h3 className="text-2xl font-display font-bold text-on-surface dark:text-on-surface">Vendor Payments</h3>
                <Button variant="default" onClick={() => setShowModal(true)}>New Payment</Button>
            </div>

            <Card className="mt-4">
                <CardContent className="p-6">
                    <div className="flex flex-wrap items-end gap-4">
                        <div className="w-64">
                            <Label className="text-sm text-muted-foreground">Status</Label>
                            <NativeSelect value={status} onChange={(event) => setStatus(event.target.value)} className="mt-1">
                                <NativeSelectOption value="">All Statuses</NativeSelectOption>
                                <NativeSelectOption value="draft">Draft</NativeSelectOption>
                                <NativeSelectOption value="posted">Posted</NativeSelectOption>
                                <NativeSelectOption value="voided">Voided</NativeSelectOption>
                            </NativeSelect>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card className="mt-4 overflow-hidden">
                {!payments || payments?.data?.length === 0 ? (
                    <div className="px-6 py-10 text-center text-sm text-muted-foreground">No vendor payments found. Record your first payment to get started.</div>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Payment #</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Vendor</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Payment Date</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Method</TableHead>
                                <TableHead className="text-right text-xs uppercase tracking-wider text-muted-foreground">Amount</TableHead>
                                <TableHead className="text-right text-xs uppercase tracking-wider text-muted-foreground">Allocated</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Status</TableHead>
                                <TableHead className="text-right text-xs uppercase tracking-wider text-muted-foreground">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {payments.data.map((payment) => (
                                <TableRow key={payment.id}>
                                    <TableCell className="font-display font-medium text-on-surface">{payment.payment_number}</TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{payment.vendor?.name}</TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{payment.payment_date}</TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{payment.payment_method?.replace('_', ' ')}</TableCell>
                                    <TableCell className="text-right text-sm font-display font-medium text-on-surface">{Number(payment.amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}</TableCell>
                                    <TableCell className="text-right text-sm text-muted-foreground">
                                        {payment.status === 'draft' && Number(payment.allocated) > 0
                                            ? Number(payment.allocated).toLocaleString(undefined, { minimumFractionDigits: 2 })
                                            : '—'}
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge tone={payment.status === 'draft' ? 'default' : payment.status === 'posted' ? 'success' : payment.status === 'voided' ? 'error' : 'warning'}>
                                            {payment.status.replace('_', ' ')}
                                        </StatusBadge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-2">
                                            {payment.can?.allocate && (
                                                <Button size="sm" variant="outline" onClick={() => setAllocatingPayment(payment)}>
                                                    Allocate
                                                </Button>
                                            )}
                                            {payment.can?.post && (
                                                <Button size="sm" variant="secondary" onClick={() => router.post(`/vendor-payments/${payment.id}/post`)}>
                                                    Post
                                                </Button>
                                            )}
                                            {payment.can?.void && (
                                                <Button size="sm" variant="outline" className="text-warning" onClick={() => setVoidingPayment(payment)}>
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

            <Pagination paginator={payments} />

            <VendorPaymentForm
                open={showModal}
                onClose={() => setShowModal(false)}
                vendors={vendors ?? []}
                accounts={accounts ?? []}
                currencies={currencies ?? []}
                baseCurrencyId={baseCurrencyId}
            />

            <VendorPaymentAllocationDialog
                payment={allocatingPayment}
                open={Boolean(allocatingPayment)}
                onClose={() => setAllocatingPayment(null)}
            />

            <ConfirmVoidDialog
                open={Boolean(voidingPayment)}
                title={`Void ${voidingPayment?.payment_number ?? 'this payment'}?`}
                description="This cannot be undone. The payment and its allocations will be reversed, so provide a reason for the audit trail."
                onClose={() => setVoidingPayment(null)}
                onConfirm={(reason) => {
                    router.post(`/vendor-payments/${voidingPayment.id}/void`, { reason });
                    setVoidingPayment(null);
                }}
            />
        </>
    );
}

VendorPaymentsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;