@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-semibold text-xs text-slate-500 uppercase tracking-wider dark:text-slate-400']) }}>
    {{ $value ?? $slot }}
</label>
