@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ $subject->icon ?? '📘' }} {{ $subject->name }}</h2>
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
        @elseif (session('status') === 'topic-created')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">Topic added.</div>
        @elseif (session('status') === 'topic-updated')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">Topic updated.</div>
        @elseif (session('status') === 'topic-removed')
            <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-4 text-sm text-slate-600 dark:border-slate-800/80 dark:bg-slate-900/60 dark:text-slate-300">Topic removed.</div>
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

            <!-- Topics -->
            <div class="{{ $cardBase }} lg:col-span-2" :class="{{ $cardTheme }}">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Topics</h3>
                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                        {{ $subject->topics->count() }} total
                    </span>
                </div>

                <!-- Add topic -->
                <form method="POST" action="{{ route('admin.subjects.topics.store', $subject) }}" class="mt-5 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200/80 p-4 dark:border-slate-800">
                    @csrf
                    <div class="min-w-40 flex-1">
                        <x-input-label for="new-topic-name" :value="__('New topic')" />
                        <x-text-input id="new-topic-name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" placeholder="e.g. Algebra" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div class="w-28">
                        <x-input-label for="new-topic-sort" :value="__('Sort')" />
                        <x-text-input id="new-topic-sort" name="sort_order" type="number" min="0" max="999" class="mt-1 block w-full" :value="old('sort_order', 0)" />
                    </div>
                    <x-primary-button>{{ __('Add topic') }}</x-primary-button>
                </form>

                @if ($subject->topics->isEmpty())
                    <p class="mt-5 text-sm text-slate-400">No topics yet — add the first one above.</p>
                @else
                    <ul class="mt-5 divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($subject->topics as $topic)
                            <li class="flex flex-wrap items-center gap-3 py-3 first:pt-0 last:pb-0">
                                <form method="POST" action="{{ route('admin.subjects.topics.update', [$subject, $topic]) }}"
                                      class="flex flex-1 flex-wrap items-center gap-3">
                                    @csrf
                                    @method('PUT')
                                    <input type="text" name="name" value="{{ $topic->name }}" required
                                           class="min-w-40 flex-1 rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900" />
                                    <input type="number" name="sort_order" value="{{ $topic->sort_order }}" min="0" max="999"
                                           class="w-20 rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900" />
                                    <label class="flex cursor-pointer items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                                        <input type="checkbox" name="is_active" value="1" @checked($topic->is_active)
                                               class="rounded border-slate-300 text-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900" />
                                        Active
                                    </label>
                                    <button type="submit" class="rounded-xl bg-primary/10 px-3 py-1.5 text-xs font-semibold text-primary transition-colors hover:bg-primary/20">
                                        Save
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('admin.subjects.topics.destroy', [$subject, $topic]) }}"
                                      onsubmit="return confirm('Remove this topic? Teacher and student selections for it will also be removed.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/30 dark:hover:text-rose-400">
                                        <span class="sr-only">Remove {{ $topic->name }}</span>
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
        </div>
    </div>
</x-app-layout>
