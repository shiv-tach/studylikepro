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
        @elseif (session('status') === 'basket-updated')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                Subject basket updated.
            </div>
        @elseif (session('status') === 'level-deleted')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                Education level deleted.
            </div>
        @elseif (session('status') === 'basket-deleted')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                Subject basket deleted. Its subjects stay in the catalog without a basket.
            </div>
        @elseif (session('status') === 'level-in-use')
            @php($inUse = session('levelInUse'))
            <div class="rounded-2xl border border-amber-200/80 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300">
                {{ __(':level still has :count :subjects. Move or delete them before deleting the level.', [
                    'level' => $inUse['name'] ?? 'This level',
                    'count' => $inUse['subjects'] ?? 0,
                    'subjects' => Str::plural('subject', $inUse['subjects'] ?? 0),
                ]) }}
            </div>
        @endif

        <p class="text-sm leading-relaxed text-slate-500 dark:text-slate-400">
            The Sri Lankan structure every subject and lesson hangs off — O/L (Grades 6–11) and A/L (Grades 12–13).
            Grades are fixed; levels can be renamed, reordered, switched off or deleted. Lessons are managed per subject, grade by grade; the O/L's optional
            subjects are grouped into the three baskets a Grade 10–11 student picks one subject from each.
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
                    <div class="flex items-center gap-4">
                        <a href="{{ route('admin.subjects.index', ['level' => $level->key]) }}"
                           class="text-xs font-semibold text-primary transition-colors hover:text-primary/80">
                            {{ __('Manage subjects') }} &rarr;
                        </a>
                        <form method="POST" action="{{ route('admin.curriculum.levels.destroy', $level) }}"
                              onsubmit="return confirm('{{ __('Delete this curriculum level? Its grades and subject baskets are removed too.') }}');">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="text-xs font-semibold text-rose-500 transition-colors hover:text-rose-600 dark:text-rose-400 dark:hover:text-rose-300">
                                {{ __('Delete level') }}
                            </button>
                        </form>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap gap-1.5">
                    @foreach ($level->grades as $grade)
                        <span class="{{ $chip }}">{{ $grade->label }}</span>
                    @endforeach
                </div>

                @if ($level->subjectBaskets->isNotEmpty())
                    <div class="mt-5 border-t border-slate-100 pt-4 dark:border-slate-800">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Subject baskets') }}</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ __('A student picks one subject from each basket their grade offers — Grades 6 to 9 choose their :basket subject, Grades 10 and 11 all three. Subjects join their basket in the subject editor.', [
                                // The basket that opens below Grade 10 is the one the junior grades
                                // pick from - Category II today, read from the basket rows so the
                                // copy follows any future reordering.
                                'basket' => $level->subjectBaskets
                                    ->first(fn ($basket) => ($gradeNumbers = $basket->gradeNumbers()) === [] || min($gradeNumbers) < 10)
                                    ?->name ?? __('basket'),
                            ]) }}
                        </p>

                        <div class="mt-3 space-y-3">
                            @foreach ($level->subjectBaskets as $basket)
                                <form method="POST" action="{{ route('admin.curriculum.baskets.update', $basket) }}" class="space-y-3">
                                    @csrf
                                    @method('PUT')

                                    <div class="flex flex-wrap items-end gap-4">
                                        <div class="w-20">
                                            <x-input-label for="basket-icon-{{ $basket->id }}" :value="__('Icon')" />
                                            <x-text-input id="basket-icon-{{ $basket->id }}" name="icon" type="text" maxlength="4"
                                                          class="mt-1 block w-full text-center" :value="old('icon', $basket->icon)" />
                                        </div>
                                        <div class="min-w-48 flex-1">
                                            <x-input-label for="basket-name-{{ $basket->id }}" :value="__('Basket name')" />
                                            <x-text-input id="basket-name-{{ $basket->id }}" name="name" type="text"
                                                          class="mt-1 block w-full" :value="old('name', $basket->name)" />
                                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                                        </div>
                                        <div class="w-28">
                                            <x-input-label for="basket-sort-{{ $basket->id }}" :value="__('Sort order')" />
                                            <x-text-input id="basket-sort-{{ $basket->id }}" name="sort_order" type="number" min="0" max="999"
                                                          class="mt-1 block w-full" :value="old('sort_order', $basket->sort_order)" />
                                            <x-input-error :messages="$errors->get('sort_order')" class="mt-2" />
                                        </div>
                                        <label class="flex cursor-pointer items-center gap-2 pb-2.5 text-sm text-slate-600 dark:text-slate-300">
                                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $basket->is_active))
                                                   class="rounded border-slate-300 text-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900" />
                                            Active
                                        </label>
                                        <x-primary-button class="mb-1">{{ __('Save') }}</x-primary-button>
                                        <span class="{{ $chip }} mb-2.5">{{ $basket->gradeRangeLabel() ?? __('Every grade') }}</span>
                                        <span class="{{ $chip }} mb-2.5">{{ $basket->subjects_count }} {{ Str::plural('subject', $basket->subjects_count) }}</span>
                                    </div>

                                    <div>
                                        <x-input-label for="basket-description-{{ $basket->id }}" :value="__('Line shown to students (optional)')" />
                                        <x-text-input id="basket-description-{{ $basket->id }}" name="description" type="text" maxlength="150"
                                                      class="mt-1 block w-full" :value="old('description', $basket->description)"
                                                      placeholder="e.g. Aesthetic, music, dancing, literature and drama subjects" />
                                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                                    </div>
                                </form>

                                <div class="flex justify-end">
                                    <form method="POST" action="{{ route('admin.curriculum.baskets.destroy', $basket) }}"
                                          onsubmit="return confirm('{{ __('Delete this basket? Its subjects stay in the catalog but are no longer grouped by it.') }}');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="text-xs font-semibold text-rose-500 transition-colors hover:text-rose-600 dark:text-rose-400 dark:hover:text-rose-300">
                                            {{ __('Delete basket') }}
                                        </button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</x-app-layout>
