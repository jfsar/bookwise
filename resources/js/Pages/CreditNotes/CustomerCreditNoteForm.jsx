import { useEffect, useMemo } from 'react';
import { useForm } from '@inertiajs/react';

import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { NativeSelect, NativeSelectOption } from '@/Components/ui/native-select';
import { Textarea } from '@/Components/ui/textarea';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';

export default function CustomerCreditNoteForm({ open, onClose, customers, invoices }) {
    const { data, setData, errors, post, processing, reset } = useForm({
        customer_id: '',
        invoice_id: '',
        date: '',
        subtotal: '',
        tax_amount: '',
        reason: '',
        idempotency_key: '',
    });

    useEffect(() => {
        if (!open) {
            return;
        }

        reset();
        setData({
            customer_id: '',
            invoice_id: '',
            date: new Date().toISOString().split('T')[0],
            subtotal: '',
            tax_amount: '0',
            reason: '',
            idempotency_key: crypto.randomUUID(),
        });
    }, [open]);

    const customerInvoices = useMemo(
        () => (invoices ?? []).filter((invoice) => String(invoice.customer_id) === String(data.customer_id)),
        [invoices, data.customer_id],
    );

    const selectedInvoice = useMemo(
        () => (invoices ?? []).find((invoice) => String(invoice.id) === String(data.invoice_id)),
        [invoices, data.invoice_id],
    );

    function submit(event) {
        event.preventDefault();
        post('/credit-notes', {
            onSuccess: () => onClose(),
        });
    }

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle className="font-display">New Credit Note</DialogTitle>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label>Customer *</Label>
                            <NativeSelect
                                value={data.customer_id}
                                onChange={(event) => setData({ ...data, customer_id: event.target.value, invoice_id: '' })}
                                className="mt-1 w-full"
                            >
                                <NativeSelectOption value="">Select customer...</NativeSelectOption>
                                {customers.map((customer) => (
                                    <NativeSelectOption key={customer.id} value={customer.id}>
                                        {customer.customer_number} - {customer.name}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                            {errors.customer_id && <p className="mt-1 text-sm font-medium text-destructive">{errors.customer_id}</p>}
                        </div>
                        <div>
                            <Label>Invoice *</Label>
                            <NativeSelect
                                value={data.invoice_id}
                                onChange={(event) => setData('invoice_id', event.target.value)}
                                className="mt-1 w-full"
                                disabled={!data.customer_id}
                            >
                                <NativeSelectOption value="">Select invoice...</NativeSelectOption>
                                {customerInvoices.map((invoice) => (
                                    <NativeSelectOption key={invoice.id} value={invoice.id}>
                                        {invoice.invoice_number} — balance {Number(invoice.balance_due).toFixed(2)}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                            {errors.invoice_id && <p className="mt-1 text-sm font-medium text-destructive">{errors.invoice_id}</p>}
                        </div>
                        <div>
                            <Label>Credit Note Date *</Label>
                            <Input type="date" value={data.date} onChange={(event) => setData('date', event.target.value)} className="mt-1" />
                            {errors.date && <p className="mt-1 text-sm font-medium text-destructive">{errors.date}</p>}
                        </div>
                        <div>
                            <Label>Currency</Label>
                            <Input value={selectedInvoice ? selectedInvoice.currency?.code ?? '' : ''} disabled className="mt-1" placeholder="From invoice" />
                        </div>
                        <div>
                            <Label>Subtotal *</Label>
                            <Input type="number" min="0" step="0.0001" value={data.subtotal} onChange={(event) => setData('subtotal', event.target.value)} className="mt-1" />
                            {errors.subtotal && <p className="mt-1 text-sm font-medium text-destructive">{errors.subtotal}</p>}
                        </div>
                        <div>
                            <Label>Tax Amount *</Label>
                            <Input type="number" min="0" step="0.0001" value={data.tax_amount} onChange={(event) => setData('tax_amount', event.target.value)} className="mt-1" />
                            {errors.tax_amount && <p className="mt-1 text-sm font-medium text-destructive">{errors.tax_amount}</p>}
                        </div>
                        <div className="col-span-2">
                            <Label>Reason *</Label>
                            <Textarea value={data.reason} onChange={(event) => setData('reason', event.target.value)} className="mt-1" />
                            {errors.reason && <p className="mt-1 text-sm font-medium text-destructive">{errors.reason}</p>}
                        </div>
                    </div>

                    <div className="mt-6 flex items-center justify-end gap-3">
                        <Button type="button" variant="secondary" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            Create Credit Note
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}