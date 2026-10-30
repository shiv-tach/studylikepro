<script setup>
/**
 * The SMS passcode. Confirming it is what actually creates the account, so the
 * form posts straight to `join.account.verify`; the resend and "change my
 * details" forms stay siblings (forms cannot nest).
 */
import { computed } from 'vue';
import { useSurveyContext } from '../../composables/useSurveyContext';
import { CARD, FIELD, PRIMARY_BUTTON } from '../../styles';
import FieldMessages from '../FieldMessages.vue';
import ResendRow from './ResendRow.vue';

const { config, form } = useSurveyContext();

const copy = computed(() => config.copy);
const busy = computed(() => form.busy.value);
</script>

<template>
    <div :class="CARD" class="space-y-5">
        <div
            v-if="config.status.resent"
            class="rounded-xl border border-emerald-200/80 bg-emerald-50 p-3 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300"
        >
            {{ copy.otpResent }}
        </div>
        <div
            v-else-if="config.status.sendFailed"
            class="rounded-xl border border-amber-200/80 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300"
        >
            {{ copy.otpSendFailed }}
        </div>

        <form
            :id="config.formId"
            method="POST"
            :action="config.urls.verify"
            class="space-y-3"
            @submit="form.guard"
        >
            <input type="hidden" name="_token" :value="config.csrfToken" />

            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300" for="code">{{ copy.codeLabel }}</label>
            <input
                id="code"
                name="code"
                type="text"
                inputmode="numeric"
                pattern="[0-9]*"
                :maxlength="config.length"
                autocomplete="one-time-code"
                required
                autofocus
                :class="[FIELD, 'text-center text-2xl font-bold tracking-[0.5em]']"
            />

            <FieldMessages :messages="form.errorsFor('code')" />

            <p class="text-center text-xs text-slate-400">{{ copy.codeHint }}</p>

            <button type="submit" :disabled="busy" :class="[PRIMARY_BUTTON, 'w-full']">
                <span v-show="!busy" class="inline-flex items-center gap-2">{{ copy.verify }}</span>
                <span v-show="busy" class="inline-flex items-center gap-2">
                    <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-90" fill="currentColor" d="M21 12a9 9 0 0 1-9 9v-4a5 5 0 0 0 5-5h4z" />
                    </svg>
                    {{ copy.checking }}
                </span>
            </button>
        </form>

        <ResendRow />
    </div>
</template>
