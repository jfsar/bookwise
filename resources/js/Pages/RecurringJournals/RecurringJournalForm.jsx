import { useEffect, useState } from 'react';
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

function today() {
    return new Date().toISOString().split('T')[0];
}

function blankLines() {
    return [
        { account_id: '', description: '', debit: '', credit: '' },
        { account_id: '', description: '', debit: '', credit: '' },
    ];
}

export default function RecurringJournalForm({ open, onClose, recurringJournal, journals, accounts, frequencies }) {
    const editing = Boolean(recurringJournal);
    const { data, setData, errors, post, put, processing, reset } = useForm({
        name: '',
        description: '',
        journal_id: '',
        frequency: 'monthly',
        start_date: today(),
        end_date: '',
        auto_submit: false,
        lines: blankLines(),
    });
    const [showLines, setShowLines] = useState(false);

    useEffect(() => {
        if (!open) {
            return;
        }

        if (recurringJournal) {
            reset();
            setData({
                name: recurringJournal.name ?? '',
                description: recurringJournal.description ?? '',
                journal_id: String(recurringJournal.journal_id ?? ''),
                frequency: recurringJournal.frequency ?? 'monthly',
                start_date: recurringJournal.start_date ?? today(),
                end_date: recurringJournal.end_date ?? '',
                auto_submit: Boolean(recurringJournal.auto_submit),
                lines: recurringJournal.lines.length
                    ? recurringJournal.lines.map((line) => ({
                          account_id: String(line.account_id),
                          description: line.description ?? '',
                          debit: line.debit ? String(line.debit) : '',
                          credit: line.credit ? String(line.credit) : '',
                      }))
                    : blankLines(),
            });
            setShowLines(true);

            return;
        }

        reset();
        setData({
            name: '',
            description: '',
            journal_id: journals.length ? String(journals[0].id) : '',
            frequency: 'monthly',
            start_date: today(),
            end_date: '',
            auto_submit: false,
            lines: blankLines(),
        });
        setShowLines(false);
    }, [open, recurringJournal]);

    function addLine() {
        setData('lines', [...data.lines, { account_id: '', description: '', debit: '', credit: '' }]);
    }

    function updateLine(index, field, value) {
        setData(
            'lines',
            data.lines.map((line, lineIndex) => (lineIndex === index ? { ...line, [field]: value } : line)),
        );
    }

    function removeLine(index) {
        setData('lines', data.lines.filter((_, lineIndex) => lineIndex !== index));
    }

    const totalDebit = data.lines.reduce((sum, line) => sum + (Number(line.debit) || 0), 0);
    const totalCredit = data.lines.reduce((sum, line) => sum + (Number(line.credit) || 0), 0);
    const balanced = Math.abs(totalDebit - totalCredit) < 0.005;

    function submit(event) {
        event.preventDefault();

        const payload = {
            ...data,
            end_date: data.end_date || null,
            auto_submit: Boolean(data.auto_submit),
            lines: data.lines.filter((line) => line.account_id !== ''),
        };

        if (editing) {
            put(`/recurring-journals/${recurringJournal.id}`, payload, { onSuccess: () => onClose() });

            return;
        }

        post('/recurring-journals', payload, { onSuccess: () => onClose() });
    }

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent className="sm:max-w-4xl">
                <DialogHeader>
                    <DialogTitle className="font-display">
                        {editing ? `Edit ${recurringJournal.name}` : 'New Recurring Journal'}
                    </DialogTitle>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label>Name *</Label>
                            <Input
                                value={data.name}
                                onChange={(event) => setData('name', event.target.value)}
                                placeholder="e.g. Monthly Rent"
                                className="mt-1"
                            />
                            {errors.name && <p className="mt-1 text-sm font-medium text-destructive">{errors.name}</p>}
                        </div>
                        <div>
                            <Label>Journal *</Label>
                            <NativeSelect
                                value={data.journal_id}
                                onChange={(event) => setData('journal_id', event.target.value)}
                                className="mt-1 w-full"
                            >
                                {journals.map((journal) => (
                                    <NativeSelectOption key={journal.id} value={journal.id}>
                                        {journal.code} - {journal.name}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                            {errors.journal_id && (
                                <p className="mt-1 text-sm font-medium text-destructive">{errors.journal_id}</p>
                            )}
                        </div>
                        <div>
                            <Label>Frequency *</Label>
                            <NativeSelect
                                value={data.frequency}
                                onChange={(event) => setData('frequency', event.target.value)}
                                className="mt-1 w-full"
                            >
                                {frequencies.map((frequency) => (
                                    <NativeSelectOption key={frequency.value} value={frequency.value}>
                                        {frequency.label}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                            {errors.frequency && (
                                <p className="mt-1 text-sm font-medium text-destructive">{errors.frequency}</p>
                            )}
                        </div>
                        <div>
                            <Label>Start Date *</Label>
                            <Input
                                type="date"
                                value={data.start_date}
                                onChange={(event) => setData('start_date', event.target.value)}
                                className="mt-1"
                            />
                            {errors.start_date && (
                                <p className="mt-1 text-sm font-medium text-destructive">{errors.start_date}</p>
                            )}
                        </div>
                        <div>
                            <Label>End Date</Label>
                            <Input
                                type="date"
                                value={data.end_date ?? ''}
                                onChange={(event) => setData('end_date', event.target.value)}
                                className="mt-1"
                            />
                            {errors.end_date && (
                                <p className="mt-1 text-sm font-medium text-destructive">{errors.end_date}</p>
                            )}
                        </div>
                        <div>
                            <Label>Description</Label>
                            <Textarea
                                rows={2}
                                value={data.description}
                                onChange={(event) => setData('description', event.target.value)}
                                className="mt-1"
                            />
                            {errors.description && (
                                <p className="mt-1 text-sm font-medium text-destructive">{errors.description}</p>
                            )}
                        </div>
                    </div>

                    <div className="rounded-lg border border-border p-3">
                        <label className="flex items-start gap-3 text-sm">
                            <input
                                type="checkbox"
                                checked={data.auto_submit}
                                onChange={(event) => setData('auto_submit', event.target.checked)}
                                className="mt-0.5 size-4 rounded border-border"
                            />
                            <span>
                                <span className="font-medium">Submit generated entries automatically</span>
                                <span className="block text-xs text-muted-foreground">
                                    Generated entries are always drafts. When enabled they move straight to the approval queue,
                                    never to posted.
                                </span>
                            </span>
                        </label>
                    </div>

                    <div>
                        <div className="flex items-center justify-between">
                            <h4 className="font-display text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                                Lines
                            </h4>
                            <Button type="button" variant="secondary" size="sm" onClick={() => (showLines ? addLine() : setShowLines(true))}>
                                {showLines ? 'Add Line' : 'Configure Lines'}
                            </Button>
                        </div>

                        {showLines && (
                            <div className="mt-3 space-y-3">
                                {data.lines.map((line, index) => (
                                    <div key={index} className="rounded-lg border p-4">
                                        <div className="grid grid-cols-4 gap-3">
                                            <div className="col-span-2">
                                                <Label>Account *</Label>
                                                <NativeSelect
                                                    value={line.account_id}
                                                    onChange={(event) => updateLine(index, 'account_id', event.target.value)}
                                                    className="mt-1 w-full"
                                                >
                                                    <NativeSelectOption value="">Select account...</NativeSelectOption>
                                                    {accounts.map((account) => (
                                                        <NativeSelectOption key={account.id} value={account.id}>
                                                            {account.code} - {account.name}
                                                        </NativeSelectOption>
                                                    ))}
                                                </NativeSelect>
                                                {errors[`lines.${index}.account_id`] && (
                                                    <p className="mt-1 text-sm font-medium text-destructive">
                                                        {errors[`lines.${index}.account_id`]}
                                                    </p>
                                                )}
                                            </div>
                                            <div className="col-span-2">
                                                <Label>Description</Label>
                                                <Input
                                                    value={line.description}
                                                    onChange={(event) => updateLine(index, 'description', event.target.value)}
                                                    className="mt-1"
                                                />
                                            </div>
                                            <div>
                                                <Label>Debit</Label>
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    step="0.0001"
                                                    value={line.debit}
                                                    onChange={(event) => updateLine(index, 'debit', event.target.value)}
                                                    className="mt-1"
                                                />
                                            </div>
                                            <div>
                                                <Label>Credit</Label>
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    step="0.0001"
                                                    value={line.credit}
                                                    onChange={(event) => updateLine(index, 'credit', event.target.value)}
                                                    className="mt-1"
                                                />
                                            </div>
                                        </div>

                                        {data.lines.length > 2 && (
                                            <div className="mt-3 flex justify-end">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    className="text-destructive"
                                                    onClick={() => removeLine(index)}
                                                >
                                                    Remove
                                                </Button>
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}

                        <p className={`mt-3 text-sm ${balanced ? 'text-muted-foreground' : 'font-medium text-destructive'}`}>
                            Debits {totalDebit.toLocaleString(undefined, { minimumFractionDigits: 2 })} / Credits{' '}
                            {totalCredit.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                            {balanced ? '' : ' — lines must balance'}
                        </p>
                        {errors.lines && <p className="mt-1 text-sm font-medium text-destructive">{errors.lines}</p>}
                    </div>

                    <div className="mt-6 flex items-center justify-end gap-3">
                        <Button type="button" variant="secondary" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing || !balanced}>
                            {editing ? 'Save Changes' : 'Create Recurring Journal'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}