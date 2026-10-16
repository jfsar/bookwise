import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';

import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import ReconciliationForm from '@/Pages/BankReconciliations/ReconciliationForm';
import StatusBadge from '@/Components/StatusBadge';
import { Button } from '@/Components/ui/button';
import { Card } from '@/Components/ui/card';
import { NativeSelect, NativeSelectOption } from '@/Components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

const statusTone = {
    in_progress: 'info',
    reopened: 'warning',
    finalized: 'success',
};

export default function BankReconciliationsIndex() {
    const { reconciliations, bankAccounts, selectedBankAccountId, canManage } = usePage().props;

    const [showModal, setShowModal] = useState(false);

    function filterByBankAccount(bankAccountId) {
        router.get(
            '/bank-reconciliations',
            { bank_account_id: bankAccountId || undefined },
            { preserveState: true, replace: true }
        );
    }

    return (
        <>
            <div className="flex items-center justify-between">
                <h3 className="text-2xl font-display font-bold text-on-surface dark:text-on-surface">Bank Reconciliations</h3>
                {canManage && (
                    <Button variant="default" onClick={() => setShowModal(true)}>
                        Start Reconciliation
                    </Button>
                )}
            </div>

            <div className="mt-4 flex flex-wrap gap-3">
                <div className="w-72">
                    <NativeSelect
                        value={selectedBankAccountId ?? ''}
                        onChange={(event) => filterByBankAccount(event.target.value)}
                        className="w-full"
                    >
                        <NativeSelectOption value="">All Bank Accounts</NativeSelectOption>
                        {bankAccounts.map((bankAccount) => (
                            <NativeSelectOption key={bankAccount.id} value={bankAccount.id}>
                                {bankAccount.bank_name} - {bankAccount.account_name}
                            </NativeSelectOption>
                        ))}
                    </NativeSelect>
                </div>
            </div>

            <Card className="mt-4 overflow-hidden">
                {!reconciliations?.data?.length ? (
                    <div className="px-6 py-10 text-center text-sm text-on-surface-muted dark:text-on-surface-muted">
                        No bank reconciliations found.
                    </div>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Period</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Bank Account</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground text-right">Opening</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground text-right">Statement</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground text-right">Reconciled</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground text-right">Difference</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Status</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {reconciliations.data.map((reconciliation) => (
                                <TableRow key={reconciliation.id}>
                                    <TableCell className="text-sm text-on-surface dark:text-on-surface">
                                        {reconciliation.statement_start_date} → {reconciliation.statement_end_date}
                                    </TableCell>
                                    <TableCell className="text-sm text-muted-foreground">
                                        {reconciliation.bank_account?.bank_name ?? '—'}
                                        {reconciliation.bank_account?.masked_account_number
                                            ? ` ${reconciliation.bank_account.masked_account_number}`
                                            : ''}
                                    </TableCell>
                                    <TableCell className="text-right text-sm font-display">{Number(reconciliation.opening_balance).toLocaleString()}</TableCell>
                                    <TableCell className="text-right text-sm font-display">{Number(reconciliation.closing_balance).toLocaleString()}</TableCell>
                                    <TableCell className="text-right text-sm font-display">
                                        {Number(reconciliation.reconciled_balance).toLocaleString()}
                                    </TableCell>
                                    <TableCell className="text-right text-sm font-display">
                                        <span className={Number(reconciliation.difference) === 0 ? 'text-success' : 'text-error'}>
                                            {Number(reconciliation.difference).toLocaleString()}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge tone={statusTone[reconciliation.status] ?? 'default'}>
                                            {reconciliation.status.replace('_', ' ')}
                                        </StatusBadge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Button
                                            size="sm"
                                            variant="secondary"
                                            onClick={() => router.get(`/bank-reconciliations/${reconciliation.id}`)}
                                        >
                                            Open
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </Card>

            <Pagination paginator={reconciliations} />

            <ReconciliationForm
                open={showModal}
                onClose={() => setShowModal(false)}
                bankAccounts={bankAccounts}
                defaultBankAccountId={selectedBankAccountId}
            />
        </>
    );
}

BankReconciliationsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
