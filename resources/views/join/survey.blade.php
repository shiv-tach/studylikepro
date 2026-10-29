@php
    // One survey question per screen. The server has narrowed the options to the
    // answer given before (the subject list only holds the chosen level), so this
    // view draws the question it was handed and nothing else.
    $cardClasses = 'survey-step rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800/80 dark:bg-slate-900 sm:p-6';
    $primaryButton = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-primary px-6 py-2.5 text-sm font-bold text-white shadow-md shadow-primary/20 transition-colors hover:bg-primary/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 disabled:cursor-not-allowed disabled:opacity-60';
    $ghostButton = 'inline-flex min-h-11 items-center rounded-xl px-3 text-sm font-semibold text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100';
    $isProof = $step === \App\Support\TeacherSurvey::STEP_PROOF;
    // A rejected save that lost its days has to show the day grid, even when a
    // suggested week is still selected, so the teacher can see what to fix.
    $daysMissing = $errors->has('days') || $errors->has('days.*');
    $formId = 'join-question-form';
    $submitLabel = $isProof ? __('Continue to your account') : __('Continue');
    $answeredCount = (int) $answered;
    $questionTotal = (int) $total;
    $percent = $questionTotal > 0 ? (int) round(($answeredCount / $questionTotal) * 100) : 0;
@endphp

<x-onboarding-layout :guest="$guest">
    <x-slot name="progress">
        <x-onboarding-progress :current="$currentGroup" :steps="$steps"
                               :answered="$answeredCount" :questions="$questionTotal" :question="$index" />
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start">
        <div class="space-y-5">
            {{-- The question itself. It rises in on a fresh load; a browser that
                 supports view transitions morphs the card below instead. --}}
            <div class="survey-enter">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wider text-primary">
                        {{ __('Question :number of :total', ['number' => $index, 'total' => $total]) }}
                    </span>

                    @if ($answeredCount > 0)
                        <span class="text-[11px] font-semibold text-slate-400">{{ $percent }}% {{ __('answered') }}</span>
                    @endif
                </div>

                <h1 class="mt-2 text-2xl font-bold leading-tight text-slate-900 dark:text-slate-100 sm:text-3xl">
                    {{ $title }}
                </h1>
                <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                    {{ $body }}
                </p>
            </div>

            @if ($errors->any())
                <div class="survey-enter flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300" role="alert">
                    <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-rose-500 text-white" aria-hidden="true">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v4m0 4h.01" />
                        </svg>
                    </span>

                    <div class="min-w-0">
                        <p class="font-semibold">{{ __('Please check your answer:') }}</p>
                        <ul class="mt-1 list-inside list-disc space-y-0.5">
                            @foreach ($errors->all() as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ $submitUrl }}"
                  id="{{ $formId }}"
                  @if ($isProof) enctype="multipart/form-data" @endif
                  x-data="submitOnce"
                  x-on:submit="guard($event)"
                  class="{{ $cardClasses }} space-y-5">
                @csrf
                @if (! $isProof)
                    @method('PUT')
                @endif

                @switch($step)
                    @case(\App\Support\TeacherSurvey::STEP_LEVEL)
                        <fieldset>
                            <legend class="sr-only">{{ $title }}</legend>

                            <div class="grid gap-3 sm:grid-cols-3">
                                @foreach ($levels as $option)
                                    <x-survey.option-card
                                        name="level"
                                        :value="$option['value']"
                                        :checked="$values['level'] === $option['value']"
                                        :icon="$option['icon']"
                                        :title="$option['label']"
                                        :hint="$option['hint']"
                                        required />
                                @endforeach
                            </div>

                            <x-input-error :messages="$errors->get('level')" class="mt-2" />
                        </fieldset>
                        @break

                    @case(\App\Support\TeacherSurvey::STEP_SUBJECTS)
                        <fieldset>
                            <legend class="sr-only">{{ $title }}</legend>

                            <div class="space-y-5">
                                @foreach ($subjectGroups as $group)
                                    <div>
                                        <p class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                            <span aria-hidden="true">{{ $group['icon'] }}</span>
                                            {{ $group['name'] }}
                                        </p>

                                        <div class="mt-2 grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                                            @foreach ($group['subjects'] as $subject)
                                                <x-survey.option-card
                                                    type="checkbox"
                                                    name="subjects[]"
                                                    :value="$subject['id']"
                                                    :checked="in_array($subject['id'], $values['subjects'], true)"
                                                    :icon="$subject['icon']"
                                                    :title="$subject['name']" />
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <x-input-error :messages="$errors->get('subjects')" class="mt-2" />
                            @foreach ($errors->get('subjects.*') as $messages)
                                <x-input-error :messages="$messages" class="mt-1" />
                            @endforeach
                        </fieldset>
                        @break

                    @case(\App\Support\TeacherSurvey::STEP_LANGUAGES)
                        <fieldset>
                            <legend class="sr-only">{{ $title }}</legend>

                            <div class="grid gap-3 sm:grid-cols-3">
                                @foreach ($languages as $language)
                                    <x-survey.option-card
                                        type="checkbox"
                                        name="languages[]"
                                        :value="$language"
                                        :checked="in_array($language, $values['languages'], true)"
                                        :title="$language"
                                        align="center" />
                                @endforeach
                            </div>

                            <x-input-error :messages="$errors->get('languages')" class="mt-2" />
                        </fieldset>
                        @break

                    @case(\App\Support\TeacherSurvey::STEP_SERVICES)
                        <fieldset>
                            <legend class="sr-only">{{ $title }}</legend>

                            <div class="grid gap-3 sm:grid-cols-2">
                                @foreach ($services as $service)
                                    <x-survey.option-card
                                        type="checkbox"
                                        name="services[]"
                                        :value="$service['value']"
                                        :checked="in_array($service['value'], $values['services'], true)"
                                        :icon="$service['icon']"
                                        :title="$service['label']"
                                        :hint="$service['hint']" />
                                @endforeach
                            </div>

                            <x-input-error :messages="$errors->get('services')" class="mt-2" />
                        </fieldset>
                        @break

                    @case(\App\Support\TeacherSurvey::STEP_EXPERIENCE)
                        <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-800 dark:bg-slate-800/40">
                            <label for="experience_years" class="block text-sm font-semibold text-slate-700 dark:text-slate-300">
                                {{ __('Years of experience') }}
                            </label>
                            <div class="mt-2 flex items-center gap-2">
                                <input id="experience_years" name="experience_years" type="number" inputmode="numeric"
                                       min="0" max="60" step="1" required
                                       value="{{ $values['experience_years'] }}"
                                       class="block w-full rounded-xl border-slate-300 bg-white text-lg font-bold text-slate-900 shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 sm:text-base">
                                <span class="shrink-0 text-sm font-semibold text-slate-500 dark:text-slate-400">{{ __('years') }}</span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                {{ __('Whole years. Enter 0 if you have not taught yet.') }}
                            </p>
                            <x-input-error :messages="$errors->get('experience_years')" class="mt-2" />
                        </div>
                        @break

                    @case(\App\Support\TeacherSurvey::STEP_EDUCATION)
                        <fieldset>
                            <legend class="sr-only">{{ $title }}</legend>

                            <div class="grid gap-3 sm:grid-cols-2">
                                @foreach ($educationOptions as $option)
                                    <x-survey.option-card
                                        name="education"
                                        :value="$option['value']"
                                        :checked="$values['education'] === $option['value']"
                                        :icon="$option['icon']"
                                        :title="$option['label']"
                                        :hint="$option['hint']"
                                        required />
                                @endforeach
                            </div>

                            <x-input-error :messages="$errors->get('education')" class="mt-2" />
                        </fieldset>
                        @break

                    @case(\App\Support\TeacherSurvey::STEP_RATES)
                        <div class="space-y-4">
                            <div class="rounded-xl border border-primary/25 bg-primary/5 p-4 dark:border-primary/30 dark:bg-primary/10">
                                <label for="hourly_rate" class="block text-sm font-semibold text-slate-800 dark:text-slate-100">
                                    {{ __('Hourly fee for a lesson (rupees)') }}
                                </label>
                                <div class="mt-2 flex items-center gap-2">
                                    <span class="shrink-0 text-sm font-semibold text-slate-500 dark:text-slate-400">{{ config('studylikepro.currency_symbol') }}</span>
                                    <input id="hourly_rate" name="hourly_rate" type="number" inputmode="numeric"
                                           min="100" max="100000" step="1" required
                                           value="{{ $values['hourly_rate'] }}"
                                           class="block w-full rounded-xl border-slate-300 bg-white text-lg font-bold text-slate-900 shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 sm:text-base">
                                    <span class="shrink-0 text-sm font-semibold text-slate-500 dark:text-slate-400">/ {{ __('hour') }}</span>
                                </div>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    {{ __('What an hour of one-to-one teaching costs.') }}
                                </p>
                                <x-input-error :messages="$errors->get('hourly_rate')" class="mt-2" />
                            </div>

                            @foreach ($pricedServices as $service)
                                <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-800 dark:bg-slate-800/40">
                                    <label for="service_rates_{{ $service['value'] }}" class="flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-300">
                                        <span aria-hidden="true">{{ $service['icon'] }}</span>
                                        {{ __('Price for :service (rupees)', ['service' => $service['label']]) }}
                                    </label>
                                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $service['hint'] }}</p>
                                    <div class="mt-2 flex items-center gap-2">
                                        <span class="shrink-0 text-sm font-semibold text-slate-500 dark:text-slate-400">{{ config('studylikepro.currency_symbol') }}</span>
                                        <input id="service_rates_{{ $service['value'] }}"
                                               name="service_rates[{{ $service['value'] }}]" type="number" inputmode="numeric"
                                               min="100" max="100000" step="1" required
                                               value="{{ $values['service_rates'][$service['value']] ?? '' }}"
                                               class="block w-full rounded-xl border-slate-300 bg-white text-base font-semibold text-slate-900 shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                                    </div>
                                    <x-input-error :messages="$errors->get('service_rates.'.$service['value'])" class="mt-2" />
                                </div>
                            @endforeach

                            @if ($pricedServices === [])
                                <p class="rounded-xl bg-slate-50 p-3 text-xs text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                                    {{ __('You picked lessons only, so the hourly fee covers everything for now.') }}
                                </p>
                            @endif
                        </div>
                        @break

                    @case(\App\Support\TeacherSurvey::STEP_AVAILABILITY)
                        <fieldset x-data="{
                                    preset: @js($values['preset'] ?? ''),
                                    days: @js(array_map('strval', $values['days'])),
                                    presets: @js($presetDays),
                                    applyPresetDays(event) {
                                        this.preset = event.target.value;

                                        if (this.preset === '') {
                                            return;
                                        }

                                        this.days = (this.presets[this.preset] ?? []).map(String);
                                    },
                                }">
                            <legend class="sr-only">{{ $title }}</legend>

                            <p class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                {{ __('Suggested weeks') }}
                            </p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                {{ __('The weeks we suggest when you set your hours. Pick one to use its days and times, or pick My own days to choose the days yourself.') }}
                            </p>

                            <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                @foreach ($availabilityPresets as $preset)
                                    <x-survey.option-card
                                        name="preset"
                                        :value="$preset['key']"
                                        :checked="$values['preset'] === $preset['key']"
                                        :title="$preset['label']"
                                        :hint="$preset['hint']"
                                        :badge="$preset['recommended'] ? __('Recommended') : null"
                                        x-model="preset"
                                        x-on:change="applyPresetDays($event)">
                                        <span class="mt-2 block space-y-0.5 text-xs font-semibold text-slate-700 dark:text-slate-300">
                                            @foreach ($preset['windows'] as $window)
                                                <span class="block">{{ $window['days'] }} · {{ $window['time'] }}</span>
                                            @endforeach
                                        </span>
                                    </x-survey.option-card>
                                @endforeach

                                <x-survey.option-card
                                    name="preset"
                                    value=""
                                    :checked="$values['preset'] === null"
                                    :title="__('My own days')"
                                    :hint="__('Tick the days yourself. Each one starts at the recommended hours.')"
                                    x-model="preset" />
                            </div>

                            <x-input-error :messages="$errors->get('preset')" class="mt-2" />

                            {{-- A suggested week already fixes the days, so the day
                                 grid only appears for a teacher who picks their own. --}}
                            <div x-show="preset === '' || {{ $daysMissing ? 'true' : 'false' }}" x-cloak class="mt-5">
                                <p class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    {{ __('Days you can teach') }}
                                </p>

                                <div class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                    @foreach ($days as $day)
                                        <x-survey.option-card
                                            type="checkbox"
                                            name="days[]"
                                            :value="$day['value']"
                                            :checked="in_array($day['value'], $values['days'], true)"
                                            :title="$day['label']"
                                            :meta="$dayTimes[$day['value']] ?? null"
                                            align="center"
                                            x-model="days" />
                                    @endforeach
                                </div>
                            </div>

                            <x-input-error :messages="$errors->get('days')" class="mt-2" />
                            @foreach ($errors->get('days.*') as $messages)
                                <x-input-error :messages="$messages" class="mt-1" />
                            @endforeach

                            <p class="mt-4 rounded-xl bg-slate-50 p-3 text-xs leading-relaxed text-slate-600 dark:bg-slate-800/60 dark:text-slate-300">
                                {{ __('You can change your availability any time after you register.') }}
                            </p>
                        </fieldset>
                        @break

                    @default
                        <div x-data="{ claims: '{{ $values['claims'] === '1' ? '1' : '0' }}' }" class="space-y-5">
                            <fieldset>
                                <legend class="sr-only">{{ $title }}</legend>

                                <div class="grid gap-3 sm:grid-cols-2">
                                    <x-survey.option-card
                                        name="claims"
                                        value="1"
                                        :checked="$values['claims'] === '1'"
                                        :title="__('Yes, I am a government teacher')"
                                        :hint="__('Upload proof and we will confirm it. Verified government teachers get a badge on their profile card.')"
                                        required
                                        x-model="claims" />

                                    <x-survey.option-card
                                        name="claims"
                                        value="0"
                                        :checked="$values['claims'] === '0'"
                                        :title="__('No, I teach privately')"
                                        :hint="__('You can add proof later from your verification page.')"
                                        required
                                        x-model="claims" />
                                </div>

                                <x-input-error :messages="$errors->get('claims')" class="mt-2" />
                            </fieldset>

                            <div x-show="claims === '1'" x-cloak class="rounded-xl border border-dashed border-slate-300 p-4 dark:border-slate-700">
                                <label for="proof" class="block text-sm font-semibold text-slate-700 dark:text-slate-300">
                                    {{ __('Proof document') }}
                                </label>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    {{ __('PDF, JPG, PNG or WebP — up to 5 MB. A school letter, appointment letter or staff ID works.') }}
                                </p>

                                @if ($proofName)
                                    <p class="mt-2 break-words text-xs font-semibold text-emerald-700 dark:text-emerald-400">
                                        {{ __('Uploaded: :name — attach a different file only if you want to replace it.', ['name' => $proofName]) }}
                                    </p>
                                @endif

                                <input id="proof" name="document" type="file"
                                       accept="application/pdf,image/jpeg,image/png,image/webp"
                                       class="mt-3 block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-primary/10 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-primary dark:text-slate-300">

                                <x-input-error :messages="$errors->get('document')" class="mt-2" />
                            </div>

                            <noscript>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ __('Choose “Yes” to attach a proof document, or “No” to skip this question.') }}
                                </p>
                            </noscript>
                        </div>
                @endswitch

                <div class="hidden items-center justify-between gap-3 border-t border-slate-200/80 pt-5 dark:border-slate-800/80 lg:flex">
                    @if ($backUrl)
                        <a href="{{ $backUrl }}" class="{{ $ghostButton }}" x-show="! busy">
                            {{ __('Back') }}
                        </a>
                    @endif

                    <button type="submit" class="{{ $primaryButton }} ms-auto" :disabled="busy">
                        <span class="inline-flex items-center gap-2" x-show="! busy">
                            {{ $submitLabel }}
                            <span aria-hidden="true">→</span>
                        </span>

                        <span class="inline-flex items-center gap-2" x-show="busy" x-cloak>
                            <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-90" fill="currentColor" d="M21 12a9 9 0 0 1-9 9v-4a5 5 0 0 0 5-5h4z" />
                            </svg>
                            {{ __('Saving…') }}
                        </span>
                    </button>
                </div>
            </form>
        </div>

        <x-survey.summary :groups="$summary" :answered="$answeredCount" :questions="$questionTotal" />
    </div>

    <x-survey.mobile-actions :form="$formId" :back-url="$backUrl" :submit-label="$submitLabel" />
</x-onboarding-layout>
