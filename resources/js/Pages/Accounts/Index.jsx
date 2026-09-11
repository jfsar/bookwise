import { useEffect, useRef, useState } from 'react';
import { router, usePage } from '@inertiajs/react';

import AccountForm from '@/Pages/Accounts/AccountForm';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import StatusBadge from '@/Components/StatusBadge';
import { Button } from '@/Components/ui/button';
import { Card } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { NativeSelect } from '@/Components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

export default function AccountsIndex() {
    const { accounts, accountTypes, parentAccounts, filters } = usePage().props;

    const [search, setSearch] = useState(filters?.search ?? '');
    const [type, setType] = useState(filters?.type ?? '');
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
            router.get('/accounts', {
                search: search || undefined,
                type: type || undefined,
                status: status || undefined,
            }, { preserveState: true, replace: true });
        }, 300);

        return () => clearTimeout(timeout);
    }, [search, type, status]);

    function openCreate() {
        setEditing(null);
        setShowModal(true);
    }

    function openEdit(account) {
        setEditing(account);
        setShowModal(true);
    }

    return (
        <>
            <div className="flex items-center justify-between">
                <h3 className="text-2xl font-display font-bold text-on-surface dark:text-on-surface">Chart of Accounts</h3>
                <Button variant="default" onClick={openCreate}>
                    New Account
                </Button>
            </div>

            <div className="mt-4 flex flex-wrap gap-3">
                <div className="w-64">
                    <Input type="text" placeholder="Search accounts..." value={search} onChange={(event) => setSearch(event.target.value)} />
                </div>
                <div className="w-48">
                    <NativeSelect value={type} onChange={(event) => setType(event.target.value)} className="w-full">
                        <option value="">All Types</option>
                        {accountTypes.map((accountType) => (
                            <option key={accountType.id} value={accountType.id}>
                                {accountType.name}
                            </option>
                        ))}
                    </NativeSelect>
                </div>
                <div className="w-48">
                    <NativeSelect value={status} onChange={(event) => setStatus(event.target.value)} className="w-full">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </NativeSelect>
                </div>
            </div>

            <Card className="mt-4 overflow-hidden">
                {accounts?.data?.length === 0 || !accounts ? (
                    <div className="px-6 py-10 text-center text-sm text-on-surface-muted dark:text-on-surface-muted">
                        No accounts found.
                    </div>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Code</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Name</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Type</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Balance</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Parent</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Status</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {accounts.data.map((account) => (
                                <TableRow key={account.id} className={account.is_active ? '' : 'opacity-50'}>
                                    <TableCell className="font-display font-medium text-on-surface dark:text-on-surface">
                                        {account.parent_id ? <span className="whitespace-pre">{'\u00A0\u00A0'}</span> : null}
                                        {account.code}
                                    </TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{account.name}</TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{account.account_type?.name}</TableCell>
                                    <TableCell>
                                        <StatusBadge tone={account.normal_balance === 'debit' ? 'info' : 'primary'}>
                                            {account.normal_balance[0].toUpperCase() + account.normal_balance.slice(1)}
                                        </StatusBadge>
                                    </TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{account.parent?.code}</TableCell>
                                    <TableCell>
                                        <div className="flex items-center gap-2">
                                            <StatusBadge tone={account.is_posting_account ? 'success' : 'default'}>
                                                {account.is_posting_account ? 'Posting' : 'Header'}
                                            </StatusBadge>
                                            <StatusBadge tone={account.is_active ? 'success' : 'error'}>
                                                {account.is_active ? 'Active' : 'Inactive'}
                                            </StatusBadge>
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Button size="sm" variant="secondary" onClick={() => openEdit(account)}>
                                            Edit
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </Card>

            <Pagination paginator={accounts} />

            <AccountForm
                open={showModal}
                onClose={() => setShowModal(false)}
                account={editing}
                accountTypes={accountTypes}
                parentAccounts={parentAccounts}
            />
        </>
    );
}

AccountsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;