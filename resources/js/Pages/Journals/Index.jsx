import { useEffect, useRef, useState } from 'react';
import { router, usePage } from '@inertiajs/react';

import AppLayout from '@/Layouts/AppLayout';
import JournalForm from '@/Pages/Journals/JournalForm';
import Pagination from '@/Components/Pagination';
import StatusBadge from '@/Components/StatusBadge';
import { Button } from '@/Components/ui/button';
import { Card } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

export default function JournalsIndex() {
    const { journals, filters } = usePage().props;
    const [search, setSearch] = useState(filters?.search ?? '');
    const [showModal, setShowModal] = useState(false);
    const firstRender = useRef(true);

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        const timeout = setTimeout(() => {
            router.get('/journals', { search: search || undefined }, { preserveState: true, replace: true });
        }, 300);

        return () => clearTimeout(timeout);
    }, [search]);

    return (
        <>
            <div className="flex items-center justify-between">
                <h3 className="text-2xl font-display font-bold text-on-surface dark:text-on-surface">Journals</h3>
                <Button variant="default" onClick={() => setShowModal(true)}>
                    New Journal
                </Button>
            </div>

            <div className="mt-4 w-64">
                <Input type="text" placeholder="Search journals..." value={search} onChange={(event) => setSearch(event.target.value)} />
            </div>

            <Card className="mt-4 overflow-hidden">
                {journals?.data?.length === 0 || !journals ? (
                    <div className="px-6 py-10 text-center text-sm text-on-surface-muted dark:text-on-surface-muted">
                        No journals found.
                    </div>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Code</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Name</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Status</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Type</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {journals.data.map((journal) => (
                                <TableRow key={journal.id}>
                                    <TableCell className="font-display font-medium text-on-surface dark:text-on-surface">{journal.code}</TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{journal.name}</TableCell>
                                    <TableCell>
                                        <StatusBadge tone={journal.is_active ? 'success' : 'default'}>
                                            {journal.is_active ? 'Active' : 'Inactive'}
                                        </StatusBadge>
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge tone="info">{journal.type}</StatusBadge>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </Card>

            <Pagination paginator={journals} />

            <JournalForm open={showModal} onClose={() => setShowModal(false)} />
        </>
    );
}

JournalsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;