import { useEffect, useRef, useState } from 'react';
import { router, usePage } from '@inertiajs/react';

import BankAccountForm from '@/Pages/BankAccounts/BankAccountForm';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import StatusBadge from '@/Components/StatusBadge';
import { Button } from '@/Components/ui/button';
import { Card } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { NativeSelect, NativeSelectOption } from '@/Components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

export default function BankAccountsIndex() {
    const { bankAccounts, accounts, currencies, filters } = usePage().props;

    const [search, setSearch] = useState(filters?.search ?? '');
    const [status, setStatus] = useState(filters?.status ?? '');

    const [showModal, setShowModal] = useState(false);
    const [editing, setEditing] = useState(null);

    const firstRender = useRef(true);

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        const timeout = setTimeout(() => {
            router.get('/bank-accounts', {
                search: search || undefined,
                status: status || undefined,
            }, { preserveState: true, replace: true });
        }, 300);

        return () => clearTimeout(timeout);
    }, [search, status]);

    function openCreate() {
        setEditing(null);
        setShowModal(true);
    }

    function openEdit(bankAccount) {
        setEditing(bankAccount);
        setShowModal(true);
    }

    return (
        <>
            <div className="flex items-center justify-between">
                <h3 className="text-2xl font-display font-bold text-on-surface dark:text-on-surface">Bank Accounts</h3>
                <Button variant="default" onClick={openCreate}>
                    New Bank Account
                </Button>
            </div>

            <div className="mt-4 flex flex-wrap gap-3">
                <div className="w-64">
                    <Input type="text" placeholder="Search bank accounts..." value={search} onChange={(event) => setSearch(event.target.value)} />
                </div>
                <div className="w-48">
                    <NativeSelect value={status} onChange={(event) => setStatus(event.target.value)} className="w-full">
                        <NativeSelectOption value="">All Status</NativeSelectOption>
                        <NativeSelectOption value="active">Active</NativeSelectOption>
                        <NativeSelectOption value="inactive">Inactive</NativeSelectOption>
                        <NativeSelectOption value="closed">Closed</NativeSelectOption>
                    </NativeSelect>
                </div>
            </div>

            <Card className="mt-4 overflow-hidden">
                {bankAccounts?.data?.length === 0 || !bankAccounts ? (
                    <div className="px-6 py-10 text-center text-sm text-on-surface-muted dark:text-on-surface-muted">
                        No bank accounts found.
                    </div>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Bank</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Account</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Number</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Currency</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Ledger Account</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Status</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {bankAccounts.data.map((bankAccount) => (
                                <TableRow key={bankAccount.id} className={bankAccount.status === 'active' ? '' : 'opacity-50'}>
                                    <TableCell className="font-display font-medium text-on-surface dark:text-on-surface">
                                        {bankAccount.bank_name}
                                    </TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{bankAccount.account_name}</TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{bankAccount.masked_account_number ?? '—'}</TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{bankAccount.currency?.code ?? '—'}</TableCell>
                                    <TableCell className="text-sm text-muted-foreground">
                                        {bankAccount.account?.code ?? '—'}
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge tone={bankAccount.status === 'active' ? 'success' : bankAccount.status === 'closed' ? 'error' : 'default'}>
                                            {bankAccount.status.charAt(0).toUpperCase() + bankAccount.status.slice(1)}
                                        </StatusBadge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {bankAccount.can?.update && (
                                            <Button size="sm" variant="secondary" onClick={() => openEdit(bankAccount)}>
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

            <Pagination paginator={bankAccounts} />

            <BankAccountForm
                open={showModal}
                onClose={() => setShowModal(false)}
                bankAccount={editing}
                accounts={accounts}
                currencies={currencies}
            />
        </>
    );
}

BankAccountsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;