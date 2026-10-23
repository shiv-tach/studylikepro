<script setup>
/**
 * The short clock on a booking deadline: the next lesson, a payment hold, the
 * start window. Under five minutes it turns red and breathes — the moments a
 * teacher actually needs to be interrupted. When the countdown reaches zero the
 * chip swaps to the zero label ("Started", "Hold ended") instead of counting
 * backwards.
 *
 * Shared by the schedule list and the lesson detail page.
 */
import { computed } from 'vue';
import { CHIP } from '../styles';
import AppIcon from './AppIcon.vue';
import useCountdown from '../composables/useCountdown';

const props = defineProps({
    deadline: { type: String, required: true },
    /** Translated by Blade; keeps its `:time` placeholder for the component to fill. */
    prefix: { type: String, required: true },
    zeroLabel: { type: String, required: true },
    tone: { type: String, default: 'amber' },
});

const { label, finished, urgent } = useCountdown(props.deadline);

const TONES = {
    amber: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    emerald: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    primary: 'bg-primary/10 text-primary',
};

const classes = computed(() => [
    CHIP,
    finished.value
        ? TONES.emerald
        : urgent.value
            ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400'
            : (TONES[props.tone] ?? TONES.amber),
]);

const text = computed(() => (finished.value ? props.zeroLabel : props.prefix.replace(':time', label.value)));
</script>

<template>
    <span :class="classes" role="timer">
        <span
            class="h-3.5 w-3.5 shrink-0 motion-reduce:animate-none"
            :class="{ 'animate-pulse': urgent || finished }"
            aria-hidden="true"
        >
            <AppIcon :name="finished ? 'check-circle' : 'clock'" />
        </span>
        <span class="tabular-nums">{{ text }}</span>
    </span>
</template>
