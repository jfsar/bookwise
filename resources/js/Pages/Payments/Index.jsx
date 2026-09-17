import { useEffect, useRef, useState } from 'react';
import { router, usePage } from '@inertiajs/react';

import CustomerPaymentForm from '@/Pages/Payments/CustomerPaymentForm';
import AllocationDialog from '@/Pages/Payments/AllocationDialog';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import StatusBadge from '@/Components/StatusBadge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Label } from '@/Components/ui/label';
import { NativeSelect, NativeSelectOption } from '@/Components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

export default function CustomerPaymentsIndex() {
    const { payments, customers, accounts, currencies, baseCurrencyId } = usePage().props;
    const [status, setStatus] = useState('');

    const [showModal, setShowModal] = useState(false);
    const [allocatingPayment, setAllocatingPayment] = useState(null);

    const firstRender = useRef(true);

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;
            return;
        }

        const timeout = setTimeout(() => {
            router.get('/payments', {
                status: status || undefined,
            }, { preserveState: true, replace: true });
        }, 300);

        return () => clearTimeout(timeout);
    }, [status]);

    return (
        <>
            <div className="flex items-center justify-between">
                <h3 className="text-2xl font-display font-bold text-on-surface dark:text-on-surface">Customer Payments</h3>
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
                    <div className="px-6 py-10 text-center text-sm text-muted-foreground">No payments found. Create your first payment to get started.</div>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Payment #</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Customer</TableHead>
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
                                    <TableCell className="text-sm text-muted-foreground">{payment.customer?.name}</TableCell>
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
                                                <Button size="sm" variant="secondary" onClick={() => router.post(`/payments/${payment.id}/post`)}>
                                                    Post
                                                </Button>
                                            )}
                                            {payment.can?.void && (
                                                <Button size="sm" variant="outline" className="text-warning" onClick={() => window.confirm('Void this payment? This cannot be undone.') && router.post(`/payments/${payment.id}/void`, { reason: 'Voided by user.' })}>
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

            <CustomerPaymentForm
                open={showModal}
                onClose={() => setShowModal(false)}
                customers={customers ?? []}
                accounts={accounts ?? []}
                currencies={currencies ?? []}
                baseCurrencyId={baseCurrencyId}
            />

            <AllocationDialog
                payment={allocatingPayment}
                open={Boolean(allocatingPayment)}
                onClose={() => setAllocatingPayment(null)}
            />
        </>
    );
}

CustomerPaymentsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;