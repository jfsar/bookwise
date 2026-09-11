import { useEffect, useState } from 'react';
import { router, useForm } from '@inertiajs/react';

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
    description: '',
    entry_date: new Date().toISOString().split('T')[0],
    fiscal_year_id: '',
    accounting_period_id: '',
    journal_id: '',
    currency_id: '',
    exchange_rate: '1',
    ledger_lines: [],
};

export default function JournalEntryForm({ open, onClose, journals, currencies, fiscalYears, accountingPeriods, accounts }) {
    const { data, setData, errors, post, processing, reset } = useForm(emptyForm);
    const [newLine, setNewLine] = useState({ account_id: '', debit: '', credit: '' });
    const [accountSearch, setAccountSearch] = useState('');
    const [isFullyBalanced, setIsFullyBalanced] = useState(false);

    useEffect(() => {
        if (open) {
            reset();
            setData(emptyForm);
            setNewLine({ account_id: '', debit: '', credit: '' });
            setAccountSearch('');
            setIsFullyBalanced(false);
        }
    }, [open]);

    useEffect(() => {
        if (!data.fiscal_year_id) {
            return;
        }

        router.reload({
            only: ['accountingPeriods'],
            preserveState: true,
            preserveScroll: true,
        });
    }, [data.fiscal_year_id]);

    const totals = data.ledger_lines.reduce(
        (acc, line) => {
            acc.debit += parseFloat(line.debit) || 0;
            acc.credit += parseFloat(line.credit) || 0;

            return acc;
        },
        { debit: 0, credit: 0 }
    );

    const isBalanced = totals.debit.toFixed(2) === totals.credit.toFixed(2);

    const searchTerm = accountSearch.trim().toLowerCase();

    const visibleAccounts = accounts.filter(
        (account) =>
            !data.ledger_lines.some((line) => line.account_id === account.id) &&
            (!searchTerm ||
                account.code.toLowerCase().includes(searchTerm) ||
                account.name.toLowerCase().includes(searchTerm))
    );

    function addLine() {
        if (!newLine.account_id) {
            return;
        }

        const account = accounts.find((account) => account.id === parseInt(newLine.account_id, 10));

        setData('ledger_lines', [
            ...data.ledger_lines,
            {
                account_id: account.id,
                account_code: account.code,
                account_name: account.name,
                debit: newLine.debit || '',
                credit: newLine.credit || '',
            },
        ]);
        setNewLine({ account_id: '', debit: '', credit: '' });
        setIsFullyBalanced(false);
    }

    function removeLine(index) {
        setData(
            'ledger_lines',
            data.ledger_lines.filter((_, i) => i !== index)
        );
        setIsFullyBalanced(false);
    }

    function submit(event) {
        event.preventDefault();
        setIsFullyBalanced(true);

        post('/journal-entries', {
            ...data,
            lines: data.ledger_lines.map(({ account_id, debit, credit }) => ({ account_id, debit, credit })),
        }, {
            onSuccess: () => onClose(),
            onError: () => setIsFullyBalanced(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent className="sm:max-w-4xl">
                <DialogHeader>
                    <DialogTitle className="font-display">New Journal Entry</DialogTitle>
                </DialogHeader>

                <form onSubmit={submit}>
                    {Object.keys(errors).length > 0 && (
                        <div className="mb-4 rounded-lg bg-error-container dark:bg-error-container px-4 py-3 text-sm text-error dark:text-error">
                            {Object.values(errors).map((error, index) => (
                                <p key={index}>{error}</p>
                            ))}
                        </div>
                    )}

                    <div className="space-y-4">
                        <div>
                            <Label>Description *</Label>
                            <Textarea rows={2} value={data.description} onChange={(event) => setData('description', event.target.value)} className="mt-1" />
                            {errors.description && <p className="mt-1 text-sm font-medium text-destructive">{errors.description}</p>}
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <Label>Entry Date *</Label>
                                <Input
                                    type="date"
                                    value={data.entry_date}
                                    onChange={(event) => setData('entry_date', event.target.value)}
                                    className="mt-1"
                                />
                                {errors.entry_date && <p className="mt-1 text-sm font-medium text-destructive">{errors.entry_date}</p>}
                            </div>
                            <div>
                                <Label>Fiscal Year *</Label>
                                <NativeSelect
                                    value={data.fiscal_year_id}
                                    onChange={(event) => {
                                        setData({ ...data, fiscal_year_id: event.target.value, accounting_period_id: '' });
                                    }}
                                    className="mt-1 w-full"
                                >
                                    <option value="">Select fiscal year...</option>
                                    {fiscalYears.map((fiscalYear) => (
                                        <option key={fiscalYear.id} value={fiscalYear.id}>
                                            {fiscalYear.name}
                                        </option>
                                    ))}
                                </NativeSelect>
                                {errors.fiscal_year_id && <p className="mt-1 text-sm font-medium text-destructive">{errors.fiscal_year_id}</p>}
                            </div>
                            <div>
                                <Label>Accounting Period *</Label>
                                <NativeSelect
                                    value={data.accounting_period_id}
                                    onChange={(event) => setData('accounting_period_id', event.target.value)}
                                    className="mt-1 w-full"
                                    disabled={!data.fiscal_year_id}
                                >
                                    <option value="">Select period...</option>
                                    {accountingPeriods
                                        .filter((period) => period.fiscal_year_id === parseInt(data.fiscal_year_id, 10))
                                        .map((period) => (
                                            <option key={period.id} value={period.id}>
                                                {period.name}
                                            </option>
                                        ))}
                                </NativeSelect>
                                {errors.accounting_period_id && (
                                    <p className="mt-1 text-sm font-medium text-destructive">{errors.accounting_period_id}</p>
                                )}
                            </div>
                            <div>
                                <Label>Journal *</Label>
                                <NativeSelect value={data.journal_id} onChange={(event) => setData('journal_id', event.target.value)} className="mt-1 w-full">
                                    <option value="">Select journal...</option>
                                    {journals.map((journal) => (
                                        <option key={journal.id} value={journal.id}>
                                            {journal.code} - {journal.name}
                                        </option>
                                    ))}
                                </NativeSelect>
                                {errors.journal_id && <p className="mt-1 text-sm font-medium text-destructive">{errors.journal_id}</p>}
                            </div>
                            <div>
                                <Label>Currency *</Label>
                                <NativeSelect value={data.currency_id} onChange={(event) => setData('currency_id', event.target.value)} className="mt-1 w-full">
                                    <option value="">Select currency...</option>
                                    {currencies.map((currency) => (
                                        <option key={currency.id} value={currency.id}>
                                            {currency.code} - {currency.name}
                                        </option>
                                    ))}
                                </NativeSelect>
                                {errors.currency_id && <p className="mt-1 text-sm font-medium text-destructive">{errors.currency_id}</p>}
                            </div>
                            <div>
                                <Label>Exchange Rate</Label>
                                <Input
                                    type="number"
                                    step="0.0001"
                                    min="0"
                                    value={data.exchange_rate}
                                    onChange={(event) => setData('exchange_rate', event.target.value)}
                                    className="mt-1"
                                />
                                {errors.exchange_rate && <p className="mt-1 text-sm font-medium text-destructive">{errors.exchange_rate}</p>}
                            </div>
                        </div>

                        <div className="rounded-lg bg-surface-low dark:bg-surface-low p-4">
                            <div className="flex items-center justify-between">
                                <h4 className="text-sm font-medium text-on-surface dark:text-on-surface">Ledger Lines *</h4>
                                <Input
                                    type="text"
                                    placeholder="Search accounts..."
                                    value={accountSearch}
                                    onChange={(event) => setAccountSearch(event.target.value)}
                                    className="w-56"
                                />
                            </div>

                            <div className="mt-3 grid grid-cols-[1fr_120px_120px_auto] gap-3 items-center">
                                <NativeSelect
                                    value={newLine.account_id}
                                    onChange={(event) => setNewLine({ ...newLine, account_id: event.target.value })}
                                    className="w-full"
                                >
                                    <option value="">Select account...</option>
                                    {visibleAccounts.map((account) => (
                                            <option key={account.id} value={account.id}>
                                                {account.code} - {account.name}
                                            </option>
                                        ))}
                                </NativeSelect>
                                <Input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="Debit"
                                    value={newLine.debit}
                                    onChange={(event) => setNewLine({ ...newLine, debit: event.target.value })}
                                />
                                <Input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="Credit"
                                    value={newLine.credit}
                                    onChange={(event) => setNewLine({ ...newLine, credit: event.target.value })}
                                />
                                <Button type="button" size="sm" variant="secondary" onClick={addLine}>
                                    Add
                                </Button>
                            </div>

                            {data.ledger_lines.length > 0 && (
                                <div className="mt-4 space-y-2">
                                    {data.ledger_lines.map((line, index) => (
                                        <div
                                            key={index}
                                            className="grid grid-cols-[1fr_120px_120px_auto] gap-3 items-center rounded-lg bg-surface-container dark:bg-surface-container px-3 py-2"
                                        >
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium text-on-surface dark:text-on-surface">
                                                    {line.account_code} - {line.account_name}
                                                </p>
                                            </div>
                                            <p className="font-display text-sm tabular-nums text-muted-foreground">{line.debit ? `$${line.debit}` : '—'}</p>
                                            <p className="font-display text-sm tabular-nums text-muted-foreground">{line.credit ? `$${line.credit}` : '—'}</p>
                                            <Button type="button" size="sm" variant="secondary" onClick={() => removeLine(index)}>
                                                Remove
                                            </Button>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        <div
                            className={`flex items-center justify-between rounded-lg px-4 py-3 ${
                                isFullyBalanced
                                    ? isBalanced
                                        ? 'bg-success-container dark:bg-success-container text-success dark:text-success'
                                        : 'bg-error-container dark:bg-error-container text-error dark:text-error'
                                    : 'bg-surface-low dark:bg-surface-low text-on-surface-muted dark:text-on-surface-muted'
                            }`}
                        >
                            <span className="text-sm font-medium">Totals</span>
                            <div className="flex items-center gap-6">
                                <span className="font-display text-sm tabular-nums">Debit: ${totals.debit.toFixed(2)}</span>
                                <span className="font-display text-sm tabular-nums">Credit: ${totals.credit.toFixed(2)}</span>
                                <span className="text-sm font-medium font-display">
                                    {isBalanced ? '✓ Balanced' : 'Not Balanced'}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="mt-6 flex items-center gap-3 justify-end">
                        <Button type="button" variant="destructive" onClick={() => reset()}>
                            Reset
                        </Button>
                        <Button type="button" variant="secondary" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" variant="default" disabled={processing || data.ledger_lines.length === 0}>
                            Submit Entry
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}