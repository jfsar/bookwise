import { Link, useForm, usePage } from '@inertiajs/react';

import GuestLayout from '@/Layouts/GuestLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

export default function Login() {
    const { flash } = usePage().props;
    const { data, setData, errors, post, processing } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    function submit(event) {
        event.preventDefault();
        post('/login');
    }

    return (
        <>
            {flash?.status && (
                <div className="font-medium text-sm text-success dark:text-success mb-4">{flash.status}</div>
            )}

            <form onSubmit={submit}>
                <div className="space-y-4">
                    <div className="space-y-2">
                        <Label>Email</Label>
                        <Input
                            type="email"
                            name="email"
                            value={data.email}
                            autoComplete="username"
                            autoFocus
                            onChange={(event) => setData('email', event.target.value)}
                            className="mt-1"
                        />
                        {errors.email && <p className="mt-1 text-sm font-medium text-destructive">{errors.email}</p>}
                    </div>

                    <div className="space-y-2">
                        <Label>Password</Label>
                        <Input
                            type="password"
                            name="password"
                            value={data.password}
                            autoComplete="current-password"
                            required
                            onChange={(event) => setData('password', event.target.value)}
                            className="mt-1"
                        />
                        {errors.password && <p className="mt-1 text-sm font-medium text-destructive">{errors.password}</p>}
                    </div>
                </div>

                <div className="mt-4 flex items-center justify-between">
                    <label className="flex items-center gap-2 text-sm text-on-surface-muted dark:text-on-surface-muted">
                        <input
                            type="checkbox"
                            name="remember"
                            checked={data.remember}
                            onChange={(event) => setData('remember', event.target.checked)}
                        />
                        Remember me
                    </label>

                    <div className="flex items-center gap-3">
                        <Link
                            href="/forgot-password"
                            className="underline text-sm text-on-surface-muted dark:text-on-surface-muted hover:text-on-surface dark:hover:text-on-surface rounded-md transition"
                        >
                            Forgot your password?
                        </Link>

                        <Button type="submit" variant="default" disabled={processing}>
                            Log in
                        </Button>
                    </div>
                </div>
            </form>
        </>
    );
}

Login.layout = (page) => <GuestLayout children={page} />;