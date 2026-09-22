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

const emptyForm = {
    vendor_id: '',
    vendor_reference: '',
    bill_date: new Date().toISOString().split('T')[0],
    due_date: '',
    currency_id: '',
    exchange_rate: '1',
    notes: '',
    idempotency_key: '',
    lines: [],
};

export default function VendorBillForm({ open, onClose, vendors, accounts, currencies, taxCodes, baseCurrencyId }) {
    const { data, setData, errors, post, processing, reset } = useForm(emptyForm);

    useEffect(() => {
        if (!open) {
            return;
        }

        reset();
        setData({
            vendor_id: '',
            vendor_reference: '',
            bill_date: new Date().toISOString().split('T')[0],
            due_date: '',
            currency_id: String(baseCurrencyId ?? ''),
            exchange_rate: '1',
            notes: '',
            idempotency_key: crypto.randomUUID(),
            lines: [],
        });
    }, [open]);

    useEffect(() => {
        if (data.lines.length === 0) {
            addLine();
        }
    }, []);

    function addLine() {
        setData('lines', [
            ...data.lines,
            {
                description: '',
                quantity: '1',
                unit_price: '',
                discount_amount: '0',
                expense_account_id: '',
                tax_code_id: '',
            },
        ]);
    }

    function updateLine(index, field, value) {
        setData('lines', data.lines.map((line, lineIndex) => (lineIndex === index ? { ...line, [field]: value } : line)));
    }

    function removeLine(index) {
        setData('lines', data.lines.filter((_, lineIndex) => lineIndex !== index));
    }

    function submit(event) {
        event.preventDefault();
        post('/bills', {
            onSuccess: () => onClose(),
        });
    }

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent className="sm:max-w-4xl">
                <DialogHeader>
                    <DialogTitle className="font-display">New Bill</DialogTitle>
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
                            <Label>Bill Date *</Label>
                            <Input type="date" value={data.bill_date} onChange={(event) => setData('bill_date', event.target.value)} className="mt-1" />
                            {errors.bill_date && <p className="mt-1 text-sm font-medium text-destructive">{errors.bill_date}</p>}
                        </div>
                        <div>
                            <Label>Due Date *</Label>
                            <Input type="date" value={data.due_date} onChange={(event) => setData('due_date', event.target.value)} className="mt-1" />
                            {errors.due_date && <p className="mt-1 text-sm font-medium text-destructive">{errors.due_date}</p>}
                        </div>
                        <div>
                            <Label>Vendor Reference</Label>
                            <Input value={data.vendor_reference} onChange={(event) => setData('vendor_reference', event.target.value)} className="mt-1" />
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
                            <Label>Notes</Label>
                            <Textarea value={data.notes} onChange={(event) => setData('notes', event.target.value)} className="mt-1" />
                        </div>
                    </div>

                    <div>
                        <div className="flex items-center justify-between">
                            <h4 className="font-display text-sm font-semibold uppercase tracking-wider text-muted-foreground">Lines</h4>
                            <Button type="button" variant="secondary" size="sm" onClick={addLine}>
                                Add Line
                            </Button>
                        </div>

                        <div className="mt-3 space-y-3">
                            {data.lines.map((line, index) => (
                                <div key={index} className="rounded-lg border p-4">
                                    <div className="grid grid-cols-4 gap-3">
                                        <div className="col-span-2">
                                            <Label>Description *</Label>
                                            <Input value={line.description} onChange={(event) => updateLine(index, 'description', event.target.value)} className="mt-1" />
                                            {errors[`lines.${index}.description`] && (
                                                <p className="mt-1 text-sm font-medium text-destructive">{errors[`lines.${index}.description`]}</p>
                                            )}
                                        </div>
                                        <div>
                                            <Label>Quantity *</Label>
                                            <Input type="number" min="0" step="0.0001" value={line.quantity} onChange={(event) => updateLine(index, 'quantity', event.target.value)} className="mt-1" />
                                            {errors[`lines.${index}.quantity`] && (
                                                <p className="mt-1 text-sm font-medium text-destructive">{errors[`lines.${index}.quantity`]}</p>
                                            )}
                                        </div>
                                        <div>
                                            <Label>Unit Price *</Label>
                                            <Input type="number" min="0" step="0.0001" value={line.unit_price} onChange={(event) => updateLine(index, 'unit_price', event.target.value)} className="mt-1" />
                                            {errors[`lines.${index}.unit_price`] && (
                                                <p className="mt-1 text-sm font-medium text-destructive">{errors[`lines.${index}.unit_price`]}</p>
                                            )}
                                        </div>
                                        <div>
                                            <Label>Discount</Label>
                                            <Input type="number" min="0" step="0.0001" value={line.discount_amount} onChange={(event) => updateLine(index, 'discount_amount', event.target.value)} className="mt-1" />
                                        </div>
                                        <div>
                                            <Label>Expense Account *</Label>
                                            <NativeSelect value={line.expense_account_id} onChange={(event) => updateLine(index, 'expense_account_id', event.target.value)} className="mt-1 w-full">
                                                <NativeSelectOption value="">Select account...</NativeSelectOption>
                                                {accounts.map((account) => (
                                                    <NativeSelectOption key={account.id} value={account.id}>
                                                        {account.code} - {account.name}
                                                    </NativeSelectOption>
                                                ))}
                                            </NativeSelect>
                                            {errors[`lines.${index}.expense_account_id`] && (
                                                <p className="mt-1 text-sm font-medium text-destructive">{errors[`lines.${index}.expense_account_id`]}</p>
                                            )}
                                        </div>
                                        <div>
                                            <Label>Tax Code</Label>
                                            <NativeSelect value={line.tax_code_id ?? ''} onChange={(event) => updateLine(index, 'tax_code_id', event.target.value)} className="mt-1 w-full">
                                                <NativeSelectOption value="">None</NativeSelectOption>
                                                {taxCodes.map((taxCode) => (
                                                    <NativeSelectOption key={taxCode.id} value={taxCode.id}>
                                                        {taxCode.code} - {taxCode.name}
                                                    </NativeSelectOption>
                                                ))}
                                            </NativeSelect>
                                        </div>
                                    </div>

                                    <div className="mt-3 flex items-center justify-between">
                                        <p className="text-sm text-muted-foreground">Subtotal: {Number((Number(line.quantity) || 0) * (Number(line.unit_price) || 0)).toLocaleString(undefined, { minimumFractionDigits: 2 })}</p>
                                        {data.lines.length > 1 && (
                                            <Button type="button" variant="ghost" size="sm" className="text-destructive" onClick={() => removeLine(index)}>
                                                Remove
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                        {errors.lines && <p className="mt-2 text-sm font-medium text-destructive">{errors.lines}</p>}
                    </div>

                    <div className="mt-6 flex items-center justify-end gap-3">
                        <Button type="button" variant="secondary" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            Create Bill
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}