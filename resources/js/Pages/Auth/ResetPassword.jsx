import { useForm } from '@inertiajs/react';

import GuestLayout from '@/Layouts/GuestLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

export default function ResetPassword({ token, email }) {
    const { data, setData, errors, post, processing } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    function submit(event) {
        event.preventDefault();
        post('/reset-password');
    }

    return (
        <form onSubmit={submit}>
            <div className="space-y-4">
                <div>
                    <Label>Email</Label>
                    <Input
                        type="email"
                        name="email"
                        value={data.email}
                        autoComplete="username"
                        autoFocus
                        required
                        onChange={(event) => setData('email', event.target.value)}
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
                    />
                    {errors.password_confirmation && <p className="mt-1 text-sm font-medium text-destructive">{errors.password_confirmation}</p>}
                </div>
            </div>

            <Button type="submit" variant="default" disabled={processing} className="mt-4">
                Reset Password
            </Button>
        </form>
    );
}

ResetPassword.layout = (page) => <GuestLayout>{page}</GuestLayout>;