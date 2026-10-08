<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Studylikepro') }} — Learn with verified tutors</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

        <!-- Blocking theme script: public pages load light unless dark was chosen explicitly -->
        <script>
            (function() {
                if (localStorage.getItem('themeMode') === 'dark') {
                    document.documentElement.classList.add('dark');
                }
            })();
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
        <!-- Header -->
        <header class="sticky top-0 z-30 border-b border-slate-200/80 bg-white/80 backdrop-blur-md dark:border-slate-800/80 dark:bg-slate-900/80">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <a href="/" class="flex items-center gap-2.5">
                    <x-application-logo class="h-8 w-8 text-primary" />
                    <span class="text-lg font-bold tracking-tight text-slate-800 dark:text-slate-100">Studylikepro</span>
                </a>
                <div class="flex items-center gap-2 sm:gap-3">
                    <a href="{{ route('catalog.subjects.index') }}" class="hidden rounded-xl px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800 sm:block">
                        Subjects
                    </a>
                    <a href="{{ route('teachers.index') }}" class="hidden rounded-xl px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800 sm:block">
                        Teachers
                    </a>
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

        <!-- Hero -->
        <section class="relative overflow-hidden">
            <div class="pointer-events-none absolute -top-24 right-0 h-72 w-72 rounded-full bg-primary/10 blur-3xl"></div>
            <div class="pointer-events-none absolute bottom-0 left-0 h-72 w-72 rounded-full bg-purple-500/10 blur-3xl"></div>

            <div class="relative mx-auto grid max-w-6xl gap-12 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:items-center lg:px-8 lg:py-24">
                <div>
                    <span class="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">
                        <span class="h-1.5 w-1.5 rounded-full bg-primary"></span>
                        AI-powered tutor matching
                    </span>
                    <h1 class="mt-5 text-4xl font-extrabold tracking-tight sm:text-5xl">
                        Stuck on a question? Get a verified tutor, fast.
                    </h1>
                    <p class="mt-4 text-lg leading-relaxed text-slate-600 dark:text-slate-400">
                        Upload a photo of your question, let AI pinpoint the lesson, book a live 1-on-1 lesson with a verified teacher, and pay securely — all in one place.
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="{{ route('register', ['role' => 'student']) }}" class="rounded-xl bg-primary px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-primary/25 transition hover:bg-primary/90">
                            I need a tutor
                        </a>
                        <a href="{{ route('legal.contact') }}" class="rounded-xl border border-slate-200/80 bg-white px-6 py-3 text-sm font-semibold text-slate-700 transition hover:border-primary/40 hover:text-primary dark:border-slate-800/80 dark:bg-slate-900 dark:text-slate-300">
                            I want to teach
                        </a>
                    </div>
                    <p class="mt-4 text-xs text-slate-400 dark:text-slate-500">Free to join · Verified teachers only · Secure payments &amp; refunds</p>
                </div>

                <!-- Journey card -->
                <div class="relative">
                    <div class="rounded-2xl border border-slate-200/80 bg-white/90 p-6 shadow-xl shadow-primary/5 backdrop-blur-md dark:border-slate-800/80 dark:bg-slate-900/90">
                        <div class="flex items-center justify-between">
                            <h2 class="text-xs font-bold uppercase tracking-widest text-slate-400">How a lesson happens</h2>
                            <span class="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-primary">MVP flow</span>
                        </div>

                        @php
                            $flow = [
                                ['📷', 'Upload your question', 'A photo of the problem you are stuck on.'],
                                ['🤖', 'AI identifies the lesson', 'Matched to the right subject instantly.'],
                                ['👨‍🏫', 'Find a verified teacher', 'Only approved tutors show up.'],
                                ['🕐', 'Choose an available time', 'Real slots from the tutor calendar.'],
                                ['💳', 'Pay securely', 'Safe checkout with receipts.'],
                                ['🎥', 'Join the live lesson', 'Private 1-on-1 video room.'],
                                ['⭐', 'Rate your teacher', 'Help other students choose well.'],
                            ];
                        @endphp

                        <ol class="mt-5 space-y-3.5">
                            @foreach ($flow as [$emoji, $title, $description])
                                <li class="flex items-start gap-3">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-base">{{ $emoji }}</span>
                                    <div>
                                        <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $title }}</h3>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $description }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features -->
        <section class="border-t border-slate-200/80 bg-white dark:border-slate-800/80 dark:bg-slate-900">
            <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
                <div class="max-w-2xl">
                    <h2 class="text-2xl font-extrabold tracking-tight sm:text-3xl">Built for real 1-on-1 learning</h2>
                    <p class="mt-3 text-slate-600 dark:text-slate-400">Every part of the journey is designed to get you unstuck quickly — and to keep quality high for everyone.</p>
                </div>

                @php
                    $features = [
                        ['🛡️', 'Verified teachers', 'Tutors are reviewed and approved by our team before they can accept a single lesson.'],
                        ['🧠', 'AI question matching', 'Snap a photo; AI suggests the subject and lesson so you always book the right help.'],
                        ['📅', 'Real availability', 'Book only the times a teacher has published — no back-and-forth scheduling.'],
                        ['💳', 'Secure payments', 'Online checkout with transparent pricing, receipts, and a fair refund policy.'],
                        ['💬', 'Chat & reminders', 'Message your teacher around the lesson and get timely reminders.'],
                        ['⭐', 'Reviews & history', 'Rate lessons, revisit your history, and find great teachers through real feedback.'],
                    ];
                @endphp

                <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($features as [$emoji, $title, $description])
                        <div class="group rounded-2xl border border-slate-200/80 bg-slate-50/50 p-6 transition-all hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-lg hover:shadow-primary/5 dark:border-slate-800/80 dark:bg-slate-950/50">
                            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-xl transition-colors group-hover:bg-primary group-hover:text-white">{{ $emoji }}</span>
                            <h3 class="mt-4 text-sm font-bold text-slate-800 dark:text-slate-100">{{ $title }}</h3>
                            <p class="mt-1.5 text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ $description }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8">
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-primary via-primary/90 to-purple-600 p-10 text-center text-white shadow-xl shadow-primary/20">
                <div class="pointer-events-none absolute -top-10 right-10 h-40 w-40 rounded-full bg-white/10 blur-2xl"></div>
                <h2 class="text-2xl font-extrabold tracking-tight sm:text-3xl">Ready to learn like a pro?</h2>
                <p class="mx-auto mt-3 max-w-xl text-sm text-white/90">Join as a student to get help with your questions. Teachers are onboarded by invitation — reach out and we'll send you a link.</p>
                <div class="mt-8 flex flex-wrap justify-center gap-3">
                    <a href="{{ route('register', ['role' => 'student']) }}" class="rounded-xl bg-white px-6 py-3 text-sm font-semibold text-primary shadow-md transition hover:bg-slate-100">
                        Get started as a student
                    </a>
                    <a href="{{ route('legal.contact') }}" class="rounded-xl border border-white/40 bg-white/10 px-6 py-3 text-sm font-semibold text-white backdrop-blur-sm transition hover:bg-white/20">
                        Apply for a teacher invite
                    </a>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t border-slate-200/80 dark:border-slate-800/80">
            <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 px-4 py-8 text-sm text-slate-400 dark:text-slate-500 sm:flex-row sm:px-6 lg:px-8">
                <div class="flex items-center gap-2">
                    <x-application-logo class="h-5 w-5 text-primary" />
                    <span class="font-semibold text-slate-500 dark:text-slate-400">Studylikepro</span>
                </div>
                <nav class="flex flex-wrap items-center justify-center gap-x-5 gap-y-2">
                    <a href="{{ route('legal.privacy') }}" class="transition-colors hover:text-primary">{{ __('Privacy') }}</a>
                    <a href="{{ route('legal.terms') }}" class="transition-colors hover:text-primary">{{ __('Terms') }}</a>
                    <a href="{{ route('legal.refunds') }}" class="transition-colors hover:text-primary">{{ __('Cancellation & refunds') }}</a>
                    <a href="{{ route('legal.contact') }}" class="transition-colors hover:text-primary">{{ __('Contact') }}</a>
                </nav>
                <p>© {{ date('Y') }} Studylikepro. Live 1-on-1 tutoring, on demand.</p>
            </div>
        </footer>
    </body>
</html>
