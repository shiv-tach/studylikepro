@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ $subject->icon ?? '📘' }} {{ $subject->name }}</h2>
            @if ($subject->educationLevel)
                <span class="rounded-full bg-primary/10 px-2.5 py-0.5 text-[11px] font-semibold text-primary">{{ $subject->educationLevel->name }}</span>
            @endif
            @unless ($subject->is_active)
                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">Inactive</span>
            @endunless
        </div>
    </x-slot>

    <div class="space-y-6">
        <a href="{{ route('admin.subjects.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 transition-colors hover:text-primary dark:text-slate-400">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Back to catalog
        </a>

        @if (session('status') === 'subject-updated')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">Subject updated.</div>
        @elseif (session('status') === 'lesson-created')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">Lesson added.</div>
        @elseif (session('status') === 'lesson-updated')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">Lesson updated.</div>
        @elseif (session('status') === 'lesson-moved-grade')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">Lesson updated and moved to its new grade.</div>
        @elseif (session('status') === 'lesson-removed')
            <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-4 text-sm text-slate-600 dark:border-slate-800/80 dark:bg-slate-900/60 dark:text-slate-300">Lesson removed.</div>
        @elseif (session('status') === 'lessons-copied')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                Copied {{ session('copied', 0) }} {{ Str::plural('lesson', (int) session('copied', 0)) }}. Lessons whose slug already existed in the target grade were left untouched.
            </div>
        @elseif (session('status') === 'lessons-status-updated')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                Updated {{ session('updated', 0) }} {{ Str::plural('lesson', (int) session('updated', 0)) }}.
            </div>
        @elseif (session('status') === 'lessons-reordered')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">Lesson order updated.</div>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Subject details -->
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Subject details</h3>

                <form method="POST" action="{{ route('admin.subjects.update', $subject) }}" class="mt-5 space-y-5">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="name" :value="__('Name')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $subject->name)" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="slug" :value="__('Slug')" />
                        <x-text-input id="slug" name="slug" type="text" class="mt-1 block w-full" :value="old('slug', $subject->slug)" />
                        <x-input-error :messages="$errors->get('slug')" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="icon" :value="__('Icon (emoji)')" />
                            <x-text-input id="icon" name="icon" type="text" class="mt-1 block w-full" :value="old('icon', $subject->icon)" maxlength="4" />
                            <x-input-error :messages="$errors->get('icon')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="sort_order" :value="__('Sort order')" />
                            <x-text-input id="sort_order" name="sort_order" type="number" min="0" max="999" class="mt-1 block w-full" :value="old('sort_order', $subject->sort_order)" />
                            <x-input-error :messages="$errors->get('sort_order')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="description" :value="__('Description')" />
                        <textarea id="description" name="description" rows="3"
                                  class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900">{{ old('description', $subject->description) }}</textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $subject->is_active))
                               class="rounded border-slate-300 text-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900" />
                        Active (visible to students)
                    </label>

                    <div class="flex justify-end">
                        <x-primary-button>{{ __('Save subject') }}</x-primary-button>
                    </div>
                </form>
            </div>

            <!-- Lessons: the grade matrix -->
            <div class="{{ $cardBase }} lg:col-span-2" :class="{{ $cardTheme }}"
                 x-data="{ grade: {{ (int) ($selectedGradeId ?? $grades->first()?->id ?? 0) }} }">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Lessons by grade</h3>
                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                        {{ $subject->lessons->count() }} total
                    </span>
                </div>

                @if ($grades->isEmpty())
                    <p class="mt-5 text-sm text-slate-400">This subject has no education level, so it has no grades to hang lessons off. Create the subject inside a level to manage lessons.</p>
                @else
                    <!-- Grade tabs -->
                    <div class="mt-5 flex flex-wrap gap-2">
                        @foreach ($grades as $grade)
                            @php $count = $lessonsByGrade->get($grade->id, collect())->count(); @endphp
                            <button type="button" @click="grade = {{ $grade->id }}"
                                    :class="grade === {{ $grade->id }}
                                        ? 'border-primary bg-primary text-white shadow-sm'
                                        : 'border-slate-200/80 text-slate-600 hover:border-primary/40 dark:border-slate-800 dark:text-slate-300'"
                                    class="inline-flex items-center gap-2 rounded-xl border px-3 py-1.5 text-xs font-semibold transition-colors">
                                {{ $grade->label }}
                                <span class="rounded-full px-1.5 text-[10px] font-bold"
                                      :class="grade === {{ $grade->id }} ? 'bg-white/25' : 'bg-slate-100 dark:bg-slate-800'">{{ $count }}</span>
                            </button>
                        @endforeach
                    </div>

                    <!-- Add lesson to the active grade -->
                    <form method="POST" action="{{ route('admin.subjects.lessons.store', $subject) }}"
                          class="mt-5 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200/80 p-4 dark:border-slate-800">
                        @csrf
                        <input type="hidden" name="grade_id" :value="grade">
                        <div class="min-w-40 flex-1">
                            <x-input-label for="new-lesson-name" :value="__('New lesson in the selected grade')" />
                            <x-text-input id="new-lesson-name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" placeholder="e.g. Unit 13" />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>
                        <div class="min-w-48 flex-1">
                            <x-input-label for="new-lesson-description" :value="__('Description (optional)')" />
                            <x-text-input id="new-lesson-description" name="description" type="text" class="mt-1 block w-full" :value="old('description')" placeholder="What this lesson covers" />
                            <x-input-error :messages="$errors->get('description')" class="mt-2" />
                        </div>
                        <div class="w-28">
                            <x-input-label for="new-lesson-sort" :value="__('Sort')" />
                            <x-text-input id="new-lesson-sort" name="sort_order" type="number" min="0" max="999" class="mt-1 block w-full" :value="old('sort_order', 0)" />
                        </div>
                        <div class="flex items-end pb-2.5">
                            <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                                <input type="checkbox" name="is_active" value="1" checked
                                       class="rounded border-slate-300 text-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900" />
                                {{ __('Active') }}
                            </label>
                        </div>
                        <x-primary-button>{{ __('Add lesson') }}</x-primary-button>
                        <x-input-error :messages="$errors->get('grade_id')" class="w-full" />
                    </form>

                    <!-- Copy a grade's lessons into another grade -->
                    <form method="POST" action="{{ route('admin.subjects.lessons.copy', $subject) }}"
                          class="mt-3 flex flex-wrap items-end gap-3 rounded-xl border border-dashed border-slate-300 p-4 dark:border-slate-700">
                        @csrf
                        <div class="w-40">
                            <x-input-label for="copy-from-grade" :value="__('Copy lessons from')" />
                            <select id="copy-from-grade" name="from_grade_id" class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                @foreach ($grades as $grade)
                                    <option value="{{ $grade->id }}" @selected((int) old('from_grade_id') === $grade->id)>{{ $grade->label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="w-40">
                            <x-input-label for="copy-to-grade" :value="__('into')" />
                            <select id="copy-to-grade" name="to_grade_id" class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                @foreach ($grades as $grade)
                                    <option value="{{ $grade->id }}" @selected((int) old('to_grade_id') === $grade->id)>{{ $grade->label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="rounded-xl border border-primary/30 bg-primary/10 px-4 py-2.5 text-sm font-semibold text-primary transition-colors hover:bg-primary/20">
                            {{ __('Copy') }}
                        </button>
                        <p class="w-full text-xs text-slate-400">Handy for subjects that repeat across grades: copy is skipped for lessons whose slug already exists in the target grade.</p>
                        <x-input-error :messages="$errors->get('from_grade_id')" class="w-full" />
                        <x-input-error :messages="$errors->get('to_grade_id')" class="w-full" />
                    </form>
                @endif

                @foreach ($grades as $grade)
                    @php
                        $gradeLessons = $lessonsByGrade->get($grade->id, collect())->values();
                        $defaultGradeId = (int) ($selectedGradeId ?? $grades->first()?->id ?? 0);
                    @endphp
                    <div x-show="grade === {{ $grade->id }}" @if ($defaultGradeId !== $grade->id) style="display: none;" @endif class="mt-5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">
                                {{ $grade->label }} · {{ $gradeLessons->count() }} {{ Str::plural('lesson', $gradeLessons->count()) }}
                            </p>
                            @if ($gradeLessons->isNotEmpty())
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-slate-400">{{ __('All:') }}</span>
                                    <form method="POST" action="{{ route('admin.subjects.lessons.bulk-active', $subject) }}">
                                        @csrf
                                        <input type="hidden" name="grade_id" value="{{ $grade->id }}">
                                        <input type="hidden" name="is_active" value="1">
                                        <button type="submit" class="rounded-lg border border-slate-200/80 px-2.5 py-1 text-xs font-semibold text-slate-500 transition-colors hover:border-emerald-300 hover:text-emerald-600 dark:border-slate-800 dark:text-slate-400">Activate</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.subjects.lessons.bulk-active', $subject) }}">
                                        @csrf
                                        <input type="hidden" name="grade_id" value="{{ $grade->id }}">
                                        <input type="hidden" name="is_active" value="0">
                                        <button type="submit" class="rounded-lg border border-slate-200/80 px-2.5 py-1 text-xs font-semibold text-slate-500 transition-colors hover:border-rose-300 hover:text-rose-600 dark:border-slate-800 dark:text-slate-400">Deactivate</button>
                                    </form>
                                </div>
                            @endif
                        </div>

                        @if ($gradeLessons->isEmpty())
                            <p class="mt-3 rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-400 dark:border-slate-700">
                                {{ __('No lessons for :grade yet — add one above, or copy them from another grade.', ['grade' => $grade->label]) }}
                            </p>
                        @else
                            <ul class="mt-2 divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach ($gradeLessons as $lesson)
                                    <li class="flex flex-wrap items-start gap-3 py-3">
                                        <!-- Order -->
                                        <div class="flex flex-col gap-1 pt-0.5">
                                            <form method="POST" action="{{ route('admin.subjects.lessons.move', [$subject, $lesson]) }}">
                                                @csrf
                                                <input type="hidden" name="direction" value="up">
                                                <button type="submit" @disabled($loop->first) title="{{ __('Move up') }}"
                                                        class="rounded-lg border border-slate-200/80 p-1 text-slate-400 transition-colors hover:border-primary/40 hover:text-primary disabled:cursor-not-allowed disabled:opacity-30 dark:border-slate-800">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                                    </svg>
                                                    <span class="sr-only">{{ __('Move up') }}</span>
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.subjects.lessons.move', [$subject, $lesson]) }}">
                                                @csrf
                                                <input type="hidden" name="direction" value="down">
                                                <button type="submit" @disabled($loop->last) title="{{ __('Move down') }}"
                                                        class="rounded-lg border border-slate-200/80 p-1 text-slate-400 transition-colors hover:border-primary/40 hover:text-primary disabled:cursor-not-allowed disabled:opacity-30 dark:border-slate-800">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                    </svg>
                                                    <span class="sr-only">{{ __('Move down') }}</span>
                                                </button>
                                            </form>
                                        </div>

                                        <!-- Lesson fields -->
                                        <form method="POST" action="{{ route('admin.subjects.lessons.update', [$subject, $lesson]) }}"
                                              class="flex flex-1 flex-wrap items-end gap-3">
                                            @csrf
                                            @method('PUT')
                                            <div class="min-w-40 flex-1">
                                                <input type="text" name="name" value="{{ $lesson->name }}" required aria-label="{{ __('Lesson name') }}"
                                                       class="w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900" />
                                                <input type="text" name="description" value="{{ $lesson->description }}" placeholder="{{ __('Description (optional)') }}" aria-label="{{ __('Lesson description') }}"
                                                       class="mt-1 w-full rounded-xl border-slate-200/80 bg-white text-xs text-slate-500 shadow-sm focus:border-primary focus:ring-primary dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400" />
                                            </div>
                                            <div class="w-28">
                                                <select name="grade_id" aria-label="{{ __('Grade') }}"
                                                        class="w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                                    @foreach ($grades as $gradeOption)
                                                        <option value="{{ $gradeOption->id }}" @selected($lesson->grade_id === $gradeOption->id)>{{ $gradeOption->label }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="w-20">
                                                <input type="number" name="sort_order" value="{{ $lesson->sort_order }}" min="0" max="999" aria-label="{{ __('Sort order') }}"
                                                       class="w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900" />
                                            </div>
                                            <label class="flex cursor-pointer items-center gap-1.5 pb-2.5 text-xs text-slate-500 dark:text-slate-400">
                                                <input type="checkbox" name="is_active" value="1" @checked($lesson->is_active)
                                                       class="rounded border-slate-300 text-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900" />
                                                Active
                                            </label>
                                            <button type="submit" class="mb-1.5 rounded-xl bg-primary/10 px-3 py-1.5 text-xs font-semibold text-primary transition-colors hover:bg-primary/20">
                                                Save
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('admin.subjects.lessons.destroy', [$subject, $lesson]) }}"
                                              onsubmit="return confirm('Remove this lesson? Teacher and student selections for it will also be removed.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="mt-1 rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/30 dark:hover:text-rose-400">
                                                <span class="sr-only">Remove {{ $lesson->name }}</span>
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </form>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
