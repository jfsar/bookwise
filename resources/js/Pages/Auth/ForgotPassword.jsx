import { useForm, usePage } from '@inertiajs/react';

import GuestLayout from '@/Layouts/GuestLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

export default function ForgotPassword() {
    const { flash } = usePage().props;
    const { data, setData, errors, post, processing } = useForm({
        email: '',
    });

    function submit(event) {
        event.preventDefault();
        post('/forgot-password');
    }

    return (
        <>
            <div className="mb-4 text-sm text-on-surface dark:text-on-surface">
                Forgot your password? No problem. Just let us know your email address and we will email
                you a password reset link.
            </div>

            {flash?.status && (
                <div className="mb-4 font-medium text-sm text-success dark:text-success">{flash.status}</div>
            )}

            <form onSubmit={submit} className="space-y-2">
                <Label>Email</Label> 
                <Input
                    type="email"
                    name="email"
                    value={data.email}
                    autoComplete="username"
                    autoFocus
                    required
                    onChange={(event) => setData('email', event.target.value)}
                    className="mt-1"
                />
                {errors.email && <p className="mt-1 text-sm font-medium text-destructive">{errors.email}</p>}
                <div className="flex justify-end mt-4">
                    <Button type="submit" variant="default" disabled={processing}>
                        Email Password Reset Link
                    </Button>
                </div>
            </form>
        </>
    );
}

ForgotPassword.layout = (page) => <GuestLayout>{page}</GuestLayout>;