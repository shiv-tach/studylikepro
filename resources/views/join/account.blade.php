@php
    // The last step of the invite survey renders in Vue (resources/js/join): one
    // URL with two states — identity + password, then the SMS code. Blade builds
    // the island and the <noscript> twin below posts the same fields.
    $config = \App\Support\JoinAccountConfig::build(
        $pending,
        $mobile,
        $length,
        $cooldown,
        $otpEnabled,
        $answers,
        $errors,
        $status ?? null,
    );
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
@endphp

<x-onboarding-layout :guest="$guest">
    <x-slot name="progress">
        <div id="join-rail"></div>
    </x-slot>

    <script type="application/json" id="join-account-config">@json($config, $jsonFlags)</script>

    <div id="join-account-app"></div>

    <noscript>
        @include('join.partials.account-form', ['config' => $config])
    </noscript>

    @vite('resources/js/join/main.js')
</x-onboarding-layout>
