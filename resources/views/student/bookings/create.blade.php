@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $fieldClasses = 'rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);
    $ratePerHour = $teacher->effectiveRateFor($subject, $learnerGradeId);
    $feeGross = $bookingFee['booking_fee_minor'];
    $feeNet = $bookingFee['net_minor'];
    $totalMinor = $priceMinor + $feeNet;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('teachers.show', $teacher) }}" class="text-xs font-semibold text-slate-400 transition-colors hover:text-primary">&larr; {{ $teacher->user->name }}</a>
            <h2 class="mt-1 font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Book a lesson') }}</h2>
        </div>
    </x-slot>

    <div class="mx-auto max-w-5xl">
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="space-y-6">
                <!-- Lesson choice -->
                <form method="GET" action="{{ route('student.bookings.create', $teacher) }}" id="booking-filter-form"
                      class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                    <input type="hidden" name="learner_grade_id" value="{{ $learnerGradeId }}">
                    <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('1. What do you need help with?') }}</h3>
                    <div class="mt-4 grid gap-4 sm:grid-cols-3">
                        <div>
                            <x-input-label for="filter-subject" :value="__('Subject')" />
                            <select id="filter-subject" name="subject_id" onchange="this.form.submit()" class="mt-1 block w-full {{ $fieldClasses }}">
                                @foreach ($subjects as $option)
                                    <option value="{{ $option->id }}" @selected($option->id === $subjectId)>{{ $option->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="filter-lesson" :value="__('Lesson (optional)')" />
                            <select id="filter-lesson" name="lesson_id" onchange="this.form.submit()" class="mt-1 block w-full {{ $fieldClasses }}">
                                <option value="">{{ __('Any lesson') }}</option>
                                @foreach ($lessons as $option)
                                    <option value="{{ $option->id }}" @selected($option->id === $selectedLessonId)>{{ $option->grade?->label ? $option->grade->label.' — ' : '' }}{{ $option->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="filter-duration" :value="__('Lesson length')" />
                            <select id="filter-duration" name="duration" onchange="this.form.submit()" class="mt-1 block w-full {{ $fieldClasses }}">
                                @foreach ($durations as $option)
                                    <option value="{{ $option }}" @selected($option === $duration)>{{ $option }} {{ __('minutes') }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @if ($lessons->isEmpty())
                        <p class="mt-3 text-xs text-slate-400">
                            @if ($learnerGrade)
                                {{ __('This teacher has not listed :grade lessons for :subject yet — you can still book without picking one.', ['grade' => $learnerGrade->label, 'subject' => $subject->name]) }}
                            @else
                                {{ __('This teacher has not listed lessons for :subject yet — you can book without picking one.', ['subject' => $subject->name]) }}
                            @endif
                        </p>
                    @endif
                </form>

                <!-- Slot picker + confirm -->
                <form method="POST" action="{{ route('student.bookings.store', $teacher) }}" id="booking-form" class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                    @csrf
                    <input type="hidden" name="subject_id" value="{{ $subjectId }}">
                    <input type="hidden" name="lesson_id" value="{{ $selectedLessonId }}">
                    <input type="hidden" name="duration" value="{{ $duration }}">

                    <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('2. Pick a time') }}</h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {{ __('All times are shown in :timezone. Selection is held for :minutes minutes while you pay.', [
                            'timezone' => $timezone,
                            'minutes' => platform_settings()->int('hold_ttl_minutes'),
                        ]) }}
                    </p>
                    <x-input-error :messages="$errors->get('starts_at')" class="mt-2" />

                    @if ($slotsByDate === [])
                        <p class="mt-4 rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                            {{ __('No open :minutes-minute slots in the next :days days — try a different lesson length.', [
                                'minutes' => $duration,
                                'days' => config('studylikepro.booking.max_advance_days'),
                            ]) }}
                        </p>
                    @else
                        <div class="mt-4 space-y-5">
                            @foreach ($slotsByDate as $date => $slots)
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ \Carbon\CarbonImmutable::parse($date)->format('D, d M') }}</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach ($slots as $slot)
                                            <label class="cursor-pointer">
                                                <input type="radio" name="starts_at" value="{{ $slot['starts_at']->toIso8601String() }}"
                                                       class="peer sr-only" {{ old('starts_at') === $slot['starts_at']->toIso8601String() ? 'checked' : '' }} required>
                                                <span class="inline-flex items-center rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 transition-colors peer-checked:border-primary peer-checked:bg-primary/10 peer-checked:text-primary peer-focus-visible:ring-2 peer-focus-visible:ring-primary/40 dark:border-slate-700 dark:text-slate-300">
                                                    {{ $slot['label'] }}
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="mt-6 border-t border-slate-200 pt-6 dark:border-slate-800">
                        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('3. Who is the lesson for?') }}</h3>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label for="learner_name" :value="__('Learner name')" />
                                <x-text-input id="learner_name" name="learner_name" type="text" class="mt-1 block w-full"
                                              :value="old('learner_name', auth()->user()->name)" />
                                <x-input-error :messages="$errors->get('learner_name')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="learner_grade_id" :value="__('Grade')" />
                                <select id="learner_grade_id" name="learner_grade_id" class="mt-1 block w-full {{ $fieldClasses }}"
                                        onchange="const filter = document.getElementById('booking-filter-form'); filter.learner_grade_id.value = this.value; filter.submit();">
                                    @foreach ($levels as $level)
                                        <optgroup label="{{ $level->name }}">
                                            @foreach ($level->grades as $grade)
                                                <option value="{{ $grade->id }}" @selected((int) old('learner_grade_id', $learnerGradeId) === $grade->id)>{{ $grade->label }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('learner_grade_id')" class="mt-2" />
                            </div>
                        </div>
                        <p class="mt-3 text-xs text-slate-400">{{ __('Booked for someone else? Put their name and grade here — changing the grade reloads the page and offers that grade\'s lessons above.') }}</p>
                    </div>

                    <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-6 dark:border-slate-800">
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            {{ __('Total') }} <span class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ $money($totalMinor) }}</span>
                            <span class="ml-1 text-xs text-slate-400">{{ __('includes the :amount booking fee', ['amount' => $money($feeNet)]) }}</span>
                        </p>
                        <x-primary-button :disabled="$slotsByDate === []">
                            {{ __('Reserve this slot') }}
                        </x-primary-button>
                    </div>
                </form>
            </div>

            <!-- Summary -->
            <aside class="space-y-4 lg:sticky lg:top-24 lg:self-start">
                <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Lesson summary') }}</p>
                    <div class="mt-3 space-y-2 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-slate-500 dark:text-slate-400">{{ __('Teacher') }}</span>
                            <span class="font-semibold text-slate-800 dark:text-slate-100">{{ $teacher->user->name }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-slate-500 dark:text-slate-400">{{ __('Subject') }}</span>
                            <span class="font-semibold text-slate-800 dark:text-slate-100">{{ $subject->name }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-slate-500 dark:text-slate-400">{{ __('Length') }}</span>
                            <span class="font-semibold text-slate-800 dark:text-slate-100">{{ $duration }} {{ __('minutes') }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-slate-500 dark:text-slate-400">{{ __('Rate') }}</span>
                            <span class="font-semibold text-slate-800 dark:text-slate-100">{{ $money($ratePerHour) }}/{{ __('hour') }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-slate-500 dark:text-slate-400">{{ __('Lesson :minutes min', ['minutes' => $duration]) }}</span>
                            <span class="font-semibold text-slate-800 dark:text-slate-100">{{ $money($priceMinor) }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-slate-500 dark:text-slate-400">{{ __('Booking fee') }}</span>
                            <span class="font-semibold {{ $feeNet < $feeGross ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-800 dark:text-slate-100' }}">
                                @if ($feeNet === 0 && $feeGross > 0)
                                    {{ __('Free') }} <span class="text-xs font-normal text-slate-400 line-through">{{ $money($feeGross) }}</span>
                                @elseif ($feeNet < $feeGross)
                                    {{ $money($feeNet) }} <span class="text-xs font-normal text-slate-400 line-through">{{ $money($feeGross) }}</span>
                                @else
                                    {{ $money($feeNet) }}
                                @endif
                            </span>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-200 pt-4 dark:border-slate-800">
                        <span class="text-sm font-semibold text-slate-600 dark:text-slate-300">{{ __('You pay') }}</span>
                        <span class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-slate-100">{{ $money($totalMinor) }}</span>
                    </div>
                    @if ($bookingFee['promotion'])
                        <p class="mt-3 rounded-xl bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400">
                            {{ __(':offer — the booking fee is discounted at checkout.', ['offer' => $bookingFee['promotion']->name]) }}
                        </p>
                    @endif
                    <p class="mt-3 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                        {{ __('Cancellation is free up to :hours hours before the lesson starts.', ['hours' => platform_settings()->int('student_cancel_window_hours')]) }}
                    </p>
                </div>
            </aside>
        </div>
    </div>
</x-app-layout>