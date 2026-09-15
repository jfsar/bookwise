import { Link, usePage } from '@inertiajs/react';
import { AlertTriangle, Home, LockKeyhole, SearchX, ServerCrash, WifiOff } from 'lucide-react';

import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';

const errors = {
    403: {
        title: 'Forbidden',
        description: 'Sorry, you are not allowed to access this page.',
        icon: LockKeyhole,
    },
    404: {
        title: 'Page Not Found',
        description: 'Sorry, the page you are looking for could not be found.',
        icon: SearchX,
    },
    500: {
        title: 'Server Error',
        description: 'Sorry, something went wrong on our servers. Please try again shortly.',
        icon: ServerCrash,
    },
    503: {
        title: 'Service Unavailable',
        description: 'Sorry, we are doing some maintenance. Please check back soon.',
        icon: WifiOff,
    },
};

export default function ErrorPage({ status }) {
    const { auth } = usePage().props;
    const { title, description, icon: Icon } = errors[status] ?? {
        title: 'Something went wrong',
        description: 'Sorry, an unexpected error occurred.',
        icon: AlertTriangle,
    };

    return (
        <div role="alert" className="animate-rise flex items-center justify-center p-4 sm:p-8">
            <Card className="w-full max-w-md border-border/60 bg-surface-lowest shadow-ambient-md">
                <CardContent className="flex flex-col items-center gap-4 px-8 py-10 text-center">
                    <span className="flex size-12 shrink-0 items-center justify-center rounded-full bg-error-container text-error dark:bg-error-container dark:text-error">
                        <Icon className="size-6" />
                    </span>

                    <div className="grid gap-1.5">
                        <h1 className="font-display text-2xl font-bold text-on-surface dark:text-on-surface">
                            {status ? `${status} · ${title}` : title}
                        </h1>
                        <p className="text-sm text-on-surface-muted dark:text-on-surface-muted">{description}</p>
                    </div>

                    <Button asChild>
                        <Link href={auth?.user ? '/dashboard' : '/'}>
                            <Home />
                            {auth?.user ? 'Back to Dashboard' : 'Go to Home'}
                        </Link>
                    </Button>
                </CardContent>
            </Card>
        </div>
    );
}

ErrorPage.layout = (page) => <AppLayout>{page}</AppLayout>;