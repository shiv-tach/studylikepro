<script setup>
import { computed, toRef } from 'vue';
import { useCountUp } from '../../shared/composables/useCountUp';
import { usePanel } from '../../shared/composables/usePanel';
import AppIcon from '../../shared/components/AppIcon.vue';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: Number, default: 0 },
    icon: { type: String, default: 'sparkles' },
    tone: { type: String, default: 'primary' },
    href: { type: String, default: null },
    hint: { type: String, default: null },
});

// Tones are semantic (never the theme accent) and stay readable in dark mode.
const toneClasses = {
    primary: 'bg-primary/10 text-primary',
    violet: 'bg-violet-100 text-violet-600 dark:bg-violet-900/30 dark:text-violet-400',
    emerald: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400',
    amber: 'bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400',
};

const panel = usePanel(true);
const displayValue = useCountUp(toRef(props, 'value'));
const tone = computed(() => toneClasses[props.tone] ?? toneClasses.primary);
</script>

<template>
    <component
        :is="href ? 'a' : 'div'"
        :href="href"
        :class="[panel, 'group block']"
    >
        <div class="flex items-start justify-between gap-3">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ label }}</p>
            <span
                :class="[tone, 'flex h-9 w-9 shrink-0 items-center justify-center rounded-xl p-2 transition-colors duration-300 group-hover:bg-primary group-hover:text-white']"
            >
                <AppIcon :name="icon" />
            </span>
        </div>
        <p class="mt-3 text-2xl font-bold tabular-nums text-slate-800 dark:text-slate-100">{{ displayValue }}</p>
        <p v-if="hint" class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ hint }}</p>
    </component>
</template>
