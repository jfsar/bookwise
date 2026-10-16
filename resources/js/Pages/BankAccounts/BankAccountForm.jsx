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
    bank_name: '',
    account_name: '',
    account_number: '',
    iban: '',
    routing_number: '',
    account_id: '',
    currency_id: '',
    opening_balance: 0,
    status: 'active',
};

export default function BankAccountForm({ open, onClose, bankAccount, accounts, currencies }) {
    const { data, setData, errors, post, put, processing } = useForm(emptyForm);

    useEffect(() => {
        if (open) {
            setData(
                bankAccount
                    ? {
                          bank_name: bankAccount.bank_name,
                          account_name: bankAccount.account_name,
                          account_number: '',
                          iban: '',
                          routing_number: '',
                          account_id: bankAccount.account_id ?? '',
                          currency_id: bankAccount.currency_id ?? '',
                          opening_balance: bankAccount.balance ?? 0,
                          status: bankAccount.status,
                      }
                    : emptyForm
            );
        }
    }, [open, bankAccount]);

    function submit(event) {
        event.preventDefault();

        if (bankAccount) {
            put(`/bank-accounts/${bankAccount.id}`, { onSuccess: () => onClose() });
        } else {
            post('/bank-accounts', { onSuccess: () => onClose() });
        }
    }

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle className="font-display">
                        {bankAccount ? 'Edit Bank Account' : 'New Bank Account'}
                    </DialogTitle>
                </DialogHeader>

                <form onSubmit={submit}>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="col-span-2">
                            <Label>Bank Name *</Label>
                            <Input value={data.bank_name} onChange={(event) => setData('bank_name', event.target.value)} className="mt-1" />
                            {errors.bank_name && <p className="mt-1 text-sm font-medium text-destructive">{errors.bank_name}</p>}
                        </div>
                        <div className="col-span-2">
                            <Label>Account Name *</Label>
                            <Input value={data.account_name} onChange={(event) => setData('account_name', event.target.value)} className="mt-1" />
                            {errors.account_name && <p className="mt-1 text-sm font-medium text-destructive">{errors.account_name}</p>}
                        </div>
                        <div className="col-span-2">
                            <Label>Account Number</Label>
                            <Input
                                type="password"
                                autoComplete="off"
                                placeholder={bankAccount?.last4 ? `•••• ${bankAccount.last4}` : 'Enter account number'}
                                value={data.account_number}
                                onChange={(event) => setData('account_number', event.target.value)}
                                className="mt-1"
                            />
                            {bankAccount?.last4 && (
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Current account ends in {bankAccount.last4}. Leave blank to keep it unchanged.
                                </p>
                            )}
                            {errors.account_number && <p className="mt-1 text-sm font-medium text-destructive">{errors.account_number}</p>}
                        </div>
                        <div>
                            <Label>IBAN</Label>
                            <Input value={data.iban} onChange={(event) => setData('iban', event.target.value)} className="mt-1" />
                            {errors.iban && <p className="mt-1 text-sm font-medium text-destructive">{errors.iban}</p>}
                        </div>
                        <div>
                            <Label>Routing Number</Label>
                            <Input value={data.routing_number} onChange={(event) => setData('routing_number', event.target.value)} className="mt-1" />
                            {errors.routing_number && <p className="mt-1 text-sm font-medium text-destructive">{errors.routing_number}</p>}
                        </div>
                        <div className="col-span-2">
                            <Label>Currency *</Label>
                            <NativeSelect value={data.currency_id} onChange={(event) => setData('currency_id', event.target.value)} className="mt-1 w-full">
                                <NativeSelectOption value="">Select currency...</NativeSelectOption>
                                {currencies.map((currency) => (
                                    <NativeSelectOption key={currency.id} value={currency.id}>
                                        {currency.code} - {currency.name}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                            {errors.currency_id && <p className="mt-1 text-sm font-medium text-destructive">{errors.currency_id}</p>}
                        </div>
                        <div className="col-span-2">
                            <Label>Ledger Account *</Label>
                            <NativeSelect value={data.account_id} onChange={(event) => setData('account_id', event.target.value)} className="mt-1 w-full">
                                <NativeSelectOption value="">Select account...</NativeSelectOption>
                                {accounts.map((account) => (
                                    <NativeSelectOption key={account.id} value={account.id}>
                                        {account.code} - {account.name}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                            {errors.account_id && <p className="mt-1 text-sm font-medium text-destructive">{errors.account_id}</p>}
                        </div>
                        <div>
                            <Label>Opening Balance *</Label>
                            <Input type="number" min="0" step="0.01" value={data.opening_balance} onChange={(event) => setData('opening_balance', event.target.value)} className="mt-1" />
                            {errors.opening_balance && <p className="mt-1 text-sm font-medium text-destructive">{errors.opening_balance}</p>}
                        </div>
                        <div>
                            <Label>Status *</Label>
                            <NativeSelect value={data.status} onChange={(event) => setData('status', event.target.value)} className="mt-1 w-full">
                                <NativeSelectOption value="active">Active</NativeSelectOption>
                                <NativeSelectOption value="inactive">Inactive</NativeSelectOption>
                                <NativeSelectOption value="closed">Closed</NativeSelectOption>
                            </NativeSelect>
                            {errors.status && <p className="mt-1 text-sm font-medium text-destructive">{errors.status}</p>}
                        </div>
                    </div>

                    <div className="mt-6 flex items-center gap-3 justify-end">
                        <Button type="button" variant="secondary" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" variant="default" disabled={processing}>
                            {bankAccount ? 'Update Bank Account' : 'Create Bank Account'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}