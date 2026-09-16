import { useEffect } from 'react';
import { useForm } from '@inertiajs/react';

import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { NativeSelect, NativeSelectOption } from '@/Components/ui/native-select';
import { Textarea } from '@/Components/ui/textarea';

const emptyForm = {
    name: '',
    email: '',
    phone: '',
    tax_number: '',
    payment_terms_days: 0,
    credit_limit: '',
    receivable_account_id: '',
    notes: '',
    status: 'active',
};

export default function CustomerForm({ open, onClose, customer, accounts }) {
    const { data, setData, errors, post, put, processing } = useForm(emptyForm);

    useEffect(() => {
        if (open) {
            setData(
                customer
                    ? {
                          name: customer.name,
                          email: customer.email ?? '',
                          phone: customer.phone ?? '',
                          tax_number: customer.tax_number ?? '',
                          payment_terms_days: customer.payment_terms_days,
                          credit_limit: customer.credit_limit ?? '',
                          receivable_account_id: customer.receivable_account_id ?? '',
                          notes: customer.notes ?? '',
                          status: customer.status,
                      }
                    : emptyForm
            );
        }
    }, [open, customer]);

    function submit(event) {
        event.preventDefault();

        if (customer) {
            put(`/customers/${customer.id}`, { onSuccess: () => onClose() });
        } else {
            post('/customers', { onSuccess: () => onClose() });
        }
    }

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle className="font-display">{customer ? 'Edit Customer' : 'New Customer'}</DialogTitle>
                </DialogHeader>

                <form onSubmit={submit}>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="col-span-2">
                            <Label>Name *</Label>
                            <Input value={data.name} onChange={(event) => setData('name', event.target.value)} className="mt-1" />
                            {errors.name && <p className="mt-1 text-sm font-medium text-destructive">{errors.name}</p>}
                        </div>
                        <div>
                            <Label>Email</Label>
                            <Input type="email" value={data.email} onChange={(event) => setData('email', event.target.value)} className="mt-1" />
                            {errors.email && <p className="mt-1 text-sm font-medium text-destructive">{errors.email}</p>}
                        </div>
                        <div>
                            <Label>Phone</Label>
                            <Input value={data.phone} onChange={(event) => setData('phone', event.target.value)} className="mt-1" />
                            {errors.phone && <p className="mt-1 text-sm font-medium text-destructive">{errors.phone}</p>}
                        </div>
                        <div>
                            <Label>Tax Number</Label>
                            <Input value={data.tax_number} onChange={(event) => setData('tax_number', event.target.value)} className="mt-1" />
                            {errors.tax_number && <p className="mt-1 text-sm font-medium text-destructive">{errors.tax_number}</p>}
                        </div>
                        <div>
                            <Label>Payment Terms (days) *</Label>
                            <Input type="number" min="0" value={data.payment_terms_days} onChange={(event) => setData('payment_terms_days', event.target.value)} className="mt-1" />
                            {errors.payment_terms_days && <p className="mt-1 text-sm font-medium text-destructive">{errors.payment_terms_days}</p>}
                        </div>
                        <div className="col-span-2">
                            <Label>Receivable Account *</Label>
                            <NativeSelect value={data.receivable_account_id} onChange={(event) => setData('receivable_account_id', event.target.value)} className="mt-1 w-full">
                                <NativeSelectOption value="">Select account...</NativeSelectOption>
                                {accounts.map((account) => (
                                    <NativeSelectOption key={account.id} value={account.id}>
                                        {account.code} - {account.name}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                            {errors.receivable_account_id && <p className="mt-1 text-sm font-medium text-destructive">{errors.receivable_account_id}</p>}
                        </div>
                        <div>
                            <Label>Credit Limit</Label>
                            <Input type="number" min="0" step="0.01" value={data.credit_limit} onChange={(event) => setData('credit_limit', event.target.value)} className="mt-1" />
                            {errors.credit_limit && <p className="mt-1 text-sm font-medium text-destructive">{errors.credit_limit}</p>}
                        </div>
                        <div>
                            <Label>Status *</Label>
                            <NativeSelect value={data.status} onChange={(event) => setData('status', event.target.value)} className="mt-1 w-full">
                                <NativeSelectOption value="active">Active</NativeSelectOption>
                                <NativeSelectOption value="inactive">Inactive</NativeSelectOption>
                                <NativeSelectOption value="archived">Archived</NativeSelectOption>
                            </NativeSelect>
                            {errors.status && <p className="mt-1 text-sm font-medium text-destructive">{errors.status}</p>}
                        </div>
                        <div className="col-span-2">
                            <Label>Notes</Label>
                            <Textarea rows={2} value={data.notes} onChange={(event) => setData('notes', event.target.value)} className="mt-1" />
                        </div>
                    </div>

                    <div className="mt-6 flex items-center gap-3 justify-end">
                        <Button type="button" variant="secondary" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" variant="default" disabled={processing}>
                            {customer ? 'Update Customer' : 'Create Customer'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}