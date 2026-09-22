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

export default function VendorDebitNoteForm({ open, onClose, vendors, bills }) {
    const { data, setData, errors, post, processing, reset } = useForm({
        vendor_id: '',
        bill_id: '',
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
            vendor_id: '',
            bill_id: '',
            date: new Date().toISOString().split('T')[0],
            subtotal: '',
            tax_amount: '0',
            reason: '',
            idempotency_key: crypto.randomUUID(),
        });
    }, [open]);

    const vendorBills = useMemo(
        () => (bills ?? []).filter((bill) => String(bill.vendor_id) === String(data.vendor_id)),
        [bills, data.vendor_id],
    );

    const selectedBill = useMemo(
        () => (bills ?? []).find((bill) => String(bill.id) === String(data.bill_id)),
        [bills, data.bill_id],
    );

    function submit(event) {
        event.preventDefault();
        post('/vendor-debit-notes', {
            onSuccess: () => onClose(),
        });
    }

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle className="font-display">New Debit Note</DialogTitle>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label>Vendor *</Label>
                            <NativeSelect
                                value={data.vendor_id}
                                onChange={(event) => setData({ ...data, vendor_id: event.target.value, bill_id: '' })}
                                className="mt-1 w-full"
                            >
                                <NativeSelectOption value="">Select vendor...</NativeSelectOption>
                                {vendors.map((vendor) => (
                                    <NativeSelectOption key={vendor.id} value={vendor.id}>
                                        {vendor.vendor_number} - {vendor.name}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                            {errors.vendor_id && <p className="mt-1 text-sm font-medium text-destructive">{errors.vendor_id}</p>}
                        </div>
                        <div>
                            <Label>Bill *</Label>
                            <NativeSelect
                                value={data.bill_id}
                                onChange={(event) => setData('bill_id', event.target.value)}
                                className="mt-1 w-full"
                                disabled={!data.vendor_id}
                            >
                                <NativeSelectOption value="">Select bill...</NativeSelectOption>
                                {vendorBills.map((bill) => (
                                    <NativeSelectOption key={bill.id} value={bill.id}>
                                        {bill.bill_number} — balance {Number(bill.balance_due).toFixed(2)}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                            {errors.bill_id && <p className="mt-1 text-sm font-medium text-destructive">{errors.bill_id}</p>}
                        </div>
                        <div>
                            <Label>Debit Note Date *</Label>
                            <Input type="date" value={data.date} onChange={(event) => setData('date', event.target.value)} className="mt-1" />
                            {errors.date && <p className="mt-1 text-sm font-medium text-destructive">{errors.date}</p>}
                        </div>
                        <div>
                            <Label>Currency</Label>
                            <Input value={selectedBill ? selectedBill.currency?.code ?? '' : ''} disabled className="mt-1" placeholder="From bill" />
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
                            Create Debit Note
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}