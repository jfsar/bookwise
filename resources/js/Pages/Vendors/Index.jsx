import { useEffect, useRef, useState } from 'react';
import { router, usePage } from '@inertiajs/react';

import VendorForm from '@/Pages/Vendors/VendorForm';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import StatusBadge from '@/Components/StatusBadge';
import { Button } from '@/Components/ui/button';
import { Card } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { NativeSelect, NativeSelectOption } from '@/Components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

export default function VendorsIndex() {
    const { vendors, accounts, filters } = usePage().props;

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
            router.get('/vendors', {
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

    function openEdit(vendor) {
        setEditing(vendor);
        setShowModal(true);
    }

    return (
        <>
            <div className="flex items-center justify-between">
                <h3 className="text-2xl font-display font-bold text-on-surface dark:text-on-surface">Vendors</h3>
                <Button variant="default" onClick={openCreate}>
                    New Vendor
                </Button>
            </div>

            <div className="mt-4 flex flex-wrap gap-3">
                <div className="w-64">
                    <Input type="text" placeholder="Search vendors..." value={search} onChange={(event) => setSearch(event.target.value)} />
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
                {vendors?.data?.length === 0 || !vendors ? (
                    <div className="px-6 py-10 text-center text-sm text-on-surface-muted dark:text-on-surface-muted">
                        No vendors found.
                    </div>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Number</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Name</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Contact</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Terms</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Payable Account</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Status</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {vendors.data.map((vendor) => (
                                <TableRow key={vendor.id} className={vendor.status === 'active' ? '' : 'opacity-50'}>
                                    <TableCell className="font-display font-medium text-on-surface dark:text-on-surface">
                                        {vendor.vendor_number}
                                    </TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{vendor.name}</TableCell>
                                    <TableCell className="text-sm text-muted-foreground">
                                        {vendor.email || vendor.phone || '—'}
                                    </TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{vendor.payment_terms_days} days</TableCell>
                                    <TableCell className="text-sm text-muted-foreground">
                                        {vendor.payable_account?.code ?? '—'}
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge tone={vendor.status === 'active' ? 'success' : vendor.status === 'archived' ? 'error' : 'default'}>
                                            {vendor.status.charAt(0).toUpperCase() + vendor.status.slice(1)}
                                        </StatusBadge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {vendor.can?.update && (
                                            <Button size="sm" variant="secondary" onClick={() => openEdit(vendor)}>
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

            <Pagination paginator={vendors} />

            <VendorForm
                open={showModal}
                onClose={() => setShowModal(false)}
                vendor={editing}
                accounts={accounts}
            />
        </>
    );
}

VendorsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;