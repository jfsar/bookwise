import { useEffect, useRef, useState } from 'react';
import { router, usePage } from '@inertiajs/react';

import AppLayout from '@/Layouts/AppLayout';
import { Badge } from '@/Components/ui/badge';
import { Card } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { NativeSelect, NativeSelectOption } from '@/Components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import Pagination from '@/Components/Pagination';

function actionLabel(action) {
    return action
        .split('.')
        .map((part) => part[0].toUpperCase() + part.slice(1))
        .join(' · ');
}

export default function AuditLogsPage() {
    const { logs, members, filters } = usePage().props;

    const [search, setSearch] = useState(filters?.search ?? '');
    const [userId, setUserId] = useState(filters?.user_id ?? '');
    const [dateFrom, setDateFrom] = useState(filters?.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters?.date_to ?? '');

    const firstRender = useRef(true);

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        const timeout = setTimeout(() => {
            router.get('/settings/audit-logs', {
                search: search || undefined,
                user_id: userId || undefined,
                date_from: dateFrom || undefined,
                date_to: dateTo || undefined,
            }, { preserveState: true, replace: true });
        }, 300);

        return () => clearTimeout(timeout);
    }, [search, userId, dateFrom, dateTo]);

    return (
        <>
            <div>
                <h3 className="text-2xl font-display font-bold text-on-surface dark:text-on-surface">Audit Logs</h3>
                <p className="mt-1 text-sm text-muted-foreground">Tamper-evident trail of actions in this organization.</p>
            </div>

            <div className="mt-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div>
                    <Input
                        type="text"
                        placeholder="Search actions..."
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                    />
                </div>
                <div>
                    <NativeSelect value={userId} onChange={(event) => setUserId(event.target.value)} className="w-full">
                        <NativeSelectOption value="">All users</NativeSelectOption>
                        {members.map((member) => (
                            <NativeSelectOption key={member.id} value={String(member.id)}>
                                {member.name}
                            </NativeSelectOption>
                        ))}
                    </NativeSelect>
                </div>
                <div>
                    <Input type="date" value={dateFrom} onChange={(event) => setDateFrom(event.target.value)} />
                </div>
                <div>
                    <Input type="date" value={dateTo} onChange={(event) => setDateTo(event.target.value)} />
                </div>
            </div>

            <Card className="mt-4 overflow-hidden">
                {!logs || logs?.data?.length === 0 ? (
                    <div className="px-6 py-10 text-center text-sm text-on-surface-muted dark:text-on-surface-muted">
                        No audit log entries found.
                    </div>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Seq</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Action</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">User</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Entity</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">IP Address</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">When</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {logs.data.map((log) => (
                                <TableRow key={log.id}>
                                    <TableCell className="text-sm text-muted-foreground">{log.sequence_no}</TableCell>
                                    <TableCell>
                                        <Badge variant="outline">{actionLabel(log.action)}</Badge>
                                    </TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{log.user?.name ?? 'System'}</TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{log.auditable}</TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{log.ip_address ?? '—'}</TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{log.created_at}</TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </Card>

            <Pagination paginator={logs} />
        </>
    );
}

AuditLogsPage.layout = (page) => <AppLayout>{page}</AppLayout>;