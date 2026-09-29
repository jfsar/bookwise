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

const emptyForm = {
    bank_account_id: '',
    transaction_date: new Date().toISOString().slice(0, 10),
    value_date: '',
    direction: 'debit',
    amount: '',
    reference: '',
    description: '',
};

export default function BankTransactionForm({ open, onClose, bankTransaction, bankAccounts, defaultBankAccountId }) {
    const { data, setData, errors, post, put, processing } = useForm(emptyForm);

    useEffect(() => {
        if (open) {
            setData(
                bankTransaction
                    ? {
                          bank_account_id: bankTransaction.bank_account_id ?? '',
                          transaction_date: bankTransaction.transaction_date,
                          value_date: bankTransaction.value_date ?? '',
                          direction: Number(bankTransaction.debit) > 0 ? 'debit' : 'credit',
                          amount: Number(bankTransaction.debit) > 0 ? bankTransaction.debit : bankTransaction.credit,
                          reference: bankTransaction.reference ?? '',
                          description: bankTransaction.description ?? '',
                      }
                    : { ...emptyForm, bank_account_id: defaultBankAccountId ?? '' }
            );
        }
    }, [open, bankTransaction, defaultBankAccountId]);

    function submit(event) {
        event.preventDefault();

        if (bankTransaction) {
            put(`/bank-transactions/${bankTransaction.id}`, { onSuccess: () => onClose() });
        } else {
            post('/bank-transactions', { onSuccess: () => onClose() });
        }
    }

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle className="font-display">
                        {bankTransaction ? 'Edit Bank Transaction' : 'Record Bank Transaction'}
                    </DialogTitle>
                </DialogHeader>

                <form onSubmit={submit}>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="col-span-2">
                            <Label>Bank Account *</Label>
                            <NativeSelect value={data.bank_account_id} onChange={(event) => setData('bank_account_id', event.target.value)} className="mt-1 w-full" disabled={!!bankTransaction}>
                                <NativeSelectOption value="">Select bank account...</NativeSelectOption>
                                {bankAccounts.map((bankAccount) => (
                                    <NativeSelectOption key={bankAccount.id} value={bankAccount.id}>
                                        {bankAccount.bank_name} - {bankAccount.account_name}
                                        {bankAccount.masked_account_number ? ` (${bankAccount.masked_account_number})` : ''}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                            {errors.bank_account_id && <p className="mt-1 text-sm font-medium text-destructive">{errors.bank_account_id}</p>}
                        </div>
                        <div>
                            <Label>Transaction Date *</Label>
                            <Input type="date" value={data.transaction_date} onChange={(event) => setData('transaction_date', event.target.value)} className="mt-1" />
                            {errors.transaction_date && <p className="mt-1 text-sm font-medium text-destructive">{errors.transaction_date}</p>}
                        </div>
                        <div>
                            <Label>Value Date</Label>
                            <Input type="date" value={data.value_date} onChange={(event) => setData('value_date', event.target.value)} className="mt-1" />
                            {errors.value_date && <p className="mt-1 text-sm font-medium text-destructive">{errors.value_date}</p>}
                        </div>
                        <div>
                            <Label>Direction *</Label>
                            <NativeSelect value={data.direction} onChange={(event) => setData('direction', event.target.value)} className="mt-1 w-full">
                                <NativeSelectOption value="debit">Debit (money in)</NativeSelectOption>
                                <NativeSelectOption value="credit">Credit (money out)</NativeSelectOption>
                            </NativeSelect>
                            {errors.direction && <p className="mt-1 text-sm font-medium text-destructive">{errors.direction}</p>}
                        </div>
                        <div>
                            <Label>Amount *</Label>
                            <Input type="number" min="0.01" step="0.01" value={data.amount} onChange={(event) => setData('amount', event.target.value)} className="mt-1" />
                            {errors.amount && <p className="mt-1 text-sm font-medium text-destructive">{errors.amount}</p>}
                        </div>
                        <div className="col-span-2">
                            <Label>Reference</Label>
                            <Input value={data.reference} onChange={(event) => setData('reference', event.target.value)} className="mt-1" />
                            {errors.reference && <p className="mt-1 text-sm font-medium text-destructive">{errors.reference}</p>}
                        </div>
                        <div className="col-span-2">
                            <Label>Description</Label>
                            <Input value={data.description} onChange={(event) => setData('description', event.target.value)} className="mt-1" />
                            {errors.description && <p className="mt-1 text-sm font-medium text-destructive">{errors.description}</p>}
                        </div>
                    </div>

                    <div className="mt-6 flex items-center gap-3 justify-end">
                        <Button type="button" variant="secondary" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" variant="default" disabled={processing}>
                            {bankTransaction ? 'Update Transaction' : 'Record Transaction'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}