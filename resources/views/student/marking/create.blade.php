@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $fieldClasses = 'rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('student.teachers.show', $offering->teacherProfile) }}" class="text-xs font-semibold text-slate-400 transition-colors hover:text-primary">&larr; {{ $offering->teacherProfile->user->name }}</a>
            <h2 class="mt-1 font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Submit your paper for marking') }}</h2>
        </div>
    </x-slot>

    <div class="mx-auto max-w-5xl">
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <form method="POST" action="{{ route('student.marking.store', $offering) }}" enctype="multipart/form-data"
                  class="space-y-6 {{ $cardBase }}" :class="{{ $cardTheme }}">
                @csrf

                <div>
                    <x-input-label for="files" :value="__('Your paper')" />
                    <input id="files" name="files[]" type="file" multiple required
                           accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf"
                           class="mt-1 block w-full text-sm text-slate-600 file:mr-4 file:rounded-xl file:border-0 file:bg-primary/10 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-primary hover:file:bg-primary/20 dark:text-slate-300">
                    <p class="mt-2 text-xs text-slate-400">
                        {{ __('Upload up to :count photos or PDFs of your paper — 5 MB each.', ['count' => $maxFiles]) }}
                    </p>
                    <x-input-error :messages="$errors->get('files')" class="mt-2" />
                    <x-input-error :messages="$errors->get('files.*')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="page_count" :value="__('Number of pages (optional)')" />
                    <x-text-input id="page_count" name="page_count" type="number" min="1" max="500"
                                  class="mt-1 block w-full" :value="old('page_count')" />
                    <x-input-error :messages="$errors->get('page_count')" class="mt-2" />
                </div>

                <div class="flex items-center justify-between gap-3 border-t border-slate-200 pt-6 dark:border-slate-800">
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ __('You will pay') }}
                        <span class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ platform_settings()->formatMinor($offering->price_minor) }}</span>
                        <span class="ml-1 text-xs text-slate-400">{{ __('on the next step') }}</span>
                    </p>
                    <x-primary-button>{{ __('Continue to payment') }}</x-primary-button>
                </div>
            </form>

            <!-- Offering summary -->
            <aside class="space-y-4 lg:sticky lg:top-24 lg:self-start">
                <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Marking offer') }}</p>
                    <div class="mt-3 space-y-2 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-slate-500 dark:text-slate-400">{{ __('Teacher') }}</span>
                            <span class="font-semibold text-slate-800 dark:text-slate-100">{{ $offering->teacherProfile->user->name }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-slate-500 dark:text-slate-400">{{ __('Subject') }}</span>
                            <span class="font-semibold text-slate-800 dark:text-slate-100">{{ $offering->subject?->name ?? __('Any subject') }}</span>
                        </div>
                        @if ($offering->max_pages)
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-slate-500 dark:text-slate-400">{{ __('Up to') }}</span>
                                <span class="font-semibold text-slate-800 dark:text-slate-100">{{ __(':pages pages', ['pages' => $offering->max_pages]) }}</span>
                            </div>
                        @endif
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-slate-500 dark:text-slate-400">{{ __('Turnaround') }}</span>
                            <span class="font-semibold text-slate-800 dark:text-slate-100">{{ __(':hours hours', ['hours' => $offering->turnaround_hours ?? config('studylikepro.services.marking_default_turnaround_hours')]) }}</span>
                        </div>
                        @if ($offering->description)
                            <p class="border-t border-slate-200 pt-3 text-xs leading-relaxed text-slate-500 dark:border-slate-800 dark:text-slate-400">{{ $offering->description }}</p>
                        @endif
                    </div>
                    <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-200 pt-4 dark:border-slate-800">
                        <span class="text-sm font-semibold text-slate-600 dark:text-slate-300">{{ __('Price') }}</span>
                        <span class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-slate-100">{{ platform_settings()->formatMinor($offering->price_minor) }}</span>
                    </div>
                    <p class="mt-3 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                        {{ __('The teacher marks your paper offline and sends back the marked copy with feedback — usually within the turnaround time above.') }}
                    </p>
                </div>
            </aside>
        </div>
    </div>
</x-app-layout>
