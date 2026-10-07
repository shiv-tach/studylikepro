@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $fieldClasses = 'rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);
    $commission = $commissionPercent;
    $weekdays = config('studylikepro.weekdays');
    $chip = 'inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400';
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Availability & pricing') }}</h2>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
        @foreach (['availability-slot-added' => 'Weekly range added.', 'availability-slot-removed' => 'Weekly range removed.', 'time-off-added' => 'Time off saved — those days are now blocked.', 'time-off-removed' => 'Time off removed.', 'availability-settings-updated' => 'Lesson length updated.'] as $status => $message)
            @if (session('status') === $status)
                <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                    {{ $message }}
                </div>
            @endif
        @endforeach

        <!-- Lesson length & pricing -->
        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Lesson length & pricing</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Slots are generated and displayed in Sri Lanka time ({{ $profile->timezone }}).
                    </p>
                </div>
                <span class="{{ $chip }}">{{ $profile->timezone }}</span>
            </div>

            <form method="POST" action="{{ route('teacher.availability.settings.update') }}" class="mt-5 flex flex-wrap items-end gap-3">
                @csrf
                @method('PUT')
                <div>
                    <x-input-label for="lesson_duration_minutes" :value="__('Lesson length')" />
                    <select id="lesson_duration_minutes" name="lesson_duration_minutes" class="mt-1 {{ $fieldClasses }}">
                        @foreach (config('studylikepro.lesson_durations') as $minutes)
                            <option value="{{ $minutes }}" @selected(old('lesson_duration_minutes', $profile->lessonDuration()) == $minutes)>{{ $minutes }} minutes</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('lesson_duration_minutes')" class="mt-2" />
                </div>
                <x-primary-button>{{ __('Save lesson length') }}</x-primary-button>
            </form>

            <div class="mt-6 grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl border border-slate-200/80 p-4 dark:border-slate-800">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Base rate</p>
                    <p class="mt-1 text-lg font-bold text-slate-800 dark:text-slate-100">{{ $money($profile->hourly_rate_minor) }}<span class="text-sm font-medium text-slate-400"> / hour</span></p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">You earn about {{ $money((int) round($profile->hourly_rate_minor * (100 - $commission) / 100)) }} after the {{ $commission }}% platform fee.</p>
                </div>

                @foreach ($profile->subjects as $subject)
                    @if ($subject->pivot->rate_per_hour_minor)
                        <div class="rounded-xl border border-primary/30 bg-primary/5 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wider text-primary">{{ $subject->icon ?? '📘' }} {{ $subject->name }}</p>
                            <p class="mt-1 text-lg font-bold text-slate-800 dark:text-slate-100">{{ $money($subject->pivot->rate_per_hour_minor) }}<span class="text-sm font-medium text-slate-400"> / hour</span></p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Override — you earn about {{ $money((int) round($subject->pivot->rate_per_hour_minor * (100 - $commission) / 100)) }} after the platform fee.</p>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        <!-- Weekly ranges -->
        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Weekly availability</h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Add the time ranges you usually teach. Students book {{ $profile->lessonDuration() }}-minute lessons inside them.
            </p>

            <div class="mt-5 space-y-3">
                @php $hasRanges = false; @endphp
                @foreach ($weekdays as $day => $label)
                    @php $ranges = $slotsByDay->get($day, collect()); @endphp
                    @continue($ranges->isEmpty())
                    @php $hasRanges = true; @endphp
                    <div class="flex flex-wrap items-center gap-2 rounded-xl border border-slate-200/80 px-4 py-3 dark:border-slate-800">
                        <span class="w-24 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $label }}</span>
                        <div class="flex flex-1 flex-wrap gap-2">
                            @foreach ($ranges as $range)
                                <span class="inline-flex items-center gap-2 rounded-full bg-primary/10 py-1 pl-3 pr-1 text-sm font-semibold text-primary">
                                    {{ $range->startLabel() }}–{{ $range->endLabel() }}
                                    <form method="POST" action="{{ route('teacher.availability.slots.destroy', $range) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="flex h-5 w-5 items-center justify-center rounded-full text-primary/70 transition hover:bg-primary/20 hover:text-primary" aria-label="{{ __('Remove :day :range', ['day' => $label, 'range' => $range->startLabel().'–'.$range->endLabel()]) }}">&times;</button>
                                    </form>
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                @unless ($hasRanges)
                    <p class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                        No weekly ranges yet — add your first one below and students can start booking those times.
                    </p>
                @endunless
            </div>

            <form method="POST" action="{{ route('teacher.availability.slots.store') }}" class="mt-5 flex flex-wrap items-end gap-3 border-t border-slate-200/80 pt-5 dark:border-slate-800">
                @csrf
                <div>
                    <x-input-label for="day_of_week" :value="__('Day')" />
                    <select id="day_of_week" name="day_of_week" class="mt-1 {{ $fieldClasses }}">
                        @foreach ($weekdays as $day => $label)
                            <option value="{{ $day }}" @selected(old('day_of_week') == $day)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="start_time" :value="__('From')" />
                    <input id="start_time" name="start_time" type="time" value="{{ old('start_time', '18:00') }}" class="mt-1 {{ $fieldClasses }}" />
                </div>
                <div>
                    <x-input-label for="end_time" :value="__('To')" />
                    <input id="end_time" name="end_time" type="time" value="{{ old('end_time', '21:00') }}" class="mt-1 {{ $fieldClasses }}" />
                </div>
                <x-primary-button>{{ __('Add range') }}</x-primary-button>
            </form>
            <x-input-error :messages="$errors->get('day_of_week')" class="mt-2" />
            <x-input-error :messages="$errors->get('start_time')" class="mt-2" />
            <x-input-error :messages="$errors->get('end_time')" class="mt-2" />
        </div>

        <!-- Upcoming open slots -->
        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Next bookable slots</h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">A preview of what students see — your local time.</p>

            @if (empty($preview))
                <p class="mt-4 rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                    Nothing bookable in the next 7 days. Add a weekly range or lift some time off.
                </p>
            @else
                <div class="mt-4 space-y-4">
                    @foreach ($preview as $date => $slots)
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ \Carbon\CarbonImmutable::parse($date)->format('D, d M') }}</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ($slots as $slot)
                                    <span class="{{ $chip }}">{{ $slot['local_time'] }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Time off -->
        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Time off</h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Blocked days disappear from your bookable slots.</p>

            @if ($timeOff->isNotEmpty())
                <ul class="mt-4 space-y-2">
                    @foreach ($timeOff as $entry)
                        <li class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200/80 px-4 py-2.5 dark:border-slate-800">
                            <div>
                                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                                    {{ $entry->starts_on->format('d M Y') }}@if (! $entry->starts_on->isSameDay($entry->ends_on)) – {{ $entry->ends_on->format('d M Y') }}@endif
                                </p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $entry->reason ?: 'Time off' }} · {{ $entry->dayCount() }} {{ Str::plural('day', $entry->dayCount()) }}</p>
                            </div>
                            <form method="POST" action="{{ route('teacher.availability.time-off.destroy', $entry) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-xl px-3 py-1.5 text-xs font-semibold text-slate-500 transition hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800">{{ __('Remove') }}</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif

            <form method="POST" action="{{ route('teacher.availability.time-off.store') }}" class="mt-5 flex flex-wrap items-end gap-3 border-t border-slate-200/80 pt-5 dark:border-slate-800">
                @csrf
                <div>
                    <x-input-label for="starts_on" :value="__('From')" />
                    <input id="starts_on" name="starts_on" type="date" value="{{ old('starts_on') }}" class="mt-1 {{ $fieldClasses }}" />
                </div>
                <div>
                    <x-input-label for="ends_on" :value="__('To')" />
                    <input id="ends_on" name="ends_on" type="date" value="{{ old('ends_on') }}" class="mt-1 {{ $fieldClasses }}" />
                </div>
                <div class="min-w-40 flex-1">
                    <x-input-label for="reason" :value="__('Reason (optional)')" />
                    <input id="reason" name="reason" type="text" value="{{ old('reason') }}" placeholder="Family function" class="mt-1 w-full {{ $fieldClasses }}" />
                </div>
                <x-primary-button>{{ __('Add time off') }}</x-primary-button>
            </form>
            <x-input-error :messages="$errors->get('starts_on')" class="mt-2" />
            <x-input-error :messages="$errors->get('ends_on')" class="mt-2" />
            <x-input-error :messages="$errors->get('reason')" class="mt-2" />
        </div>
    </div>
</x-app-layout>