<script setup>
/**
 * The tool rail: pointer, freehand pen, highlighter, straight shapes, pins,
 * correction cards and the teacher's one-click stamps. Every control is a real
 * button with a visible focus ring; the shortcuts are printed next to the tool.
 */
import { computed } from 'vue';
import { usePanel } from '../../../shared/composables/usePanel';
import { useCopy } from '../../composables/usePageContext';

const props = defineProps({
    tool: { type: String, default: 'select' },
    stamps: { type: Array, default: () => [] },
    brush: { type: Object, required: true },
    activeStamp: { type: Object, default: null },
});

const emit = defineEmits(['set-tool', 'set-stamp', 'set-color', 'clear-page']);

const copy = useCopy();
const panel = usePanel();

const tools = computed(() => [
    { key: 'select', icon: '↖', label: copy.studio?.tools?.select, shortcut: 'V' },
    { key: 'pen', icon: '🖊', label: copy.studio?.tools?.pen, shortcut: 'P' },
    { key: 'highlighter', icon: '🖍', label: copy.studio?.tools?.highlighter, shortcut: 'H' },
    { key: 'line', icon: '╱', label: copy.studio?.tools?.line, shortcut: 'L' },
    { key: 'ellipse', icon: '◯', label: copy.studio?.tools?.ellipse, shortcut: 'E' },
    { key: 'comment', icon: '📌', label: copy.studio?.tools?.comment, shortcut: 'C' },
    { key: 'correction', icon: '✨', label: copy.studio?.tools?.correction, shortcut: 'K' },
]);

const colors = ['#e11d48', '#2563eb', '#059669', '#d97706', '#7c3aed', '#0f172a'];

function buttonClass(key) {
    return key === props.tool
        ? 'flex w-full items-center justify-between gap-2 rounded-xl border border-primary/40 bg-primary/10 px-3 py-2 text-left text-xs font-bold text-primary shadow-sm transition-colors'
        : 'flex w-full items-center justify-between gap-2 rounded-xl border border-transparent px-3 py-2 text-left text-xs font-semibold text-slate-600 transition-colors hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800/70';
}
</script>

<template>
    <section :class="panel" aria-labelledby="studio-tools-heading">
        <h2 id="studio-tools-heading" class="text-[11px] font-bold uppercase tracking-wider text-slate-400">
            {{ copy.studio?.tools?.label }}
        </h2>

        <div class="mt-2 space-y-1">
            <button
                v-for="entry in tools"
                :key="entry.key"
                type="button"
                :class="buttonClass(entry.key)"
                :aria-pressed="tool === entry.key"
                @click="emit('set-tool', entry.key)"
            >
                <span class="flex items-center gap-2">
                    <span class="text-base leading-none" aria-hidden="true">{{ entry.icon }}</span>
                    <span>{{ entry.label }}</span>
                </span>
                <kbd class="rounded border border-slate-200 px-1 py-0.5 text-[9px] font-bold text-slate-400 dark:border-slate-700">{{ entry.shortcut }}</kbd>
            </button>
        </div>

        <div class="mt-3 border-t border-slate-200 pt-3 dark:border-slate-800">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ copy.studio?.tools?.color }}</p>
            <div class="mt-2 flex flex-wrap gap-1.5">
                <button
                    v-for="color in colors"
                    :key="color"
                    type="button"
                    class="h-6 w-6 rounded-full ring-2 ring-offset-2 transition-transform hover:scale-110 focus:outline-none focus-visible:ring-primary dark:ring-offset-slate-900"
                    :class="brush.color === color ? 'ring-primary' : 'ring-transparent'"
                    :style="{ backgroundColor: color }"
                    :aria-label="color"
                    :aria-pressed="brush.color === color"
                    @click="emit('set-color', color)"
                ></button>
            </div>
        </div>

        <div class="mt-3 border-t border-slate-200 pt-3 dark:border-slate-800">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ copy.studio?.tools?.stampGroup }}</p>
            <div class="mt-2 space-y-1">
                <button
                    v-for="stamp in stamps"
                    :key="stamp.key"
                    type="button"
                    class="flex w-full items-center justify-between gap-2 rounded-xl border px-2.5 py-2 text-left text-[11px] font-semibold transition-colors"
                    :class="activeStamp?.key === stamp.key
                        ? 'border-primary bg-primary/10 text-primary'
                        : 'border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800/60'"
                    :aria-pressed="activeStamp?.key === stamp.key"
                    @click="emit('set-stamp', stamp)"
                >
                    <span class="flex items-center gap-1.5">
                        <span aria-hidden="true">{{ stamp.icon }}</span>
                        {{ stamp.label }}
                    </span>
                    <span
                        v-if="stamp.points !== null"
                        class="rounded px-1 py-0.5 text-[9px] font-bold text-white"
                        :style="{ backgroundColor: stamp.color }"
                    >+{{ stamp.points }}</span>
                </button>
            </div>
        </div>

        <div class="mt-3 border-t border-slate-200 pt-3 dark:border-slate-800">
            <button
                type="button"
                class="w-full rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 transition-colors hover:bg-rose-100 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-300"
                @click="emit('clear-page')"
            >
                🧹 {{ copy.studio?.tools?.clearPage }}
            </button>
        </div>
    </section>
</template>
