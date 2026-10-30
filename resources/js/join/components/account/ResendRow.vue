<script setup>
/**
 * "Send a new code" and "Change my details". Two real forms sitting beside the
 * code form (never inside it), with the resend button counting down the
 * server's cooldown so it cannot be pressed early.
 */
import { computed } from 'vue';
import { useSurveyContext } from '../../composables/useSurveyContext';
import { useResendCooldown } from '../../composables/useResendCooldown';

const { config } = useSurveyContext();
const copy = computed(() => config.copy);

const { remaining } = useResendCooldown(config.cooldown);
</script>

<template>
    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200/80 pt-4 dark:border-slate-800/80">
        <form method="POST" :action="config.urls.resend">
            <input type="hidden" name="_token" :value="config.csrfToken" />

            <button
                type="submit"
                :disabled="remaining > 0"
                class="text-sm font-semibold text-primary hover:underline disabled:cursor-not-allowed disabled:text-slate-400 dark:disabled:text-slate-500"
            >
                <span v-if="remaining <= 0">{{ copy.resend }}</span>
                <span v-else>{{ copy.resendIn }} {{ remaining }}s</span>
            </button>
        </form>

        <form method="POST" :action="config.urls.edit">
            <input type="hidden" name="_token" :value="config.csrfToken" />

            <button type="submit" class="text-sm font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-100">
                {{ copy.changeDetails }}
            </button>
        </form>
    </div>
</template>
