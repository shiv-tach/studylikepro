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
             opens in the theme the teacher already chose. --}}
        <script>
            (function () {
                const mode = localStorage.getItem('themeMode') || '{{ $userTheme['mode'] ?? 'light' }}';
                const isDark = mode === 'dark' || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', isDark);

                const accent = localStorage.getItem('themeAccent') || '{{ $userTheme['accent'] ?? 'indigo' }}';
                const colors = { indigo: '99 102 241', violet: '139 92 246', emerald: '16 185 129', rose: '244 63 94', amber: '245 158 11', blue: '59 130 246', cyan: '6 182 212' };
                document.documentElement.style.setProperty('--primary', colors[accent] || colors.indigo);

                const preset = localStorage.getItem('themePreset') || '{{ $userTheme['preset'] ?? 'classic' }}';
                let bgClass = 'bg-slate-50';
                if (isDark) {
                    bgClass = 'bg-slate-950';
                } else if (preset === 'midnight') {
                    bgClass = 'bg-slate-900';
                } else if (preset === 'sunset') {
                    bgClass = 'bg-amber-50/40';
                } else if (preset === 'glass') {
                    bgClass = 'bg-slate-100';
                } else if (preset === 'ocean') {
                    bgClass = 'bg-cyan-50/30';
                }
                document.documentElement.classList.add(bgClass);
            })();
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    @php
        $preset = $userTheme['preset'] ?? 'classic';
        $bgClass = 'bg-slate-50 dark:bg-slate-950';
        if ($preset === 'midnight') {
            $bgClass = 'bg-slate-900 dark:bg-slate-950 bg-pattern-grid';
        } elseif ($preset === 'sunset') {
            $bgClass = 'bg-amber-50/40 dark:bg-slate-950 bg-pattern-dots';
        } elseif ($preset === 'glass') {
            $bgClass = 'bg-slate-100 dark:bg-slate-950 bg-pattern-grid';
        } elseif ($preset === 'ocean') {
            $bgClass = 'bg-cyan-50/30 dark:bg-slate-950 bg-pattern-dots';
        }
    @endphp
    <body class="min-h-dvh font-sans text-slate-900 antialiased dark:text-slate-100 {{ $bgClass }}">
        <a href="#onboarding-content"
           class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-xl focus:bg-primary focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white">
            {{ __('Skip to content') }}
        </a>

        <header class="sticky top-0 z-30 border-b border-slate-200/80 bg-white/90 backdrop-blur-md dark:border-slate-800/80 dark:bg-slate-950/90">
            <div class="mx-auto flex h-14 w-full max-w-3xl items-center justify-between gap-3 px-4 sm:h-16 sm:px-6 lg:max-w-5xl xl:max-w-6xl">
                <a href="{{ route('teacher.onboarding.show') }}" class="flex min-w-0 items-center gap-2">
                    <x-application-logo class="h-7 w-auto" />
                    <span class="hidden min-w-0 sm:block">
                        <span class="block truncate text-sm font-bold leading-5">{{ config('app.name', 'Studylikepro') }}</span>
                        <span class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ __('Teacher setup') }}</span>
                    </span>
                </a>

                <nav class="flex shrink-0 items-center gap-1" aria-label="{{ __('Setup actions') }}">
                    <a href="{{ route('legal.contact') }}"
                       class="hidden rounded-xl px-2.5 py-2 text-xs font-semibold text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200 sm:inline-flex">
                        {{ __('Need help?') }}
                    </a>
                    <a href="{{ route('teacher.onboarding.show') }}"
                       class="rounded-xl px-2.5 py-2 text-xs font-semibold text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200">
                        {{ __('Save & exit') }}
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                class="rounded-xl px-2.5 py-2 text-xs font-semibold text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200">
                            {{ __('Sign out') }}
                        </button>
                    </form>
                </nav>
            </div>

            @isset($progress)
                {{ $progress }}
            @endisset
        </header>

        <main id="onboarding-content" class="mx-auto w-full max-w-3xl px-4 pb-28 pt-5 sm:px-6 sm:pt-8 lg:max-w-5xl lg:pb-12 xl:max-w-6xl">
            {{ $slot }}
        </main>

        <noscript>
            <div class="fixed inset-x-0 bottom-0 z-40 border-t border-amber-200 bg-amber-50 p-3 text-center text-xs text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/60 dark:text-amber-300">
                {{ __('JavaScript is off, so autosave is unavailable. Your answers are sent when you press a save button.') }}
            </div>
        </noscript>
    </body>
</html>
