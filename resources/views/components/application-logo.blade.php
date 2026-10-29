@props(['alt' => 'Study Like Pro'])

{{-- The wordmark carries the app name, so headers render it without extra text. --}}
<img
    src="{{ asset('images/brand/logo-nav.png') }}"
    alt="{{ $alt }}"
    width="699"
    height="128"
    decoding="async"
    {{ $attributes->merge(['class' => 'object-contain dark:hidden']) }}
>
<img
    src="{{ asset('images/brand/logo-nav-dark.png') }}"
    alt="{{ $alt }}"
    width="699"
    height="128"
    decoding="async"
    {{ $attributes->merge(['class' => 'hidden object-contain dark:block']) }}
>
