import { useEffect } from 'react';
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

export default function VendorPaymentForm({ open, onClose, vendors, accounts, currencies, baseCurrencyId }) {
    const { data, setData, errors, post, processing, reset } = useForm({
        vendor_id: '',
        payment_date: '',
        currency_id: '',
        exchange_rate: '1',
        amount: '',
        payment_method: 'bank_transfer',
        cash_account_id: '',
        reference: '',
        notes: '',
        idempotency_key: '',
    });

    useEffect(() => {
        if (!open) {
            return;
        }

        reset();
        setData({
            vendor_id: '',
            payment_date: new Date().toISOString().split('T')[0],
            currency_id: String(baseCurrencyId ?? ''),
            exchange_rate: '1',
            amount: '',
            payment_method: 'bank_transfer',
            cash_account_id: '',
            reference: '',
            notes: '',
            idempotency_key: crypto.randomUUID(),
        });
    }, [open]);

    function submit(event) {
        event.preventDefault();
        post('/vendor-payments', {
            onSuccess: () => onClose(),
        });
    }

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle className="font-display">New Vendor Payment</DialogTitle>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label>Vendor *</Label>
                            <NativeSelect value={data.vendor_id} onChange={(event) => setData('vendor_id', event.target.value)} className="mt-1 w-full">
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
                            <Label>Payment Date *</Label>
                            <Input type="date" value={data.payment_date} onChange={(event) => setData('payment_date', event.target.value)} className="mt-1" />
                            {errors.payment_date && <p className="mt-1 text-sm font-medium text-destructive">{errors.payment_date}</p>}
                        </div>
                        <div>
                            <Label>Amount *</Label>
                            <Input type="number" min="0" step="0.0001" value={data.amount} onChange={(event) => setData('amount', event.target.value)} className="mt-1" />
                            {errors.amount && <p className="mt-1 text-sm font-medium text-destructive">{errors.amount}</p>}
                        </div>
                        <div>
                            <Label>Payment Method *</Label>
                            <NativeSelect value={data.payment_method} onChange={(event) => setData('payment_method', event.target.value)} className="mt-1 w-full">
                                <NativeSelectOption value="cash">Cash</NativeSelectOption>
                                <NativeSelectOption value="bank_transfer">Bank Transfer</NativeSelectOption>
                                <NativeSelectOption value="check">Check</NativeSelectOption>
                                <NativeSelectOption value="credit_card">Credit Card</NativeSelectOption>
                                <NativeSelectOption value="other">Other</NativeSelectOption>
                            </NativeSelect>
                            {errors.payment_method && <p className="mt-1 text-sm font-medium text-destructive">{errors.payment_method}</p>}
                        </div>
                        <div>
                            <Label>Currency *</Label>
                            <NativeSelect value={data.currency_id} onChange={(event) => setData('currency_id', event.target.value)} className="mt-1 w-full">
                                {currencies.map((currency) => (
                                    <NativeSelectOption key={currency.id} value={currency.id}>
                                        {currency.code}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                            {errors.currency_id && <p className="mt-1 text-sm font-medium text-destructive">{errors.currency_id}</p>}
                        </div>
                        {data.currency_id && String(data.currency_id) !== String(baseCurrencyId ?? '') && (
                            <div>
                                <Label>Exchange Rate *</Label>
                                <Input type="number" min="0" step="0.0001" value={data.exchange_rate} onChange={(event) => setData('exchange_rate', event.target.value)} className="mt-1" />
                                {errors.exchange_rate && <p className="mt-1 text-sm font-medium text-destructive">{errors.exchange_rate}</p>}
                            </div>
                        )}
                        <div>
                            <Label>Cash Account *</Label>
                            <NativeSelect value={data.cash_account_id} onChange={(event) => setData('cash_account_id', event.target.value)} className="mt-1 w-full">
                                <NativeSelectOption value="">Select account...</NativeSelectOption>
                                {accounts.map((account) => (
                                    <NativeSelectOption key={account.id} value={account.id}>
                                        {account.code} - {account.name}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                            {errors.cash_account_id && <p className="mt-1 text-sm font-medium text-destructive">{errors.cash_account_id}</p>}
                        </div>
                        <div>
                            <Label>Reference</Label>
                            <Input value={data.reference} onChange={(event) => setData('reference', event.target.value)} className="mt-1" />
                        </div>
                        <div>
                            <Label>Notes</Label>
                            <Textarea value={data.notes} onChange={(event) => setData('notes', event.target.value)} className="mt-1" />
                        </div>
                    </div>

                    <div className="mt-6 flex items-center justify-end gap-3">
                        <Button type="button" variant="secondary" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            Create Payment
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}