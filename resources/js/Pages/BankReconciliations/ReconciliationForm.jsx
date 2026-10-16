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
    statement_date: '',
    statement_start_date: '',
    statement_end_date: '',
    opening_balance: 0,
    closing_balance: 0,
};

export default function ReconciliationForm({ open, onClose, bankAccounts, defaultBankAccountId }) {
    const { data, setData, errors, post, processing } = useForm(emptyForm);

    // Re-seed on open: useForm only reads its initial values on first mount, so
    // changing the bank account filter on the index page would otherwise leave
    // this dialog defaulting to the account that was selected earlier.
    useEffect(() => {
        if (open) {
            setData({ ...emptyForm, bank_account_id: defaultBankAccountId ?? '' });
        }
    }, [open, defaultBankAccountId]);

    function submit(event) {
        event.preventDefault();

        post('/bank-reconciliations', { onSuccess: () => onClose() });
    }

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle className="font-display">Start Reconciliation</DialogTitle>
                </DialogHeader>

                <form onSubmit={submit}>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="col-span-2">
                            <Label htmlFor="recon-form-account">Bank Account *</Label>
                            <NativeSelect
                                id="recon-form-account"
                                value={data.bank_account_id}
                                onChange={(event) => setData('bank_account_id', event.target.value)}
                                className="mt-1 w-full"
                            >
                                <NativeSelectOption value="">Select bank account...</NativeSelectOption>
                                {bankAccounts.map((bankAccount) => (
                                    <NativeSelectOption key={bankAccount.id} value={bankAccount.id}>
                                        {bankAccount.bank_name} - {bankAccount.account_name}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                            {errors.bank_account_id && <p className="mt-1 text-sm font-medium text-destructive">{errors.bank_account_id}</p>}
                        </div>
                        <div className="col-span-2">
                            <Label htmlFor="recon-form-start">Statement Period Start *</Label>
                            <Input
                                id="recon-form-start"
                                type="date"
                                value={data.statement_start_date}
                                onChange={(event) => setData('statement_start_date', event.target.value)}
                                className="mt-1"
                            />
                            {errors.statement_start_date && (
                                <p className="mt-1 text-sm font-medium text-destructive">{errors.statement_start_date}</p>
                            )}
                        </div>
                        <div>
                            <Label htmlFor="recon-form-end">Statement Period End *</Label>
                            <Input
                                id="recon-form-end"
                                type="date"
                                value={data.statement_end_date}
                                onChange={(event) => setData('statement_end_date', event.target.value)}
                                className="mt-1"
                            />
                            {errors.statement_end_date && (
                                <p className="mt-1 text-sm font-medium text-destructive">{errors.statement_end_date}</p>
                            )}
                        </div>
                        <div>
                            <Label htmlFor="recon-form-date">Statement Date *</Label>
                            <Input
                                id="recon-form-date"
                                type="date"
                                value={data.statement_date}
                                onChange={(event) => setData('statement_date', event.target.value)}
                                className="mt-1"
                            />
                            {errors.statement_date && <p className="mt-1 text-sm font-medium text-destructive">{errors.statement_date}</p>}
                        </div>
                        <div>
                            <Label htmlFor="recon-form-open">Statement Opening Balance *</Label>
                            <Input
                                id="recon-form-open"
                                type="number"
                                min="0"
                                step="0.01"
                                value={data.opening_balance}
                                onChange={(event) => setData('opening_balance', event.target.value)}
                                className="mt-1"
                            />
                            {errors.opening_balance && <p className="mt-1 text-sm font-medium text-destructive">{errors.opening_balance}</p>}
                        </div>
                        <div>
                            <Label htmlFor="recon-form-close">Statement Closing Balance *</Label>
                            <Input
                                id="recon-form-close"
                                type="number"
                                min="0"
                                step="0.01"
                                value={data.closing_balance}
                                onChange={(event) => setData('closing_balance', event.target.value)}
                                className="mt-1"
                            />
                            {errors.closing_balance && <p className="mt-1 text-sm font-medium text-destructive">{errors.closing_balance}</p>}
                        </div>
                    </div>

                    <div className="mt-6 flex items-center gap-3 justify-end">
                        <Button type="button" variant="secondary" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" variant="default" disabled={processing}>
                            Start Reconciliation
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
