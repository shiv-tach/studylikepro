<script setup>
/**
 * The finder's filters: subject, language, sort order and the day plus time
 * window the student wants their lesson. This stays a real GET form, so the
 * query string Laravel validates is built by the browser — the Vue app only
 * renders the options Blade pre-labelled.
 */
import { usePanel } from '../../shared/composables/usePanel';
import AppIcon from '../../shared/components/AppIcon.vue';
import { useCopy, useUrls } from '../composables/usePageContext';

defineProps({
    filters: { type: Object, required: true },
    subjectOptions: { type: Array, default: () => [] },
    languageOptions: { type: Array, default: () => [] },
    sortOptions: { type: Array, default: () => [] },
    today: { type: String, default: '' },
    totalLabel: { type: String, default: null },
    errorMessage: { type: String, default: null },
});

const copy = useCopy();
const urls = useUrls();
const panel = usePanel();

const labelClass = 'block text-xs font-semibold uppercase tracking-wider text-slate-400';
const fieldClass =
    'mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm transition-colors focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';
const submitClass =
    'inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary/20 transition-all duration-200 hover:bg-primary/90 hover:shadow-lg hover:shadow-primary/25 active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-primary/30';
</script>

<template>
    <form method="GET" :action="urls.index" :class="panel" :aria-label="copy.filtersLabel">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
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

            <span v-if="totalLabel" class="ml-auto text-sm text-slate-400">{{ totalLabel }}</span>
        </div>

        <p v-if="errorMessage" class="mt-3 text-sm text-rose-600 dark:text-rose-400" role="alert">
            {{ errorMessage }}
        </p>
    </form>
</template>
