import { Link, usePage } from '@inertiajs/react';
import { BookOpenCheck } from 'lucide-react';
import ThemeToggle from '@/Components/ThemeToggle';
import ErrorBoundary from '@/Components/ErrorBoundary';

export default function GuestLayout({ children }) {
    const { appName } = usePage().props;

    return (
        <div className="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-surface dark:bg-surface page-grain text-on-surface dark:text-on-surface font-sans antialiased">
            <div className="w-full flex justify-end px-6 pt-6">
                <ThemeToggle />
            </div>

            <div className="flex-1 flex flex-col sm:justify-center items-center w-full px-4">
                <div className="flex items-center gap-2.5">
                    <span className="flex size-9 items-center justify-center rounded-lg bg-primary text-primary-foreground shadow-ambient-sm">
                        <BookOpenCheck className="size-5" />
                    </span>
                    <Link href="/" className="text-2xl font-bold text-primary dark:text-primary font-display">
                        {appName}
                    </Link>
                </div>

                <div className="w-full sm:max-w-md mt-6 px-6 py-4 bg-surface-lowest dark:bg-surface-lowest shadow-ambient-md overflow-hidden sm:rounded-xl">
                    <ErrorBoundary name="page-content">{children}</ErrorBoundary>
                </div>
            </div>
        </div>
    );
}

export const layout = (page) => <GuestLayout>{page}</GuestLayout>;