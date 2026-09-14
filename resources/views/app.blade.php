<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name') }}</title>

        <script nonce="{{ Vite::cspNonce() }}">
            if (
                localStorage.getItem('theme') === 'dark' ||
                (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)
            ) {
                document.documentElement.classList.add('dark');
            }
        </script>

        @viteReactRefresh
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
        @inertia
    </head>
    <body class="bg-surface dark:bg-surface text-on-surface dark:text-on-surface font-sans antialiased">
        @inertia
    </body>
</html>