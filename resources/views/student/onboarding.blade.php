@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $radioClasses = 'h-4 w-4 shrink-0 border-slate-300 text-primary focus:ring-primary dark:border-slate-600 dark:bg-slate-900';
    $optionClasses = 'flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition-all hover:border-primary/40 hover:shadow-sm dark:hover:border-primary/30';
    $optionIdle = 'border-slate-200/80 dark:border-slate-800';
    $optionActive = 'border-primary bg-primary/5 ring-1 ring-primary/30';
    $languageIcons = ['Sinhala' => '🇱🇰', 'English' => '🌐'];
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Set up your learning') }}</h2>
    </x-slot>

    <div class="mx-auto max-w-3xl"
         x-data="{
             levels: @js($wizard['levels']),
             basketNames: new Map(@js($wizard['basketNames'])),
             subjectNames: new Map(@js($wizard['subjectNames'])),
             labels: {
                 level: @js(__('Level')),
                 grade: @js(__('Grade')),
                 baskets: @js(__('Subjects')),
                 language: @js(__('Language')),
                 summary: @js(__('Finish')),
                 stepOf: @js(__('Step :current of :total')),
             },
             step: @js($wizard['initial']['step']),
             level: @js($wizard['initial']['level']),
             grade: @js($wizard['initial']['grade']),
             subjects: @js($wizard['initial']['subjects']),
             language: @js($wizard['initial']['language']),

             init() {
                 // The server sends the step that owns the validation error.
                 this.step = Math.min(Math.max(this.step, 1), this.steps.length);
             },

             // The grade the student picked, wherever it lives in the catalog.
             get currentGrade() {
                 for (const level of this.levels) {
                     const grade = level.grades.find((candidate) => candidate.id === this.grade);

                     if (grade) return grade;
                 }

                 return null;
             },
             // The O/L basket step only exists for the grades that choose from baskets.
             get steps() {
                 const keys = ['level', 'grade'];

                 if ((this.currentGrade?.basketCount ?? 0) > 0) keys.push('baskets');

                 keys.push('language', 'summary');

                 return keys;
             },
             get current() {
                 return this.steps[this.step - 1] ?? 'level';
             },
             get basketCount() {
                 return this.currentGrade?.basketCount ?? 0;
             },
             get pickedBaskets() {
                 return Object.values(this.subjects).filter((subjectId) => !! subjectId).length;
             },
             get progress() {
                 return Math.round(((this.step - 1) / Math.max(this.steps.length - 1, 1)) * 100);
             },
             get stepText() {
                 return this.labels.stepOf.replace(':current', this.step).replace(':total', this.steps.length);
             },
             get levelName() {
                 return this.levels.find((level) => level.id === this.level)?.name ?? '';
             },
             get levelIcon() {
                 return this.levels.find((level) => level.id === this.level)?.icon ?? '🎓';
             },
             get gradeLabel() {
                 return this.currentGrade?.label ?? '';
             },
             get canAdvance() {
                 return {
                     level: !! this.level,
                     grade: !! this.grade,
                     baskets: this.pickedBaskets >= this.basketCount,
                     language: !! this.language,
                     summary: true,
                 }[this.current] ?? false;
             },
             get canFinish() {
                 return !! this.level && !! this.grade && this.pickedBaskets >= this.basketCount && !! this.language;
             },

             stepIndex(key) {
                 return this.steps.indexOf(key) + 1;
             },
             // A new level brings its own grades, so the old answers cannot survive.
             onLevel() {
                 this.grade = null;
                 this.subjects = {};
                 this.clampStep();
             },
             onGrade() {
                 this.subjects = {};
                 this.clampStep();
             },
             clampStep() {
                 this.step = Math.min(this.step, this.steps.length);
             },
             next() {
                 if (this.canAdvance && this.step < this.steps.length) this.step++;
             },
             back() {
                 if (this.step > 1) this.step--;
             },
             goTo(step) {
                 if (step < this.step) this.step = step;
             },
         }">
        <div class="space-y-6">
            <!-- Welcome and progress -->
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <div class="flex items-start gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-xl">✨</span>
                    <div>
                        <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">
                            {{ __('Welcome, :name!', ['name' => Auth::user()->name]) }}
                        </h3>
                        <p class="mt-1 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                            {{ __('A few quick questions so we can match you with the right tutors and lessons. You can change any of this later from your profile.') }}
                        </p>
                    </div>
                </div>

                <div class="mt-6">
                    <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-wider text-slate-400">
                        <span x-text="stepText"></span>
                        <span x-text="Math.round(progress) + '%'"></span>
                    </div>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                        <div class="h-full rounded-full bg-primary transition-all duration-500 ease-out"
                             :style="`width: ${progress}%`"></div>
                    </div>

                    <ol class="mt-4 flex flex-wrap gap-2">
                        <template x-for="(key, index) in steps" :key="key">
                            <li>
                                <button type="button" @click="goTo(index + 1)" :disabled="index + 1 > step"
                                        :aria-current="index + 1 === step ? 'step' : null"
                                        class="flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold transition-colors"
                                        :class="index + 1 === step
                                            ? 'border-primary bg-primary/10 text-primary'
                                            : (index + 1 < step
                                                ? 'border-emerald-200/80 bg-emerald-50 text-emerald-600 hover:border-emerald-300 dark:border-emerald-900/40 dark:bg-emerald-950/20 dark:text-emerald-400'
                                                : 'border-slate-200/80 text-slate-400 dark:border-slate-800')">
                                    <span x-text="index + 1"></span>
                                    <span x-text="labels[key]"></span>
                                </button>
                            </li>
                        </template>
                    </ol>
                </div>
            </div>

            @if ($errors->any())
                <div class="rounded-2xl border border-rose-200/80 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300">
                    <p class="font-semibold">{{ __('Please check your answers.') }}</p>
                    <ul class="mt-1 list-inside list-disc space-y-0.5">
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('student.onboarding.store') }}" class="space-y-6"
                  @submit="if (! canFinish) $event.preventDefault()">
                @csrf

                <!-- Step 1: education level -->
                <section x-show="current === 'level'" x-cloak class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Which level are you studying?') }}</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ __('Pick your exam stage — the next steps only offer the grades and subjects that belong to it.') }}
                    </p>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        @foreach ($levels as $level)
                            <label class="{{ $optionClasses }}"
                                   :class="level === {{ $level->id }} ? '{{ $optionActive }}' : '{{ $optionIdle }}'">
                                <input type="radio" name="level_id" value="{{ $level->id }}"
                                       x-model.number="level" @change="onLevel()" class="mt-1 {{ $radioClasses }}" />
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-lg">{{ $level->icon ?: '🎓' }}</span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-bold text-slate-800 dark:text-slate-100">{{ $level->name }}</span>
                                    <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $level->gradeRangeLabel() }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </section>

                <!-- Step 2: grade -->
                <section x-show="current === 'grade'" x-cloak class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Which grade are you in?') }}</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ __('Lessons and tutors are matched to this grade, so keep it up to date each year.') }}
                    </p>

                    @foreach ($levels as $level)
                        <div class="mt-5" x-show="level === {{ $level->id }}" x-cloak>
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $level->name }}</p>
                            <div class="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-4">
                                @foreach ($level->grades as $grade)
                                    <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border px-3 py-3 text-center transition-all hover:border-primary/40 dark:hover:border-primary/30"
                                           :class="grade === {{ $grade->id }} ? '{{ $optionActive }}' : '{{ $optionIdle }}'">
                                        <input type="radio" name="grade_id" value="{{ $grade->id }}"
                                               x-model.number="grade" @change="onGrade()"
                                               :disabled="level !== {{ $level->id }}"
                                               class="{{ $radioClasses }}" />
                                        <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                                            {{ $grade->number ?? $grade->label }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </section>

                <!-- Step 3: O/L basket subjects (only the grades that choose from baskets) -->
                <section x-show="current === 'baskets'" x-cloak class="space-y-4">
                    @foreach ($basketGroups as $gradeId => $groups)
                        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}" x-show="grade === {{ $gradeId }}" x-cloak>
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Pick your subjects') }}</h3>
                                <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-[11px] font-semibold text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                                    <span x-text="pickedBaskets"></span>/{{ count($groups) }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                {{ __('Grade 10 and 11 students take one subject from each of the three O/L baskets. Pick the subject you are studying for each one.') }}
                            </p>

                            <div class="mt-5 space-y-6">
                                @foreach ($groups as $group)
                                    @php $basketKey = $group['basket']->key; @endphp
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h4 class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                                                {{ trim(($group['basket']->icon ?? '').' '.$group['basket']->name) }}
                                            </h4>
                                            <span class="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-primary">
                                                {{ __('pick one') }}
                                            </span>
                                            <span class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400"
                                                  x-show="!! subjects['{{ $basketKey }}']" x-cloak>{{ __('chosen') }}</span>
                                        </div>
                                        @if ($group['basket']->description)
                                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $group['basket']->description }}</p>
                                        @endif

                                        <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                            @foreach ($group['subjects'] as $subject)
                                                <label class="flex cursor-pointer items-center gap-3 rounded-xl border px-4 py-3 transition-colors hover:border-primary/40 dark:hover:border-primary/30"
                                                       :class="subjects['{{ $basketKey }}'] === {{ $subject->id }} ? '{{ $optionActive }}' : '{{ $optionIdle }}'">
                                                    <input type="radio" name="basket_subjects[{{ $basketKey }}]" value="{{ $subject->id }}"
                                                           x-model.number="subjects['{{ $basketKey }}']" class="{{ $radioClasses }}" />
                                                    <span class="text-lg">{{ $subject->icon ?? '📘' }}</span>
                                                    <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $subject->name }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </section>

                <!-- Step 4: learning language -->
                <section x-show="current === 'language'" x-cloak class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Which language do you learn in?') }}</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ __('Your lessons are taught in this language, and teachers who match it are shown first.') }}
                    </p>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        @foreach ($languages as $language)
                            <label class="{{ $optionClasses }} items-center"
                                   :class="language === @js($language) ? '{{ $optionActive }}' : '{{ $optionIdle }}'">
                                <input type="radio" name="learning_language" value="{{ $language }}"
                                       x-model="language" class="{{ $radioClasses }}" />
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-lg">
                                    {{ $languageIcons[$language] ?? '🗣️' }}
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-bold text-slate-800 dark:text-slate-100">{{ $language }}</span>
                                    <span class="block text-xs text-slate-500 dark:text-slate-400">
                                        {{ __('Lessons and lesson notes are prepared in this language.') }}
                                    </span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </section>

                <!-- Step 5: summary -->
                <section x-show="current === 'summary'" x-cloak class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Ready to go?') }}</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ __('Check your answers, then finish setup to open your dashboard.') }}
                    </p>

                    <dl class="mt-5 divide-y divide-slate-200/80 dark:divide-slate-800/80">
                        <div class="flex items-center justify-between gap-4 py-3">
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Level') }}</dt>
                            <dd class="flex items-center gap-3">
                                <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                                    <span x-text="levelIcon"></span> <span x-text="levelName"></span>
                                </span>
                                <button type="button" @click="goTo(stepIndex('level'))"
                                        class="text-xs font-semibold text-primary hover:underline">{{ __('Edit') }}</button>
                            </dd>
                        </div>

                        <div class="flex items-center justify-between gap-4 py-3">
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Grade') }}</dt>
                            <dd class="flex items-center gap-3">
                                <span class="text-sm font-semibold text-slate-700 dark:text-slate-200" x-text="gradeLabel"></span>
                                <button type="button" @click="goTo(stepIndex('grade'))"
                                        class="text-xs font-semibold text-primary hover:underline">{{ __('Edit') }}</button>
                            </dd>
                        </div>

                        <template x-if="basketCount > 0">
                            <div class="flex items-start justify-between gap-4 py-3">
                                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Subjects') }}</dt>
                                <dd class="flex items-start gap-3">
                                    <div class="space-y-1 text-right">
                                        <template x-for="(subjectId, basketKey) in subjects" :key="basketKey">
                                            <p class="text-sm text-slate-700 dark:text-slate-200">
                                                <span class="text-xs font-semibold uppercase tracking-wide text-slate-400" x-text="basketNames.get(basketKey)"></span>
                                                <span class="block font-semibold" x-text="subjectNames.get(subjectId)"></span>
                                            </p>
                                        </template>
                                    </div>
                                    <button type="button" @click="goTo(stepIndex('baskets'))"
                                            class="text-xs font-semibold text-primary hover:underline">{{ __('Edit') }}</button>
                                </dd>
                            </div>
                        </template>

                        <div class="flex items-center justify-between gap-4 py-3">
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Learning language') }}</dt>
                            <dd class="flex items-center gap-3">
                                <span class="text-sm font-semibold text-slate-700 dark:text-slate-200" x-text="language"></span>
                                <button type="button" @click="goTo(stepIndex('language'))"
                                        class="text-xs font-semibold text-primary hover:underline">{{ __('Edit') }}</button>
                            </dd>
                        </div>
                    </dl>
                </section>

                <!-- Navigation -->
                <div class="flex items-center justify-between gap-3">
                    <button type="button" @click="back()" :disabled="step === 1"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 transition-colors hover:border-slate-300 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700 dark:text-slate-300 dark:hover:border-slate-600">
                        {{ __('Back') }}
                    </button>

                    <button type="button" @click="next()" x-show="current !== 'summary'" x-cloak
                            :disabled="! canAdvance"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/20 transition-colors hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-40">
                        {{ __('Continue') }}
                    </button>

                    <x-primary-button x-show="current === 'summary'" x-cloak x-bind:disabled="! canFinish">
                        {{ __('Finish setup') }}
                    </x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
