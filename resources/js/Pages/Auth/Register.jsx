import { Link, useForm } from '@inertiajs/react';

import GuestLayout from '@/Layouts/GuestLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

export default function Register() {
    const { data, setData, errors, post, processing } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    function submit(event) {
        event.preventDefault();
        post('/register');
    }

    return (
        <form onSubmit={submit}>
            <div className="space-y-4">
                <div>
                    <Label>Name</Label>
                    <Input
                        type="text"
                        name="name"
                        value={data.name}
                        autoComplete="name"
                        autoFocus
                        required
                        onChange={(event) => setData('name', event.target.value)}
                        className="mt-1"
                    />
                    {errors.name && <p className="mt-1 text-sm font-medium text-destructive">{errors.name}</p>}
                </div>

                <div>
                    <Label>Email</Label>
                    <Input
                        type="email"
                        name="email"
                        value={data.email}
                        autoComplete="username"
                        required
                        onChange={(event) => setData('email', event.target.value)}
                        className="mt-1"
                    />
                    {errors.email && <p className="mt-1 text-sm font-medium text-destructive">{errors.email}</p>}
                </div>

                <div>
                    <Label>Password</Label>
                    <Input
                        type="password"
                        name="password"
                        value={data.password}
                        autoComplete="new-password"
                        required
                        onChange={(event) => setData('password', event.target.value)}
                        className="mt-1"
                    />
                    {errors.password && <p className="mt-1 text-sm font-medium text-destructive">{errors.password}</p>}
                </div>

                <div>
                    <Label>Confirm Password</Label>
                    <Input
                        type="password"
                        name="password_confirmation"
                        value={data.password_confirmation}
                        autoComplete="new-password"
                        required
                        onChange={(event) => setData('password_confirmation', event.target.value)}
                        className="mt-1"
                    />
                    {errors.password_confirmation && <p className="mt-1 text-sm font-medium text-destructive">{errors.password_confirmation}</p>}
                </div>
            </div>

            <div className="mt-4 flex items-center justify-end gap-3">
                <Link
                    href="/login"
                    className="text-sm text-on-surface-muted dark:text-on-surface-muted hover:text-on-surface dark:hover:text-on-surface underline rounded-md transition"
                >
                    Already registered?
                </Link>

                <Button type="submit" variant="default" disabled={processing}>
                    Register
                </Button>
            </div>
        </form>
    );
}

Register.layout = (page) => <GuestLayout>{page}</GuestLayout>;