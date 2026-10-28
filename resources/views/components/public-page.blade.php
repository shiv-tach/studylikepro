{{--
    The public site shell: the landing page's design tokens, fonts and Vite
    entry, reused by the catalog, teacher directory and contact screens so a
    visitor never sees two different public designs. Pages supply their own
    Vue island, mount point and no-JavaScript fallback in the slot.
--}}
@props([
    'title',
    'description' => null,
    'vite' => [],
    'ogImage' => null,
    'noindex' => false,
])

@php
    $fullTitle = config('app.name', 'Studylikepro').' — '.$title;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $fullTitle }}</title>
        @if ($noindex)
            <meta name="robots" content="noindex,nofollow">
        @endif
        @if ($description)
            <meta name="description" content="{{ $description }}">
        @endif

        <x-favicon-links />

        <meta property="og:type" content="website">
        <meta property="og:title" content="{{ $fullTitle }}">
        @if ($description)
            <meta property="og:description" content="{{ $description }}">
        @endif
        @if ($ogImage)
            <meta property="og:image" content="{{ $ogImage }}">
        @endif

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <link href="https://api.fontshare.com/v2/css?f[]=clash-grotesk@500,600,700&display=swap" rel="stylesheet">

        <!-- Blocking theme script: public pages load light unless dark was chosen explicitly -->
        <script>
            (function() {
                if (localStorage.getItem('themeMode') === 'dark') {
                    document.documentElement.classList.add('dark');
                }
            })();
        </script>

        @vite($vite)
    </head>
    <body class="bg-white font-sans text-ink antialiased">
        {{ $slot }}
    </body>
</html>
