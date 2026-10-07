@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $chip = 'inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400';
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Curriculum') }}</h2>
            <a href="{{ route('admin.subjects.index') }}"
               class="rounded-xl bg-primary/10 px-4 py-2 text-sm font-semibold text-primary transition-colors hover:bg-primary/20">
                {{ __('Browse subjects') }}
            </a>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if (session('status') === 'level-updated')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                Education level updated.
            </div>
        @endif

        <p class="text-sm leading-relaxed text-slate-500 dark:text-slate-400">
            The Sri Lankan structure every subject and lesson hangs off — Primary (Grades 1–5), O/L (Grades 6–11), A/L (Grades 12–13) and Other.
            Grades are fixed; levels can be renamed, reordered or switched off. Lessons are managed per subject, grade by grade.
        </p>

        @foreach ($levels as $level)
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <form method="POST" action="{{ route('admin.curriculum.levels.update', $level) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div class="flex flex-wrap items-end gap-4">
                        <div class="w-20">
                            <x-input-label for="icon-{{ $level->id }}" :value="__('Icon')" />
                            <x-text-input id="icon-{{ $level->id }}" name="icon" type="text" maxlength="4"
                                          class="mt-1 block w-full text-center" :value="old('icon', $level->icon)" />
                            <x-input-error :messages="$errors->get('icon')" class="mt-2" />
                        </div>
                        <div class="min-w-48 flex-1">
                            <x-input-label for="name-{{ $level->id }}" :value="__('Level name')" />
                            <x-text-input id="name-{{ $level->id }}" name="name" type="text"
                                          class="mt-1 block w-full" :value="old('name', $level->name)" />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>
                        <div class="w-28">
                            <x-input-label for="sort-{{ $level->id }}" :value="__('Sort order')" />
                            <x-text-input id="sort-{{ $level->id }}" name="sort_order" type="number" min="0" max="999"
                                          class="mt-1 block w-full" :value="old('sort_order', $level->sort_order)" />
                            <x-input-error :messages="$errors->get('sort_order')" class="mt-2" />
                        </div>
                        <label class="flex cursor-pointer items-center gap-2 pb-2.5 text-sm text-slate-600 dark:text-slate-300">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $level->is_active))
                                   class="rounded border-slate-300 text-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900" />
                            Active
                        </label>
                        <x-primary-button class="mb-1">{{ __('Save') }}</x-primary-button>
                    </div>
                </form>

                <div class="mt-5 flex flex-wrap items-start justify-between gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="{{ $chip }}">{{ $level->gradeRangeLabel() }}</span>
                        <span class="{{ $chip }}">{{ $level->subjects_count }} {{ Str::plural('subject', $level->subjects_count) }}</span>
                        <span class="{{ $chip }}">{{ $level->grades->count() }} {{ Str::plural('grade', $level->grades->count()) }}</span>
                    </div>
                    <a href="{{ route('admin.subjects.index', ['level' => $level->key]) }}"
                       class="text-xs font-semibold text-primary transition-colors hover:text-primary/80">
                        {{ __('Manage subjects') }} &rarr;
                    </a>
                </div>

                <div class="mt-3 flex flex-wrap gap-1.5">
                    @foreach ($level->grades as $grade)
                        <span class="{{ $chip }}">{{ $grade->label }}</span>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</x-app-layout>
