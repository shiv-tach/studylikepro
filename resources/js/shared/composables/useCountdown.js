import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { formatCountdown } from '../formatCountdown';

/**
 * A clock pointed at one instant. Blade sends the deadline as an ISO string, so
 * the seconds left are counted here and the chip ticks without a request.
 *
 * Pass `null` to keep it inert (a lesson that is already running).
 */
export function useCountdown(deadline) {
    const deadlineMs = deadline ? Date.parse(deadline) : Number.NaN;
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
        }
    };

    onMounted(() => {
        if (remaining.value === 0) {
            return;
        }

        timer = setInterval(tick, 1000);
    });

    onBeforeUnmount(stop);

    const label = computed(() => formatCountdown(remaining.value));
    const finished = computed(() => Number.isFinite(deadlineMs) && remaining.value === 0);
    const urgent = computed(() => remaining.value > 0 && remaining.value <= 300);

    return { remaining, label, finished, urgent };
}

export default useCountdown;
