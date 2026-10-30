import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * The SMS resend cooldown, counted down in the browser. The server enforces the
 * same window; this only keeps the button from lying about when it will work
 * (the Vue twin of the Alpine `otpResend` helper the Blade account step used).
 */
export function useResendCooldown(seconds) {
    const remaining = ref(Math.max(0, Number(seconds) || 0));
    let timer = null;

    const stop = () => {
        if (timer !== null) {
            clearInterval(timer);
            timer = null;
        }
    };

    onMounted(() => {
        if (remaining.value <= 0) {
            return;
        }

        timer = setInterval(() => {
            remaining.value -= 1;

            if (remaining.value <= 0) {
                remaining.value = 0;
                stop();
            }
        }, 1000);
    });

    onBeforeUnmount(stop);

    return { remaining };
}

export default useResendCooldown;
