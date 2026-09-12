import { useState } from 'react';
import { usePage } from '@inertiajs/react';

import AppLayout from '@/Layouts/AppLayout';
import UserForm from '@/Pages/Settings/UserForm';
import StatusBadge from '@/Components/StatusBadge';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card } from '@/Components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

export default function UsersPage() {
    const { members, roles, canManageUsers } = usePage().props;
    const [target, setTarget] = useState(null);
    const [showModal, setShowModal] = useState(false);

    return (
        <>
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-2xl font-display font-bold text-on-surface dark:text-on-surface">Users</h3>
                    <p className="mt-1 text-sm text-muted-foreground">Manage members and their roles in this organization.</p>
                </div>
                {canManageUsers && (
                    <Button variant="default" onClick={() => setShowModal(true)}>
                        New User
                    </Button>
                )}
            </div>

            <Card className="mt-4 overflow-hidden">
                {members?.length === 0 ? (
                    <div className="px-6 py-10 text-center text-sm text-on-surface-muted dark:text-on-surface-muted">
                        No users found.
                    </div>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">User</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Status</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Roles</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Last Login</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {members.map((member) => (
                                <TableRow key={member.id}>
                                    <TableCell>
                                        <div>
                                            <div className="font-medium text-on-surface dark:text-on-surface">{member.name}</div>
                                            <div className="text-sm text-muted-foreground">{member.email}</div>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge tone={member.status === 'active' ? 'success' : 'error'}>
                                            {member.status === 'active' ? 'Active' : 'Inactive'}
                                        </StatusBadge>
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex flex-wrap gap-1">
                                            {member.roles.map((role) => (
                                                <Badge key={role.id} variant="secondary">
                                                    {role.name}
                                                </Badge>
                                            ))}
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{member.last_login_at ?? 'Never'}</TableCell>
                                    <TableCell>
                                        <Button size="sm" variant="outline" onClick={() => setTarget(member)}>
                                            Edit
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </Card>

            <UserForm open={showModal || target !== null} member={target} roles={roles} onClose={() => {
                setShowModal(false);
                setTarget(null);
            }} />
        </>
    );
}

UsersPage.layout = (page) => <AppLayout>{page}</AppLayout>;