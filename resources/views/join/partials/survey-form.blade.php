@php
    // The no-JavaScript twin of one survey question: the same fields, the same
    // names and the same URL, drawn by Blade. Only rendered inside <noscript>.
    $c = $config;
    $step = $c['step'];
    $values = $c['values'];
    $copy = $c['copy'];
    $qc = $c['questionCopy'];
    $options = $c['options'];

    $cardClasses = 'survey-step rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800/80 dark:bg-slate-900 sm:p-6';
    $numberField = 'block w-full rounded-xl border-slate-300 bg-white text-lg font-bold text-slate-900 shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 sm:text-base';

    $formId = 'join-question-form';
    $submitLabel = $c['isProof'] ? $copy['continueToAccount'] : $copy['continue'];
    $percent = $c['percent'];

    // A rejected save that lost its days still needs the day grid visible here.
    $daysMissing = $errors->has('days') || $errors->has('days.*');
@endphp

<x-onboarding-progress :current="$c['currentGroup']" :steps="$c['rail']"
                       :answered="$c['answered']" :questions="$c['total']" :rail-units="$c['railUnits']"
                       :question="$c['index']" />

<div class="mt-5 grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start">
    <div class="space-y-5">
        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wider text-primary">
                {{ __('Question :number of :total', ['number' => $c['index'], 'total' => $c['total']]) }}
            </span>

            @if ($c['answered'] > 0)
                <span class="text-[11px] font-semibold text-slate-400">{{ $percent }}% {{ $copy['answered'] }}</span>
            @endif
        </div>

        <h1 class="text-2xl font-bold leading-tight text-slate-900 dark:text-slate-100 sm:text-3xl">{{ $c['title'] }}</h1>
        <p class="max-w-2xl text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ $c['body'] }}</p>

        @if ($errors->any())
            <div class="flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300" role="alert">
                <div class="min-w-0">
                    <p class="font-semibold">{{ $copy['errorTitle'] }}</p>
                    <ul class="mt-1 list-inside list-disc space-y-0.5">
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ $c['urls']['submit'] }}" id="{{ $formId }}"
              @if ($c['isProof']) enctype="multipart/form-data" @endif
              class="{{ $cardClasses }} space-y-5">
            @csrf
            @if (! $c['isProof'])
                @method('PUT')
            @endif

            @switch($step)
                @case(\App\Support\TeacherSurvey::STEP_LEVEL)
                    <fieldset>
                        <legend class="sr-only">{{ $c['title'] }}</legend>

                        <div class="grid gap-3 sm:grid-cols-3">
                            @foreach ($options['levels'] as $option)
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
                        <legend class="sr-only">{{ $c['title'] }}</legend>

                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $qc['subjectNoscriptHint'] }}</p>

                        <div class="mt-3 space-y-5">
                            @foreach ($options['subjectGroups'] as $group)
                                <div>
                                    <p class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                        <span aria-hidden="true">{{ $group['icon'] }}</span>
                                        {{ $group['shortName'] ?? $group['name'] }}
                                        <span class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold tracking-normal text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                            {{ count($group['subjects']) }}
                                        </span>
                                    </p>

                                    <div class="mt-2 grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                                        @foreach ($group['subjects'] as $subject)
                                            <x-survey.option-card
                                                type="checkbox"
                                                name="subjects[]"
                                                :value="$subject['id']"
                                                :checked="in_array($subject['id'], $values['subjects'], true)"
                                                :icon="$subject['icon']"
                                                :title="$subject['name']"
                                                :badge="count($options['subjectGroups']) > 1 ? $subject['levelName'] : null" />
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
                        <legend class="sr-only">{{ $c['title'] }}</legend>

                        <div class="grid gap-3 sm:grid-cols-3">
                            @foreach ($options['languages'] as $language)
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
                    <fieldset class="space-y-4">
                        <legend class="sr-only">{{ $c['title'] }}</legend>

                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $qc['serviceNoscriptHint'] }}</p>

                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ($options['services'] as $service)
                                @php
                                    $checked = in_array($service['value'], $values['services'], true);
                                    $priceName = $service['priced'] ? 'service_rates['.$service['value'].']' : 'hourly_rate';
                                    $priceValue = $service['priced']
                                        ? ($values['service_rates'][$service['value']] ?? '')
                                        : $values['hourly_rate'];
                                @endphp

                                <div class="rounded-2xl border-2 border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                                    <label class="flex items-start gap-3">
                                        <input type="checkbox" name="services[]" value="{{ $service['value'] }}"
                                               @checked($checked)
                                               class="mt-1 h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary">

                                        <span class="min-w-0 flex-1">
                                            <span class="flex flex-wrap items-center gap-2">
                                                <span class="text-base font-bold text-slate-900 dark:text-slate-100">{{ $service['label'] }}</span>
                                                <span class="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-primary">{{ $service['badge'] }}</span>
                                            </span>
                                            <span class="mt-1 block text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ $service['hint'] }}</span>
                                        </span>
                                    </label>

                                    <div class="mt-3 border-t border-slate-200/80 pt-3 dark:border-slate-800/80">
                                        <label for="price_{{ $service['value'] }}" class="block text-xs font-semibold text-slate-600 dark:text-slate-300">
                                            {{ $service['priceLabel'] }}
                                        </label>

                                        <div class="mt-1.5 flex items-center gap-2">
                                            <span class="shrink-0 text-sm font-semibold text-slate-500 dark:text-slate-400">{{ $c['currency']['symbol'] }}</span>
                                            <input id="price_{{ $service['value'] }}" name="{{ $priceName }}" type="number" inputmode="numeric"
                                                   min="{{ $c['limits']['rateMin'] }}" max="{{ $c['limits']['rateMax'] }}" step="1"
                                                   value="{{ $priceValue }}"
                                                   class="{{ $numberField }}">
                                            @if ($service['unit'] === 'hour')
                                                <span class="shrink-0 text-sm font-semibold text-slate-500 dark:text-slate-400">/ {{ $qc['hourUnit'] }}</span>
                                            @endif
                                        </div>

                                        <x-input-error :messages="$errors->get($priceName)" class="mt-2" />
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <x-input-error :messages="$errors->get('services')" class="mt-2" />
                    </fieldset>
                    @break

                @case(\App\Support\TeacherSurvey::STEP_EXPERIENCE)
                    <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-800 dark:bg-slate-800/40">
                        <label for="experience_years" class="block text-sm font-semibold text-slate-700 dark:text-slate-300">
                            {{ $qc['years'] }}
                        </label>
                        <div class="mt-2 flex items-center gap-2">
                            <input id="experience_years" name="experience_years" type="number" inputmode="numeric"
                                   min="{{ $c['limits']['experienceMin'] }}" max="{{ $c['limits']['experienceMax'] }}" step="1" required
                                   value="{{ $values['experience_years'] }}"
                                   class="{{ $numberField }}">
                            <span class="shrink-0 text-sm font-semibold text-slate-500 dark:text-slate-400">{{ $qc['yearsUnit'] }}</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $qc['yearsHint'] }}</p>
                        <x-input-error :messages="$errors->get('experience_years')" class="mt-2" />
                    </div>
                    @break

                @case(\App\Support\TeacherSurvey::STEP_EDUCATION)
                    <fieldset>
                        <legend class="sr-only">{{ $c['title'] }}</legend>

                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ($options['educationOptions'] as $option)
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

                @case(\App\Support\TeacherSurvey::STEP_AVAILABILITY)
                    <fieldset>
                        <legend class="sr-only">{{ $c['title'] }}</legend>

                        <p class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $qc['presetsTitle'] }}</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $qc['presetsHint'] }}</p>

                        <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            @foreach ($options['presets'] as $preset)
                                <x-survey.option-card
                                    name="preset"
                                    :value="$preset['key']"
                                    :checked="$values['preset'] === $preset['key']"
                                    :title="$preset['label']"
                                    :hint="$preset['hint']"
                                    :badge="$preset['recommended'] ? $qc['recommended'] : null">
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
                                :title="$qc['ownDaysTitle']"
                                :hint="$qc['ownDaysHint']" />
                        </div>

                        <x-input-error :messages="$errors->get('preset')" class="mt-2" />

                        {{-- Without JavaScript every day is on screen; a suggested
                             week can still be picked above. A preset that is only
                             this screen's default leaves the grid on screen, so
                             the days can still be changed by hand. --}}
                        <div class="mt-5" @if (! $daysMissing && $values['preset'] !== null && ! $c['presetDefaulted']) hidden @endif>
                            <p class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $qc['daysTitle'] }}</p>

                            <div class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                @foreach ($options['days'] as $day)
                                    <x-survey.option-card
                                        type="checkbox"
                                        name="days[]"
                                        :value="$day['value']"
                                        :checked="in_array($day['value'], $values['days'], true)"
                                        :title="$day['label']"
                                        :meta="$options['dayTimes'][$day['value']] ?? null"
                                        align="center" />
                                @endforeach
                            </div>
                        </div>

                        <x-input-error :messages="$errors->get('days')" class="mt-2" />
                        @foreach ($errors->get('days.*') as $messages)
                            <x-input-error :messages="$messages" class="mt-1" />
                        @endforeach

                        <p class="mt-4 rounded-xl bg-slate-50 p-3 text-xs leading-relaxed text-slate-600 dark:bg-slate-800/60 dark:text-slate-300">
                            {{ $qc['availabilityNote'] }}
                        </p>
                    </fieldset>
                    @break

                @default
                    <div class="space-y-5">
                        <fieldset>
                            <legend class="sr-only">{{ $c['title'] }}</legend>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <x-survey.option-card
                                    name="claims"
                                    value="1"
                                    :checked="$values['claims'] === '1'"
                                    :title="$qc['claimsYes']"
                                    :hint="$qc['claimsYesHint']"
                                    required />

                                <x-survey.option-card
                                    name="claims"
                                    value="0"
                                    :checked="$values['claims'] === '0'"
                                    :title="$qc['claimsNo']"
                                    :hint="$qc['claimsNoHint']"
                                    required />
                            </div>

                            <x-input-error :messages="$errors->get('claims')" class="mt-2" />
                        </fieldset>

                        <div class="rounded-xl border border-dashed border-slate-300 p-4 dark:border-slate-700">
                            <label for="proof" class="block text-sm font-semibold text-slate-700 dark:text-slate-300">
                                {{ $qc['proofLabel'] }}
                            </label>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $qc['proofHint'] }}</p>

                            @if ($c['proofName'])
                                <p class="mt-2 break-words text-xs font-semibold text-emerald-700 dark:text-emerald-400">
                                    {{ __('Uploaded: :name — attach a different file only if you want to replace it.', ['name' => $c['proofName']]) }}
                                </p>
                            @endif

                            <input id="proof" name="document" type="file"
                                   accept="application/pdf,image/jpeg,image/png,image/webp"
                                   class="mt-3 block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-primary/10 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-primary dark:text-slate-300">

                            <x-input-error :messages="$errors->get('document')" class="mt-2" />
                        </div>

                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $qc['proofNoScript'] }}</p>
                    </div>
            @endswitch

            <div class="hidden items-center justify-between gap-3 border-t border-slate-200/80 pt-5 dark:border-slate-800/80 lg:flex">
                @if ($c['urls']['back'])
                    <a href="{{ $c['urls']['back'] }}" class="inline-flex min-h-11 items-center rounded-xl px-3 text-sm font-semibold text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100">
                        {{ $copy['back'] }}
                    </a>
                @endif

                <button type="submit"
                        class="ms-auto inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-primary px-6 py-2.5 text-sm font-bold text-white shadow-md shadow-primary/20 transition-colors hover:bg-primary/90">
                    {{ $submitLabel }}
                    <span aria-hidden="true">→</span>
                </button>
            </div>
        </form>
    </div>

    <x-survey.summary :groups="$c['summary']" :answered="$c['answered']" :questions="$c['total']" />
</div>

<x-survey.mobile-actions :form="$formId" :back-url="$c['urls']['back']" :submit-label="$submitLabel" />
