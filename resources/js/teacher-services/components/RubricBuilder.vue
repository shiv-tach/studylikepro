<script setup>
/**
 * Rubric authoring for paper-marking offerings. The rows are the criteria the
 * marking studio scores against; the keys are stable so a paper already in a
 * teacher's queue keeps its scores when the rubric is edited later.
 */
import { computed } from 'vue';
import { useCopy, usePage, useWorkspace } from '../composables/usePageContext';

const FIELD = 'block w-full rounded-xl border-slate-300 bg-white text-xs shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';

const copy = useCopy();
const page = usePage();
const workspace = useWorkspace();

const { values, errors } = workspace;
const fields = computed(() => copy.fields ?? {});
const limits = computed(() => page.limits ?? {});

const rubricErrors = computed(() => errors.value.rubric ?? []);

const totalPoints = computed(() => values.rubric
    .map((row) => Number(row.max))
    .filter((value) => Number.isFinite(value) && value > 0)
    .reduce((sum, value) => sum + value, 0));

function nextKey() {
    const used = new Set(values.rubric.map((row) => row.key));
    let index = values.rubric.length + 1;

    while (used.has(`c${index}`)) {
        index += 1;
    }

    return `c${index}`;
}

function addRow() {
    if (values.rubric.length >= (limits.value.rubricMaxCount ?? 20)) {
        return;
    }

    values.rubric.push({ key: nextKey(), title: '', max: '', description: '' });
}

function removeRow(index) {
    values.rubric.splice(index, 1);
}
</script>

<template>
    <section class="space-y-3 rounded-xl border border-dashed border-slate-300 p-4 dark:border-slate-700">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ fields.rubricTitle }}</h4>
                <p class="mt-1 text-[11px] leading-relaxed text-slate-400">{{ fields.rubricHint }}</p>
            </div>

            <span class="rounded-full bg-primary/10 px-2.5 py-1 text-[10px] font-bold text-primary">
                {{ (fields.rubricTotal ?? 'Total :points points').replace(':points', totalPoints) }}
            </span>
        </div>

        <p v-if="rubricErrors.length" class="rounded-lg bg-rose-50 px-2.5 py-1.5 text-[11px] font-semibold text-rose-700 dark:bg-rose-950/30 dark:text-rose-300">
            {{ rubricErrors[0] }}
        </p>

        <p v-if="values.rubric.length === 0" class="text-[11px] text-slate-400">{{ fields.rubricEmpty }}</p>

        <div v-else class="space-y-2">
            <div
                v-for="(row, index) in values.rubric"
                :key="row.key"
                class="grid gap-2 rounded-xl border border-slate-200/80 p-2.5 sm:grid-cols-[minmax(0,1fr)_5.5rem_2rem] dark:border-slate-800"
            >
                <input type="hidden" :name="`rubric[${index}][key]`" :value="row.key">

                <div class="min-w-0 space-y-1.5">
                    <input
                        v-model="row.title"
                        type="text"
                        :name="`rubric[${index}][title]`"
                        :maxlength="limits.rubricTitleMax ?? 120"
                        :placeholder="fields.rubricCriterionPlaceholder"
                        :aria-label="fields.rubricCriterion"
                        :class="FIELD"
                    >
                    <input
                        v-model="row.description"
                        type="text"
                        :name="`rubric[${index}][description]`"
                        :maxlength="limits.rubricDescriptionMax ?? 500"
                        :placeholder="fields.rubricDescriptionPlaceholder"
                        :aria-label="fields.rubricDescription"
                        :class="FIELD"
                    >
                </div>

                <div>
                    <input
                        v-model="row.max"
                        type="number"
                        min="1"
                        :max="limits.rubricPointsMax ?? 1000"
                        :name="`rubric[${index}][max]`"
                        :placeholder="fields.rubricPoints"
                        :aria-label="fields.rubricPoints"
                        :class="FIELD"
                    >
                </div>

                <button
                    type="button"
                    class="flex h-8 w-8 items-center justify-center self-center rounded-lg text-slate-400 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/30"
                    :aria-label="fields.rubricRemove"
                    @click="removeRow(index)"
                >✕</button>
            </div>
        </div>

        <button
            type="button"
            class="rounded-lg border border-dashed border-slate-300 px-3 py-1.5 text-[11px] font-bold text-slate-500 transition-colors hover:border-primary hover:text-primary disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700 dark:text-slate-400"
            :disabled="values.rubric.length >= (limits.rubricMaxCount ?? 20)"
            @click="addRow"
        >
            + {{ fields.rubricAdd }}
        </button>
    </section>
</template>
