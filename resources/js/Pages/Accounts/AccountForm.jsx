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
import { NativeSelect } from '@/Components/ui/native-select';
import { Textarea } from '@/Components/ui/textarea';

const emptyForm = {
    code: '',
    name: '',
    description: '',
    account_type_id: '',
    normal_balance: 'debit',
    parent_id: '',
    is_posting_account: false,
    is_active: true,
    allow_manual_posting: true,
};

export default function AccountForm({ open, onClose, account, accountTypes, parentAccounts }) {
    const { data, setData, errors, post, put, processing } = useForm(emptyForm);

    useEffect(() => {
        if (open) {
            setData(
                account
                    ? {
                          code: account.code,
                          name: account.name,
                          description: account.description ?? '',
                          account_type_id: account.account_type_id ?? '',
                          normal_balance: account.normal_balance ?? 'debit',
                          parent_id: account.parent_id ?? '',
                          is_posting_account: account.is_posting_account,
                          is_active: account.is_active,
                          allow_manual_posting: account.allow_manual_posting,
                      }
                    : emptyForm
            );
        }
    }, [open, account]);

    function submit(event) {
        event.preventDefault();

        if (account) {
            put(`/accounts/${account.id}`, { onSuccess: () => onClose() });
        } else {
            post('/accounts', { onSuccess: () => onClose() });
        }
    }

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle className="font-display">{account ? 'Edit Account' : 'New Account'}</DialogTitle>
                </DialogHeader>

                <form onSubmit={submit}>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label>Code *</Label>
                            <Input value={data.code} onChange={(event) => setData('code', event.target.value)} className="mt-1" />
                            {errors.code && <p className="mt-1 text-sm font-medium text-destructive">{errors.code}</p>}
                        </div>
                        <div>
                            <Label>Name *</Label>
                            <Input value={data.name} onChange={(event) => setData('name', event.target.value)} className="mt-1" />
                            {errors.name && <p className="mt-1 text-sm font-medium text-destructive">{errors.name}</p>}
                        </div>
                        <div className="col-span-2">
                            <Label>Description</Label>
                            <Textarea rows={2} value={data.description} onChange={(event) => setData('description', event.target.value)} className="mt-1" />
                        </div>
                        <div>
                            <Label>Account Type *</Label>
                            <NativeSelect value={data.account_type_id} onChange={(event) => setData('account_type_id', event.target.value)} className="mt-1 w-full">
                                <option value="">Select type...</option>
                                {accountTypes.map((type) => (
                                    <option key={type.id} value={type.id}>
                                        {type.name}
                                    </option>
                                ))}
                            </NativeSelect>
                            {errors.account_type_id && <p className="mt-1 text-sm font-medium text-destructive">{errors.account_type_id}</p>}
                        </div>
                        <div>
                            <Label>Normal Balance *</Label>
                            <NativeSelect value={data.normal_balance} onChange={(event) => setData('normal_balance', event.target.value)} className="mt-1 w-full">
                                <option value="debit">Debit</option>
                                <option value="credit">Credit</option>
                            </NativeSelect>
                            {errors.normal_balance && <p className="mt-1 text-sm font-medium text-destructive">{errors.normal_balance}</p>}
                        </div>
                        <div className="col-span-2">
                            <Label>Parent Account</Label>
                            <NativeSelect value={data.parent_id} onChange={(event) => setData('parent_id', event.target.value)} className="mt-1 w-full">
                                <option value="">None (Root)</option>
                                {parentAccounts.map((parent) => (
                                    <option key={parent.id} value={parent.id}>
                                        {parent.code} - {parent.name}
                                    </option>
                                ))}
                            </NativeSelect>
                            {errors.parent_id && <p className="mt-1 text-sm font-medium text-destructive">{errors.parent_id}</p>}
                        </div>
                        <div className="col-span-2">
                            <div className="flex items-center gap-6">
                                <label className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <input
                                        type="checkbox"
                                        checked={data.is_posting_account}
                                        onChange={(event) => setData('is_posting_account', event.target.checked)}
                                        className="rounded border-outline-variant/30 text-primary focus:ring-primary/20 bg-surface-low dark:bg-surface-low"
                                    />
                                    Posting Account
                                </label>
                                <label className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <input
                                        type="checkbox"
                                        checked={data.is_active}
                                        onChange={(event) => setData('is_active', event.target.checked)}
                                        className="rounded border-outline-variant/30 text-primary focus:ring-primary/20 bg-surface-low dark:bg-surface-low"
                                    />
                                    Active
                                </label>
                                <label className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <input
                                        type="checkbox"
                                        checked={data.allow_manual_posting}
                                        onChange={(event) => setData('allow_manual_posting', event.target.checked)}
                                        className="rounded border-outline-variant/30 text-primary focus:ring-primary/20 bg-surface-low dark:bg-surface-low"
                                    />
                                    Allow Manual Posting
                                </label>
                            </div>
                        </div>
                    </div>

                    <div className="mt-6 flex items-center gap-3 justify-end">
                        <Button type="button" variant="secondary" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" variant="default" disabled={processing}>
                            {account ? 'Update Account' : 'Create Account'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}