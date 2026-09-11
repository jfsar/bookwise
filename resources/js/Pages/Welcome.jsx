import { usePage } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { CheckIcon } from 'lucide-react';

import StatusBadge from '@/Components/StatusBadge';
import ThemeToggle from '@/Components/ThemeToggle';
import { Button } from '@/Components/ui/button';

export default function Welcome() {
    const { appName, auth } = usePage().props;
    const user = auth?.user;

    return (
        <div className="min-h-screen bg-surface dark:bg-surface page-grain text-on-surface dark:text-on-surface antialiased">
            <nav className="fixed top-0 inset-x-0 z-40 backdrop-blur-xl bg-surface-lowest/80 dark:bg-surface-lowest/80">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center justify-between h-16">
                        <Link href="/" className="text-lg font-bold text-primary dark:text-primary font-display">
                            {appName}
                        </Link>

                        <div className="flex items-center gap-4">
                            {user ? (
                                <Link
                                    href="/dashboard"
                                    className="text-sm font-medium text-on-surface-muted dark:text-on-surface-muted hover:text-on-surface dark:hover:text-on-surface transition"
                                >
                                    Dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href="/login"
                                        className="text-sm font-medium text-on-surface-muted dark:text-on-surface-muted hover:text-on-surface dark:hover:text-on-surface transition"
                                    >
                                        Sign In
                                    </Link>
                                    <Button asChild variant="default">
                                        <Link href="/register">Get Started</Link>
                                    </Button>
                                </>
                            )}
                            <ThemeToggle />
                        </div>
                    </div>
                </div>
            </nav>

            <main className="pt-16">
                <section className="relative overflow-hidden">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-16 pb-24 grid lg:grid-cols-2 gap-12 items-center">
                        <div>
                            <p
                                className="animate-rise text-xs font-semibold uppercase tracking-[0.22em] text-primary dark:text-primary"
                            >
                                Ledger suite
                            </p>
                            <h1
                                className="animate-rise mt-3 text-4xl sm:text-5xl lg:text-6xl font-display font-bold tracking-tight text-on-surface dark:text-on-surface"
                                style={{ animationDelay: '80ms' }}
                            >
                                Precision Accounting,
                                <br />
                                Intelligently Designed
                            </h1>
                            <p
                                className="animate-rise mt-6 text-lg text-on-surface-muted dark:text-on-surface-muted max-w-xl"
                                style={{ animationDelay: '160ms' }}
                            >
                                Double-entry ledger management, multi-entity support, and approval workflows — all
                                in one clean, purposeful interface.
                            </p>
                            <div className="animate-rise mt-8 flex flex-wrap gap-4" style={{ animationDelay: '240ms' }}>
                                {user ? (
                                    <Button asChild variant="default" size="lg">
                                        <Link href="/dashboard">Go to Dashboard</Link>
                                    </Button>
                                ) : (
                                    <>
                                        <Button asChild variant="default" size="lg">
                                            <Link href="/register">Get Started</Link>
                                        </Button>
                                        <Button asChild variant="ghost" size="lg">
                                           <Link
                                             href="/login"
                                             className="text-sm font-medium text-on-surface-muted dark:text-on-surface-muted hover:text-on-surface dark:hover:text-on-surface transition"
                                            >
                                               Sign In
                                           </Link>
                                        </Button>
                                    </>
                                )}
                            </div>
                        </div>

                        <div className="animate-rise relative flex items-center justify-center" style={{ animationDelay: '320ms' }}>
                            <div className="-rotate-2 w-full max-w-sm rounded-xl bg-surface-lowest dark:bg-surface-lowest shadow-ambient-lg p-5 text-left">
                                <div className="flex items-center justify-between">
                                    <span className="text-xs uppercase tracking-wider text-on-surface-muted dark:text-on-surface-muted">
                                        Journal Entry
                                    </span>
                                    <span className="font-display text-sm font-medium text-on-surface dark:text-on-surface">JE-2024-0042</span>
                                </div>
                                <div className="mt-2 flex items-center justify-between">
                                    <span className="text-xs text-on-surface-muted dark:text-on-surface-muted">Jan 15, 2024</span>
                                    <span className="ml-auto">
                                    <StatusBadge tone="primary">
                                        <CheckIcon className="size-3" />
                                        Posted
                                    </StatusBadge>
                                </span>
                                </div>
                                <div className="mt-5 space-y-4">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <div className="text-sm font-medium text-on-surface dark:text-on-surface">Cash</div>
                                            <div className="text-xs text-on-surface-muted dark:text-on-surface-muted">1010 — Current Assets</div>
                                        </div>
                                        <div className="font-display text-sm font-semibold tabular-nums text-primary dark:text-primary">12,500.00</div>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <div className="text-sm font-medium text-on-surface-muted dark:text-on-surface-muted">Revenue</div>
                                            <div className="text-xs text-on-surface-muted dark:text-on-surface-muted">4010 — Operating Income</div>
                                        </div>
                                        <div className="font-display text-sm font-medium tabular-nums text-on-surface-muted dark:text-on-surface-muted">12,500.00</div>
                                    </div>
                                </div>
                            </div>

                            <div className="absolute -bottom-6 -left-2 rounded-xl bg-surface-lowest dark:bg-surface-lowest shadow-ambient-md px-4 py-3">
                                <div className="text-xs text-on-surface-muted dark:text-on-surface-muted">Trial Balance</div>
                                <div className="text-lg font-display font-semibold tabular-nums text-on-surface dark:text-on-surface">
                                    $0.00 <span className="text-sm font-medium text-success dark:text-success">balanced</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section className="bg-surface-low dark:bg-surface-low py-20">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <h2 className="text-center text-3xl font-display font-bold text-on-surface dark:text-on-surface">
                            Built for accuracy
                        </h2>
                        <p className="mt-3 text-center text-on-surface-muted dark:text-on-surface-muted">
                            Every feature designed around the principles of double-entry accounting.
                        </p>

                        <div className="mt-12 grid md:grid-cols-2 gap-6">
                            <div className="rounded-xl bg-surface-lowest dark:bg-surface-lowest p-6 shadow-ambient-sm">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-container dark:bg-primary-container text-primary dark:text-primary">
                                    <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                        <rect x="3" y="4" width="6" height="16" rx="1" />
                                        <rect x="15" y="4" width="6" height="16" rx="1" />
                                    </svg>
                                </div>
                                <h3 className="mt-4 text-lg font-semibold text-on-surface dark:text-on-surface">Double-Entry Ledger</h3>
                                <p className="mt-2 text-sm text-on-surface-muted dark:text-on-surface-muted">
                                    The core accounting engine. Every transaction has an equal and opposite entry. Built-in
                                    trial balance, chart of accounts, and journal management ensure your books always reconcile.
                                </p>
                            </div>

                            <div className="rounded-xl bg-surface-lowest dark:bg-surface-lowest p-6 shadow-ambient-sm">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-container dark:bg-primary-container text-primary dark:text-primary">
                                    <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                        <rect x="8" y="3" width="12" height="18" rx="2" />
                                        <rect x="3" y="8" width="12" height="13" rx="2" />
                                        <circle cx="12" cy="18" r="1" />
                                    </svg>
                                </div>
                                <h3 className="mt-4 text-lg font-semibold text-on-surface dark:text-on-surface">Multi-Entity Support</h3>
                                <p className="mt-2 text-sm text-on-surface-muted dark:text-on-surface-muted">
                                    Manage multiple organizations with complete data isolation. Switch between entities seamlessly.
                                </p>
                            </div>

                            <div className="md:col-span-2 rounded-xl bg-surface-lowest dark:bg-surface-lowest p-6 shadow-ambient-sm">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-container dark:bg-primary-container text-primary dark:text-primary">
                                    <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                        <polyline points="20 6 9 17 4 12" />
                                    </svg>
                                </div>
                                <h3 className="mt-4 text-lg font-semibold text-on-surface dark:text-on-surface">Approval Workflows</h3>
                                <p className="mt-2 text-sm text-on-surface-muted dark:text-on-surface-muted">
                                    Maker-checker segregation of duties. Submit, review, approve, and post journal entries with
                                    full audit trails. Enforce financial controls without slowing down your team.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>
            </main>

            <footer className="bg-surface dark:bg-surface">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 flex items-center justify-between">
                    <span className="text-sm font-semibold text-on-surface dark:text-on-surface">{appName}</span>
                    <span className="text-sm text-on-surface-muted dark:text-on-surface-muted">BookWise</span>
                </div>
            </footer>
        </div>
    );
}