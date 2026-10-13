@php
    // The landing page is rendered client-side by resources/js/landing. Blade only
    // renders the document shell and hands the Vue app the route map, auth state
    // and support details, so no URL is hardcoded in JavaScript.
    $landing = [
        'appName' => config('app.name', 'Studylikepro'),
        'authenticated' => auth()->check(),
        'urls' => [
            'home' => route('home'),
            'subjects' => route('catalog.subjects.index'),
            'teachers' => route('teachers.index'),
            'login' => route('login'),
            'registerStudent' => route('register', ['role' => 'student']),
            'dashboard' => route('dashboard'),
            'contact' => route('legal.contact'),
            'privacy' => route('legal.privacy'),
            'terms' => route('legal.terms'),
            'refunds' => route('legal.refunds'),
        ],
        'support' => [
            'email' => config('studylikepro.support.email'),
            'phone' => config('studylikepro.support.phone'),
            'hours' => config('studylikepro.support.hours'),
        ],
    ];

    // JSON_UNESCAPED_SLASHES keeps the URLs readable in the document (and matchable
    // by tests); the HEX flags keep the island safe inside a <script> tag.
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;

    $title = config('app.name', 'Studylikepro').' — Learn with verified Sri Lankan tutors';
    $description = 'Upload a question, let AI pinpoint the lesson, and book a live 1-on-1 lesson with a verified teacher. Grades 1–13 on the Sri Lankan syllabus, paid securely in rupees.';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title }}</title>
        <meta name="description" content="{{ $description }}">

        <meta property="og:type" content="website">
        <meta property="og:title" content="{{ $title }}">
        <meta property="og:description" content="{{ $description }}">

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

        @vite(['resources/css/app.css', 'resources/js/landing/main.js'])
    </head>
    <body class="font-sans antialiased bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
        <script type="application/json" id="landing-config">@json($landing, $jsonFlags)</script>

        <div id="landing-app"></div>

        {{-- Fallback for crawlers and visitors without JavaScript. --}}
        <noscript>
            <div style="max-width:44rem;margin:0 auto;padding:3rem 1.5rem;font-family:system-ui,sans-serif;line-height:1.6">
                <h1>{{ $title }}</h1>
                <p>{{ $description }}</p>
                <ul>
                    <li><a href="{{ route('register', ['role' => 'student']) }}">Create a student account</a></li>
                    <li><a href="{{ route('login') }}">Log in</a></li>
                    <li><a href="{{ route('catalog.subjects.index') }}">Browse subjects</a></li>
                    <li><a href="{{ route('teachers.index') }}">Browse teachers</a></li>
                    <li><a href="{{ route('legal.contact') }}">Contact us</a></li>
                    <li><a href="{{ route('legal.privacy') }}">Privacy</a></li>
                    <li><a href="{{ route('legal.terms') }}">Terms</a></li>
                    <li><a href="{{ route('legal.refunds') }}">Cancellation &amp; refunds</a></li>
                </ul>
            </div>
        </noscript>
    </body>
</html>
