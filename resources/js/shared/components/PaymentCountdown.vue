<script setup>
/**
 * The clock on a purchase or booking that is still waiting to be paid for. Blade
 * sends the deadline as an instant, so the seconds left are counted here and the
 * chip ticks without a request. Under an hour it counts in minutes and seconds,
 * and under five it turns red and breathes — the two moments a student actually
 * needs to be interrupted. Reaching zero tells the page, which stops offering a
 * payment whose window has closed.
 */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { CHIP } from '../styles';
import { formatCountdown } from '../formatCountdown';
import AppIcon from './AppIcon.vue';

const props = defineProps({
    deadline: { type: String, required: true },
    /** Both strings are translated by Blade; `payWithin` keeps its `:time` placeholder for the component to fill. */
    payWithin: { type: String, required: true },
    holdExpired: { type: String, required: true },
});

const emit = defineEmits(['expired']);

const deadlineMs = Date.parse(props.deadline);
const secondsLeft = () => (Number.isFinite(deadlineMs)
    ? Math.max(0, Math.round((deadlineMs - Date.now()) / 1000))
    : 0);

const remaining = ref(secondsLeft());
let timer = null;

const stop = () => {
    if (timer !== null) {
        clearInterval(timer);
        timer = null;
    }
};

const tick = () => {
    remaining.value = secondsLeft();

    if (remaining.value === 0) {
        stop();
        emit('expired');
    }
};

onMounted(() => {
    if (remaining.value === 0) {
        emit('expired');

        return;
    }

    timer = setInterval(tick, 1000);
});

onBeforeUnmount(stop);

const urgent = computed(() => remaining.value > 0 && remaining.value <= 300);
const expired = computed(() => remaining.value === 0);

const tone = computed(() => (urgent.value || expired.value
    ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400'
    : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'));

const label = computed(() => (expired.value
    ? props.holdExpired
    : props.payWithin.replace(':time', formatCountdown(remaining.value))));
</script>

<template>
    <span :class="[CHIP, tone]" role="timer">
        <span
            class="h-3.5 w-3.5 shrink-0 motion-reduce:animate-none"
            :class="{ 'animate-pulse': urgent || expired }"
        >
            <AppIcon name="clock" />
        </span>
        <span class="tabular-nums">{{ label }}</span>
    </span>
</template>
