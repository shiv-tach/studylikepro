@php
    // The invite survey renders in Vue (resources/js/join). Blade keeps building
    // the island — answers, options, recap, rail, validation messages, routes and
    // CSRF — and the <noscript> twin below posts the identical fields, so a
    // browser without JavaScript can still answer every question.
    $config = \App\Support\JoinSurveyConfig::build($step, $answers, $errors);
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
@endphp

<x-onboarding-layout :guest="$guest">
    <x-slot name="progress">
        {{-- The sticky header rail is mounted from the same island. --}}
        <div id="join-rail"></div>
    </x-slot>

    <script type="application/json" id="join-survey-config">@json($config, $jsonFlags)</script>

    <div id="join-survey-app"></div>

    <noscript>
        @include('join.partials.survey-form', ['config' => $config])
    </noscript>

    @vite('resources/js/join/main.js')
</x-onboarding-layout>
