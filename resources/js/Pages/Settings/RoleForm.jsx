import { useEffect } from 'react';
import { useForm } from '@inertiajs/react';

import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';

export default function RoleForm({ open, onClose, role, permissions }) {
    const { data, setData, errors, post, put, processing } = useForm({
        name: '',
        description: '',
        permission_ids: [],
    });

    useEffect(() => {
        if (open) {
            setData({
                name: role?.name ?? '',
                description: role?.description ?? '',
                permission_ids: role?.permission_ids ?? [],
            });
        }
    }, [open, role]);

    function togglePermission(id) {
        setData('permission_ids', (current) =>
            current.includes(id) ? current.filter((existing) => existing !== id) : [...current, id]
        );
    }

    function submit(event) {
        event.preventDefault();

        if (role) {
            put(`/settings/roles/${role.id}`, { onSuccess: () => onClose() });
        } else {
            post('/settings/roles', { onSuccess: () => onClose() });
        }
    }

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle className="font-display">{role ? 'Edit Role' : 'New Role'}</DialogTitle>
                    <DialogDescription>Set the role name and the permissions it grants.</DialogDescription>
                </DialogHeader>

                <form onSubmit={submit}>
                    <div className="grid gap-4">
                        <div>
                            <Label>Name *</Label>
                            <Input value={data.name} onChange={(event) => setData('name', event.target.value)} className="mt-1" />
                            {errors.name && <p className="mt-1 text-sm font-medium text-destructive">{errors.name}</p>}
                        </div>
                        <div>
                            <Label>Description</Label>
                            <Textarea rows={2} value={data.description} onChange={(event) => setData('description', event.target.value)} className="mt-1" />
                        </div>
                        <div>
                            <Label>Permissions</Label>
                            <div className="mt-1 max-h-64 overflow-y-auto rounded-lg border p-3">
                                {permissions.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">No permissions available.</p>
                                ) : (
                                    <div className="grid gap-1.5">
                                        {permissions.map((permission) => (
                                            <label
                                                key={permission.id}
                                                className="flex items-start gap-2 rounded p-1 text-sm text-muted-foreground hover:bg-surface-low"
                                            >
                                                <input
                                                    type="checkbox"
                                                    checked={data.permission_ids.includes(permission.id)}
                                                    onChange={() => togglePermission(permission.id)}
                                                    className="mt-0.5 rounded border-outline-variant/30 text-primary focus:ring-primary/20"
                                                />
                                                <span>
                                                    <span className="block font-medium text-on-surface dark:text-on-surface">{permission.name}</span>
                                                    {permission.description && (
                                                        <span className="block text-xs text-muted-foreground">{permission.description}</span>
                                                    )}
                                                </span>
                                            </label>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="mt-6 flex items-center gap-3 justify-end">
                        <Button type="button" variant="secondary" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" variant="default" disabled={processing}>
                            {role ? 'Update Role' : 'Create Role'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}