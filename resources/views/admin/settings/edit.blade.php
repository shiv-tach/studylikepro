@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $fieldClasses = 'rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';

    // Group the definitions by area while keeping the settings keys intact.
    $groups = [];
    foreach ($definitions as $key => $definition) {
        $groups[$definition['group']][$key] = $definition;
    }

    $groupLabels = [
        'money' => __('Money'),
        'bookings' => __('Bookings'),
        'refunds' => __('Cancellations & refunds'),
        'ai' => __('AI matching'),
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Platform settings') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Commission, fees, holds and the refund policy the marketplace runs on.') }}</p>
            </div>
            <a href="{{ route('admin.offers.index') }}" class="text-xs font-semibold text-primary hover:underline">{{ __('Special offers') }} &rarr;</a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-6">
        @if (session('status') === 'settings-saved')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                {{ __('Settings saved.') }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            @foreach ($groups as $group => $items)
                <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                    <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ $groupLabels[$group] ?? ucfirst($group) }}</h3>

                    <div class="mt-4 space-y-5">
                        @foreach ($items as $key => $definition)
                            @php
                                $rawValue = old($key, $values[$key] ?? '');
                                $inputValue = $definition['type'] === 'money'
                                    ? number_format(((int) $rawValue) / 100, 2, '.', '')
                                    : $rawValue;
                            @endphp
                            <div>
                                <x-input-label :for="'setting-'.$key" :value="$definition['label']" />
                                @if ($definition['type'] === 'text')
                                    <textarea id="setting-{{ $key }}" name="{{ $key }}" rows="3"
                                              class="mt-1 block w-full {{ $fieldClasses }}">{{ $rawValue }}</textarea>
                                @else
                                    <x-text-input :id="'setting-'.$key" :name="$key"
                                                  :type="$definition['type'] === 'string' ? 'text' : 'number'"
                                                  :step="in_array($definition['type'], ['float', 'money'], true) ? '0.01' : null"
                                                  class="mt-1 block w-full"
                                                  :value="$inputValue" />
                                @endif
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    {{ $definition['description'] }}
                                    @if ($definition['type'] === 'money')
                                        · {{ __('in :currency', ['currency' => platform_settings()->currencySymbol()]) }}
                                    @endif
                                </p>
                                <x-input-error :messages="$errors->get($key)" class="mt-2" />
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="flex justify-end">
                <x-primary-button>{{ __('Save settings') }}</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
