<script setup>
/**
 * The history filters: status, subject, teacher and a date range. This stays a
 * real GET form, so the query string Laravel validates is built by the browser
 * and the panel only renders the options Blade pre-labelled.
 */
import { usePanel } from '../../shared/composables/usePanel';
import { useCopy, useUrls } from '../composables/usePageContext';

defineProps({
    tab: { type: String, required: true },
    filters: { type: Object, required: true },
    statusOptions: { type: Array, default: () => [] },
    subjectOptions: { type: Array, default: () => [] },
    teacherOptions: { type: Array, default: () => [] },
    hasActiveFilters: { type: Boolean, default: false },
    matchLabel: { type: String, default: null },
});

const copy = useCopy();
const urls = useUrls();
const panel = usePanel();

const labelClass = 'block font-semibold text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400';
const fieldClass =
    'mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';
const submitClass =
    'inline-flex items-center justify-center rounded-xl bg-primary px-4 py-2.5 text-sm font-bold text-white shadow-md shadow-primary/20 transition-all duration-200 hover:bg-primary/90 hover:shadow-lg hover:shadow-primary/25 active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-primary/30';
</script>

<template>
    <form method="GET" :action="urls.index" :class="panel" :aria-label="copy.filterHistory">
        <input type="hidden" name="tab" :value="tab">

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div>
                <label for="filter-status" :class="labelClass">{{ copy.status }}</label>
                <select id="filter-status" name="status" :class="fieldClass">
                    <option value="">{{ copy.anyStatus }}</option>
                    <option
                        v-for="option in statusOptions"
                        :key="option.value"
                        :value="option.value"
                        :selected="option.value === filters.status"
                    >
                        {{ option.label }}
                    </option>
                </select>
            </div>

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
                <label for="filter-teacher" :class="labelClass">{{ copy.teacher }}</label>
                <select id="filter-teacher" name="teacher" :class="fieldClass">
                    <option value="">{{ copy.anyTeacher }}</option>
                    <option
                        v-for="option in teacherOptions"
                        :key="option.value"
                        :value="option.value"
                        :selected="option.value === filters.teacher"
                    >
                        {{ option.label }}
                    </option>
                </select>
            </div>

            <div>
                <label for="filter-from" :class="labelClass">{{ copy.from }}</label>
                <input id="filter-from" type="date" name="from" :value="filters.from" :class="fieldClass">
            </div>

            <div>
                <label for="filter-to" :class="labelClass">{{ copy.to }}</label>
                <input id="filter-to" type="date" name="to" :value="filters.to" :class="fieldClass">
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <button type="submit" :class="submitClass">{{ copy.filter }}</button>

            <a
                v-if="hasActiveFilters"
                :href="urls.reset"
                class="text-sm font-semibold text-slate-500 transition-colors hover:text-primary"
            >
                {{ copy.reset }}
            </a>

            <span v-if="matchLabel" class="text-xs text-slate-400">{{ matchLabel }}</span>
        </div>
    </form>
</template>
