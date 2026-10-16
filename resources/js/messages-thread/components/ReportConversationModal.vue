<script setup>
/**
 * The "Report a problem" dialog. Submitting it is a plain server form POST — a
 * dispute is created, support is notified and the page comes back with its flash
 * banner — so this component only owns the dialog behaviour: the backdrop, the
 * Escape key, a focus trap and the focus that lands on the first field.
 *
 * A rejected submit bounces back to the same page, and the island carries the
 * typed values and the field messages, so the dialog reopens already filled in.
 */
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { useCopy, useCsrfToken, useUrls } from '../composables/usePageContext';

const props = defineProps({
    open: { type: Boolean, default: false },
    report: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['close']);

const copy = useCopy();
const urls = useUrls();
const csrf = useCsrfToken();

const panel = ref(null);
const reason = ref('');
const details = ref('');

function close() {
    emit('close');
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        close();
    }
}

function trapFocus(event) {
    if (event.key !== 'Tab' || !panel.value) {
        return;
    }

    const focusables = [...panel.value.querySelectorAll(
        'a[href], button:not([disabled]), select, textarea, input:not([type="hidden"])',
    )];

    if (!focusables.length) {
        return;
    }

    const first = focusables[0];
    const last = focusables[focusables.length - 1];

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

watch(() => props.open, async (open) => {
    document.body.classList.toggle('overflow-y-hidden', open);

    if (open) {
        // Reset to what the server last saw, so a cancelled draft never leaks
        // into the next attempt.
        reason.value = props.report?.old?.reason ?? props.report?.reasons?.[0]?.value ?? '';
        details.value = props.report?.old?.details ?? '';

        document.addEventListener('keydown', onKeydown);
        await nextTick();
        panel.value?.querySelector('select, textarea')?.focus();

        return;
    }

    document.removeEventListener('keydown', onKeydown);
}, { immediate: true });

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeydown);
    document.body.classList.remove('overflow-y-hidden');
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0"
                role="dialog"
                aria-modal="true"
                aria-labelledby="report-conversation-title"
            >
                <div class="fixed inset-0 bg-slate-500/75 dark:bg-slate-900/75" @click="close"></div>

                <form
                    ref="panel"
                    method="POST"
                    :action="urls.report"
                    class="relative mb-6 overflow-hidden rounded-2xl bg-white p-6 shadow-xl sm:mx-auto sm:w-full sm:max-w-2xl dark:bg-slate-800"
                    @keydown="trapFocus"
                >
                    <input type="hidden" name="_token" :value="csrf" />

                    <h2 id="report-conversation-title" class="text-lg font-bold text-slate-800 dark:text-slate-100">
                        {{ copy.reportTitle }}
                    </h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ copy.reportIntro }}</p>

                    <div class="mt-4 space-y-4">
                        <div>
                            <label for="report-reason" class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                                {{ copy.reasonLabel }}
                            </label>
                            <select
                                id="report-reason"
                                v-model="reason"
                                name="reason"
                                class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                            >
                                <option v-for="option in report?.reasons ?? []" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                            <p v-if="report?.errors?.reason" class="mt-2 text-sm text-rose-600 dark:text-rose-400" role="alert">
                                {{ report.errors.reason }}
                            </p>
                        </div>

                        <div>
                            <label for="report-details" class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                                {{ copy.detailsLabel }}
                            </label>
                            <textarea
                                id="report-details"
                                v-model="details"
                                name="details"
                                rows="3"
                                maxlength="1000"
                                class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                            ></textarea>
                            <p v-if="report?.errors?.details" class="mt-2 text-sm text-rose-600 dark:text-rose-400" role="alert">
                                {{ report.errors.details }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <button
                            type="button"
                            class="inline-flex items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                            @click="close"
                        >
                            {{ copy.cancel }}
                        </button>
                        <button
                            type="submit"
                            class="inline-flex items-center rounded-xl bg-rose-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition-colors hover:bg-rose-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-400"
                        >
                            {{ copy.submitReport }}
                        </button>
                    </div>
                </form>
            </div>
        </Transition>
    </Teleport>
</template>
