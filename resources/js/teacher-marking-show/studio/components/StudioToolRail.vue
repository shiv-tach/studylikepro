<script setup>
/**
 * The tool rail: pointer, freehand pen, highlighter, straight shapes, pins,
 * correction cards and the teacher's one-click stamps. Every control is a real
 * button with a visible focus ring; the shortcuts are printed next to the tool.
 */
import { computed } from 'vue';
import { usePanel } from '../../../shared/composables/usePanel';
import { useCopy } from '../../composables/usePageContext';
import { DEFAULT_STAMP_SIZE, MAX_STAMP_SIZE, MIN_STAMP_SIZE } from '../renderAnnotations';

const props = defineProps({
    tool: { type: String, default: 'select' },
    stamps: { type: Array, default: () => [] },
    brush: { type: Object, required: true },
    activeStamp: { type: Object, default: null },
    stampSize: { type: Number, default: DEFAULT_STAMP_SIZE },
});

const emit = defineEmits(['set-tool', 'set-stamp', 'set-stamp-size', 'end-stamp-size', 'set-color', 'clear-page']);

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

// The slider speaks in percentages of the default glyph so "100%" always means
// the size a stamp had before the teacher touched anything.
const stampSizePercent = computed(() => Math.round((props.stampSize / DEFAULT_STAMP_SIZE) * 100));
const stampPreviewSize = computed(() => Math.round(14 + ((props.stampSize - MIN_STAMP_SIZE) / (MAX_STAMP_SIZE - MIN_STAMP_SIZE)) * 16));

function onStampSizeInput(event) {
    emit('set-stamp-size', Number(event.target.value));
}

function resetStampSize() {
    emit('set-stamp-size', DEFAULT_STAMP_SIZE);
    emit('end-stamp-size');
}

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
                    class="flex w-full items-center gap-2 rounded-xl border px-2.5 py-2 text-left text-[11px] font-semibold transition-colors"
                    :class="activeStamp?.key === stamp.key
                        ? 'border-primary bg-primary/10 text-primary'
                        : 'border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800/60'"
                    :aria-pressed="activeStamp?.key === stamp.key"
                    @click="emit('set-stamp', stamp)"
                >
                    <span
                        class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[11px] font-bold leading-none text-white shadow-sm"
                        :style="{ backgroundColor: stamp.color }"
                        aria-hidden="true"
                    >{{ stamp.icon }}</span>
                    {{ stamp.label }}
                </button>
            </div>

            <div v-if="stamps.length" class="mt-3">
                <div class="flex items-center justify-between gap-2">
                    <label for="studio-stamp-size" class="text-[11px] font-bold uppercase tracking-wider text-slate-400">
                        {{ copy.studio?.tools?.stampSize }}
                    </label>
                    <button
                        type="button"
                        class="rounded-md px-1.5 py-0.5 text-[10px] font-bold text-slate-500 transition-colors hover:bg-slate-100 hover:text-primary dark:text-slate-400 dark:hover:bg-slate-800"
                        :title="copy.studio?.tools?.stampSizeReset"
                        :aria-label="`${copy.studio?.tools?.stampSizeReset} (${stampSizePercent}%)`"
                        @click="resetStampSize"
                    >{{ stampSizePercent }}%</button>
                </div>

                <div class="mt-1.5 flex items-center gap-2">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 dark:border-slate-700" aria-hidden="true">
                        <span
                            class="font-bold leading-none"
                            :style="{ fontSize: `${stampPreviewSize}px`, color: activeStamp?.color ?? '#4f46e5' }"
                        >{{ activeStamp?.icon ?? '✓' }}</span>
                    </span>

                    <input
                        id="studio-stamp-size"
                        type="range"
                        class="h-1.5 w-full cursor-pointer accent-primary"
                        :min="MIN_STAMP_SIZE"
                        :max="MAX_STAMP_SIZE"
                        step="0.002"
                        :value="stampSize"
                        @input="onStampSizeInput"
                        @change="emit('end-stamp-size')"
                    >
                </div>
            </div>

            <p class="mt-2 text-[10px] font-medium leading-snug text-slate-400 dark:text-slate-500">
                {{ copy.studio?.tools?.dragHint }}
            </p>
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
