<script setup>
import { computed } from 'vue';
import { usePanel } from '../../shared/composables/usePanel';
import { CHIP } from '../../shared/styles';
import AppIcon from '../../shared/components/AppIcon.vue';

const props = defineProps({
    label: { type: String, required: true },
    description: { type: String, default: '' },
    eyebrow: { type: String, default: null },
    url: { type: String, required: true },
    icon: { type: String, default: 'sparkles' },
    tone: { type: String, default: 'primary' },
    badge: { type: String, default: null },
});

// Semantic icon tones, so a preset's accent colour never leaks into a card.
const toneClasses = {
    primary: 'bg-primary/10 text-primary',
    violet: 'bg-violet-100 text-violet-600 dark:bg-violet-900/30 dark:text-violet-400',
    emerald: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400',
    amber: 'bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400',
    sky: 'bg-sky-100 text-sky-600 dark:bg-sky-900/30 dark:text-sky-400',
    rose: 'bg-rose-100 text-rose-600 dark:bg-rose-900/30 dark:text-rose-400',
};

const panel = usePanel(true);
const tone = computed(() => toneClasses[props.tone] ?? toneClasses.primary);
</script>

<template>
    <a :href="url" :class="[panel, 'group flex flex-col']">
        <div class="flex items-center justify-between gap-3">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ eyebrow ?? label }}</span>
            <span v-if="badge" :class="[CHIP, 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400']">
                {{ badge }}
            </span>
        </div>

        <div class="mt-4 flex items-start gap-3">
            <span
                :class="[tone, 'flex h-10 w-10 shrink-0 items-center justify-center rounded-xl p-2.5 transition-colors duration-300 group-hover:bg-primary group-hover:text-white']"
            >
                <AppIcon :name="icon" />
            </span>
            <div class="min-w-0">
                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ label }}</h3>
                <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ description }}</p>
            </div>
        </div>

        <span class="mt-4 inline-flex items-center gap-1.5 text-xs font-semibold text-primary">
            Open
            <span class="h-3.5 w-3.5 transition-transform duration-300 group-hover:translate-x-0.5">
                <AppIcon name="arrow-right" />
            </span>
        </span>
    </a>
</template>
