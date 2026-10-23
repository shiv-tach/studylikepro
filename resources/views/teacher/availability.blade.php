@php
    // /teacher/availability is rendered by resources/js/teacher-availability.
    // Blade decides the saved week, the default price, every formatted time and
    // price, the validation messages and the route map, then hands them over in
    // one JSON island. The <noscript> block below repeats the same four actions
    // as plain forms, so a browser without JavaScript loses nothing.
    $copy = $config['copy'];
    $pricing = $config['pricing'];
    $week = $config['week'];

    // JSON_UNESCAPED_SLASHES keeps the URLs readable; the HEX flags keep the
    // island safe inside a <script> tag.
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;

    $cardBase = 'rounded-2xl border p-6 border-slate-200/80 bg-white dark:border-slate-800/80 dark:bg-slate-900';
    $fieldClasses = 'mt-1 rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Availability & pricing') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Times shown in :timezone.', ['timezone' => $config['timezone']]) }}
                </p>
            </div>
            <a href="{{ $config['urls']['schedule'] }}"
               class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 transition-colors hover:border-primary/40 hover:text-primary dark:border-slate-700 dark:text-slate-300">
                {{ $copy['hero']['schedule'] }}
            </a>
        </div>
    </x-slot>

    <div class="w-full">
        <script type="application/json" id="teacher-availability-config">@json($config, $jsonFlags)</script>

        <div id="teacher-availability-app" class="space-y-6"></div>

        <noscript>
            <div class="mx-auto max-w-4xl space-y-6">
                <section class="{{ $cardBase }}">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ $copy['editorTitle'] }}</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ $copy['timezoneNote'] }}
                        {{ __('Your lessons are :minutes minutes long, so every range must last at least that long.', ['minutes' => $pricing['lessonDuration']]) }}
                    </p>

                    <x-input-error :messages="$errors->get('days')" class="mt-3" />

                    <form method="POST" action="{{ $config['urls']['week'] }}" class="mt-4 space-y-4">
                        @csrf
                        @method('PUT')

                        @foreach ($week as $index => $day)
                            <fieldset class="rounded-xl border border-slate-200/80 p-3 dark:border-slate-800">
                                <input type="hidden" name="days[{{ $index }}][value]" value="{{ $day['value'] }}">

                                <label class="flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
                                    <input type="checkbox" name="days[{{ $index }}][enabled]" value="1"
                                           class="rounded border-slate-300 text-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900"
                                           @checked($day['enabled']) />
                                    {{ $day['label'] }}
                                </label>

                                <div class="mt-3 space-y-2">
                                    @foreach (range(0, 3) as $row)
                                        @continue($row > 0 && ! isset($day['ranges'][$row]))

                                        <div class="flex flex-wrap items-end gap-3">
                                            <div>
                                                <x-input-label for="day-{{ $index }}-{{ $row }}-start" :value="$copy['fromLabel']" />
                                                <input id="day-{{ $index }}-{{ $row }}-start" type="time"
                                                       name="days[{{ $index }}][ranges][{{ $row }}][start_time]"
                                                       value="{{ $day['ranges'][$row]['start'] ?? '' }}" class="{{ $fieldClasses }}" />
                                            </div>
                                            <div>
                                                <x-input-label for="day-{{ $index }}-{{ $row }}-end" :value="$copy['toLabel']" />
                                                <input id="day-{{ $index }}-{{ $row }}-end" type="time"
                                                       name="days[{{ $index }}][ranges][{{ $row }}][end_time]"
                                                       value="{{ $day['ranges'][$row]['end'] ?? '' }}" class="{{ $fieldClasses }}" />
                                            </div>
                                        </div>

                                        <x-input-error :messages="$errors->get('days.'.$index.'.ranges.'.$row.'.start_time')" class="mt-1" />
                                        <x-input-error :messages="$errors->get('days.'.$index.'.ranges.'.$row.'.end_time')" class="mt-1" />
                                    @endforeach
                                </div>

                                <x-input-error :messages="$errors->get('days.'.$index.'.ranges')" class="mt-2" />
                            </fieldset>
                        @endforeach

                        <div class="flex justify-end">
                            <x-primary-button>{{ $copy['save'] }}</x-primary-button>
                        </div>
                    </form>
                </section>

                <section class="{{ $cardBase }}">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Weekly availability</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $copy['editorHint'] }}</p>

                    <div class="mt-4 space-y-2">
                        @foreach ($week as $day)
                            @continue(empty($day['ranges']))
                            <div class="flex flex-wrap items-center gap-2 rounded-xl border border-slate-200/80 px-4 py-3 dark:border-slate-800">
                                <span class="w-24 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $day['label'] }}</span>
                                <div class="flex flex-1 flex-wrap gap-2">
                                    @foreach ($day['ranges'] as $range)
                                        <span class="inline-flex items-center rounded-full bg-primary/10 px-3 py-1 text-sm font-semibold text-primary">
                                            {{ display_clock($range['start']) }}–{{ display_clock($range['end']) }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="{{ $cardBase }}">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ $copy['pricing']['title'] }}</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $copy['pricing']['description'] }}</p>

                    <form method="POST" action="{{ $config['urls']['pricing'] }}" class="mt-4 flex flex-wrap items-end gap-3">
                        @csrf
                        @method('PUT')
                        <div>
                            <x-input-label for="hourly_rate" :value="$copy['pricing']['rateLabel']" />
                            <input id="hourly_rate" name="hourly_rate" type="number" step="0.01" min="100" max="100000"
                                   value="{{ old('hourly_rate', $pricing['rate']) }}" class="{{ $fieldClasses }}" />
                        </div>
                        <x-primary-button>{{ $copy['pricing']['rateSave'] }}</x-primary-button>
                    </form>
                    <x-input-error :messages="$errors->get('hourly_rate')" class="mt-2" />

                    <div class="mt-4 rounded-xl border border-slate-200/80 p-4 dark:border-slate-800">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Base rate</p>
                        <p class="mt-1 text-lg font-bold text-slate-800 dark:text-slate-100">
                            {{ $pricing['rateLabel'] }}<span class="text-sm font-medium text-slate-400"> {{ $copy['pricing']['perHour'] }}</span>
                        </p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $pricing['earningsLabel'] }}</p>
                    </div>

                    <form method="POST" action="{{ $config['urls']['settings'] }}" class="mt-4 flex flex-wrap items-end gap-3 border-t border-slate-200/80 pt-4 dark:border-slate-800">
                        @csrf
                        @method('PUT')
                        <div>
                            <x-input-label for="lesson_duration_minutes" :value="$copy['pricing']['lessonLengthLabel']" />
                            <select id="lesson_duration_minutes" name="lesson_duration_minutes" class="{{ $fieldClasses }}">
                                @foreach ($pricing['durations'] as $duration)
                                    <option value="{{ $duration['value'] }}" @selected(old('lesson_duration_minutes', $pricing['lessonDuration']) == $duration['value'])>{{ $duration['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <x-primary-button>{{ $copy['pricing']['lessonLengthSave'] }}</x-primary-button>
                    </form>
                    <x-input-error :messages="$errors->get('lesson_duration_minutes')" class="mt-2" />

                    @if (! empty($pricing['subjects']))
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            @foreach ($pricing['subjects'] as $subject)
                                <div class="rounded-xl border border-primary/30 bg-primary/5 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-wider text-primary">{{ $subject['icon'] }} {{ $subject['name'] }}</p>

                                    @if ($subject['rateLabel'])
                                        <p class="mt-1 text-lg font-bold text-slate-800 dark:text-slate-100">
                                            {{ $subject['rateLabel'] }}<span class="text-sm font-medium text-slate-400"> {{ $copy['pricing']['perHour'] }}</span>
                                        </p>
                                    @endif

                                    @foreach ($subject['gradeRates'] as $grade)
                                        <div class="mt-2 flex items-center justify-between gap-2 text-xs text-slate-500 dark:text-slate-400">
                                            <span>{{ $grade['label'] }}</span>
                                            <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $grade['rateLabel'] }}</span>
                                        </div>
                                    @endforeach

                                    <a href="{{ $subject['editUrl'] }}" class="mt-3 inline-block text-xs font-semibold text-primary hover:text-primary/80">{{ $copy['pricing']['editRates'] }} →</a>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>

                <section class="{{ $cardBase }}">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ $copy['preview']['title'] }}</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $copy['preview']['description'] }}</p>

                    @if (empty($config['preview']))
                        <p class="mt-4 rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                            {{ $copy['preview']['emptyTitle'] }} — {{ $copy['preview']['emptyBody'] }}
                        </p>
                    @else
                        <div class="mt-4 space-y-4">
                            @foreach ($config['preview'] as $day)
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $day['dateLabel'] }}</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach ($day['slots'] as $slot)
                                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">{{ $slot['time'] }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>

                <section class="{{ $cardBase }}">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ $copy['timeOff']['title'] }}</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $copy['timeOff']['description'] }}</p>

                    @if (! empty($config['timeOff']))
                        <ul class="mt-4 space-y-2">
                            @foreach ($config['timeOff'] as $entry)
                                <li class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200/80 px-4 py-2.5 dark:border-slate-800">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $entry['rangeLabel'] }}</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $entry['reason'] }} · {{ $entry['dayCountLabel'] }}</p>
                                    </div>

                                    <form method="POST" action="{{ $entry['deleteUrl'] }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-xl px-3 py-1.5 text-xs font-semibold text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800">{{ $copy['timeOff']['remove'] }}</button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <form method="POST" action="{{ $config['urls']['timeOff'] }}" class="mt-5 flex flex-wrap items-end gap-3 border-t border-slate-200/80 pt-5 dark:border-slate-800">
                        @csrf
                        <div>
                            <x-input-label for="starts_on" :value="$copy['timeOff']['fromLabel']" />
                            <input id="starts_on" name="starts_on" type="date" value="{{ old('starts_on') }}" class="{{ $fieldClasses }}" />
                        </div>
                        <div>
                            <x-input-label for="ends_on" :value="$copy['timeOff']['toLabel']" />
                            <input id="ends_on" name="ends_on" type="date" value="{{ old('ends_on') }}" class="{{ $fieldClasses }}" />
                        </div>
                        <div class="min-w-40 flex-1">
                            <x-input-label for="reason" :value="$copy['timeOff']['reasonLabel']" />
                            <input id="reason" name="reason" type="text" maxlength="120" value="{{ old('reason') }}"
                                   placeholder="{{ $copy['timeOff']['reasonPlaceholder'] }}" class="w-full {{ $fieldClasses }}" />
                        </div>
                        <x-primary-button>{{ $copy['timeOff']['add'] }}</x-primary-button>
                    </form>
                    <x-input-error :messages="$errors->get('starts_on')" class="mt-2" />
                    <x-input-error :messages="$errors->get('ends_on')" class="mt-2" />
                    <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                </section>
            </div>
        </noscript>
    </div>

    @vite('resources/js/teacher-availability/main.js')
</x-app-layout>
