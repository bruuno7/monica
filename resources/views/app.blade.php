<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ htmldir() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="color-scheme" content="light dark">

        <title inertia>{{ config('app.name', 'Monica') }}</title>

        <link rel="shortcut icon" href="/img/favicon.svg">

        <!-- Scripts -->
        <script type="text/javascript">
            (function () {
                // The preference stored on the account wins (it follows the user across devices);
                // the browser copy is the fallback for guests and for the very first paint.
                var preference = @json(auth()->user()?->only(['theme', 'accent_color', 'oled_dark']));
                var theme = (preference && preference.theme) || localStorage.theme || 'system';
                var oled = preference ? !!preference.oled_dark : localStorage.oled === 'true';
                var accent = (preference && preference.accent_color) || localStorage.accent || 'blue';

                var dark = theme === 'dark'
                    || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);

                var root = document.documentElement;
                root.classList.toggle('dark', dark);
                root.classList.toggle('oled', dark && oled);
                root.dataset.accent = accent;

                localStorage.theme = theme;
                localStorage.oled = oled;
                localStorage.accent = accent;
            })();
        </script>

        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased bg-surface text-ink">
        @inertia
    </body>
</html>
