@php
    use App\Enums\ClassificationStatus;

    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $fieldClasses = 'rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);
    $timezone = auth()->user()->studentProfile?->timezone ?? config('studylikepro.default_display_timezone');
    $status = $tutoringRequest->classification_status;
    $isOwner = $tutoringRequest->student_id === auth()->id();
    $windows = $tutoringRequest->windowLabels($timezone);
    $lessonMap = $subjects->mapWithKeys(fn ($subject) => [
        $subject->id => $subject->lessons->map(fn ($lesson) => [
            'id' => $lesson->id,
            'name' => ($lesson->grade?->label ? $lesson->grade->label.' — ' : '').$lesson->name,
        ])->values()->all(),
    ])->all();
    $openForm = in_array($status, [ClassificationStatus::LowConfidence, ClassificationStatus::Failed], true);
    $pollKey = "studylikepro:request-{$tutoringRequest->id}:polls";
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <a href="{{ route('student.requests.index') }}" class="text-xs font-semibold text-slate-400 transition-colors hover:text-primary">&larr; {{ __('All requests') }}</a>
                <h2 class="mt-1 font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">
                    {{ $tutoringRequest->lesson?->name ?? __('New question') }}
                </h2>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $tutoringRequest->status->badgeClasses() }}">{{ $tutoringRequest->status->label() }}</span>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">{{ $tutoringRequest->created_at->format('d M Y, H:i') }}</span>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-6">
        @if (session('status') === 'request-created')
            <div class="rounded-2xl border border-primary/30 bg-primary/5 p-4 text-sm text-primary">
                Your question is in — we are reading it now and notifying matching teachers. Keep this page open and it will update automatically.
            </div>
        @elseif (session('status') === 'lesson-confirmed')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                Lesson confirmed — matching teachers have been notified.
            </div>
        @endif

        <!-- AI classification -->
        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}"
             @if ($status === ClassificationStatus::Pending)
                 x-data="{
                     polls: Number(sessionStorage.getItem('{{ $pollKey }}') ?? 0),
                     get exhausted() { return this.polls >= 10; },
                     schedule() {
                         if (this.exhausted) return;
                         this.polls++;
                         sessionStorage.setItem('{{ $pollKey }}', this.polls);
                         setTimeout(() => window.location.reload(), 6000);
                     },
                 }"
                 x-init="schedule()"
             @endif>
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-start gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-xl">
                        @switch($status)
                            @case(ClassificationStatus::Completed) ✅ @break
                            @case(ClassificationStatus::LowConfidence) 🤔 @break
                            @case(ClassificationStatus::Failed) ✋ @break
                            @default ⏳
                        @endswitch
                    </span>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ __('AI lesson matching') }}</h3>
                            <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $status->badgeClasses() }}">{{ $status->label() }}</span>
                        </div>

                        @if ($status === ClassificationStatus::Pending)
                            <p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                                We are reading your question{{ $tutoringRequest->attachments->isNotEmpty() ? ' and your photo' : '' }}
                                to pick the exact subject and lesson. This usually takes a few seconds.
                            </p>
                            <div class="mt-3 flex items-center gap-3">
                                <span class="inline-flex h-2 w-2 animate-ping rounded-full bg-primary"></span>
                                <p class="text-xs text-slate-400" x-show="! exhausted">{{ __('Checking again in a moment…') }}</p>
                            </div>
                            <p class="mt-3 text-xs text-slate-400" x-show="exhausted" x-cloak>
                                This is taking longer than usual. Refresh the page in a minute — or cancel this request and try again.
                            </p>
                        @elseif ($status === ClassificationStatus::Completed)
                            <p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                                We filed your question under
                                <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $tutoringRequest->subject?->name }} · {{ $tutoringRequest->lesson?->name }}</span>
                                @if ($tutoringRequest->ai_confidence)
                                    with {{ round($tutoringRequest->ai_confidence * 100) }}% confidence.
                                @else
                                    .
                                @endif
                            </p>
                        @elseif ($status === ClassificationStatus::LowConfidence)
                            <p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                                We were not confident enough to publish your question. Confirm the right subject and lesson below and we will notify matching teachers instantly.
                            </p>
                        @else
                            <p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                                We could not read your question automatically. Pick the subject and lesson below and matching teachers will be notified.
                            </p>
                        @endif
                    </div>
                </div>
            </div>

            @if ($status->isSettled() && $tutoringRequest->isOpen())
                <details class="mt-5 rounded-xl border border-slate-200/80 px-4 py-3 dark:border-slate-800" @if ($openForm) open @endif>
                    <summary class="cursor-pointer text-sm font-semibold text-slate-600 dark:text-slate-300">
                        {{ $status === ClassificationStatus::Completed ? __('Not the right lesson? Change it') : __('Choose the subject and lesson') }}
                    </summary>

                    @if ($grade)
                        <p class="mt-2 text-xs text-slate-400">{{ __('Showing :level subjects and :grade lessons only.', ['level' => $grade->educationLevel?->name ?? __('all'), 'grade' => $grade->label]) }}</p>
                    @endif

                    <form method="POST" action="{{ route('student.requests.lesson.update', $tutoringRequest) }}" class="mt-4 flex flex-wrap items-end gap-3"
                          x-data="{
                              subject: @js((int) old('subject_id', $suggestedSubjectId)),
                              lesson: @js((int) old('lesson_id', $suggestedLessonId)),
                              lessons: @js($lessonMap),
                          }">
                        @csrf
                        @method('PUT')

                        <div class="grow">
                            <x-input-label for="subject_id" :value="__('Subject')" />
                            <select id="subject_id" name="subject_id" x-model.number="subject" @change="lesson = null" required class="mt-1 w-full {{ $fieldClasses }}">
                                <option value="" disabled>{{ __('Pick a subject') }}</option>
                                @foreach ($subjects as $subject)
                                    <option value="{{ $subject->id }}">{{ $subject->icon ?? '📘' }} {{ $subject->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('subject_id')" class="mt-2" />
                        </div>

                        <div class="grow">
                            <x-input-label for="lesson_id" :value="__('Lesson')" />
                            <select id="lesson_id" name="lesson_id" x-model.number="lesson" required :disabled="! subject" class="mt-1 w-full {{ $fieldClasses }} disabled:opacity-50">
                                <template x-for="option in (lessons[subject] ?? [])" :key="option.id">
                                    <option :value="option.id" x-text="option.name"></option>
                                </template>
                            </select>
                            <x-input-error :messages="$errors->get('lesson_id')" class="mt-2" />
                        </div>

                        <x-primary-button>{{ __('Confirm lesson') }}</x-primary-button>
                    </form>
                </details>
            @endif
        </div>

        <!-- Booking / hold -->
        @if ($tutoringRequest->booking)
            @php
                $booking = $tutoringRequest->booking;
                $isHold = $booking->isHold();
                $tone = $isHold
                    ? 'border-amber-200/80 bg-amber-50/60 dark:border-amber-900/40 dark:bg-amber-950/20'
                    : 'border-emerald-200/80 bg-emerald-50/60 dark:border-emerald-900/40 dark:bg-emerald-950/20';
            @endphp
            <div class="{{ $cardBase }} {{ $tone }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex items-start gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $isHold ? 'bg-amber-100 dark:bg-amber-900/40' : 'bg-emerald-100 dark:bg-emerald-900/40' }} text-xl">{{ $isHold ? '⏳' : '✅' }}</span>
                        <div>
                            <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">
                                {{ $isHold ? __('Your teacher is holding this slot') : __('Your lesson is booked') }}
                            </h3>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                                {{ $booking->teacherProfile->user->name }} · {{ $booking->starts_at->copy()->setTimezone($timezone)->format('D d M Y, H:i') }}–{{ $booking->ends_at->copy()->setTimezone($timezone)->format('H:i') }}
                            </p>
                            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                @if ($isHold && $booking->expires_at)
                                    Reserved until {{ $booking->expires_at->copy()->setTimezone($timezone)->format('H:i') }} ({{ $booking->expires_at->diffForHumans() }}).
                                    Pay inside that window and the lesson is confirmed — otherwise the slot is released to other students.
                                @elseif ($isHold)
                                    Pay to confirm this lesson — the slot stays held for you.
                                @else
                                    The slot is confirmed and paid for. Track it from My lessons.
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $booking->status->badgeClasses() }}">{{ $booking->status->label() }}</span>
                        <p class="mt-2 text-lg font-bold text-slate-800 dark:text-slate-100">{{ $money($booking->totalMinor()) }}</p>
                        <a href="{{ route('student.bookings.show', $booking) }}"
                           class="mt-2 inline-flex items-center rounded-xl bg-primary px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-primary/90">
                            {{ $isHold ? __('Complete payment') : __('View lesson') }}
                        </a>
                    </div>
                </div>
            </div>
        @endif

        <!-- Question details -->
        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Your question') }}</h3>
            <p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $tutoringRequest->description }}</p>

            <dl class="mt-5 grid gap-4 border-t border-slate-200/80 pt-5 text-sm sm:grid-cols-3 dark:border-slate-800">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Subject') }}</dt>
                    <dd class="mt-1 font-semibold text-slate-700 dark:text-slate-200">{{ $tutoringRequest->subject?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Budget') }}</dt>
                    <dd class="mt-1 font-semibold text-slate-700 dark:text-slate-200">
                        {{ $tutoringRequest->budget_minor ? $money($tutoringRequest->budget_minor).' / lesson' : __('Flexible') }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Open until') }}</dt>
                    <dd class="mt-1 font-semibold text-slate-700 dark:text-slate-200">{{ $tutoringRequest->expires_at?->format('d M Y') ?? '—' }}</dd>
                </div>
            </dl>

            @if ($windows !== [])
                <div class="mt-5 border-t border-slate-200/80 pt-5 dark:border-slate-800">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Times that suit you') }} ({{ $timezone }})</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($windows as $label)
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">🕒 {{ $label }}</span>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($tutoringRequest->attachments->isNotEmpty())
                <div class="mt-5 border-t border-slate-200/80 pt-5 dark:border-slate-800">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Attached photos') }}</p>
                    <div class="mt-2 space-y-2">
                        @foreach ($tutoringRequest->attachments as $attachment)
                            <a href="{{ route('request-attachments.show', $attachment) }}" target="_blank"
                               class="flex items-center justify-between gap-3 rounded-xl border border-slate-200/80 px-3 py-2 text-sm text-slate-600 transition-colors hover:border-primary/40 hover:text-primary dark:border-slate-800 dark:text-slate-300">
                                <span class="truncate">🖼 {{ $attachment->original_name }}</span>
                                <span class="shrink-0 text-xs text-slate-400">{{ number_format($attachment->size / 1024) }} KB</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <!-- Teacher proposals -->
        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Teacher proposals') }}</h3>
                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">{{ $tutoringRequest->responses->count() }}</span>
            </div>

            <div class="mt-4 space-y-3">
                @forelse ($tutoringRequest->responses as $response)
                    @php $teacher = $response->teacherProfile; @endphp
                    <div class="rounded-xl border border-slate-200/80 p-4 dark:border-slate-800">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="flex items-start gap-3">
                                @if ($teacher->user->avatarUrl())
                                    <img src="{{ $teacher->user->avatarUrl() }}" alt="{{ $teacher->user->name }}" class="h-10 w-10 rounded-xl object-cover" />
                                @else
                                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-sm font-bold text-primary">{{ substr($teacher->user->name, 0, 2) }}</span>
                                @endif
                                <div>
                                    <a href="{{ route('student.teachers.show', $teacher) }}" class="text-sm font-bold text-slate-800 transition-colors hover:text-primary dark:text-slate-100">{{ $teacher->user->name }}</a>
                                    <p class="mt-0.5 text-xs text-slate-400">
                                        {{ $teacher->headline ?: $teacher->subjects->pluck('name')->join(' · ') }}
                                    </p>
                                    @if ($response->starts_at)
                                        <p class="mt-2 text-sm font-semibold text-slate-600 dark:text-slate-300">
                                            🕒 {{ $response->starts_at->copy()->setTimezone($timezone)->format('D d M Y, H:i') }}–{{ $response->ends_at->copy()->setTimezone($timezone)->format('H:i') }}
                                        </p>
                                    @endif
                                    @if (filled($response->message))
                                        <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ $response->message }}</p>
                                    @endif
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $response->status->badgeClasses() }}">{{ $response->status->label() }}</span>
                                @if ($response->price_minor)
                                    <p class="mt-2 text-lg font-bold text-slate-800 dark:text-slate-100">{{ $money($response->price_minor) }}</p>
                                    <p class="text-xs text-slate-400">{{ __('per lesson') }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                        @if ($tutoringRequest->isOpen() && $status->isSettled())
                            No proposals yet — matching teachers were just notified. You will get an email and a notification the moment one replies.
                        @elseif ($status === ClassificationStatus::Pending)
                            Proposals appear here as soon as the lesson is confirmed.
                        @else
                            No proposals for this request.
                        @endif
                    </p>
                @endforelse
            </div>
        </div>

        @if ($isOwner && $tutoringRequest->isOpen())
            <form method="POST" action="{{ route('student.requests.cancel', $tutoringRequest) }}"
                  onsubmit="return confirm('{{ __('Cancel this request? Teachers already notified will stop seeing it.') }}');"
                  class="flex justify-end">
                @csrf
                <x-danger-button>{{ __('Cancel request') }}</x-danger-button>
            </form>
        @endif
    </div>
</x-app-layout>