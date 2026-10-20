@props(['alt' => ''])

<img
    src="{{ asset('images/brand/logo-mark.png') }}"
    alt="{{ $alt }}"
    width="193"
    height="240"
    decoding="async"
    {{ $attributes->merge(['class' => 'object-contain']) }}
>
