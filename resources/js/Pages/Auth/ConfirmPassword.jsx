import { useForm } from '@inertiajs/react';

import GuestLayout from '@/Layouts/GuestLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

export default function ConfirmPassword() {
    const { data, setData, errors, post, processing } = useForm({
        password: '',
    });

    function submit(event) {
        event.preventDefault();
        post('/password/confirm');
    }

    return (
        <>
            <div className="mb-4 text-sm text-on-surface dark:text-on-surface">
                This is a secure area of the application. Please confirm your password before continuing.
            </div>

            <form onSubmit={submit} className="space-y-4">
                <Label>Password</Label>
                <Input
                    type="password"
                    name="password"
                    value={data.password}
                    autoComplete="current-password"
                    autoFocus
                    required
                    onChange={(event) => setData('password', event.target.value)}
                    className="mt-1"
                />
                {errors.password && <p className="mt-1 text-sm font-medium text-destructive">{errors.password}</p>}

                <Button type="submit" variant="default" disabled={processing} className="mt-4">
                    Confirm Password
                </Button>
            </form>
        </>
    );
}

ConfirmPassword.layout = (page) => <GuestLayout>{page}</GuestLayout>;