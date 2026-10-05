@php
    use Carbon\Carbon;

    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $fieldClasses = 'rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);
    $profile = auth()->user()->teacherProfile;
    $timezone = $profile->timezone ?: config('studylikepro.default_display_timezone');
    $chip = 'inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400';
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Request inbox') }}</h2>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
        @if (session('status') === 'request-accepted')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                Slot held. The student has been notified and has {{ platform_settings()->int('hold_ttl_minutes') }} minutes to pay before it is released.
            </div>
        @elseif (session('status') === 'request-declined')
            <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-4 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-300">
                Proposal declined — the student was told politely and the request stays open for others.
            </div>
        @endif

        @error('starts_at')
            <div class="rounded-2xl border border-rose-200/80 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300">
                {{ $message }}
            </div>
        @enderror
        @error('status')
            <div class="rounded-2xl border border-rose-200/80 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300">
                {{ $message }}
            </div>
        @enderror

        <div class="flex items-start gap-3 {{ $cardBase }}" :class="{{ $cardTheme }}">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-xl">📥</span>
            <div>
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">Questions matched to your topics</h3>
                <p class="mt-1 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                    Only requests inside your subjects, your topics and your available hours appear here — in your timezone ({{ $timezone }}).
                    Accepting one holds the slot for the student while they pay.
                </p>
            </div>
        </div>

        @forelse ($requests as $tutoringRequest)
            @php
                $slots = $suggestedSlots[$tutoringRequest->id] ?? [];
                $gradeLevel = $tutoringRequest->student->studentProfile?->grade_level;
            @endphp
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-primary/10 px-2.5 py-0.5 text-[11px] font-semibold text-primary">
                                {{ $tutoringRequest->subject?->icon ?? '📘' }} {{ $tutoringRequest->subject?->name }} · {{ $tutoringRequest->topic?->name }}
                            </span>
                            @if ($gradeLevel)
                                <span class="{{ $chip }}">{{ $gradeLevel }}</span>
                            @endif
                            <span class="{{ $chip }}">{{ $tutoringRequest->created_at->diffForHumans() }}</span>
                            @if ($tutoringRequest->responded)
                                <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">{{ __('You responded') }}</span>
                            @endif
                        </div>

                        <p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $tutoringRequest->description }}</p>

                        <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-slate-400">
                            <span>👤 {{ $tutoringRequest->student->name }}</span>
                            <span>·</span>
                            <span>{{ $tutoringRequest->budget_minor ? __('Budget :amount', ['amount' => $money($tutoringRequest->budget_minor)]) : __('Flexible budget') }}</span>
                        </div>

                        @php $windows = $tutoringRequest->windowLabels($timezone); @endphp
                        @if ($windows !== [])
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach ($windows as $label)
                                    <span class="{{ $chip }}">🕒 {{ $label }}</span>
                                @endforeach
                            </div>
                        @endif

                        @if ($tutoringRequest->attachments->isNotEmpty())
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach ($tutoringRequest->attachments as $attachment)
                                    <a href="{{ route('request-attachments.show', $attachment) }}" target="_blank"
                                       class="inline-flex items-center gap-1 rounded-full border border-slate-200/80 px-2.5 py-1 text-[11px] font-semibold text-slate-500 transition-colors hover:border-primary/40 hover:text-primary dark:border-slate-700 dark:text-slate-400">
                                        🖼 {{ $attachment->original_name }}
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="text-right text-xs text-slate-400">
                        <p>{{ $tutoringRequest->expires_at?->format('d M') }}</p>
                        <p class="mt-0.5">{{ __('open until') }}</p>
                    </div>
                </div>

                @if ($slots !== [])
                    <form method="POST" action="{{ route('teacher.requests.respond', $tutoringRequest) }}" class="mt-5 border-t border-slate-200/80 pt-5 dark:border-slate-800">
                        @csrf

                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Offer one of these slots') }}</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($slots as $index => $slot)
                                <label class="cursor-pointer">
                                    <input type="radio" name="starts_at" value="{{ $slot['starts_at']->toIso8601String() }}" @checked($index === 0) class="peer sr-only" />
                                    <span class="inline-flex flex-col items-center rounded-xl border border-slate-200/80 px-3 py-2 text-xs font-semibold text-slate-600 transition-colors peer-checked:border-primary peer-checked:bg-primary/5 peer-checked:text-primary dark:border-slate-700 dark:text-slate-300">
                                        <span>{{ Carbon::parse($slot['local_date'])->format('D d M') }}</span>
                                        <span class="mt-0.5 text-[11px] font-medium text-slate-400">{{ $slot['local_time'] }}–{{ $slot['ends_at']->copy()->setTimezone($timezone)->format('H:i') }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        <div class="mt-4">
                            <label for="message-{{ $tutoringRequest->id }}" class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Message (optional)') }}</label>
                            <textarea id="message-{{ $tutoringRequest->id }}" name="message" rows="2" maxlength="1000"
                                      class="mt-1 w-full {{ $fieldClasses }}"
                                      placeholder="{{ __('e.g. Bring the worksheet — we will fix the factorising first.') }}">{{ old('message') }}</textarea>
                        </div>

                        @if ($tutoringRequest->responded)
                            <p class="mt-3 text-xs text-slate-400">{{ __('You already proposed a time — sending again updates your offer while the request is open.') }}</p>
                        @endif

                        <div class="mt-4 flex flex-wrap items-center justify-end gap-3">
                            <span class="text-xs text-slate-400">
                                {{ __('Lesson fee') }}: <span class="font-bold text-slate-600 dark:text-slate-300">{{ $money(($profile->effectiveRateFor($tutoringRequest->subject))) }}</span>
                            </span>
                            <x-primary-button name="action" value="accept">{{ __('Accept & hold slot') }}</x-primary-button>
                        </div>

                        <div class="mt-3 flex justify-end">
                            <button type="submit" name="action" value="decline"
                                    class="text-xs font-semibold text-slate-400 transition-colors hover:text-rose-500">
                                {{ __('Not a fit — decline politely') }}
                            </button>
                        </div>
                    </form>
                @else
                    <p class="mt-5 border-t border-slate-200/80 pt-5 text-sm text-slate-400 dark:border-slate-800">
                        {{ __('No open slots left inside this student\'s preferred windows.') }}
                    </p>
                @endif
            </div>
        @empty
            <div class="{{ $cardBase }} text-center" :class="{{ $cardTheme }}">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10 text-2xl">📭</span>
                <h3 class="mt-4 text-base font-bold text-slate-800 dark:text-slate-100">No matching requests right now</h3>
                <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                    When a student asks about a topic you teach, inside hours you are available for, the question lands here and we email you.
                    Widen your topics or weekly availability to see more.
                </p>
                <div class="mt-5 flex flex-wrap items-center justify-center gap-3">
                    <a href="{{ route('teacher.subjects.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 transition-colors hover:border-primary/40 hover:text-primary dark:border-slate-700 dark:text-slate-300">{{ __('Update subjects') }}</a>
                    <a href="{{ route('teacher.availability.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 transition-colors hover:border-primary/40 hover:text-primary dark:border-slate-700 dark:text-slate-300">{{ __('Update availability') }}</a>
                </div>
            </div>
        @endforelse

        @if ($requests->hasPages())
            <div>{{ $requests->links() }}</div>
        @endif

        <!-- My proposals -->
        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('My proposals') }}</h3>

            <div class="mt-4 space-y-2">
                @forelse ($myResponses as $response)
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200/80 px-4 py-3 dark:border-slate-800">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-700 dark:text-slate-200">
                                {{ $response->tutoringRequest?->topic?->name ?? __('Request removed') }}
                                <span class="font-normal text-slate-400">· {{ $response->tutoringRequest?->subject?->name }}</span>
                            </p>
                            <p class="mt-0.5 text-xs text-slate-400">
                                {{ $response->responded_at?->diffForHumans() }}
                                @if ($response->starts_at)
                                    · {{ $response->starts_at->copy()->setTimezone($timezone)->format('D d M, H:i') }}
                                @endif
                                @if ($response->price_minor)
                                    · {{ $money($response->price_minor) }}
                                @endif
                            </p>
                        </div>
                        <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $response->status->badgeClasses() }}">{{ $response->status->label() }}</span>
                    </div>
                @empty
                    <p class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                        You have not proposed on any request yet.
                    </p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>