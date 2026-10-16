<script setup>
/**
 * The delete-account confirmation dialog: a focus-trapped modal around a real
 * DELETE form, so a rejected password bounces back with the userDeletion bag and
 * the dialog reopens with Laravel's message.
 */
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { useCopy, useCsrfToken, useUrls } from '../composables/usePageContext';

const props = defineProps({
    open: { type: Boolean, default: false },
    errors: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

const copy = useCopy();
const urls = useUrls();
const csrfToken = useCsrfToken();

const panel = ref(null);
const passwordInput = ref(null);
let previousActive = null;

function focusables() {
    if (!panel.value) {
        return [];
    }

    return [...panel.value.querySelectorAll('a, button, input:not([type="hidden"]), [tabindex]:not([tabindex="-1"])')]
        .filter((element) => !element.hasAttribute('disabled'));
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        emit('close');

        return;
    }

    if (event.key !== 'Tab') {
        return;
    }

    const items = focusables();

    if (items.length === 0) {
        return;
    }

    const first = items[0];
    const last = items[items.length - 1];

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

watch(
    () => props.open,
    async (open) => {
        if (open) {
            previousActive = document.activeElement;
            document.body.classList.add('overflow-y-hidden');
            await nextTick();
            passwordInput.value?.focus();
        } else {
            document.body.classList.remove('overflow-y-hidden');
            previousActive?.focus?.();
        }
    },
);

onBeforeUnmount(() => document.body.classList.remove('overflow-y-hidden'));
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0"
                role="dialog"
                aria-modal="true"
                aria-labelledby="delete-account-title"
                @keydown="onKeydown"
            >
                <div class="fixed inset-0 bg-slate-500 opacity-75 dark:bg-slate-900" @click="$emit('close')"></div>

                <div
                    ref="panel"
                    class="relative mb-6 rounded-2xl border border-slate-200 bg-white shadow-xl sm:mx-auto sm:w-full sm:max-w-2xl dark:border-slate-800 dark:bg-slate-900"
                >
                    <form method="POST" :action="urls.destroy" class="p-6">
                        <input type="hidden" name="_token" :value="csrfToken">
                        <input type="hidden" name="_method" value="DELETE">

                        <h2 id="delete-account-title" class="text-lg font-bold text-slate-800 dark:text-slate-100">
                            {{ copy.deleteConfirmTitle }}
                        </h2>

                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            {{ copy.deleteConfirmBody }}
                        </p>

                        <div class="mt-6">
                            <label for="delete_account_password" class="sr-only">{{ copy.passwordLabel }}</label>
                            <input
                                id="delete_account_password"
                                ref="passwordInput"
                                name="password"
                                type="password"
                                :placeholder="copy.passwordLabel"
                                class="mt-1 block w-3/4 rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                            >

                            <ul v-if="errors.length" class="mt-2 space-y-1" role="alert">
                                <li v-for="(message, index) in errors" :key="index" class="text-sm font-medium text-rose-600 dark:text-rose-400">
                                    {{ message }}
                                </li>
                            </ul>
                        </div>

                        <div class="mt-6 flex justify-end gap-3">
                            <button
                                type="button"
                                class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                                @click="$emit('close')"
                            >
                                {{ copy.cancel }}
                            </button>

                            <button
                                type="submit"
                                class="inline-flex items-center rounded-xl bg-rose-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2"
                            >
                                {{ copy.delete }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
