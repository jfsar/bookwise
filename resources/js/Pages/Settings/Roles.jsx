import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';

import AppLayout from '@/Layouts/AppLayout';
import RoleForm from '@/Pages/Settings/RoleForm';
import StatusBadge from '@/Components/StatusBadge';
import { Button } from '@/Components/ui/button';
import { Card } from '@/Components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

export default function RolesPage() {
    const { roles, permissions, canManageRoles } = usePage().props;
    const [target, setTarget] = useState(null);
    const [showModal, setShowModal] = useState(false);

    function deleteRole(role) {
        router.delete(`/settings/roles/${role.id}`, { preserveScroll: true });
    }

    return (
        <>
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-2xl font-display font-bold text-on-surface dark:text-on-surface">Roles</h3>
                    <p className="mt-1 text-sm text-muted-foreground">Roles bundle permissions and can be assigned to users.</p>
                </div>
                {canManageRoles && (
                    <Button variant="default" onClick={() => setShowModal(true)}>
                        New Role
                    </Button>
                )}
            </div>

            <Card className="mt-4 overflow-hidden">
                {roles?.length === 0 ? (
                    <div className="px-6 py-10 text-center text-sm text-on-surface-muted dark:text-on-surface-muted">
                        No roles found.
                    </div>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Role</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Type</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Permissions</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {roles.map((role) => (
                                <TableRow key={role.id}>
                                    <TableCell>
                                        <div>
                                            <div className="font-medium text-on-surface dark:text-on-surface">{role.name}</div>
                                            {role.description && (
                                                <div className="text-sm text-muted-foreground">{role.description}</div>
                                            )}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge tone={role.is_global ? 'info' : 'default'}>
                                            {role.is_global ? 'System' : 'Custom'}
                                        </StatusBadge>
                                    </TableCell>
                                    <TableCell className="text-sm text-muted-foreground">
                                        {role.permissions.length === 0
                                            ? 'No permissions'
                                            : `${role.permissions.length} permission${role.permissions.length === 1 ? '' : 's'}`}
                                    </TableCell>
                                    <TableCell>
                                        {!role.is_global && canManageRoles && (
                                            <div className="flex items-center gap-2">
                                                <Button size="sm" variant="outline" onClick={() => setTarget(role)}>
                                                    Edit
                                                </Button>
                                                <Button size="sm" variant="outline" className="text-destructive hover:text-destructive" onClick={() => deleteRole(role)}>
                                                    Delete
                                                </Button>
                                            </div>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </Card>

            <RoleForm
                open={showModal || target !== null}
                role={target}
                permissions={permissions}
                onClose={() => {
                    setShowModal(false);
                    setTarget(null);
                }}
            />
        </>
    );
}

RolesPage.layout = (page) => <AppLayout>{page}</AppLayout>;