<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <x-favicon-links />

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />

        <!-- Blocking theme script: public pages load light unless dark was chosen explicitly -->
        <script>
            (function() {
                if (localStorage.getItem('themeMode') === 'dark') {
                    document.documentElement.classList.add('dark');
                }
            })();
        </script>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-slate-900 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-slate-100 dark:bg-slate-950">
            <div>
                <a href="/" class="flex flex-col items-center">
                    <img
                        src="{{ asset('images/brand/logo-full.png') }}"
                        alt="Studylikepro"
                        width="960"
                        height="851"
                        class="h-auto w-64 dark:hidden"
                    >
                    <img
                        src="{{ asset('images/brand/logo-full-dark.png') }}"
                        alt="Studylikepro"
                        width="960"
                        height="850"
                        class="hidden h-auto w-64 dark:block"
                    >
                </a>
            </div>

            <div class="w-full sm:max-w-md mt-6 px-6 py-6 bg-white dark:bg-slate-900 shadow-md border border-slate-200/80 dark:border-slate-800/80 overflow-hidden sm:rounded-2xl">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
