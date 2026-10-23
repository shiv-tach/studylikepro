<script setup>
/**
 * The live classroom. Blade decides whether the room is open, still waiting for
 * its join window, failed or simply not live; this panel only renders that
 * state and the links it was handed.
 */
import { computed } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';
import { usePanel } from '../../shared/composables/usePanel';
import { useCopy } from '../composables/usePageContext';

const props = defineProps({
    join: { type: Object, required: true },
});

const copy = useCopy();
const panel = usePanel();

const TONES = {
    open: {
        card: 'border-emerald-200/80 bg-emerald-50/70 dark:border-emerald-900/40 dark:bg-emerald-950/20',
        tile: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        label: 'text-emerald-700 dark:text-emerald-300',
    },
    failed: {
        card: 'border-rose-200/80 bg-rose-50/70 dark:border-rose-900/40 dark:bg-rose-950/20',
        tile: 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
        label: 'text-rose-700 dark:text-rose-300',
    },
    waiting: {
        card: 'border-sky-200/80 bg-sky-50/70 dark:border-sky-900/40 dark:bg-sky-950/20',
        tile: 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300',
        label: 'text-sky-700 dark:text-sky-300',
    },
    closed: {
        card: '',
        tile: 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
        label: 'text-slate-500 dark:text-slate-400',
    },
};

const tone = computed(() => TONES[props.join.state] ?? TONES.closed);

// A closed room keeps the neutral preset surface; the coloured states replace it
// outright so two border/bg colours never compete in the class list.
const surface = computed(() => (tone.value.card
    ? `rounded-2xl border p-6 transition-all duration-300 ${tone.value.card}`
    : panel.value));
</script>

<template>
    <section :class="surface" aria-labelledby="booking-classroom-title">
        <div class="flex items-start gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl p-2" :class="tone.tile" aria-hidden="true">
                <AppIcon :name="join.state === 'open' ? 'video' : join.state === 'failed' ? 'alert' : 'clock'" />
            </span>

            <div class="min-w-0">
                <p class="text-[11px] font-bold uppercase tracking-wider" :class="tone.label">{{ copy.classroom?.title }}</p>
                <h2 id="booking-classroom-title" class="mt-0.5 text-sm font-bold text-slate-800 dark:text-slate-100">{{ join.title }}</h2>
                <p class="mt-1 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ join.body }}</p>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            <a
                v-if="join.canJoin && join.joinUrl"
                :href="join.joinUrl"
                class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-bold text-white shadow-sm shadow-primary/20 transition-transform hover:-translate-y-0.5"
            >
                <span class="h-4 w-4" aria-hidden="true"><AppIcon name="video" /></span>
                {{ join.enterLabel }}
            </a>

            <a
                v-else-if="join.roomUrl"
                :href="join.roomUrl"
                class="inline-flex items-center gap-2 rounded-xl border border-slate-200/80 px-4 py-2 text-sm font-semibold text-slate-600 transition-colors hover:border-primary/40 hover:text-primary dark:border-slate-800/80 dark:text-slate-300"
            >
                <span class="h-4 w-4" aria-hidden="true"><AppIcon name="video" /></span>
                {{ join.enterLabel }}
            </a>

            <a
                v-if="join.canMessage && join.messageUrl"
                :href="join.messageUrl"
                class="inline-flex items-center gap-2 rounded-xl border border-slate-200/80 px-4 py-2 text-sm font-semibold text-slate-600 transition-colors hover:border-primary/40 hover:text-primary dark:border-slate-800/80 dark:text-slate-300"
            >
                <span class="h-4 w-4" aria-hidden="true"><AppIcon name="chat" /></span>
                {{ join.messageLabel }}
            </a>
        </div>
    </section>
</template>
