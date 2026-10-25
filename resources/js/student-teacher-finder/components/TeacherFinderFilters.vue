<script setup>
/**
 * The finder's filters: subject, language, sort order and the day plus time
 * window the student wants their lesson. This stays a real GET form, so the
 * query string Laravel validates is built by the browser — the Vue app only
 * renders the options Blade pre-labelled. The panel starts folded so the
 * teacher cards own the fold, and unfolds on its own when the page arrives
 * with a narrowed result set or a rejected one.
 */
import { computed, ref } from 'vue';
import { usePanel } from '../../shared/composables/usePanel';
import AppIcon from '../../shared/components/AppIcon.vue';
import { useCopy, useUrls } from '../composables/usePageContext';

const props = defineProps({
    filters: { type: Object, required: true },
    subjectOptions: { type: Array, default: () => [] },
    languageOptions: { type: Array, default: () => [] },
    sortOptions: { type: Array, default: () => [] },
    today: { type: String, default: '' },
    totalLabel: { type: String, default: null },
    errorMessage: { type: String, default: null },
    hasActiveFilters: { type: Boolean, default: false },
});

const copy = useCopy();
const urls = useUrls();
const panel = usePanel();

// The sort order alone is not a filter, so it never counts towards the badge.
const activeFilterCount = computed(() =>
    ['subject', 'language', 'date', 'time_from', 'time_to']
        .filter((key) => String(props.filters[key] ?? '').trim() !== '')
        .length,
);

const filtersOpen = ref(props.hasActiveFilters || props.errorMessage !== null);

const toggleClass =
    'inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 transition-colors hover:border-primary/40 hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/30 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:text-slate-100';
const labelClass = 'block text-xs font-semibold uppercase tracking-wider text-slate-400';
const fieldClass =
    'mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm transition-colors focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';
const submitClass =
    'inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary/20 transition-all duration-200 hover:bg-primary/90 hover:shadow-lg hover:shadow-primary/25 active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-primary/30';
</script>

<template>
    <div :class="panel">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <button
                type="button"
                :class="toggleClass"
                :aria-expanded="filtersOpen"
                aria-controls="student-finder-filters"
                @click="filtersOpen = !filtersOpen"
            >
                <span class="h-4 w-4"><AppIcon name="filter" /></span>
                {{ filtersOpen ? copy.filtersHide : copy.filtersToggle }}
                <span
                    v-if="activeFilterCount"
                    class="inline-flex min-w-5 items-center justify-center rounded-full bg-primary px-1.5 text-[11px] font-bold text-white"
                >{{ activeFilterCount }}</span>
                <span
                    class="h-4 w-4 transition-transform duration-200"
                    :class="filtersOpen ? 'rotate-180' : ''"
                ><AppIcon name="chevron-down" /></span>
            </button>

            <span v-if="totalLabel" class="text-sm text-slate-400">{{ totalLabel }}</span>
        </div>

        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 -translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-1"
        >
            <form
                v-if="filtersOpen"
                id="student-finder-filters"
                method="GET"
                :action="urls.index"
                :aria-label="copy.filtersLabel"
                class="mt-5"
            >
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6">
                    <div>
                        <label for="filter-subject" :class="labelClass">{{ copy.subject }}</label>
                        <select id="filter-subject" name="subject" :class="fieldClass">
                            <option value="">{{ copy.anySubject }}</option>
                            <option
                                v-for="option in subjectOptions"
                                :key="option.value"
                                :value="option.value"
                                :selected="option.value === filters.subject"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label for="filter-language" :class="labelClass">{{ copy.language }}</label>
                        <select id="filter-language" name="language" :class="fieldClass">
                            <option value="">{{ copy.anyLanguage }}</option>
                            <option
                                v-for="language in languageOptions"
                                :key="language"
                                :value="language"
                                :selected="language === filters.language"
                            >
                                {{ language }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label for="filter-sort" :class="labelClass">{{ copy.sortBy }}</label>
                        <select id="filter-sort" name="sort" :class="fieldClass">
                            <option
                                v-for="option in sortOptions"
                                :key="option.value"
                                :value="option.value"
                                :selected="option.value === filters.sort"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label for="filter-date" :class="labelClass">{{ copy.date }}</label>
                        <input
                            id="filter-date"
                            name="date"
                            type="date"
                            :min="today"
                            :value="filters.date"
                            :class="fieldClass"
                        >
                        <p class="mt-1 text-xs text-slate-400">{{ copy.anyDay }}</p>
                    </div>

                    <div>
                        <label for="filter-time-from" :class="labelClass">{{ copy.from }}</label>
                        <input
                            id="filter-time-from"
                            name="time_from"
                            type="time"
                            :value="filters.time_from"
                            :class="fieldClass"
                        >
                    </div>

                    <div>
                        <label for="filter-time-to" :class="labelClass">{{ copy.to }}</label>
                        <input
                            id="filter-time-to"
                            name="time_to"
                            type="time"
                            :value="filters.time_to"
                            :class="fieldClass"
                        >
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap items-center gap-3">
                    <button type="submit" :class="submitClass">
                        <span class="h-4 w-4"><AppIcon name="search" /></span>
                        {{ copy.submit }}
                    </button>

                    <a
                        :href="urls.reset"
                        class="text-sm font-semibold text-slate-500 transition-colors hover:text-primary dark:text-slate-400 dark:hover:text-slate-200"
                    >
                        {{ copy.clear }}
                    </a>
                </div>

                <p v-if="errorMessage" class="mt-3 text-sm text-rose-600 dark:text-rose-400" role="alert">
                    {{ errorMessage }}
                </p>
            </form>
        </Transition>
    </div>
</template>
