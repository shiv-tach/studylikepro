@props(['status'])

<span {{ $attributes->merge(['class' => 'rounded-full px-2.5 py-0.5 text-[11px] font-semibold '.$status->badgeClasses()]) }}>
    {{ $status->label() }}
</span>
