<script setup>
/**
 * The studio's command bar: who the paper belongs to, whether the work is
 * saved, undo/redo and the single delivery action. It never formats a date or
 * builds a URL — Blade already did both.
 */
import { computed } from 'vue';
import { usePanel } from '../../../shared/composables/usePanel';
import { useCopy } from '../../composables/usePageContext';

const props = defineProps({
    order: { type: Object, required: true },
    autosaveStatus: { type: String, default: 'idle' },
    savedAtLabel: { type: String, default: null },
    canUndo: { type: Boolean, default: false },
    canRedo: { type: Boolean, default: false },
    canDeliver: { type: Boolean, default: true },
    nextUrl: { type: String, default: null },
    urls: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['undo', 'redo', 'deliver']);

const copy = useCopy();
const panel = usePanel();

const autosaveLabel = computed(() => {
    const bar = copy.studio?.topBar ?? {};

    if (props.autosaveStatus === 'saving') {
        return bar.autosaveSaving;
    }

    if (props.autosaveStatus === 'saved') {
        return props.savedAtLabel ? String(bar.autosaveSaved).replace(':time', props.savedAtLabel) : bar.autosaveSaved;
    }

    if (props.autosaveStatus === 'error') {
        return bar.autosaveError;
    }

    return bar.autosaveIdle;
});

const autosaveTone = computed(() => {
    if (props.autosaveStatus === 'error') {
        return 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300';
    }

    if (props.autosaveStatus === 'saved') {
        return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300';
    }

    return 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300';
});
</script>

<template>
    <header :class="panel">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <a :href="urls.index" class="text-xs font-semibold text-slate-400 transition-colors hover:text-primary">
                    ← {{ copy.studio?.topBar?.back }}
                </a>
                <div class="mt-1 flex flex-wrap items-center gap-2">
                    <h1 class="truncate text-lg font-extrabold tracking-tight text-slate-800 dark:text-slate-100">
                        {{ order.studentName }}
                    </h1>
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                        {{ order.reference }}
                    </span>
                    <span class="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-bold text-primary">
                        {{ order.subjectName }}
                    </span>
                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                        {{ order.status?.label }}
                    </span>
                    <span v-if="order.overdue" class="rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-700 dark:bg-rose-950/40 dark:text-rose-300">
                        {{ copy.hero?.overdue }}
                    </span>
                </div>
                <p v-if="order.deadlineLabel" class="mt-1 text-xs text-slate-400">
                    {{ order.deadlineLabel }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-full px-3 py-1 text-[11px] font-semibold" :class="autosaveTone" aria-live="polite">
                    {{ autosaveLabel }}
                </span>

                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 text-sm font-bold text-slate-600 transition-colors hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                        :disabled="!canUndo"
                        :title="copy.studio?.tools?.undo"
                        :aria-label="copy.studio?.tools?.undo"
                        @click="emit('undo')"
                    >↶</button>
                    <button
                        type="button"
                        class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 text-sm font-bold text-slate-600 transition-colors hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                        :disabled="!canRedo"
                        :title="copy.studio?.tools?.redo"
                        :aria-label="copy.studio?.tools?.redo"
                        @click="emit('redo')"
                    >↷</button>
                </div>

                <a
                    v-if="nextUrl"
                    :href="nextUrl"
                    class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 transition-colors hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                >
                    {{ copy.studio?.topBar?.saveNext }}
                </a>

                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-bold text-white shadow-sm shadow-primary/20 transition-colors hover:bg-primary/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!canDeliver"
                    @click="emit('deliver')"
                >
                    {{ copy.studio?.topBar?.deliver }}
                </button>
            </div>
        </div>
    </header>
</template>
