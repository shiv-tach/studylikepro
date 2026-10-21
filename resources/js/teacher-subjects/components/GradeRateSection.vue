<script setup>
/**
 * Step 1: the grades this subject covers and the hourly rate for each. Ticking
 * a grade enables its rate and its lessons; an empty rate falls back to the
 * default (and then the teacher's base rate) on the server.
 */
import { useCopy, usePage } from '../composables/usePageContext';
import SetupSectionHeading from './SetupSectionHeading.vue';
import { SMALL_FIELD } from '../styles';

defineProps({
    subject: { type: Object, required: true },
});

const copy = useCopy();
const page = usePage();
</script>

<template>
    <section aria-labelledby="subject-grades-heading">
        <SetupSectionHeading
            id="subject-grades-heading"
            :step="copy.grades?.step"
            :title="copy.grades?.title"
            :hint="copy.grades?.hint"
        />

        <div v-if="subject.grades.length" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <label
                v-for="grade in subject.grades"
                :key="grade.id"
                class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border px-3 py-2 transition-colors focus-within:ring-2 focus-within:ring-primary/40"
                :class="grade.selected
                    ? 'border-primary/40 bg-primary/5 dark:border-primary/40 dark:bg-primary/10'
                    : 'border-slate-200/80 hover:border-primary/30 dark:border-slate-800 dark:hover:border-primary/30'"
            >
                <span class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-200">
                    <input
                        type="checkbox"
                        name="grades[]"
                        :value="grade.id"
                        v-model="grade.selected"
                        class="rounded border-slate-300 text-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900"
                    >
                    {{ grade.label }}
                </span>

                <span class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-xs text-slate-400">
                        {{ page.teacher?.currencySymbol }}
                    </span>
                    <input
                        type="number"
                        :name="`grade_rates[${grade.id}]`"
                        min="100"
                        max="100000"
                        step="50"
                        inputmode="decimal"
                        v-model.number="grade.rate"
                        :disabled="!grade.selected"
                        :placeholder="String(subject.baseRatePlaceholder ?? '')"
                        :aria-label="`${copy.grades?.rateFor ?? 'Hourly rate for'} ${grade.label}`"
                        :class="[SMALL_FIELD, 'w-28 pl-7 pr-2 text-right disabled:cursor-not-allowed disabled:opacity-40']"
                    >
                </span>
            </label>
        </div>

        <p v-else class="rounded-xl border border-dashed border-slate-300 p-4 text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
            {{ copy.grades?.noGrades }}
        </p>
    </section>
</template>
