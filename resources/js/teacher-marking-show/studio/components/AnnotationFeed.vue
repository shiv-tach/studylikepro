<script setup>
/**
 * A text list of everything placed on the paper. It is the accessible twin of
 * the canvas markup: every pin, stamp and correction is reachable here, can be
 * located on the page and removed without touching the drawing.
 */
import { usePanel } from '../../../shared/composables/usePanel';
import { useCopy } from '../../composables/usePageContext';

const props = defineProps({
    rows: { type: Array, default: () => [] },
    currentKey: { type: String, default: '' },
    copy: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['locate', 'remove']);

const panel = usePanel();
const copyAll = useCopy();

function kindLabel(row) {
    if (row.kind === 'strokes') {
        return `✏️ ${row.count}`;
    }

    if (row.kind === 'stamp') {
        return `🏷 ${props.copy.stamp ?? 'Stamp'}`;
    }

    if (row.kind === 'correction') {
        return `✨ ${props.copy.correction ?? 'Correction'}`;
    }

    return `📌 ${props.copy.comment ?? 'Comment'}`;
}

function rowKey(row) {
    return `${row.file}:${row.page}`;
}
</script>

<template>
    <section :class="panel" aria-labelledby="studio-feed-heading">
        <div class="flex items-center justify-between gap-2">
            <h2 id="studio-feed-heading" class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ copy.title }}</h2>
            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-300">{{ rows.length }}</span>
        </div>

        <p v-if="rows.length === 0" class="mt-2 text-[11px] leading-snug text-slate-400">{{ copy.empty }}</p>

        <ul v-else class="mt-2 max-h-72 space-y-1.5 overflow-y-auto pr-1">
            <li
                v-for="row in rows"
                :key="row.id"
                class="group flex items-start gap-2 rounded-xl border px-2.5 py-2 transition-colors"
                :class="rowKey(row) === currentKey
                    ? 'border-primary/40 bg-primary/5'
                    : 'border-slate-200/80 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/50'"
            >
                <button type="button" class="min-w-0 flex-1 text-left" @click="emit('locate', row)">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">
                        {{ kindLabel(row) }} · {{ (copy.page ?? 'Page :page').replace(':page', row.page + 1) }}
                    </span>
                    <span v-if="row.kind !== 'strokes'" class="mt-0.5 block text-[11px] font-medium leading-snug text-slate-700 dark:text-slate-200">
                        {{ row.text }}
                    </span>
                </button>

                <button
                    v-if="row.kind !== 'strokes'"
                    type="button"
                    class="shrink-0 rounded-md px-1 py-0.5 text-[11px] font-bold text-slate-300 opacity-0 transition-opacity hover:bg-rose-50 hover:text-rose-600 group-hover:opacity-100 dark:hover:bg-rose-950/30"
                    :aria-label="copyAll.studio?.feed?.remove ?? 'Remove'"
                    @click="emit('remove', row)"
                >✕</button>
            </li>
        </ul>
    </section>
</template>
