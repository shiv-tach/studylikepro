@props(['guest' => false])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <x-favicon-links />

        <title>{{ config('app.name', 'Studylikepro') }} · {{ __('Teacher setup') }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

        {{-- Same blocking theme script as the workspace shell, so the wizard
             opens in the theme the teacher already chose. The document owns the
             background (body stays transparent) which is what lets a guest
             preset — including its pattern — survive a step change. --}}
        <script>
            (function () {
                const root = document.documentElement;
                const mode = localStorage.getItem('themeMode') || '{{ $userTheme['mode'] ?? 'light' }}';
                const isDark = mode === 'dark' || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                root.classList.toggle('dark', isDark);

                const accent = localStorage.getItem('themeAccent') || '{{ $userTheme['accent'] ?? 'indigo' }}';
                const colors = { indigo: '99 102 241', violet: '139 92 246', emerald: '16 185 129', rose: '244 63 94', amber: '245 158 11', blue: '59 130 246', cyan: '6 182 212' };
                root.style.setProperty('--primary', colors[accent] || colors.indigo);

                const preset = localStorage.getItem('themePreset') || '{{ $userTheme['preset'] ?? 'classic' }}';
                const surfaces = { classic: 'bg-slate-50', forest: 'bg-slate-50', midnight: 'bg-slate-900', sunset: 'bg-amber-50/40', glass: 'bg-slate-100', ocean: 'bg-cyan-50/30' };
                const patterns = { midnight: 'bg-pattern-grid', glass: 'bg-pattern-grid', sunset: 'bg-pattern-dots', ocean: 'bg-pattern-dots' };

                root.dataset.preset = preset;
                root.classList.add(isDark ? 'bg-slate-950' : (surfaces[preset] || 'bg-slate-50'));

                if (patterns[preset]) {
                    root.classList.add(patterns[preset]);
                }
            })();
        </script>

        @if ($guest)
            {{-- One question per page load: let the browser morph the question
                 card between steps (Chromium), everyone else gets a plain load. --}}
            @verbatim
                <style>
                    @view-transition { navigation: auto; }
                </style>
            @endverbatim
        @endif

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-dvh font-sans text-slate-900 antialiased dark:text-slate-100">
        <a href="#onboarding-content"
           class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-xl focus:bg-primary focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white">
            {{ __('Skip to content') }}
        </a>

        <header class="sticky top-0 z-30 border-b border-slate-200/80 bg-white/90 backdrop-blur-md dark:border-slate-800/80 dark:bg-slate-950/90">
            <div class="mx-auto flex h-14 w-full max-w-3xl items-center justify-between gap-3 px-4 sm:h-16 sm:px-6 lg:max-w-5xl xl:max-w-6xl">
                <a href="{{ $guest ? route('join.start') : route('teacher.dashboard') }}" class="flex min-w-0 items-center gap-2.5">
                    <x-application-logo class="h-7 w-auto" />
                    <span class="hidden text-[11px] font-semibold uppercase tracking-wider text-slate-400 sm:block">
                        {{ __('Teacher setup') }}
                    </span>
                </a>

                <nav class="flex shrink-0 items-center gap-1" aria-label="{{ __('Setup actions') }}">
                    <a href="{{ route('legal.contact') }}"
                       class="hidden rounded-xl px-2.5 py-2 text-xs font-semibold text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200 sm:inline-flex">
                        {{ __('Need help?') }}
                    </a>

                    @if ($guest)
                        {{-- The invite link often opens in a browser that has never
                             visited the app: give the visitor the same light/dark
                             control the workspace has in Theme Settings. --}}
                        <button type="button"
                                x-data="themeToggle"
                                x-on:click="toggle()"
                                :aria-pressed="dark"
                                :aria-label="dark ? '{{ __('Switch to light mode') }}' : '{{ __('Switch to dark mode') }}'"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200">
                            <svg x-show="! dark" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z" />
                            </svg>
                            <svg x-show="dark" x-cloak class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2m0 14v2M3 12h2m14 0h2M5.6 5.6l1.4 1.4m10 10l1.4 1.4m0-12.8l-1.4 1.4m-10 10L5.6 18.4M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                        </button>

                        {{-- Nothing is saved to an account yet, so "sign out" would be
                             meaningless: a visitor only needs their way back in. --}}
                        <a href="{{ route('login') }}"
                           class="rounded-xl px-2.5 py-2 text-xs font-semibold text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200">
                            {{ __('Sign in') }}
                        </a>
                    @else
                        <a href="{{ route('teacher.dashboard') }}"
                           class="rounded-xl px-2.5 py-2 text-xs font-semibold text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200">
                            {{ __('Back to dashboard') }}
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                    class="rounded-xl px-2.5 py-2 text-xs font-semibold text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200">
                                {{ __('Sign out') }}
                            </button>
                        </form>
                    @endif
                </nav>
            </div>

            @isset($progress)
                {{ $progress }}
            @endisset
        </header>

        <main id="onboarding-content" class="relative isolate mx-auto w-full max-w-3xl px-4 pb-32 pt-5 sm:px-6 sm:pt-8 lg:max-w-5xl lg:pb-12 xl:max-w-6xl">
            {{-- A quiet accent wash behind the first screen of a step, so the
                 wizard reads as one connected journey rather than plain pages. --}}
            <div class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-64 bg-gradient-to-b from-primary/[0.07] via-primary/[0.03] to-transparent" aria-hidden="true"></div>

            {{ $slot }}
        </main>

        @unless ($guest)
            <noscript>
                <div class="fixed inset-x-0 bottom-0 z-40 border-t border-amber-200 bg-amber-50 p-3 text-center text-xs text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/60 dark:text-amber-300">
                    {{ __('JavaScript is off, so autosave is unavailable. Your answers are sent when you press a save button.') }}
                </div>
            </noscript>
        @endunless
    </body>
</html>
