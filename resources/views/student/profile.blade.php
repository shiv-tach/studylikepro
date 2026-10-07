@php
    $user = Auth::user();
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $selectClasses = 'mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900';
    $textareaClasses = 'mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900';
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">
            {{ $isOnboarding ? __('Complete your profile') : __('My Learning Profile') }}
        </h2>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-6">
        @if (session('status') === 'complete-your-profile')
            <div class="rounded-2xl border border-amber-200/80 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300">
                Finish setting up your profile to unlock your dashboard and the rest of Studylikepro.
            </div>
        @elseif (session('status') === 'profile-updated')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                Your profile has been updated.
            </div>
        @endif

        @if ($isOnboarding)
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <div class="flex items-start gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-xl">👋</span>
                    <div>
                        <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">Welcome to Studylikepro!</h3>
                        <p class="mt-1 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                            Tell us a little about yourself so we can match the right tutors and lesson times. You can update this anytime from your profile.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('student.profile.update') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method('PUT')

            <!-- About you -->
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">About you</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">This helps teachers understand how to help. All lesson times are shown in Sri Lanka time.</p>

                <div class="mt-5">
                    <x-input-label for="grade_id" :value="__('Grade')" />
                    <select id="grade_id" name="grade_id" class="{{ $selectClasses }}">
                        <option value="">{{ __('Select your grade') }}</option>
                        @foreach ($levels as $level)
                            <optgroup label="{{ $level->name }}">
                                @foreach ($level->grades as $grade)
                                    <option value="{{ $grade->id }}" @selected((int) old('grade_id', $profile?->grade_id) === $grade->id)>{{ $grade->label }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('grade_id')" class="mt-2" />
                </div>

                <div class="mt-5">
                    <x-input-label for="learning_goals" :value="__('Learning goals (optional)')" />
                    <textarea id="learning_goals" name="learning_goals" rows="3" class="{{ $textareaClasses }}" placeholder="e.g. Prepare for board exams, strengthen calculus basics...">{{ old('learning_goals', $profile?->learning_goals) }}</textarea>
                    <x-input-error :messages="$errors->get('learning_goals')" class="mt-2" />
                </div>
            </div>

            <!-- Guardian -->
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Parent / guardian <span class="font-medium text-slate-400">(optional)</span></h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Add these if a parent or guardian books lessons on your behalf.</p>

                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div>
                        <x-input-label for="guardian_name" :value="__('Guardian name')" />
                        <x-text-input id="guardian_name" name="guardian_name" type="text" class="mt-1 block w-full" :value="old('guardian_name', $profile?->guardian_name)" />
                        <x-input-error :messages="$errors->get('guardian_name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="guardian_phone" :value="__('Guardian phone')" />
                        <x-text-input id="guardian_phone" name="guardian_phone" type="tel" class="mt-1 block w-full" :value="old('guardian_phone', $profile?->guardian_phone)" />
                        <x-input-error :messages="$errors->get('guardian_phone')" class="mt-2" />
                    </div>
                </div>
            </div>

            <!-- Photo -->
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Photo</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">A friendly photo helps teachers recognise you. JPG, PNG or WebP up to 2 MB.</p>

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
                    {{ $isOnboarding ? __('Complete profile & continue') : __('Save changes') }}
                </x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
