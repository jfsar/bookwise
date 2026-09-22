import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';

import AppLayout from '@/Layouts/AppLayout';
import ConfirmVoidDialog from '@/Components/ConfirmVoidDialog';
import Pagination from '@/Components/Pagination';
import StatusBadge from '@/Components/StatusBadge';
import VendorBillForm from '@/Pages/Bills/VendorBillForm';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Label } from '@/Components/ui/label';
import { NativeSelect, NativeSelectOption } from '@/Components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

export default function VendorBillsIndex() {
    const { bills, vendors, accounts, currencies, taxCodes, baseCurrencyId } = usePage().props;
    const [status, setStatus] = useState('');

    const [showModal, setShowModal] = useState(false);
    const [voidingBill, setVoidingBill] = useState(null);

    useEffect(() => {
        if (!status) {
            return;
        }

        const timeout = setTimeout(() => {
            router.get('/bills', {
                status: status || undefined,
            }, { preserveState: true, replace: true });
        }, 300);

        return () => clearTimeout(timeout);
    }, [status]);

    return (
        <>
            <div className="flex items-center justify-between">
                <h3 className="text-2xl font-display font-bold text-on-surface dark:text-on-surface">Bills</h3>
                <Button variant="default" onClick={() => setShowModal(true)}>New Bill</Button>
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
                                <NativeSelectOption value="partially_paid">Partially Paid</NativeSelectOption>
                                <NativeSelectOption value="paid">Paid</NativeSelectOption>
                                <NativeSelectOption value="voided">Voided</NativeSelectOption>
                            </NativeSelect>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card className="mt-4 overflow-hidden">
                {!bills || bills?.data?.length === 0 ? (
                    <div className="px-6 py-10 text-center text-sm text-muted-foreground">No bills found. Create your first bill to get started.</div>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Bill #</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Vendor</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Bill Date</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Due Date</TableHead>
                                <TableHead className="text-right text-xs uppercase tracking-wider text-muted-foreground">Total</TableHead>
                                <TableHead className="text-right text-xs uppercase tracking-wider text-muted-foreground">Balance Due</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Status</TableHead>
                                <TableHead className="text-right text-xs uppercase tracking-wider text-muted-foreground">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {bills.data.map((bill) => (
                                <TableRow key={bill.id}>
                                    <TableCell className="font-display font-medium text-on-surface">{bill.bill_number}</TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{bill.vendor?.name}</TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{bill.bill_date}</TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{bill.due_date}</TableCell>
                                    <TableCell className="text-right text-sm font-display font-medium text-on-surface">{Number(bill.total_amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}</TableCell>
                                    <TableCell className="text-right text-sm text-muted-foreground">{Number(bill.balance_due).toLocaleString(undefined, { minimumFractionDigits: 2 })}</TableCell>
                                    <TableCell>
                                        <StatusBadge tone={bill.status === 'draft' ? 'default' : bill.status === 'posted' ? 'success' : bill.status === 'paid' || bill.status === 'partially_paid' ? 'success' : bill.status === 'voided' ? 'error' : 'warning'}>
                                            {bill.status.replace('_', ' ')}
                                        </StatusBadge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-2">
                                            {bill.can?.post && (
                                                <Button size="sm" variant="secondary" onClick={() => router.post(`/bills/${bill.id}/post`)}>
                                                    Post
                                                </Button>
                                            )}
                                            {bill.can?.void && (
                                                <Button size="sm" variant="outline" className="text-warning" onClick={() => setVoidingBill(bill)}>
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

            <Pagination paginator={bills} />

            <VendorBillForm
                open={showModal}
                onClose={() => setShowModal(false)}
                vendors={vendors ?? []}
                accounts={accounts ?? []}
                currencies={currencies ?? []}
                taxCodes={taxCodes ?? []}
                baseCurrencyId={baseCurrencyId}
            />

            <ConfirmVoidDialog
                open={Boolean(voidingBill)}
                title={`Void ${voidingBill?.bill_number ?? 'this bill'}?`}
                description="This cannot be undone. A posted bill will be reversed in the ledger, so provide a reason for the audit trail."
                onClose={() => setVoidingBill(null)}
                onConfirm={(reason) => {
                    router.post(`/bills/${voidingBill.id}/void`, { reason });
                    setVoidingBill(null);
                }}
            />
        </>
    );
}

VendorBillsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;