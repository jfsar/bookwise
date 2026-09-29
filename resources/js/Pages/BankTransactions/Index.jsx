import { useEffect, useRef, useState } from 'react';
import { router, usePage } from '@inertiajs/react';

import BankTransactionForm from '@/Pages/BankTransactions/BankTransactionForm';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import StatusBadge from '@/Components/StatusBadge';
import { Button } from '@/Components/ui/button';
import { Card } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { NativeSelect, NativeSelectOption } from '@/Components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

const reconTone = {
    unreconciled: 'default',
    matched: 'info',
    reconciled: 'success',
};

export default function BankTransactionsIndex() {
    const { bankTransactions, bankAccounts, selectedBankAccountId, filters } = usePage().props;

    const [bankAccountId, setBankAccountId] = useState(selectedBankAccountId ?? filters?.bank_account_id ?? '');
    const [search, setSearch] = useState(filters?.search ?? '');
    const [reconStatus, setReconStatus] = useState(filters?.reconciliation_status ?? '');

    const [showModal, setShowModal] = useState(false);
    const [editing, setEditing] = useState(null);

    const firstRender = useRef(true);

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        const timeout = setTimeout(() => {
            router.get('/bank-transactions', {
                bank_account_id: bankAccountId || undefined,
                search: search || undefined,
                reconciliation_status: reconStatus || undefined,
            }, { preserveState: true, replace: true });
        }, 300);

        return () => clearTimeout(timeout);
    }, [bankAccountId, search, reconStatus]);

    function openCreate() {
        setEditing(null);
        setShowModal(true);
    }

    function openEdit(bankTransaction) {
        setEditing(bankTransaction);
        setShowModal(true);
    }

    return (
        <>
            <div className="flex items-center justify-between">
                <h3 className="text-2xl font-display font-bold text-on-surface dark:text-on-surface">Bank Transactions</h3>
                <Button variant="default" onClick={openCreate}>
                    Record Transaction
                </Button>
            </div>

            <div className="mt-4 flex flex-wrap gap-3">
                <div className="w-64">
                    <NativeSelect value={bankAccountId} onChange={(event) => setBankAccountId(event.target.value)} className="w-full">
                        <NativeSelectOption value="">All Bank Accounts</NativeSelectOption>
                        {bankAccounts.map((bankAccount) => (
                            <NativeSelectOption key={bankAccount.id} value={bankAccount.id}>
                                {bankAccount.bank_name} - {bankAccount.account_name}
                            </NativeSelectOption>
                        ))}
                    </NativeSelect>
                </div>
                <div className="w-64">
                    <Input type="text" placeholder="Search transactions..." value={search} onChange={(event) => setSearch(event.target.value)} />
                </div>
                <div className="w-48">
                    <NativeSelect value={reconStatus} onChange={(event) => setReconStatus(event.target.value)} className="w-full">
                        <NativeSelectOption value="">All Reconciliation</NativeSelectOption>
                        <NativeSelectOption value="unreconciled">Unreconciled</NativeSelectOption>
                        <NativeSelectOption value="matched">Matched</NativeSelectOption>
                        <NativeSelectOption value="reconciled">Reconciled</NativeSelectOption>
                    </NativeSelect>
                </div>
            </div>

            <Card className="mt-4 overflow-hidden">
                {bankTransactions?.data?.length === 0 || !bankTransactions ? (
                    <div className="px-6 py-10 text-center text-sm text-on-surface-muted dark:text-on-surface-muted">
                        No bank transactions found.
                    </div>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Date</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Description</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Reference</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground text-right">Debit</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground text-right">Credit</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Source</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Reconciliation</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {bankTransactions.data.map((tx) => (
                                <TableRow key={tx.id} className={tx.reconciliation_status === 'unreconciled' ? '' : 'opacity-50'}>
                                    <TableCell className="text-sm text-on-surface dark:text-on-surface">{tx.transaction_date}</TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{tx.description ?? '—'}</TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{tx.reference ?? '—'}</TableCell>
                                    <TableCell className="text-right text-sm">
                                        {Number(tx.debit) > 0 ? `${Number(tx.debit).toLocaleString()} ${tx.currency?.code}` : '—'}
                                    </TableCell>
                                    <TableCell className="text-right text-sm">
                                        {Number(tx.credit) > 0 ? `${Number(tx.credit).toLocaleString()} ${tx.currency?.code}` : '—'}
                                    </TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{tx.source}</TableCell>
                                    <TableCell>
                                        <StatusBadge tone={reconTone[tx.reconciliation_status] ?? 'default'}>
                                            {tx.reconciliation_status.charAt(0).toUpperCase() + tx.reconciliation_status.slice(1)}
                                        </StatusBadge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {tx.can?.update && (
                                            <Button size="sm" variant="secondary" onClick={() => openEdit(tx)}>
                                                Edit
                                            </Button>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </Card>

            <Pagination paginator={bankTransactions} />

            <BankTransactionForm
                open={showModal}
                onClose={() => setShowModal(false)}
                bankTransaction={editing}
                bankAccounts={bankAccounts}
                defaultBankAccountId={bankAccountId}
            />
        </>
    );
}

BankTransactionsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;