@php
    // Wizard step 4 ("Weekly availability"). App\Support\AvailabilitySetupConfig
    // owns the starting week: a rejected save wins over the autosaved draft,
    // which wins over the saved ranges and the recommended preset.
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;

    $cardClasses = 'rounded-2xl border border-slate-200/80 bg-white p-5 dark:border-slate-800/80 dark:bg-slate-900 sm:p-6';
    $fieldClasses = 'mt-1 rounded-xl border-slate-300 bg-white text-base shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 sm:text-sm';
@endphp
<x-onboarding-layout>
    <x-slot name="progress">
        <x-onboarding-progress :current="4" />
    </x-slot>

    <script type="application/json" id="teacher-availability-setup-config">@json($config, $jsonFlags)</script>

    <div id="teacher-availability-setup-app"></div>

    <noscript>
        <div class="space-y-5">
            @if ($errors->any())
                <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300">
                    <p class="font-semibold">{{ __('Please fix the following:') }}</p>
                    <ul class="mt-1 list-inside list-disc space-y-0.5">
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('teacher.onboarding.availability.update') }}" class="space-y-5">
                @csrf
                @method('PUT')

                <div class="{{ $cardClasses }}">
                    <h1 class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ __('When can you teach?') }}</h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ __('Times are in :timezone. Your lessons are :minutes minutes long, so every range must last at least that long.', ['timezone' => $config['profile']['timezone'], 'minutes' => $config['profile']['lessonDuration']]) }}
                    </p>

                    <x-input-error :messages="$errors->get('days')" class="mt-3" />

                    <div class="mt-4 space-y-3">
                        @foreach ($config['week'] as $index => $day)
                            <fieldset class="rounded-xl border border-slate-200/80 p-3 dark:border-slate-800">
                                <input type="hidden" name="days[{{ $index }}][value]" value="{{ $day['value'] }}">

                                <label class="flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
                                    <input type="checkbox" name="days[{{ $index }}][enabled]" value="1"
                                           class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900"
                                           @checked($day['enabled']) />
                                    {{ $day['label'] }}
                                </label>

                                <div class="mt-3 flex flex-wrap items-end gap-3">
                                    <div>
                                        <x-input-label for="day-{{ $index }}-start" :value="__('From')" />
                                        <input id="day-{{ $index }}-start" name="days[{{ $index }}][ranges][0][start_time]" type="time"
                                               value="{{ $day['ranges'][0]['start'] ?? '' }}" class="{{ $fieldClasses }}" />
                                    </div>
                                    <div>
                                        <x-input-label for="day-{{ $index }}-end" :value="__('To')" />
                                        <input id="day-{{ $index }}-end" name="days[{{ $index }}][ranges][0][end_time]" type="time"
                                               value="{{ $day['ranges'][0]['end'] ?? '' }}" class="{{ $fieldClasses }}" />
                                    </div>
                                </div>

                                <x-input-error :messages="$errors->get('days.'.$index.'.ranges')" class="mt-2" />
                                <x-input-error :messages="$errors->get('days.'.$index.'.ranges.0.start_time')" class="mt-2" />
                                <x-input-error :messages="$errors->get('days.'.$index.'.ranges.0.end_time')" class="mt-2" />
                            </fieldset>
                        @endforeach
                    </div>
                </div>

                <div class="flex items-center justify-between gap-3">
                    <a href="{{ route('teacher.onboarding.preferences') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-100">
                        {{ __('Back') }}
                    </a>
                    <x-primary-button>{{ __('Save week & continue') }}</x-primary-button>
                </div>
            </form>
        </div>
    </noscript>

    @vite('resources/js/teacher-availability-setup/main.js')
</x-onboarding-layout>
