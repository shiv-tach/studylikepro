<script setup>
/**
 * The scoring panel. With a rubric it renders one row per criterion (number
 * input plus 0 / half / full shortcuts) and a live total; without one it falls
 * back to the original free-form label/value rows so old offerings keep working.
 */
import { computed } from 'vue';
import { usePanel } from '../../../shared/composables/usePanel';
import { useCopy } from '../../composables/usePageContext';

const props = defineProps({
    rubric: { type: Object, required: true },
    marks: { type: Array, default: () => [] },
    maxMarks: { type: Number, default: 50 },
    copy: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['set-score', 'add-mark', 'remove-mark']);

const copyAll = useCopy();
const panel = usePanel();
const labels = computed(() => props.copy ?? {});

const percent = computed(() => {
    if (!props.rubric.max) {
        return 0;
    }

    return Math.min(100, Math.round((props.rubric.total / props.rubric.max) * 100));
});

function half(criterion) {
    return Math.round((Number(criterion.max) / 2) * 10) / 10;
}
</script>

<template>
    <section :class="panel" aria-labelledby="studio-rubric-heading">
        <div class="flex items-start justify-between gap-2">
            <div>
                <h2 id="studio-rubric-heading" class="text-sm font-bold text-slate-800 dark:text-slate-100">
                    {{ labels.title }}
                </h2>
                <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">{{ labels.hint }}</p>
            </div>

            <div v-if="rubric.hasCriteria" class="shrink-0 text-right">
                <p class="text-2xl font-extrabold leading-none tracking-tight text-primary">{{ rubric.totalLabel }}</p>
                <p class="text-[10px] font-semibold text-slate-400">{{ (labels.outOf ?? 'out of :max').replace(':max', rubric.maxLabel) }}</p>
            </div>
        </div>

        <div v-if="rubric.hasCriteria" class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
            <div class="h-full rounded-full bg-gradient-to-r from-primary via-emerald-400 to-teal-400 transition-all duration-300" :style="{ width: `${percent}%` }"></div>
        </div>

        <div v-if="rubric.hasCriteria" class="mt-3 space-y-2.5">
            <div
                v-for="criterion in rubric.criteria"
                :key="criterion.key"
                class="rounded-xl border border-slate-200/80 p-2.5 dark:border-slate-800"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-xs font-bold text-slate-700 dark:text-slate-200">{{ criterion.title }}</p>
                        <p v-if="criterion.description" class="mt-0.5 text-[10px] leading-snug text-slate-400">{{ criterion.description }}</p>
                    </div>

                    <div class="flex shrink-0 items-center gap-1">
                        <input
                            type="number"
                            min="0"
                            :max="criterion.max"
                            step="0.5"
                            :value="criterion.score ?? ''"
                            :aria-label="criterion.title"
                            class="w-16 rounded-lg border-slate-300 bg-white px-2 py-1 text-right text-xs font-bold text-slate-700 focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                            @input="emit('set-score', criterion.key, $event.target.value)"
                        >
                        <span class="text-[10px] font-semibold text-slate-400">/ {{ criterion.maxLabel }}</span>
                    </div>
                </div>

                <div class="mt-1.5 flex gap-1">
                    <button type="button" class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-500 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300" @click="emit('set-score', criterion.key, 0)">0</button>
                    <button type="button" class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-500 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300" @click="emit('set-score', criterion.key, half(criterion))">{{ half(criterion) }}</button>
                    <button type="button" class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-500 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300" @click="emit('set-score', criterion.key, criterion.max)">{{ criterion.maxLabel }}</button>
                </div>
            </div>
        </div>

        <div v-else class="mt-3">
            <p class="text-xs font-bold text-slate-700 dark:text-slate-200">{{ labels.noCriteria }}</p>
            <p class="mt-0.5 text-[11px] leading-snug text-slate-500 dark:text-slate-400">{{ labels.noCriteriaHint }}</p>

            <div class="mt-2 space-y-1.5">
                <div v-for="(row, index) in marks" :key="index" class="flex items-center gap-1.5">
                    <input
                        v-model="row.label"
                        type="text"
                        maxlength="120"
                        class="min-w-0 flex-1 rounded-lg border-slate-300 bg-white px-2 py-1 text-xs text-slate-700 focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                    >
                    <input
                        v-model="row.value"
                        type="text"
                        maxlength="120"
                        class="w-20 shrink-0 rounded-lg border-slate-300 bg-white px-2 py-1 text-right text-xs font-bold text-slate-700 focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                    >
                    <button
                        type="button"
                        class="shrink-0 rounded-lg px-1.5 py-1 text-[11px] font-bold text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/30"
                        :aria-label="copyAll.studio?.feed?.remove ?? 'Remove'"
                        @click="emit('remove-mark', index)"
                    >✕</button>
                </div>
            </div>

            <button
                type="button"
                class="mt-2 rounded-lg border border-dashed border-slate-300 px-2.5 py-1 text-[11px] font-bold text-slate-500 transition-colors hover:border-primary hover:text-primary dark:border-slate-700 dark:text-slate-400"
                :disabled="marks.length >= maxMarks"
                @click="emit('add-mark')"
            >
                + {{ labels.addMark ?? copyAll.form?.addRow ?? 'Add row' }}
            </button>
        </div>
    </section>
</template>
