import { useForm, usePage } from '@inertiajs/react';

import GuestLayout from '@/Layouts/GuestLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

export default function TwoFactorChallenge() {
    const { flash } = usePage().props;
    const { data, setData, errors, post, processing } = useForm({
        code: '',
    });

    function submit(event) {
        event.preventDefault();
        post('/two-factor-challenge');
    }

    return (
        <>
            <div className="mb-4 text-sm text-on-surface dark:text-on-surface">
                Please confirm access to your account by entering one of your authenticator app codes.
            </div>

            {flash?.status && (
                <div className="mb-4 font-medium text-sm text-success dark:text-success">{flash.status}</div>
            )}

            <form onSubmit={submit}>
                <Label>Code</Label>
                <Input
                    type="text"
                    name="code"
                    value={data.code}
                    autoComplete="one-time-code"
                    inputMode="numeric"
                    pattern="[0-9]*"
                    autoFocus
                    required
                    onChange={(event) => setData('code', event.target.value)}
                    className="mt-1"
                />
                {errors.code && <p className="mt-1 text-sm font-medium text-destructive">{errors.code}</p>}

                <Button type="submit" variant="default" disabled={processing} className="mt-4">
                    Log in
                </Button>
            </form>
        </>
    );
}

TwoFactorChallenge.layout = (page) => <GuestLayout>{page}</GuestLayout>;