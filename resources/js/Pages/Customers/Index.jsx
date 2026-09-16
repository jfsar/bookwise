import { useEffect, useRef, useState } from 'react';
import { router, usePage } from '@inertiajs/react';

import CustomerForm from '@/Pages/Customers/CustomerForm';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import StatusBadge from '@/Components/StatusBadge';
import { Button } from '@/Components/ui/button';
import { Card } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { NativeSelect, NativeSelectOption } from '@/Components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

export default function CustomersIndex() {
    const { customers, accounts, filters } = usePage().props;

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
            router.get('/customers', {
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

    function openEdit(customer) {
        setEditing(customer);
        setShowModal(true);
    }

    return (
        <>
            <div className="flex items-center justify-between">
                <h3 className="text-2xl font-display font-bold text-on-surface dark:text-on-surface">Customers</h3>
                <Button variant="default" onClick={openCreate}>
                    New Customer
                </Button>
            </div>

            <div className="mt-4 flex flex-wrap gap-3">
                <div className="w-64">
                    <Input type="text" placeholder="Search customers..." value={search} onChange={(event) => setSearch(event.target.value)} />
                </div>
                <div className="w-48">
                    <NativeSelect value={status} onChange={(event) => setStatus(event.target.value)} className="w-full">
                        <NativeSelectOption value="">All Status</NativeSelectOption>
                        <NativeSelectOption value="active">Active</NativeSelectOption>
                        <NativeSelectOption value="inactive">Inactive</NativeSelectOption>
                        <NativeSelectOption value="archived">Archived</NativeSelectOption>
                    </NativeSelect>
                </div>
            </div>

            <Card className="mt-4 overflow-hidden">
                {customers?.data?.length === 0 || !customers ? (
                    <div className="px-6 py-10 text-center text-sm text-on-surface-muted dark:text-on-surface-muted">
                        No customers found.
                    </div>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Number</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Name</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Contact</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Terms</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Credit Limit</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Receivable Account</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Status</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {customers.data.map((customer) => (
                                <TableRow key={customer.id} className={customer.status === 'active' ? '' : 'opacity-50'}>
                                    <TableCell className="font-display font-medium text-on-surface dark:text-on-surface">
                                        {customer.customer_number}
                                    </TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{customer.name}</TableCell>
                                    <TableCell className="text-sm text-muted-foreground">
                                        {customer.email || customer.phone || '—'}
                                    </TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{customer.payment_terms_days} days</TableCell>
                                    <TableCell className="text-sm text-muted-foreground">
                                        {customer.credit_limit ? Number(customer.credit_limit).toLocaleString() : '—'}
                                    </TableCell>
                                    <TableCell className="text-sm text-muted-foreground">
                                        {customer.receivable_account?.code ?? '—'}
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge tone={customer.status === 'active' ? 'success' : customer.status === 'archived' ? 'error' : 'default'}>
                                            {customer.status.charAt(0).toUpperCase() + customer.status.slice(1)}
                                        </StatusBadge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {customer.can?.update && (
                                            <Button size="sm" variant="secondary" onClick={() => openEdit(customer)}>
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

            <Pagination paginator={customers} />

            <CustomerForm
                open={showModal}
                onClose={() => setShowModal(false)}
                customer={editing}
                accounts={accounts}
            />
        </>
    );
}

CustomersIndex.layout = (page) => <AppLayout>{page}</AppLayout>;