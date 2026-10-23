<script setup>
/**
 * One real POST action, rendered as a form so CSRF and the server's state
 * machine stay in charge. The schedule asks to come back to itself with a
 * `return=schedule` field; the controller's default redirect is untouched.
 *
 * A `confirm` action (a no-show) opens the shared focus-trapped dialog first and
 * submits once the teacher confirms.
 */
import { ref } from 'vue';
import UiConfirmDialog from '../../shared/components/UiConfirmDialog.vue';

const props = defineProps({
    action: { type: String, required: true },
    token: { type: String, required: true },
    label: { type: String, required: true },
    tone: { type: String, default: 'primary' },
    size: { type: String, default: 'sm' },
    confirm: { type: Boolean, default: false },
    confirmTitle: { type: String, default: null },
    confirmBody: { type: String, default: null },
    confirmLabel: { type: String, default: null },
    cancelLabel: { type: String, default: null },
    busyLabel: { type: String, default: null },
});

const TONES = {
    primary: 'bg-primary text-white shadow-sm shadow-primary/20 hover:bg-primary/90',
    ghost: 'border border-slate-200/80 text-slate-600 hover:border-primary/40 hover:text-primary dark:border-slate-800/80 dark:text-slate-300',
    danger: 'border border-rose-200/80 text-rose-600 hover:bg-rose-50 dark:border-rose-900/50 dark:text-rose-300 dark:hover:bg-rose-950/40',
};

const SIZES = {
    sm: 'px-3 py-1.5 text-xs',
    md: 'px-4 py-2.5 text-sm',
};

const formEl = ref(null);
const asking = ref(false);
const busy = ref(false);

function onClick(event) {
    if (!props.confirm) {
        return;
    }

    event.preventDefault();
    asking.value = true;
}

function confirmSubmit() {
    asking.value = false;
    busy.value = true;

    if (formEl.value?.requestSubmit) {
        formEl.value.requestSubmit();
    } else {
        formEl.value?.submit();
    }
}
</script>

<template>
    <form ref="formEl" method="POST" :action="action" class="inline-flex" @submit="busy = true">
        <input type="hidden" name="_token" :value="token" />
        <input type="hidden" name="return" value="schedule" />

        <button
            type="submit"
            :disabled="busy"
            class="inline-flex items-center justify-center gap-1.5 rounded-xl font-semibold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 disabled:cursor-not-allowed disabled:opacity-60"
            :class="[TONES[tone] ?? TONES.primary, SIZES[size] ?? SIZES.sm]"
            @click="onClick"
        >
            {{ busy && busyLabel ? busyLabel : label }}
        </button>
    </form>

    <UiConfirmDialog
        v-if="confirm"
        :open="asking"
        :title="confirmTitle"
        :body="confirmBody"
        :confirm-label="confirmLabel"
        :cancel-label="cancelLabel"
        :busy="busy"
        tone="rose"
        @confirm="confirmSubmit"
        @close="asking = false"
    />
</template>
