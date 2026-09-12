import { router, usePage } from '@inertiajs/react';
import { Monitor, Smartphone } from 'lucide-react';

import AppLayout from '@/Layouts/AppLayout';
import StatusBadge from '@/Components/StatusBadge';
import { Button } from '@/Components/ui/button';
import { Card } from '@/Components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

export default function SessionsPage() {
    const { sessions } = usePage().props;

    function signOut(session) {
        router.delete(`/settings/sessions/${session.id}`, { preserveScroll: true });
    }

    function signOutOthers() {
        router.delete('/settings/sessions', { preserveScroll: true });
    }

    return (
        <>
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-2xl font-display font-bold text-on-surface dark:text-on-surface">Active Sessions</h3>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Devices signed into your account.
                    </p>
                </div>
                {sessions.length > 1 && (
                    <Button variant="outline" size="sm" onClick={signOutOthers}>
                        Sign out all other sessions
                    </Button>
                )}
            </div>

            <Card className="mt-4 overflow-hidden">
                {sessions?.length === 0 ? (
                    <div className="px-6 py-10 text-center text-sm text-muted-foreground">
                        No active sessions found.
                    </div>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Device</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">IP Address</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Last Active</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Status</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {sessions.map((session) => (
                                <TableRow key={session.id}>
                                    <TableCell>
                                        <div className="flex items-center gap-3">
                                            {session.device === 'iPhone' || session.device === 'Android' ? (
                                                <Smartphone className="size-4 text-muted-foreground" />
                                            ) : (
                                                <Monitor className="size-4 text-muted-foreground" />
                                            )}
                                            <div>
                                                <div className="font-medium text-on-surface dark:text-on-surface">
                                                    {session.browser} on {session.device}
                                                </div>
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{session.ip_address}</TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{session.last_active_at}</TableCell>
                                    <TableCell>
                                        {session.is_current ? (
                                            <StatusBadge tone="success">Current</StatusBadge>
                                        ) : (
                                            <span className="text-sm text-muted-foreground">Active</span>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            disabled={session.is_current}
                                            onClick={() => signOut(session)}
                                            className="text-destructive hover:text-destructive disabled:opacity-50"
                                        >
                                            Sign out
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </Card>
        </>
    );
}

SessionsPage.layout = (page) => <AppLayout>{page}</AppLayout>;