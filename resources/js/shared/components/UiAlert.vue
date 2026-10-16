<script setup>
/**
 * The banner a workspace page shows above its cards: a redirect's flash message
 * or a validation failure. The tone is semantic — `primary` for something the
 * student just created, `emerald`/`amber`/`rose`/`slate` for what happened to it
 * — so a preset's accent never turns an error green.
 */
import { computed } from 'vue';
import AppIcon from './AppIcon.vue';

const props = defineProps({
    tone: { type: String, default: 'slate' },
    icon: { type: String, default: null },
    role: { type: String, default: 'status' },
});

const TONES = {
    primary: 'border-primary/30 bg-primary/5 text-primary',
    emerald: 'border-emerald-200/80 bg-emerald-50 text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300',
    amber: 'border-amber-200/80 bg-amber-50 text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300',
    rose: 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300',
    slate: 'border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300',
};

const classes = computed(() => [TONES[props.tone] ?? TONES.slate]);
</script>

<template>
    <div :class="classes" :role="role" class="flex items-start gap-3 rounded-2xl border p-4 text-sm">
        <span v-if="icon" class="mt-0.5 h-4 w-4 shrink-0">
            <AppIcon :name="icon" />
        </span>
        <span><slot /></span>
    </div>
</template>
