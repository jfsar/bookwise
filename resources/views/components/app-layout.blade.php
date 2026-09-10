<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ $title ?? config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="font-sans antialiased bg-surface dark:bg-surface text-on-surface dark:text-on-surface">
        <nav class="bg-surface-lowest/80 dark:bg-surface-lowest/80 backdrop-blur-xl">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <a href="{{ route('dashboard') }}" class="text-lg font-bold text-primary dark:text-primary font-display">
                            {{ config('app.name') }}
                        </a>
                    </div>
                    <div class="flex items-center gap-4">
                        @auth
                            <span class="text-sm text-on-surface-muted dark:text-on-surface-muted">{{ Auth::user()->name }}</span>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="text-sm text-on-surface-muted dark:text-on-surface-muted hover:text-on-surface dark:hover:text-on-surface transition">
                                    Logout
                                </button>
                            </form>
                        @endauth
                    </div>
                </div>
            </div>
        </nav>

        @if (isset($header))
            <header class="bg-surface-low dark:bg-surface-low">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    {{ $header }}
                </div>
            </header>
        @endif

        <main>
            {{ $slot }}
        </main>

        @livewireScriptConfig
    </body>
</html>
