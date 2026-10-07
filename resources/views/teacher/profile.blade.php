@php
    $user = Auth::user();
    $profile = $profile ?? null;
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $textareaClasses = 'mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900';
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">
                {{ $profile?->isComplete() ? __('Teaching Profile') : __('Set up your teaching profile') }}
            </h2>
            @if ($profile)
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $profile->verification_status->badgeClasses() }}">
                    {{ $profile->verification_status->label() }}
                </span>
            @endif
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-6">
        @if (session('status') === 'complete-your-profile')
            <div class="rounded-2xl border border-amber-200/80 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300">
                Finish your teaching profile to unlock your dashboard and continue to verification.
            </div>
        @elseif (session('status') === 'profile-updated')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                Your teaching profile has been updated.
            </div>
        @endif

        <x-teacher-onboarding-steps :current="1" />

        @unless ($profile?->isComplete())
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <div class="flex items-start gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-xl">👨‍🏫</span>
                    <div>
                        <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">Welcome, teacher!</h3>
                        <p class="mt-1 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                            Step 1 of 2 — tell students about yourself and set your rate. Next you will upload verification documents for review.
                        </p>
                    </div>
                </div>
            </div>
        @endunless

        @if ($profile?->isComplete() && ! $profile->hasSubmittedVerification())
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-xl">🪪</span>
                        <div>
                            <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">Step 1 complete — next: verification</h3>
                            <p class="mt-1 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                                Upload your documents and submit them for review to unlock your dashboard.
                            </p>
                        </div>
                    </div>
                    <a href="{{ route('teacher.verification') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2.5 text-sm font-bold text-white shadow-md shadow-primary/20 transition-all duration-200 hover:bg-primary/90 hover:shadow-lg active:scale-[0.98]">
                        {{ __('Continue to verification') }}
                        <span aria-hidden="true">→</span>
                    </a>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('teacher.profile.update') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method('PUT')

            <!-- Professional details -->
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Professional details</h3>

                <div class="mt-5">
                    <x-input-label for="headline" :value="__('Headline')" />
                    <x-text-input id="headline" name="headline" type="text" class="mt-1 block w-full" :value="old('headline', $profile?->headline)" placeholder="e.g. Physics tutor for high school students" />
                    <x-input-error :messages="$errors->get('headline')" class="mt-2" />
                </div>

                <div class="mt-5">
                    <x-input-label for="bio" :value="__('About you')" />
                    <textarea id="bio" name="bio" rows="4" class="{{ $textareaClasses }}" placeholder="Describe your teaching style, strengths, and the students you work best with.">{{ old('bio', $profile?->bio) }}</textarea>
                    <x-input-error :messages="$errors->get('bio')" class="mt-2" />
                </div>

                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div>
                        <x-input-label for="experience_years" :value="__('Years of experience')" />
                        <x-text-input id="experience_years" name="experience_years" type="number" min="0" max="60" class="mt-1 block w-full" :value="old('experience_years', $profile?->experience_years)" />
                        <x-input-error :messages="$errors->get('experience_years')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="education" :value="__('Education')" />
                        <x-text-input id="education" name="education" type="text" class="mt-1 block w-full" :value="old('education', $profile?->education)" placeholder="e.g. M.Sc. Mathematics, IIT Delhi" />
                        <x-input-error :messages="$errors->get('education')" class="mt-2" />
                    </div>
                </div>
            </div>

            <!-- Teaching preferences -->
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Teaching preferences</h3>

                <div class="mt-5">
                    <x-input-label :value="__('Languages you teach in')" />
                    <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                        @foreach (config('studylikepro.languages') as $language)
                            <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200/80 px-3 py-2 text-sm text-slate-700 transition-colors hover:border-primary/40 dark:border-slate-800 dark:text-slate-300">
                                <input type="checkbox" name="languages[]" value="{{ $language }}"
                                       class="rounded border-slate-300 text-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900"
                                       @checked(in_array($language, old('languages', $profile?->languages ?? ['English']), true)) />
                                {{ $language }}
                            </label>
                        @endforeach
                    </div>
                    <x-input-error :messages="$errors->get('languages')" class="mt-2" />
                </div>

                <div class="mt-5">
                    <x-input-label for="hourly_rate" :value="__('Hourly rate')" />
                    <div class="relative mt-1">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-slate-400">{{ platform_settings()->currencySymbol() }}</span>
                        <input id="hourly_rate" name="hourly_rate" type="number" min="100" max="100000" step="1"
                               class="block w-full rounded-xl border-slate-300 bg-white pl-8 text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900"
                               value="{{ old('hourly_rate', $profile ? (int) $profile->hourlyRate() : '') }}" />
                    </div>
                    <p class="mt-1 text-xs text-slate-400">Students see this per 60-minute lesson before commission.</p>
                    <x-input-error :messages="$errors->get('hourly_rate')" class="mt-2" />
                </div>
            </div>

            <!-- Photo -->
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Profile photo</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">A clear, friendly photo builds trust with students. JPG, PNG or WebP up to 2 MB.</p>

                <div class="mt-5 flex items-center gap-4">
                    @if ($user->avatarUrl())
                        <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}" class="h-16 w-16 rounded-2xl object-cover shadow-md" />
                    @else
                        <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-primary text-xl font-bold text-white shadow-md shadow-primary/20">
                            {{ substr($user->name, 0, 2) }}
                        </div>
                    @endif

                    <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp"
                           class="block w-full text-sm text-slate-500 file:mr-4 file:rounded-xl file:border-0 file:bg-primary/10 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-primary hover:file:bg-primary/20 dark:text-slate-400" />
                </div>
                <x-input-error :messages="$errors->get('avatar')" class="mt-2" />
            </div>

            <div class="flex items-center justify-end">
                <x-primary-button>
                    {{ $profile?->isComplete() ? __('Save changes') : __('Save profile & continue to verification') }}
                </x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
