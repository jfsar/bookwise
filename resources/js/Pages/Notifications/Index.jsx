import { router, usePage } from '@inertiajs/react';
import { Bell, CheckCheck } from 'lucide-react';

import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import StatusBadge from '@/Components/StatusBadge';
import { Button } from '@/Components/ui/button';
import { Card } from '@/Components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

function relativeTime(value) {
    if (!value) {
        return '';
    }

    const minutes = Math.round((Date.now() - new Date(value).getTime()) / 60000);

    if (minutes < 1) {
        return 'Just now';
    }

    if (minutes < 60) {
        return `${minutes}m ago`;
    }

    if (minutes < 1440) {
        return `${Math.round(minutes / 60)}h ago`;
    }

    return `${Math.round(minutes / 1440)}d ago`;
}

export default function NotificationsIndexPage() {
    const { notifications, filters } = usePage().props;

    function setFilter(filter) {
        router.get(
            '/notifications',
            { filter: filter ?? undefined },
            { preserveState: true, preserveScroll: true, replace: true }
        );
    }

    function toggleRead(notification) {
        router.patch(
            `/notifications/${notification.id}`,
            { read: !notification.is_read },
            { preserveScroll: true }
        );
    }

    function markAllRead() {
        router.post('/notifications/read-all', {}, { preserveScroll: true });
    }

    return (
        <>
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-2xl font-display font-bold text-on-surface dark:text-on-surface">Notifications</h3>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Approval activity, due dates and period closings for this organization.
                    </p>
                </div>

                <div className="flex items-center gap-3">
                    <Button
                        variant={filters.filter === 'unread' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => setFilter(filters.filter === 'unread' ? null : 'unread')}
                    >
                        Unread only
                    </Button>

                    <Button variant="outline" size="sm" onClick={markAllRead}>
                        <CheckCheck className="size-4" />
                        Mark all read
                    </Button>
                </div>
            </div>

            <Card className="mt-4 overflow-hidden">
                {!notifications || notifications.data.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 px-6 py-12 text-center">
                        <Bell className="size-6 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                            {filters.filter === 'unread' ? 'No unread notifications.' : 'No notifications yet.'}
                        </p>
                    </div>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">
                                    Notification
                                </TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">
                                    Type
                                </TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">
                                    Received
                                </TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">
                                    Status
                                </TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">
                                    Actions
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {notifications.data.map((notification) => (
                                <TableRow key={notification.id} className={notification.is_read ? undefined : 'bg-surface-low'}>
                                    <TableCell className={notification.is_read ? 'text-muted-foreground' : 'font-medium text-on-surface dark:text-on-surface'}>
                                        {notification.message}
                                    </TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{notification.type}</TableCell>
                                    <TableCell className="text-sm text-muted-foreground">
                                        {relativeTime(notification.created_at)}
                                    </TableCell>
                                    <TableCell>
                                        {notification.is_read ? (
                                            <StatusBadge>Read</StatusBadge>
                                        ) : (
                                            <StatusBadge tone="primary">Unread</StatusBadge>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <Button variant="ghost" size="sm" onClick={() => toggleRead(notification)}>
                                            {notification.is_read ? 'Mark unread' : 'Mark read'}
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </Card>

            <Pagination paginator={notifications} />
        </>
    );
}

NotificationsIndexPage.layout = (page) => <AppLayout>{page}</AppLayout>;