@php
    // Wizard step 2 ("About you"). The island is built by
    // App\Support\TeacherProfileConfig, so this step and the workspace profile
    // share every field, limit and validation message.
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;

    $cardClasses = 'rounded-2xl border border-slate-200/80 bg-white p-5 dark:border-slate-800/80 dark:bg-slate-900 sm:p-6';
@endphp
<x-onboarding-layout :guest="$guest ?? false">
    <x-slot name="progress">
        <x-onboarding-progress :current="$currentStep ?? 2" :steps="$config['steps']" />
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

            <form method="POST" action="{{ $config['urls']['update'] }}" class="space-y-5">
                @csrf
                @method('PUT')

                <div class="{{ $cardClasses }}">
                    <h1 class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ __('Tell students about you') }}</h1>

                    <div class="mt-4">
                        <x-input-label for="headline" :value="__('Headline')" />
                        <x-text-input id="headline" name="headline" type="text" maxlength="120" required class="mt-1 block w-full" :value="$config['values']['headline']" />
                        <x-input-error :messages="$errors->get('headline')" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="bio" :value="__('About you')" />
                        <textarea id="bio" name="bio" rows="4" maxlength="2000" class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-base shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 sm:text-sm">{{ $config['values']['bio'] }}</textarea>
                        <x-input-error :messages="$errors->get('bio')" class="mt-2" />
                    </div>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="experience_years" :value="__('Years of experience')" />
                            <x-text-input id="experience_years" name="experience_years" type="number" inputmode="numeric" min="0" max="60" required class="mt-1 block w-full" :value="$config['values']['experienceYears']" />
                            <x-input-error :messages="$errors->get('experience_years')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="education" :value="__('Education')" />
                            <x-text-input id="education" name="education" type="text" maxlength="255" required class="mt-1 block w-full" :value="$config['values']['education']" />
                            <x-input-error :messages="$errors->get('education')" class="mt-2" />
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-3">
                    <a href="{{ $config['urls']['back'] ?? route('teacher.onboarding.show') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-100">
                        {{ __('Back') }}
                    </a>
                    <x-primary-button>{{ __('Save & continue') }}</x-primary-button>
                </div>
            </form>
        </div>
    </noscript>

    @vite('resources/js/teacher-profile/main.js')
</x-onboarding-layout>
