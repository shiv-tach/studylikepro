<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Studylikepro') }} — {{ $title ?? 'Verified live tutoring' }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

        <!-- Blocking theme script: prevents flash of light theme on page load -->
        <script>
            (function() {
                const mode = localStorage.getItem('themeMode') || 'system';
                const isDark = mode === 'dark' || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                if (isDark) document.documentElement.classList.add('dark');
            })();
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-screen flex-col bg-slate-50 font-sans text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
        <header class="sticky top-0 z-30 border-b border-slate-200/80 bg-white/80 backdrop-blur-md dark:border-slate-800/80 dark:bg-slate-900/80">
            <div class="mx-auto flex h-16 w-full max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-5">
                    <a href="/" class="flex items-center gap-2.5">
                        <x-application-logo class="h-8 w-8 text-primary" />
                        <span class="text-lg font-bold tracking-tight text-slate-800 dark:text-slate-100">Studylikepro</span>
                    </a>
                    <nav class="hidden items-center gap-1 sm:flex">
                        <a href="{{ route('catalog.subjects.index') }}"
                           class="rounded-xl px-3 py-2 text-sm font-semibold transition-colors {{ request()->routeIs('catalog.*') ? 'bg-primary/10 text-primary' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                            Subjects
                        </a>
                        <a href="{{ route('teachers.index') }}"
                           class="rounded-xl px-3 py-2 text-sm font-semibold transition-colors {{ request()->routeIs('teachers.*') ? 'bg-primary/10 text-primary' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                            Teachers
                        </a>
                        <a href="{{ route('legal.contact') }}"
                           class="rounded-xl px-3 py-2 text-sm font-semibold transition-colors {{ request()->routeIs('legal.contact') ? 'bg-primary/10 text-primary' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                            Contact
                        </a>
                    </nav>
                </div>
                <div class="flex items-center gap-2">
                    @auth
                        <a href="{{ route('dashboard') }}" class="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-md shadow-primary/20 transition hover:bg-primary/90">
                            Go to dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                            Log in
                        </a>
                        <a href="{{ route('register') }}" class="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-md shadow-primary/20 transition hover:bg-primary/90">
                            Get started
                        </a>
                    @endauth
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-10 sm:px-6 lg:px-8">
            {{ $slot }}
        </main>

        <footer class="border-t border-slate-200/80 dark:border-slate-800/80">
            <div class="mx-auto flex w-full max-w-6xl flex-col items-center justify-between gap-3 px-4 py-6 text-sm text-slate-400 dark:text-slate-500 sm:flex-row sm:px-6 lg:px-8">
                <span>© {{ date('Y') }} Studylikepro · Live 1-on-1 tutoring, on demand.</span>
                <nav class="flex flex-wrap items-center justify-center gap-x-5 gap-y-2">
                    <a href="{{ route('legal.privacy') }}" class="transition-colors hover:text-primary">{{ __('Privacy') }}</a>
                    <a href="{{ route('legal.terms') }}" class="transition-colors hover:text-primary">{{ __('Terms') }}</a>
                    <a href="{{ route('legal.refunds') }}" class="transition-colors hover:text-primary">{{ __('Cancellation & refunds') }}</a>
                    <a href="{{ route('legal.contact') }}" class="transition-colors hover:text-primary">{{ __('Contact') }}</a>
                </nav>
            </div>
        </footer>
    </body>
</html>
