@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $fieldClasses = 'rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';
    $defaultWindows = [[
        'date' => old('windows.0.date', now()->addDay()->toDateString()),
        'from' => old('windows.0.from', '18:00'),
        'to' => old('windows.0.to', '19:00'),
    ]];
    $timezone = auth()->user()->studentProfile?->timezone ?? config('studylikepro.default_display_timezone');
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Ask a question') }}</h2>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-6">
        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <div class="flex items-start gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-xl">🤖</span>
                <div>
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">Describe it in your own words</h3>
                    <p class="mt-1 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                        Write the question exactly as you would to a teacher. Our AI reads it (and the photo you attach) to pick the subject and lesson,
                        then quietly notifies the best-matched verified teachers. You will see their proposals right here.
                    </p>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('student.requests.store') }}" enctype="multipart/form-data" class="space-y-6"
              x-data="{
                  windows: @js($defaultWindows),
                  maxWindows: 5,
                  addWindow() {
                      if (this.windows.length >= this.maxWindows) return;
                      this.windows.push({ date: '', from: '18:00', to: '19:00' });
                  },
              }">
            @csrf

            <!-- Question -->
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Your question</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">At least a couple of sentences — the more detail, the better the match.</p>

                <textarea id="description" name="description" rows="5" maxlength="2000" required
                          class="mt-4 w-full {{ $fieldClasses }}"
                          placeholder="e.g. I keep getting stuck on factorising quadratic equations when the middle term splits into fractions. My exam is next month.">{{ old('description') }}</textarea>
                <x-input-error :messages="$errors->get('description')" class="mt-2" />

                <div class="mt-5 sm:max-w-xs">
                    <x-input-label :value="__('Learner grade')" />
                    <p class="mt-1 rounded-xl border border-slate-200/80 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-200">
                        {{ $learnerGrade?->label ?? __('Not set') }}
                    </p>
                    <p class="mt-1 text-xs text-slate-400">
                        {{ __('From your learning profile — we only match lessons for this grade.') }}
                        <a href="{{ route('student.profile') }}" class="font-semibold text-primary hover:underline">{{ __('Change grade') }}</a>
                    </p>
                </div>
            </div>

            <!-- Preferred windows -->
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">When could you attend?</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            Add up to 5 time windows in your timezone ({{ $timezone }}). Teachers can only propose times inside them.
                        </p>
                    </div>
                    <button type="button" @click="addWindow()"
                            :disabled="windows.length >= maxWindows"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 transition-colors hover:border-primary/40 hover:text-primary disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700 dark:text-slate-300">
                        + {{ __('Add window') }}
                    </button>
                </div>

                <div class="mt-4 space-y-3">
                    <template x-for="(window, index) in windows" :key="index">
                        <div class="flex flex-wrap items-end gap-3 rounded-xl border border-slate-200/80 p-4 dark:border-slate-800">
                            <div class="grow">
                                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400" :for="`window-date-${index}`">{{ __('Date') }}</label>
                                <input type="date" :id="`window-date-${index}`" :name="`windows[${index}][date]`" x-model="window.date" required
                                       :min="'{{ now()->toDateString() }}'" class="mt-1 w-full {{ $fieldClasses }}" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400" :for="`window-from-${index}`">{{ __('From') }}</label>
                                <input type="time" :id="`window-from-${index}`" :name="`windows[${index}][from]`" x-model="window.from" required
                                       class="mt-1 {{ $fieldClasses }}" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400" :for="`window-to-${index}`">{{ __('To') }}</label>
                                <input type="time" :id="`window-to-${index}`" :name="`windows[${index}][to]`" x-model="window.to" required
                                       class="mt-1 {{ $fieldClasses }}" />
                            </div>
                            <button type="button" @click="windows.length > 1 && windows.splice(index, 1)"
                                    :disabled="windows.length <= 1"
                                    class="mb-0.5 flex h-9 w-9 items-center justify-center rounded-xl text-slate-400 transition-colors hover:bg-rose-50 hover:text-rose-500 disabled:cursor-not-allowed disabled:opacity-30 dark:hover:bg-rose-950/30"
                                    aria-label="{{ __('Remove window') }}">&times;</button>
                        </div>
                    </template>
                </div>

                <p class="mt-3 text-xs text-slate-400" x-show="windows.length >= maxWindows" x-cloak>{{ __('That is the maximum number of windows.') }}</p>

                @if ($errors->has('windows') || $errors->has('windows.*'))
                    <ul class="mt-3 space-y-1 text-sm text-red-600 dark:text-red-400">
                        @foreach ($errors->get('windows') as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                        @foreach ($errors->get('windows.*') as $messages)
                            @foreach ($messages as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        @endforeach
                    </ul>
                @endif
            </div>

            <!-- Attachment -->
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Photo of the problem <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">optional</span></h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Up to {{ $maxAttachments }} images (JPG, PNG or WebP, 5 MB each). A clear photo often lets the AI match the exact lesson on the first try.
                </p>

                <input type="file" name="attachments[]" accept="image/jpeg,image/png,image/webp" multiple
                       class="mt-4 block w-full cursor-pointer text-sm text-slate-500 file:mr-3 file:rounded-xl file:border-0 file:bg-primary/10 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-primary hover:file:bg-primary/20 dark:text-slate-400" />
                <x-input-error :messages="$errors->get('attachments')" class="mt-2" />
                <x-input-error :messages="$errors->get('attachments.0')" class="mt-1" />
                <x-input-error :messages="$errors->get('attachments.1')" class="mt-1" />
                <x-input-error :messages="$errors->get('attachments.2')" class="mt-1" />
            </div>

            <!-- Budget & submit -->
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Budget <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">optional</span></h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    A rough per-lesson budget helps teachers price fairly. Leave it blank and they will quote their usual rate.
                </p>

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                        <span class="text-sm font-semibold text-slate-400">{{ platform_settings()->currencySymbol() }}</span>
                        <input type="number" id="budget" name="budget" step="0.01" min="0" max="100000" value="{{ old('budget') }}"
                               placeholder="800" class="w-28 border-0 bg-transparent p-0 text-sm text-slate-800 focus:ring-0 dark:text-slate-200" />
                    </div>
                    <span class="text-xs text-slate-400">{{ __('per lesson') }}</span>
                </div>
                <x-input-error :messages="$errors->get('budget')" class="mt-2" />
            </div>

            <div class="flex flex-wrap items-center justify-end gap-3">
                <a href="{{ route('student.requests.index') }}" class="text-sm font-semibold text-slate-500 transition-colors hover:text-primary dark:text-slate-400">{{ __('Cancel') }}</a>
                <x-primary-button>{{ __('Submit question') }}</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
