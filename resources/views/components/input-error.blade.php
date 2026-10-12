@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'text-sm text-red-600 dark:text-red-400 space-y-1']) }}>
        @foreach (collect((array) $messages)->flatten() as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
