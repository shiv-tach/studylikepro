@php
    // Wizard step 3 ("Teaching preferences"). Same island builder as the About
    // step; `stepKey` tells the Vue app which cards and which draft to use.
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;

    $cardClasses = 'rounded-2xl border border-slate-200/80 bg-white p-5 dark:border-slate-800/80 dark:bg-slate-900 sm:p-6';
    $languages = config('studylikepro.languages');
@endphp
<x-onboarding-layout>
    <x-slot name="progress">
        <x-onboarding-progress :current="3" />
    </x-slot>

    <script type="application/json" id="teacher-profile-config">@json($config, $jsonFlags)</script>

    <div id="teacher-profile-app"></div>

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

            <form method="POST" action="{{ route('teacher.onboarding.preferences.update') }}" enctype="multipart/form-data" class="space-y-5">
                @csrf
                @method('PUT')

                <div class="{{ $cardClasses }}">
                    <h1 class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ __('How you teach and what you charge') }}</h1>

                    <div class="mt-4">
                        <x-input-label :value="__('Languages you teach in')" />
                        <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                            @foreach ($languages as $language)
                                <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200/80 px-3 py-2 text-sm text-slate-700 dark:border-slate-800 dark:text-slate-300">
                                    <input type="checkbox" name="languages[]" value="{{ $language }}"
                                           class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900"
                                           @checked(in_array($language, old('languages', $profile?->languages ?? ['English']), true)) />
                                    {{ $language }}
                                </label>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('languages')" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="hourly_rate" :value="__('Hourly rate')" />
                        <div class="relative mt-1">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-slate-400">
                                {{ platform_settings()->currencySymbol() }}
                            </span>
                            <input id="hourly_rate" name="hourly_rate" type="number" inputmode="numeric" min="100" max="100000" step="1" required
                                   class="block w-full rounded-xl border-slate-300 bg-white pl-8 text-base shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 sm:text-sm"
                                   value="{{ old('hourly_rate', $profile ? (int) $profile->hourlyRate() : '') }}" />
                        </div>
                        <x-input-error :messages="$errors->get('hourly_rate')" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="avatar" :value="__('Profile photo')" />
                        <input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp"
                               class="mt-1 block w-full text-sm text-slate-500 file:mr-4 file:rounded-xl file:border-0 file:bg-primary/10 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-primary hover:file:bg-primary/20 dark:text-slate-400" />
                        <x-input-error :messages="$errors->get('avatar')" class="mt-2" />
                    </div>
                </div>

                <div class="flex items-center justify-between gap-3">
                    <a href="{{ route('teacher.onboarding.profile') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-100">
                        {{ __('Back') }}
                    </a>
                    <x-primary-button>{{ __('Save & continue') }}</x-primary-button>
                </div>
            </form>
        </div>
    </noscript>

    @vite('resources/js/teacher-profile/main.js')
</x-onboarding-layout>
