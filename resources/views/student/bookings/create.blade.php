@php
    // The direct-booking stepper is rendered by resources/js/student-booking-create.
    // Blade keeps ownership of the grade scope, the lesson list, the prices, the
    // permission check and the route map, then hands them over as a JSON island.
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);
    $themePreset = auth()->user()->theme['preset'];

    $ratePerHour = $teacher->effectiveRateFor($subject, $learnerGradeId);
    $feeGross = $bookingFee['booking_fee_minor'];
    $feeNet = $bookingFee['net_minor'];
    $feeIsPercent = $bookingFee['type'] === \App\Enums\BookingFeeType::Percent;
    $feeNote = $feeIsPercent
        ? __(':percent of the lesson price', ['percent' => \App\Enums\BookingFeeType::percentLabel($bookingFee['percent'])])
        : null;
    $totalMinor = $priceMinor + $feeNet;

    // Slot groups, each already labelled in the viewer's timezone.
    $slotGroups = collect($slotsByDate)->map(fn ($slots, $date) => [
        'date' => (string) $date,
        'label' => \Carbon\CarbonImmutable::parse($date)->format('D, d M'),
        'slots' => collect($slots)->map(fn ($slot) => [
            'value' => $slot['starts_at']->toIso8601String(),
            'label' => $slot['label'],
        ])->values()->all(),
    ])->values()->all();

    $bookingPayload = [
        'appName' => config('app.name', 'Studylikepro'),
        'csrfToken' => csrf_token(),
        'theme' => ['preset' => $themePreset],
        'teacher' => [
            'name' => $teacher->user->name,
            'url' => route('student.teachers.show', $teacher),
        ],
        'subjects' => $subjects
            ->map(fn ($option) => ['value' => (string) $option->id, 'label' => $option->name])
            ->values()->all(),
        // Only the lessons this teacher runs at the learner's grade are offered.
        'lessons' => $lessons
            ->map(fn ($option) => [
                'value' => (string) $option->id,
                'label' => ($option->grade?->label ? $option->grade->label.' — ' : '').$option->name,
            ])
            ->values()->all(),
        'durations' => collect($durations)
            ->map(fn ($option) => ['value' => (string) $option, 'label' => $option.' '.__('minutes')])
            ->values()->all(),
        'selection' => [
            'subjectId' => (string) $subjectId,
            'lessonId' => $selectedLessonId === null ? '' : (string) $selectedLessonId,
            'duration' => (string) $duration,
        ],
        'lessonsNotice' => $lessons->isNotEmpty() ? null : ($learnerGrade
            ? __('This teacher has not listed :grade lessons for :subject yet — you can still book without picking one.', ['grade' => $learnerGrade->label, 'subject' => $subject->name])
            : __('This teacher has not listed lessons for :subject yet — you can book without picking one.', ['subject' => $subject->name])),
        'timezone' => $timezone,
        'holdNotice' => __('All times are shown in :timezone. Selection is held for :minutes minutes while you pay.', [
            'timezone' => $timezone,
            'minutes' => platform_settings()->int('hold_ttl_minutes'),
        ]),
        'slots' => $slotGroups,
        'slotsNotice' => $slotGroups !== [] ? null : __('No open :minutes-minute slots in the next :days days — try a different lesson length.', [
            'minutes' => $duration,
            'days' => config('studylikepro.booking.max_advance_days'),
        ]),
        // A rejected submit bounces back with the picked slot and the errors.
        'selectedSlot' => old('starts_at'),
        'learner' => [
            'name' => old('learner_name', auth()->user()->name),
            'gradeLabel' => $learnerGrade?->label ?? __('Not set'),
            'gradeHint' => __('From your learning profile.'),
            'changeLabel' => __('Change grade'),
            'changeUrl' => route('student.profile'),
            'note' => __('Booked for someone else? Put their name here — the lesson always follows your learning profile grade.'),
        ],
        'errors' => [
            'startsAt' => $errors->first('starts_at') ?: null,
            'learnerName' => $errors->first('learner_name') ?: null,
        ],
        // Every figure is pre-formatted, so the summary card never computes a price.
        'summary' => [
            'teacher' => $teacher->user->name,
            'subject' => $subject->name,
            'length' => $duration.' '.__('minutes'),
            'rate' => $money($ratePerHour).__('/hour'),
            'lessonLabel' => __('Lesson :minutes min', ['minutes' => $duration]),
            'lessonPrice' => $money($priceMinor),
            'feeValue' => $money($feeNet),
            'feeGross' => $money($feeGross),
            'feeNote' => $feeNote,
            'feeIsFree' => $feeNet === 0 && $feeGross > 0,
            'feeFreeLabel' => __('Free'),
            'feeHasDiscount' => $feeNet < $feeGross,
            'youPay' => __('You pay'),
            'total' => $money($totalMinor),
            'includesFee' => __('includes the :amount booking fee', ['amount' => $money($feeNet)]),
            'promotion' => $bookingFee['promotion']
                ? __(':offer — the booking fee is discounted at checkout.', ['offer' => $bookingFee['promotion']->name])
                : null,
            'cancelNotice' => __('Cancellation is free up to :hours hours before the lesson starts.', [
                'hours' => platform_settings()->int('student_cancel_window_hours'),
            ]),
        ],
        'copy' => [
            'stepPick' => __('1. What do you need help with?'),
            'subject' => __('Subject'),
            'lesson' => __('Lesson (optional)'),
            'anyLesson' => __('Any lesson'),
            'duration' => __('Lesson length'),
            'stepTime' => __('2. Pick a time'),
            'stepWho' => __('3. Who is the lesson for?'),
            'learnerName' => __('Learner name'),
            'grade' => __('Grade'),
            'total' => __('Total'),
            'reserve' => __('Reserve this slot'),
            'summaryTitle' => __('Lesson summary'),
            'teacher' => __('Teacher'),
            'length' => __('Length'),
            'rate' => __('Rate'),
            'bookingFee' => __('Booking fee'),
        ],
        'urls' => [
            'filter' => route('student.bookings.create', $teacher),
            'store' => route('student.bookings.store', $teacher),
        ],
    ];

    // JSON_UNESCAPED_SLASHES keeps the URLs readable; the HEX flags keep the
    // island safe inside a <script> tag.
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('student.teachers.show', $teacher) }}" class="text-xs font-semibold text-slate-400 transition-colors hover:text-primary">&larr; {{ $teacher->user->name }}</a>
            <h2 class="mt-1 font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Book a lesson') }}</h2>
        </div>
    </x-slot>

    <script type="application/json" id="student-booking-create-config">@json($bookingPayload, $jsonFlags)</script>

    <div id="student-booking-create-app"></div>

    {{-- Fallback for a browser without JavaScript: the stepper is a thin shell,
         so print the same choice, slots and total the Vue app would render. --}}
    <noscript>
        <div class="mx-auto max-w-5xl space-y-6">
            <div class="rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('Lesson summary') }}</h3>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    {{ $bookingPayload['summary']['teacher'] }} · {{ $bookingPayload['summary']['subject'] }} · {{ $bookingPayload['summary']['length'] }}
                </p>
                <p class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100">
                    {{ __('You pay') }} {{ $bookingPayload['summary']['total'] }}
                </p>
            </div>

            @if ($slotGroups === [])
                <div class="rounded-2xl border border-slate-200/80 bg-white p-6 text-center text-sm text-slate-500 dark:border-slate-800/80 dark:bg-slate-900 dark:text-slate-400">
                    {{ $bookingPayload['slotsNotice'] }}
                </div>
            @else
                <form method="POST" action="{{ route('student.bookings.store', $teacher) }}" class="space-y-6 rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                    @csrf
                    <input type="hidden" name="subject_id" value="{{ $subjectId }}">
                    <input type="hidden" name="lesson_id" value="{{ $selectedLessonId }}">
                    <input type="hidden" name="duration" value="{{ $duration }}">

                    @foreach ($slotGroups as $group)
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $group['label'] }}</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ($group['slots'] as $slot)
                                    <label class="cursor-pointer">
                                        <input type="radio" name="starts_at" value="{{ $slot['value'] }}" class="peer sr-only" required>
                                        <span class="inline-flex items-center rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 peer-checked:border-primary peer-checked:bg-primary/10 peer-checked:text-primary dark:border-slate-700 dark:text-slate-300">
                                            {{ $slot['label'] }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    <div>
                        <label for="noscript-learner-name" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Learner name') }}</label>
                        <input id="noscript-learner-name" name="learner_name" type="text" value="{{ auth()->user()->name }}"
                               class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    </div>

                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary/20 transition-colors hover:bg-primary/90">
                        {{ __('Reserve this slot') }}
                    </button>
                </form>
            @endif
        </div>
    </noscript>

    @vite('resources/js/student-booking-create/main.js')
</x-app-layout>