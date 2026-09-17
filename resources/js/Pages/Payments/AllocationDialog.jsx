import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';

import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';

export default function AllocationDialog({ payment, open, onClose }) {
    const [amounts, setAmounts] = useState({});
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        if (open && payment) {
            const initial = {};
            (payment.allocable_invoices ?? []).forEach((invoice) => {
                initial[invoice.id] = '';
            });
            setAmounts(initial);
            setSubmitting(false);
        }
    }, [open, payment?.id]);

    if (!payment) {
        return null;
    }

    const total = Object.values(amounts).reduce((sum, value) => sum + (parseFloat(value) || 0), 0);
    const remaining = Number(payment.remaining) || 0;
    const over = total - remaining;

    function submit() {
        const allocations = Object.entries(amounts)
            .filter(([, value]) => value !== '' && parseFloat(value) > 0)
            .map(([invoiceId, value]) => ({ invoice_id: Number(invoiceId), amount: parseFloat(value) }));

        if (allocations.length === 0 || over > 0.005 || submitting) {
            return;
        }

        setSubmitting(true);

        router.post(`/payments/${payment.id}/allocate`, { allocations }, {
            onSuccess: () => onClose(),
        });
    }

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle className="font-display">Allocate {payment.payment_number}</DialogTitle>
                </DialogHeader>

                <div className="flex items-center justify-between rounded-lg bg-surface-container px-4 py-3 text-sm">
                    <span className="text-muted-foreground">Amount</span>
                    <span className="font-display font-medium text-on-surface">
                        {Number(payment.amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}{payment.currency ? ` ${payment.currency.code}` : ''}
                    </span>
                    <span className="text-muted-foreground">Remaining</span>
                    <span className="font-display font-medium text-on-surface">{Number(payment.remaining).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                </div>

                {!payment.allocable_invoices?.length ? (
                    <p className="py-6 text-center text-sm text-muted-foreground">
                        No posted invoices with an outstanding balance are available for this customer.
                    </p>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Invoice</TableHead>
                                <TableHead className="text-right text-xs uppercase tracking-wider text-muted-foreground">Due</TableHead>
                                <TableHead className="text-right text-xs uppercase tracking-wider text-muted-foreground">Balance</TableHead>
                                <TableHead className="text-right text-xs uppercase tracking-wider text-muted-foreground">Allocate</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {payment.allocable_invoices.map((invoice) => {
                                const value = amounts[invoice.id] ?? '';

                                return (
                                    <TableRow key={invoice.id}>
                                        <TableCell className="font-display text-sm font-medium text-on-surface">{invoice.invoice_number}</TableCell>
                                        <TableCell className="text-right text-sm text-muted-foreground">{invoice.due_date}</TableCell>
                                        <TableCell className="text-right text-sm text-muted-foreground">{Number(invoice.available).toLocaleString(undefined, { minimumFractionDigits: 2 })}</TableCell>
                                        <TableCell className="text-right">
                                            <Input
                                                type="number"
                                                min="0"
                                                step="0.0001"
                                                max={invoice.available}
                                                value={value}
                                                placeholder="0.00"
                                                className="ml-auto w-32 text-right"
                                                onChange={(event) => setAmounts({ ...amounts, [invoice.id]: event.target.value })}
                                            />
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>
                )}

                <div className="flex items-center justify-between">
                    <div>
                        <Label className="text-sm text-muted-foreground">Selected total</Label>
                        <div className={`font-display font-medium ${over > 0.005 ? 'text-destructive' : 'text-on-surface'}`}>
                            {total.toLocaleString(undefined, { minimumFractionDigits: 2 })} / {remaining.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                        </div>
                        {over > 0.005 && <p className="mt-1 text-xs font-medium text-destructive">Exceeds the remaining payment amount.</p>}
                    </div>
                    <div className="flex gap-3">
                        <Button type="button" variant="secondary" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="button" disabled={submitting || total <= 0.005 || over > 0.005} onClick={submit}>
                            Save Allocation
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}