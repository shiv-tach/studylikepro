@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $selectClasses = 'mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900';
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Subjects & lessons') }}</h2>
    </x-slot>

    <div class="space-y-6" x-data="{ showCreate: {{ $errors->any() ? 'true' : 'false' }} }">
        @if (session('status') === 'subject-created')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                Subject created — add its lessons below.
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-slate-500 dark:text-slate-400">The catalog students browse and AI matches questions against.</p>
            <button @click="showCreate = ! showCreate"
                    class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-bold text-white shadow-md shadow-primary/20 transition hover:bg-primary/90">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                New subject
            </button>
        </div>

        <!-- Level filter -->
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.subjects.index') }}"
               class="rounded-full px-3 py-1.5 text-xs font-semibold transition-colors {{ ($filters['level'] ?? null) === null ? 'bg-primary text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700' }}">
                All levels
            </a>
            @foreach ($levels as $level)
                <a href="{{ route('admin.subjects.index', ['level' => $level->key]) }}"
                   class="rounded-full px-3 py-1.5 text-xs font-semibold transition-colors {{ ($filters['level'] ?? null) === $level->key ? 'bg-primary text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700' }}">
                    {{ $level->icon }} {{ $level->name }}
                    <span class="ml-1 opacity-70">{{ $level->subjects_count }}</span>
                </a>
            @endforeach
            <a href="{{ route('admin.curriculum.index') }}" class="text-xs font-semibold text-primary transition-colors hover:text-primary/80">
                {{ __('Education levels') }} &rarr;
            </a>
        </div>

        <!-- Create form -->
        <div x-show="showCreate" x-transition class="{{ $cardBase }}" :class="{{ $cardTheme }}" style="display: none;">
            <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">New subject</h3>
            <form method="POST" action="{{ route('admin.subjects.store') }}" class="mt-5 space-y-5">
                @csrf
                <div class="grid gap-5 sm:grid-cols-3">
                    <div>
                        <x-input-label for="education_level_id" :value="__('Education level')" />
                        @php
                            $defaultLevelId = (int) old('education_level_id') ?: ($levels->firstWhere('key', $filters['level'] ?? null)?->id ?? $levels->first()?->id);
                        @endphp
                        <select id="education_level_id" name="education_level_id" class="{{ $selectClasses }}">
                            @foreach ($levels as $level)
                                <option value="{{ $level->id }}" @selected($defaultLevelId === $level->id)>{{ $level->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('education_level_id')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="name" :value="__('Name')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" placeholder="e.g. Mathematics" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="slug" :value="__('Slug (optional)')" />
                        <x-text-input id="slug" name="slug" type="text" class="mt-1 block w-full" :value="old('slug')" placeholder="auto-generated from name" />
                        <x-input-error :messages="$errors->get('slug')" class="mt-2" />
                    </div>
                </div>

                <div class="grid gap-5 sm:grid-cols-3">
                    <div>
                        <x-input-label for="icon" :value="__('Icon (emoji)')" />
                        <x-text-input id="icon" name="icon" type="text" class="mt-1 block w-full" :value="old('icon', '📘')" maxlength="4" />
                        <x-input-error :messages="$errors->get('icon')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="sort_order" :value="__('Sort order')" />
                        <x-text-input id="sort_order" name="sort_order" type="number" min="0" max="999" class="mt-1 block w-full" :value="old('sort_order', 0)" />
                        <x-input-error :messages="$errors->get('sort_order')" class="mt-2" />
                    </div>
                    <div class="flex items-end pb-3">
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                            <input type="checkbox" name="is_active" value="1" checked
                                   class="rounded border-slate-300 text-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900" />
                            Active (visible to students)
                        </label>
                    </div>
                </div>

                <div>
                    <x-input-label for="description" :value="__('Description (optional)')" />
                    <textarea id="description" name="description" rows="2"
                              class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900">{{ old('description') }}</textarea>
                    <x-input-error :messages="$errors->get('description')" class="mt-2" />
                </div>

                <div class="flex justify-end">
                    <x-primary-button>{{ __('Create subject') }}</x-primary-button>
                </div>
            </form>
        </div>

        <!-- Catalog list -->
        <div class="{{ $cardBase }} p-0" :class="{{ $cardTheme }}">
            @if ($subjects->isEmpty())
                <div class="p-10 text-center">
                    <p class="text-sm text-slate-500 dark:text-slate-400">No subjects yet — create the first one.</p>
                </div>
            @else
                @foreach ($subjects->groupBy(fn ($subject) => $subject->educationLevel?->name ?? __('Other')) as $levelName => $levelSubjects)
                    <p class="px-6 pt-5 pb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $levelName }}</p>
                    <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($levelSubjects as $subject)
                        <li class="flex flex-wrap items-center justify-between gap-4 px-6 py-4">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-lg">{{ $subject->icon ?? '📘' }}</span>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $subject->name }}</p>
                                        @unless ($subject->is_active)
                                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">Inactive</span>
                                        @endunless
                                    </div>
                                    <p class="truncate font-mono text-xs text-slate-400">/{{ $subject->slug }}</p>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-4 text-xs text-slate-400">
                                <span>{{ $subject->lessons_count }} {{ Str::plural('lesson', $subject->lessons_count) }}</span>
                                <span>{{ $subject->teacher_profiles_count }} {{ Str::plural('teacher', $subject->teacher_profiles_count) }}</span>
                                <span>sort {{ $subject->sort_order }}</span>
                                <a href="{{ route('admin.subjects.edit', $subject) }}"
                                   class="rounded-xl bg-primary/10 px-3 py-1.5 text-xs font-semibold text-primary transition-colors hover:bg-primary/20">
                                    Edit & lessons
                                </a>
                            </div>
                        </li>
                    @endforeach
                    </ul>
                @endforeach
            @endif
        </div>
    </div>
</x-app-layout>
