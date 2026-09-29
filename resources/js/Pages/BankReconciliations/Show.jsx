import { useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';

import AppLayout from '@/Layouts/AppLayout';
import StatusBadge from '@/Components/StatusBadge';
import { Button } from '@/Components/ui/button';
import { Card } from '@/Components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { NativeSelect, NativeSelectOption } from '@/Components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

const statusTone = {
    in_progress: 'info',
    reopened: 'warning',
    finalized: 'success',
};

const matchTone = {
    exact: 'success',
    partial: 'warning',
    split: 'info',
    combined: 'primary',
};

const emptyMatch = { bank_transaction_id: '', journal_entry_line_id: '', matched_amount: '' };

function money(value) {
    return Number(value ?? 0).toLocaleString();
}

export default function BankReconciliationShow() {
    const { reconciliation, matches, unmatchedTransactions, candidateJournalLines, can } = usePage().props;

    const [showMatch, setShowMatch] = useState(false);
    const [showReopen, setShowReopen] = useState(false);

    const matchForm = useForm(emptyMatch);
    const reopenForm = useForm({ reason: '' });

    function submitMatch(event) {
        event.preventDefault();

        matchForm.post(`/bank-reconciliations/${reconciliation.id}/matches`, {
            preserveScroll: true,
            onSuccess: () => {
                matchForm.reset();
                setShowMatch(false);
            },
        });
    }

    function submitReopen(event) {
        event.preventDefault();

        reopenForm.post(`/bank-reconciliations/${reconciliation.id}/reopen`, {
            onSuccess: () => {
                reopenForm.reset();
                setShowReopen(false);
            },
        });
    }

    return (
        <>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 className="text-2xl font-display font-bold text-on-surface dark:text-on-surface">Reconciliation</h3>
                    <p className="text-sm text-muted-foreground">
                        {reconciliation.bank_account?.bank_name} · {reconciliation.bank_account?.masked_account_number} ·{' '}
                        {reconciliation.statement_start_date} → {reconciliation.statement_end_date}
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <StatusBadge tone={statusTone[reconciliation.status] ?? 'default'}>{reconciliation.status.replace('_', ' ')}</StatusBadge>
                    {can.match && (
                        <Button variant="default" onClick={() => setShowMatch(true)}>
                            Match Transaction
                        </Button>
                    )}
                    {can.finalize && (
                        <Button
                            variant="outline"
                            className="text-success"
                            onClick={() => router.post(`/bank-reconciliations/${reconciliation.id}/finalize`, {}, { preserveScroll: true })}
                        >
                            Finalize
                        </Button>
                    )}
                    {can.reopen && (
                        <Button variant="outline" className="text-warning" onClick={() => setShowReopen(true)}>
                            Reopen
                        </Button>
                    )}
                </div>
            </div>

            <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {[
                    { label: 'Statement Opening', value: reconciliation.opening_balance },
                    { label: 'Statement Closing', value: reconciliation.closing_balance },
                    { label: 'Reconciled Balance', value: reconciliation.reconciled_balance },
                    {
                        label: 'Difference',
                        value: reconciliation.difference,
                        emphasis: Number(reconciliation.difference) === 0 ? 'text-success' : 'text-error',
                    },
                ].map((tile) => (
                    <Card key={tile.label} className="p-4">
                        <p className="text-xs uppercase tracking-wider text-muted-foreground">{tile.label}</p>
                        <p className={`mt-1 font-display text-xl font-bold ${tile.emphasis ?? 'text-on-surface dark:text-on-surface'}`}>
                            {money(tile.value)}
                        </p>
                    </Card>
                ))}
            </div>

            <Card className="mt-4 overflow-hidden">
                <div className="border-b border-border px-4 py-3">
                    <h4 className="font-display text-sm font-semibold text-on-surface dark:text-on-surface">Matches</h4>
                </div>
                {matches.length === 0 ? (
                    <div className="px-6 py-8 text-center text-sm text-on-surface-muted dark:text-on-surface-muted">
                        No bank transactions matched yet.
                    </div>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Bank Transaction</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Date</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Journal Line</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground text-right">Amount</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Type</TableHead>
                                {can.match && <TableHead className="text-xs uppercase tracking-wider text-muted-foreground text-right">Actions</TableHead>}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {matches.map((match) => (
                                <TableRow key={match.id}>
                                    <TableCell className="text-sm text-muted-foreground">
                                        {match.bank_transaction?.description ?? '—'}
                                    </TableCell>
                                    <TableCell className="text-sm text-on-surface dark:text-on-surface">
                                        {match.bank_transaction?.transaction_date ?? '—'}
                                    </TableCell>
                                    <TableCell className="text-sm text-muted-foreground">
                                        {match.journal_entry_line_id
                                            ? `#${match.journal_entry_line_id}`
                                            : 'Bank-side only'}
                                    </TableCell>
                                    <TableCell className="text-right text-sm font-display">{money(match.matched_amount)}</TableCell>
                                    <TableCell>
                                        <StatusBadge tone={matchTone[match.match_type] ?? 'default'}>{match.match_type}</StatusBadge>
                                    </TableCell>
                                    {can.match && (
                                        <TableCell className="text-right">
                                            <Button
                                                size="sm"
                                                variant="secondary"
                                                onClick={() =>
                                                    router.delete(
                                                        `/bank-reconciliations/${reconciliation.id}/matches/${match.id}`,
                                                        { preserveScroll: true }
                                                    )
                                                }
                                            >
                                                Unmatch
                                            </Button>
                                        </TableCell>
                                    )}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </Card>

            {can.match && unmatchedTransactions.length > 0 && (
                <Card className="mt-4 overflow-hidden">
                    <div className="border-b border-border px-4 py-3">
                        <h4 className="font-display text-sm font-semibold text-on-surface dark:text-on-surface">
                            Open bank transactions
                        </h4>
                    </div>
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Date</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Description</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Reference</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground text-right">Amount</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {unmatchedTransactions.map((transaction) => (
                                <TableRow key={transaction.id}>
                                    <TableCell className="text-sm text-on-surface dark:text-on-surface">
                                        {transaction.transaction_date}
                                    </TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{transaction.description ?? '—'}</TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{transaction.reference ?? '—'}</TableCell>
                                    <TableCell className="text-right text-sm font-display">
                                        {money(Number(transaction.debit) > 0 ? transaction.debit : transaction.credit)}
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge tone={transaction.reconciliation_status === 'matched' ? 'info' : 'default'}>
                                            {transaction.reconciliation_status}
                                        </StatusBadge>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Card>
            )}

            <Dialog open={showMatch} onOpenChange={(next) => !next && setShowMatch(false)}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle className="font-display">Match Bank Transaction</DialogTitle>
                    </DialogHeader>

                    <form onSubmit={submitMatch}>
                        <div className="grid grid-cols-2 gap-4">
                            <div className="col-span-2">
                                <Label>Bank Transaction *</Label>
                                <NativeSelect
                                    value={matchForm.data.bank_transaction_id}
                                    onChange={(event) => matchForm.setData('bank_transaction_id', event.target.value)}
                                    className="mt-1 w-full"
                                >
                                    <NativeSelectOption value="">Select transaction...</NativeSelectOption>
                                    {unmatchedTransactions.map((transaction) => (
                                        <NativeSelectOption key={transaction.id} value={transaction.id}>
                                            {transaction.transaction_date} · {transaction.description ?? '—'} ·{' '}
                                            {money(Number(transaction.debit) > 0 ? transaction.debit : transaction.credit)}
                                        </NativeSelectOption>
                                    ))}
                                </NativeSelect>
                                {matchForm.errors.bank_transaction_id && (
                                    <p className="mt-1 text-sm font-medium text-destructive">{matchForm.errors.bank_transaction_id}</p>
                                )}
                            </div>
                            <div className="col-span-2">
                                <Label>Journal Entry Line</Label>
                                <NativeSelect
                                    value={matchForm.data.journal_entry_line_id}
                                    onChange={(event) => matchForm.setData('journal_entry_line_id', event.target.value)}
                                    className="mt-1 w-full"
                                >
                                    <NativeSelectOption value="">Bank-side match only</NativeSelectOption>
                                    {candidateJournalLines.map((line) => (
                                        <NativeSelectOption key={line.id} value={line.id}>
                                            Line #{line.id} · JE #{line.journal_entry_id} ·{' '}
                                            {money(Number(line.debit) > 0 ? line.debit : line.credit)}
                                        </NativeSelectOption>
                                    ))}
                                </NativeSelect>
                                {matchForm.errors.journal_entry_line_id && (
                                    <p className="mt-1 text-sm font-medium text-destructive">{matchForm.errors.journal_entry_line_id}</p>
                                )}
                            </div>
                            <div className="col-span-2">
                                <Label>Matched Amount *</Label>
                                <Input
                                    type="number"
                                    min="0.0001"
                                    step="0.0001"
                                    value={matchForm.data.matched_amount}
                                    onChange={(event) => matchForm.setData('matched_amount', event.target.value)}
                                    className="mt-1"
                                />
                                {matchForm.errors.matched_amount && (
                                    <p className="mt-1 text-sm font-medium text-destructive">{matchForm.errors.matched_amount}</p>
                                )}
                            </div>
                        </div>

                        <div className="mt-6 flex items-center gap-3 justify-end">
                            <Button type="button" variant="secondary" onClick={() => setShowMatch(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" variant="default" disabled={matchForm.processing}>
                                Match
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={showReopen} onOpenChange={(next) => !next && setShowReopen(false)}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle className="font-display">Reopen Reconciliation</DialogTitle>
                    </DialogHeader>

                    <form onSubmit={submitReopen}>
                        <Label>Correction Reason *</Label>
                        <Input
                            value={reopenForm.data.reason}
                            onChange={(event) => reopenForm.setData('reason', event.target.value)}
                            className="mt-1"
                            placeholder="Explain why the finalized reconciliation must be reopened"
                        />
                        {reopenForm.errors.reason && (
                            <p className="mt-1 text-sm font-medium text-destructive">{reopenForm.errors.reason}</p>
                        )}

                        <div className="mt-6 flex items-center gap-3 justify-end">
                            <Button type="button" variant="secondary" onClick={() => setShowReopen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" variant="destructive" disabled={reopenForm.processing}>
                                Reopen
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

BankReconciliationShow.layout = (page) => <AppLayout>{page}</AppLayout>;
