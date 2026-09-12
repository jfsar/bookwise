import { useEffect } from 'react';
import { useForm } from '@inertiajs/react';

import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { NativeSelect, NativeSelectOption } from '@/Components/ui/native-select';

export default function UserForm({ open, onClose, member, roles }) {
    const { data, setData, errors, post, put, processing } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        status: 'active',
        role_id: '',
    });

    useEffect(() => {
        if (open) {
            setData({
                name: member?.name ?? '',
                email: member?.email ?? '',
                password: '',
                password_confirmation: '',
                status: member?.status ?? 'active',
                role_id: member?.roles?.[0]?.id ? String(member.roles[0].id) : '',
            });
        }
    }, [open, member]);

    function submit(event) {
        event.preventDefault();

        if (member) {
            put(`/settings/users/${member.id}`, { onSuccess: () => onClose() });
        } else {
            post('/settings/users', { onSuccess: () => onClose() });
        }
    }

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="font-display">{member ? 'Edit User' : 'New User'}</DialogTitle>
                </DialogHeader>

                <form onSubmit={submit}>
                    <div className="grid gap-4">
                        <div>
                            <Label>Name *</Label>
                            <Input value={data.name} onChange={(event) => setData('name', event.target.value)} className="mt-1" />
                            {errors.name && <p className="mt-1 text-sm font-medium text-destructive">{errors.name}</p>}
                        </div>
                        <div>
                            <Label>Email *</Label>
                            <Input type="email" value={data.email} onChange={(event) => setData('email', event.target.value)} className="mt-1" />
                            {errors.email && <p className="mt-1 text-sm font-medium text-destructive">{errors.email}</p>}
                        </div>
                        {!member && (
                            <>
                                <div>
                                    <Label>Password *</Label>
                                    <Input
                                        type="password"
                                        value={data.password}
                                        onChange={(event) => setData('password', event.target.value)}
                                        className="mt-1"
                                    />
                                    {errors.password && <p className="mt-1 text-sm font-medium text-destructive">{errors.password}</p>}
                                </div>
                                <div>
                                    <Label>Confirm Password *</Label>
                                    <Input
                                        type="password"
                                        value={data.password_confirmation}
                                        onChange={(event) => setData('password_confirmation', event.target.value)}
                                        className="mt-1"
                                    />
                                </div>
                            </>
                        )}
                        {member && (
                            <div>
                                <Label>Status *</Label>
                                <NativeSelect value={data.status} onChange={(event) => setData('status', event.target.value)} className="mt-1 w-full">
                                    <NativeSelectOption value="active">Active</NativeSelectOption>
                                    <NativeSelectOption value="inactive">Inactive</NativeSelectOption>
                                </NativeSelect>
                                {errors.status && <p className="mt-1 text-sm font-medium text-destructive">{errors.status}</p>}
                            </div>
                        )}
                        <div>
                            <Label>Role</Label>
                            <NativeSelect value={data.role_id} onChange={(event) => setData('role_id', event.target.value)} className="mt-1 w-full">
                                <NativeSelectOption value="">No role</NativeSelectOption>
                                {roles.map((role) => (
                                    <NativeSelectOption key={role.id} value={String(role.id)}>
                                        {role.name}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                            {errors.role_id && <p className="mt-1 text-sm font-medium text-destructive">{errors.role_id}</p>}
                        </div>
                    </div>

                    <div className="mt-6 flex items-center gap-3 justify-end">
                        <Button type="button" variant="secondary" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" variant="default" disabled={processing}>
                            {member ? 'Update User' : 'Create User'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}