@php
    use App\Enums\VerificationStatus;

    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $chip = 'inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300';
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Review application') }}</h2>
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $profile->verification_status->badgeClasses() }}">
                {{ $profile->verification_status->label() }}
            </span>
        </div>
    </x-slot>

    <div class="space-y-6">
        <a href="{{ route('admin.verifications.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 transition-colors hover:text-primary dark:text-slate-400">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Back to queue
        </a>

        @if (session('status') === 'verification-not-pending')
            <div class="rounded-2xl border border-amber-200/80 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300">
                This application is no longer pending — it may already be decided.
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Profile -->
            <div class="{{ $cardBase }} lg:col-span-2" :class="{{ $cardTheme }}">
                <div class="flex items-center gap-4">
                    @if ($profile->user->avatarUrl())
                        <img src="{{ $profile->user->avatarUrl() }}" alt="{{ $profile->user->name }}" class="h-14 w-14 rounded-2xl object-cover shadow-md" />
                    @else
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary text-lg font-bold text-white shadow-md shadow-primary/20">
                            {{ substr($profile->user->name, 0, 2) }}
                        </div>
                    @endif
                    <div>
                        <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ $profile->user->name }}</h3>
                        <p class="text-sm text-slate-400">{{ $profile->user->email }}</p>
                    </div>
                </div>

                <dl class="mt-6 grid gap-5 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Headline</dt>
                        <dd class="mt-1 text-sm text-slate-700 dark:text-slate-200">{{ $profile->headline }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Experience</dt>
                        <dd class="mt-1 text-sm text-slate-700 dark:text-slate-200">{{ $profile->experience_years }} {{ Str::plural('year', $profile->experience_years) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Education</dt>
                        <dd class="mt-1 text-sm text-slate-700 dark:text-slate-200">{{ $profile->education }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Base rate</dt>
                        <dd class="mt-1 text-sm text-slate-700 dark:text-slate-200">{{ $money((int) round($profile->hourlyRate() * 100)) }} / hour</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Timezone</dt>
                        <dd class="mt-1 text-sm text-slate-700 dark:text-slate-200">{{ $profile->timezone }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Languages</dt>
                        <dd class="mt-1 flex flex-wrap gap-1.5">
                            @foreach ($profile->languages as $language)
                                <span class="{{ $chip }}">{{ $language }}</span>
                            @endforeach
                        </dd>
                    </div>
                </dl>

                @if ($profile->bio)
                    <div class="mt-5">
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">About</dt>
                        <p class="mt-1 whitespace-pre-line text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $profile->bio }}</p>
                    </div>
                @endif

                <div class="mt-5">
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Subjects & lessons</dt>
                    <div class="mt-2 space-y-2">
                        @forelse ($profile->subjects as $subject)
                            <div class="rounded-xl border border-slate-200/80 px-4 py-3 dark:border-slate-800">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $subject->icon ?? '📘' }} {{ $subject->name }}</span>
                                    @if ($subject->pivot->rate_per_hour_minor)
                                        <span class="{{ $chip }}">{{ $money($subject->pivot->rate_per_hour_minor) }}/hr</span>
                                    @endif
                                </div>
                                <p class="mt-1 text-xs text-slate-400">
                                    {{ $profile->lessons->where('subject_id', $subject->id)->pluck('name')->join(', ') ?: 'No specific lessons selected' }}
                                </p>
                            </div>
                        @empty
                            <p class="text-sm text-slate-400">No subjects selected yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Documents + decision -->
            <div class="space-y-6">
                <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Documents</h3>

                    @if ($profile->documents->isEmpty())
                        <p class="mt-2 text-sm text-slate-400">No documents uploaded.</p>
                    @else
                        <ul class="mt-4 space-y-3">
                            @foreach ($profile->documents as $document)
                                <li class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $document->typeLabel() }}</p>
                                        <p class="truncate text-xs text-slate-400">{{ $document->original_name }}</p>
                                    </div>
                                    <a href="{{ route('verification-documents.show', $document) }}" target="_blank"
                                       class="shrink-0 rounded-xl bg-primary/10 px-3 py-1.5 text-xs font-semibold text-primary transition-colors hover:bg-primary/20">
                                        View
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                    @if ($profile->verification_status === VerificationStatus::Pending)
                        <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Decision</h3>
                        <p class="mt-1 text-xs text-slate-400">Submitted {{ $profile->submitted_at?->diffForHumans() }}.</p>

                        <form method="POST" action="{{ route('admin.verifications.approve', $profile) }}" class="mt-4">
                            @csrf
                            <x-primary-button class="w-full justify-center">{{ __('Approve teacher') }}</x-primary-button>
                        </form>

                        <form method="POST" action="{{ route('admin.verifications.reject', $profile) }}" class="mt-4 space-y-3 border-t border-slate-100 pt-4 dark:border-slate-800">
                            @csrf
                            <label for="reason" class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Rejection notes</label>
                            <textarea id="reason" name="reason" rows="3"
                                      class="block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900"
                                      placeholder="Explain what the teacher should fix before resubmitting.">{{ old('reason') }}</textarea>
                            <x-input-error :messages="$errors->get('reason')" />
                            <button type="submit"
                                    class="inline-flex w-full items-center justify-center rounded-xl border border-rose-200/80 px-4 py-2.5 text-sm font-bold text-rose-600 transition-colors hover:bg-rose-50 dark:border-rose-900/40 dark:text-rose-400 dark:hover:bg-rose-950/30">
                                Reject application
                            </button>
                        </form>
                    @else
                        <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Decision</h3>
                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                            @if ($profile->verification_status === VerificationStatus::Approved)
                                Approved {{ $profile->verified_at?->diffForHumans() }}.
                            @else
                                Rejected. Reviewer notes:
                            @endif
                        </p>
                        @if ($profile->verification_notes)
                            <div class="mt-3 rounded-xl border border-rose-200/80 bg-rose-50 p-3 text-sm text-rose-800 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300">
                                {{ $profile->verification_notes }}
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
