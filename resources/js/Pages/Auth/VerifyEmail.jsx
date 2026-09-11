import { Link, useForm, usePage } from '@inertiajs/react';

import GuestLayout from '@/Layouts/GuestLayout';
import { Button } from '@/Components/ui/button';

export default function VerifyEmail() {
    const { flash, auth } = usePage().props;
    const { post, processing } = useForm({});

    return (
        <>
            <div className="mb-4 text-sm text-on-surface dark:text-on-surface">
                Thanks for signing up! Before getting started, could you verify your email address by
                clicking on the link we just emailed to you? If you didn&apos;t receive the email, we
                will gladly send you another.
            </div>

            {flash?.status && (
                <div className="mb-4 font-medium text-sm text-success dark:text-success">{flash.status}</div>
            )}

            <div className="mt-4 flex items-center justify-between">
                <Button variant="default" disabled={processing} onClick={() => post('/email/verification-notification')}>
                    Resend Verification Email
                </Button>

                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    className="text-sm text-on-surface-muted dark:text-on-surface-muted underline hover:text-on-surface dark:hover:text-on-surface rounded-md transition"
                >
                    Log Out
                </Link>
            </div>
        </>
    );
}

VerifyEmail.layout = (page) => <GuestLayout>{page}</GuestLayout>;