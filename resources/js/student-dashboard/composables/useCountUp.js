import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Eases a number up to its target on mount so the stat tiles come alive. Static
 * when the visitor prefers reduced motion.
 */
export function useCountUp(target, duration = 700) {
    const display = ref(0);
    let frame = null;

    onMounted(() => {
        const goal = Number(target.value) || 0;

        if (goal === 0 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            display.value = goal;

            return;
        }

        const startedAt = performance.now();

        const step = (now) => {
            const progress = Math.min((now - startedAt) / duration, 1);
            display.value = Math.round(goal * (1 - Math.pow(1 - progress, 3)));
            frame = progress < 1 ? requestAnimationFrame(step) : null;
        };

        frame = requestAnimationFrame(step);
    });

    onBeforeUnmount(() => {
        if (frame !== null) {
            cancelAnimationFrame(frame);
        }
    });

    return display;
}

export default useCountUp;
