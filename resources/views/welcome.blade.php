<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name') }} — Precision Accounting</title>

        <script>
            if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body
        class="bg-surface dark:bg-surface text-on-surface dark:text-on-surface font-sans antialiased min-h-screen"
        x-data="{ dark: false }"
        x-init="
            dark = localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches);
            $watch('dark', val => {
                localStorage.setItem('theme', val ? 'dark' : 'light');
                document.documentElement.classList.toggle('dark', val);
            });
        "
    >
        {{-- Navigation --}}
        <nav class="fixed top-0 inset-x-0 z-50 bg-surface-lowest/80 dark:bg-surface-lowest/80 backdrop-blur-xl">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <a href="/" class="font-display font-bold text-lg text-primary dark:text-primary">
                        {{ config('app.name') }}
                    </a>

                    <div class="flex items-center gap-4">
                        @auth
                            <a
                                href="{{ url('/dashboard') }}"
                                class="text-sm text-on-surface-muted dark:text-on-surface-muted hover:text-on-surface dark:hover:text-on-surface transition"
                            >
                                Dashboard
                            </a>
                        @else
                            @if (Route::has('login'))
                                <a
                                    href="{{ route('login') }}"
                                    class="text-sm text-on-surface-muted dark:text-on-surface-muted hover:text-on-surface dark:hover:text-on-surface transition"
                                >
                                    Sign In
                                </a>
                            @endif

                            @if (Route::has('register'))
                                <a
                                    href="{{ route('register') }}"
                                    class="inline-flex items-center px-4 py-2 bg-primary dark:bg-primary rounded-lg font-semibold text-sm text-on-primary hover:bg-primary-light dark:hover:bg-primary-light focus:outline-none focus:ring-2 focus:ring-primary/30 focus:ring-offset-2 dark:focus:ring-offset-surface transition duration-150"
                                >
                                    Get Started
                                </a>
                            @endif
                        @endauth

                        <button
                            @click="dark = !dark"
                            type="button"
                            class="p-2 rounded-lg hover:bg-surface-low dark:hover:bg-surface-low text-on-surface-muted dark:text-on-surface-muted transition"
                            aria-label="Toggle dark mode"
                        >
                            <svg x-show="!dark" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                            </svg>
                            <svg x-show="dark" x-cloak xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="5"/>
                                <line x1="12" y1="1" x2="12" y2="3"/>
                                <line x1="12" y1="21" x2="12" y2="23"/>
                                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>
                                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
                                <line x1="1" y1="12" x2="3" y2="12"/>
                                <line x1="21" y1="12" x2="23" y2="12"/>
                                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>
                                <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </nav>

        {{-- Hero Section --}}
        <section class="min-h-screen flex items-center pt-16">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">
                <div class="flex flex-col lg:flex-row items-center gap-12 lg:gap-20">

                    {{-- Left: Content --}}
                    <div class="flex-1 max-w-xl">
                        <h1 class="font-display text-4xl sm:text-5xl lg:text-6xl font-bold text-on-surface dark:text-on-surface leading-[1.1] tracking-tight opacity-100 transition-all duration-700 starting:opacity-0 starting:translate-y-4">
                            Precision Accounting,<br>Intelligently Designed
                        </h1>

                        <p class="mt-6 text-lg text-on-surface-muted dark:text-on-surface-muted leading-relaxed opacity-100 transition-all duration-700 delay-150 starting:opacity-0 starting:translate-y-4">
                            Double-entry ledger management, multi-entity support, and approval workflows — all in one clean, purposeful interface.
                        </p>

                        <div class="mt-10 flex items-center gap-4 opacity-100 transition-all duration-700 delay-300 starting:opacity-0 starting:translate-y-4">
                            @auth
                                <a
                                    href="{{ url('/dashboard') }}"
                                    class="inline-flex items-center px-6 py-3 bg-primary dark:bg-primary rounded-lg font-semibold text-sm text-on-primary hover:bg-primary-light dark:hover:bg-primary-light focus:outline-none focus:ring-2 focus:ring-primary/30 focus:ring-offset-2 dark:focus:ring-offset-surface transition duration-150"
                                >
                                    Go to Dashboard
                                </a>
                            @else
                                @if (Route::has('register'))
                                    <a
                                        href="{{ route('register') }}"
                                        class="inline-flex items-center px-6 py-3 bg-primary dark:bg-primary rounded-lg font-semibold text-sm text-on-primary hover:bg-primary-light dark:hover:bg-primary-light focus:outline-none focus:ring-2 focus:ring-primary/30 focus:ring-offset-2 dark:focus:ring-offset-surface transition duration-150"
                                    >
                                        Get Started
                                    </a>
                                @endif

                                @if (Route::has('login'))
                                    <a
                                        href="{{ route('login') }}"
                                        class="inline-flex items-center px-6 py-3 text-primary dark:text-primary font-semibold hover:text-primary-light dark:hover:text-primary-light transition duration-150"
                                    >
                                        Sign In
                                    </a>
                                @endif
                            @endauth
                        </div>
                    </div>

                    {{-- Right: Abstract Visual --}}
                    <div class="flex-1 w-full max-w-lg lg:max-w-none">
                        <div class="relative">
                            {{-- Subtle ambient glow --}}
                            <div class="absolute -inset-10 bg-primary-container/10 dark:bg-primary-container/5 rounded-full blur-3xl"></div>

                            {{-- Main ledger card --}}
                            <div class="relative bg-surface-low dark:bg-surface-low rounded-xl shadow-ambient-lg p-6 lg:-rotate-2 transition-all duration-700 delay-200 starting:opacity-0 starting:translate-x-8">
                                {{-- Header --}}
                                <div class="flex items-center justify-between mb-6">
                                    <div>
                                        <p class="text-xs text-on-surface-muted dark:text-on-surface-muted uppercase tracking-wider">Journal Entry</p>
                                        <p class="font-display font-semibold text-on-surface dark:text-on-surface mt-1">JE-2024-0042</p>
                                    </div>
                                    <p class="text-xs text-on-surface-muted dark:text-on-surface-muted">Jan 15, 2024</p>
                                </div>

                                {{-- Transaction lines --}}
                                <div class="space-y-3">
                                    <div class="bg-surface-lowest dark:bg-surface-lowest rounded-lg p-4 flex items-center justify-between">
                                        <div>
                                            <p class="font-medium text-on-surface dark:text-on-surface text-sm">Cash</p>
                                            <p class="text-xs text-on-surface-muted dark:text-on-surface-muted">1010 — Current Assets</p>
                                        </div>
                                        <p class="font-display font-semibold text-primary dark:text-primary">12,500.00</p>
                                    </div>

                                    <div class="bg-surface-lowest dark:bg-surface-lowest rounded-lg p-4 flex items-center justify-between">
                                        <div>
                                            <p class="font-medium text-on-surface dark:text-on-surface text-sm">Revenue</p>
                                            <p class="text-xs text-on-surface-muted dark:text-on-surface-muted">4010 — Operating Income</p>
                                        </div>
                                        <p class="font-display font-semibold text-on-surface-muted dark:text-on-surface-muted">12,500.00</p>
                                    </div>
                                </div>
                            </div>

                            {{-- Floating status chip --}}
                            <div class="absolute -top-3 -right-3 bg-primary-container dark:bg-primary-container rounded-full px-4 py-2 shadow-ambient-md opacity-100 transition-all duration-700 delay-500 starting:opacity-0 starting:scale-90">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-primary dark:text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                    <span class="text-sm font-medium text-primary dark:text-primary">Posted</span>
                                </div>
                            </div>

                            {{-- Floating balance indicator --}}
                            <div class="absolute -bottom-4 -left-4 bg-surface-lowest dark:bg-surface-lowest rounded-lg shadow-ambient-md px-5 py-3 opacity-100 transition-all duration-700 delay-700 starting:opacity-0 starting:translate-y-4">
                                <p class="text-xs text-on-surface-muted dark:text-on-surface-muted">Trial Balance</p>
                                <p class="font-display font-bold text-lg text-on-surface dark:text-on-surface mt-0.5">
                                    $0.00
                                    <span class="text-sm font-normal text-success dark:text-success">balanced</span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Features Section --}}
        <section class="py-24 bg-surface-low dark:bg-surface-low">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="max-w-2xl mb-16">
                    <h2 class="font-display text-3xl sm:text-4xl font-bold text-on-surface dark:text-on-surface">
                        Built for accuracy
                    </h2>
                    <p class="mt-4 text-on-surface-muted dark:text-on-surface-muted text-lg">
                        Every feature designed around the principles of double-entry accounting.
                    </p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
                    {{-- Feature 1: Double-Entry Ledger (60%) --}}
                    <div class="lg:col-span-3 bg-surface-lowest dark:bg-surface-lowest rounded-xl shadow-ambient-sm p-8">
                        <div class="w-12 h-12 rounded-lg bg-primary-container dark:bg-primary-container flex items-center justify-center mb-5">
                            <svg class="w-6 h-6 text-primary dark:text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="7" height="18" rx="1"/>
                                <rect x="14" y="3" width="7" height="18" rx="1"/>
                            </svg>
                        </div>
                        <h3 class="font-display text-lg font-medium text-on-surface dark:text-on-surface">
                            Double-Entry Ledger
                        </h3>
                        <p class="mt-2 text-on-surface-muted dark:text-on-surface-muted leading-relaxed">
                            The core accounting engine. Every transaction has an equal and opposite entry. Built-in trial balance, chart of accounts, and journal management ensure your books always reconcile.
                        </p>
                    </div>

                    {{-- Feature 2: Multi-Entity Support (40%) --}}
                    <div class="lg:col-span-2 bg-surface-lowest dark:bg-surface-lowest rounded-xl shadow-ambient-sm p-8">
                        <div class="w-12 h-12 rounded-lg bg-secondary-container dark:bg-secondary-container flex items-center justify-center mb-5">
                            <svg class="w-6 h-6 text-secondary dark:text-secondary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="2" width="20" height="8" rx="2"/>
                                <rect x="2" y="14" width="20" height="8" rx="2"/>
                                <circle cx="6" cy="6" r="1" fill="currentColor"/>
                                <circle cx="6" cy="18" r="1" fill="currentColor"/>
                            </svg>
                        </div>
                        <h3 class="font-display text-lg font-medium text-on-surface dark:text-on-surface">
                            Multi-Entity Support
                        </h3>
                        <p class="mt-2 text-on-surface-muted dark:text-on-surface-muted leading-relaxed">
                            Manage multiple organizations with complete data isolation. Switch between entities seamlessly.
                        </p>
                    </div>
                </div>

                {{-- Feature 3: Approval Workflows (Full width) --}}
                <div class="mt-6 bg-surface-lowest dark:bg-surface-lowest rounded-xl shadow-ambient-sm p-8">
                    <div class="flex flex-col sm:flex-row sm:items-start gap-5">
                        <div class="w-12 h-12 rounded-lg bg-primary-container dark:bg-primary-container flex items-center justify-center shrink-0">
                            <svg class="w-6 h-6 text-primary dark:text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 11l3 3L22 4"/>
                                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-display text-lg font-medium text-on-surface dark:text-on-surface">
                                Approval Workflows
                            </h3>
                            <p class="mt-2 text-on-surface-muted dark:text-on-surface-muted leading-relaxed max-w-3xl">
                                Maker-checker segregation of duties. Submit, review, approve, and post journal entries with full audit trails. Enforce financial controls without slowing down your team.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Footer --}}
        <footer class="py-8 bg-surface dark:bg-surface">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-on-surface-muted dark:text-on-surface-muted font-display">{{ config('app.name') }}</span>
                    <span class="text-sm text-on-surface-muted dark:text-on-surface-muted">v{{ app()->version() }}</span>
                </div>
            </div>
        </footer>
    </body>
</html>
