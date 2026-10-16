<script setup>
/**
 * The "cancel this lesson" dialog. Submitting it is a plain server form POST —
 * the lesson is released and the page comes back with its flash banner — so this
 * component only owns the dialog behaviour: the backdrop, the Escape key, a focus
 * trap and the focus that lands on the reason field. A rejected submit bounces
 * back to the same page, where the island reopens the dialog with the error.
 */
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { useCsrfToken } from '../composables/usePageContext';

const props = defineProps({
    open: { type: Boolean, default: false },
    cancel: { type: Object, required: true },
});

const emit = defineEmits(['close']);

const csrfToken = useCsrfToken();

const panel = ref(null);
const reason = ref('');

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
        'a[href], button:not([disabled]), textarea, input:not([type="hidden"])',
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
        document.addEventListener('keydown', onKeydown);
        await nextTick();
        panel.value?.querySelector('textarea')?.focus();

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
                aria-labelledby="cancel-booking-title"
            >
                <div class="fixed inset-0 bg-slate-500/75 dark:bg-slate-900/75" @click="close"></div>

                <form
                    ref="panel"
                    method="POST"
                    :action="cancel.url"
                    class="relative mb-6 overflow-hidden rounded-2xl bg-white p-6 shadow-xl sm:mx-auto sm:w-full sm:max-w-lg dark:bg-slate-800"
                    @keydown="trapFocus"
                >
                    <input type="hidden" name="_token" :value="csrfToken">

                    <h2 id="cancel-booking-title" class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ cancel.title }}</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ cancel.body }}</p>

                    <div class="mt-4">
                        <label for="cancel-reason" class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ cancel.reasonLabel }}</label>
                        <textarea
                            id="cancel-reason"
                            v-model="reason"
                            name="reason"
                            rows="3"
                            class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                        ></textarea>
                        <p v-if="cancel.error" class="mt-2 text-sm text-rose-600 dark:text-rose-400" role="alert">{{ cancel.error }}</p>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <button
                            type="button"
                            class="inline-flex items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                            @click="close"
                        >
                            {{ cancel.keepLabel }}
                        </button>
                        <button
                            type="submit"
                            class="inline-flex items-center rounded-xl bg-rose-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition-colors hover:bg-rose-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-400"
                        >
                            {{ cancel.submitLabel }}
                        </button>
                    </div>
                </form>
            </div>
        </Transition>
    </Teleport>
</template>
