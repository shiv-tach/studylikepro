<script setup>
/**
 * Delete confirmation. The dialog owns the real DELETE form so the request is
 * identical to the old page; a service that already has purchases never reaches
 * this state — the card disables the action — but the blocked copy is here as a
 * second guard.
 */
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';
import { useCopy, useCsrfToken } from '../composables/usePageContext';

const props = defineProps({
    offering: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const copy = useCopy();
const csrfToken = useCsrfToken();

const panel = ref(null);
const cancelButton = ref(null);
let previousActive = null;

function focusables() {
    if (!panel.value) {
        return [];
    }

    return [...panel.value.querySelectorAll('button, [href], input, [tabindex]:not([tabindex="-1"])')]
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
    () => props.offering,
    async (offering) => {
        if (offering) {
            previousActive = document.activeElement;
            document.body.classList.add('overflow-y-hidden');
            await nextTick();
            cancelButton.value?.focus();
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
                v-if="offering"
                class="fixed inset-0 z-[55] overflow-y-auto px-4 py-6 sm:px-0"
                role="dialog"
                aria-modal="true"
                aria-labelledby="service-delete-title"
                @keydown="onKeydown"
            >
                <div class="fixed inset-0 bg-slate-500/75 dark:bg-slate-900/80" @click="emit('close')"></div>

                <div
                    ref="panel"
                    class="relative mb-6 rounded-2xl border border-slate-200 bg-white shadow-xl sm:mx-auto sm:w-full sm:max-w-lg dark:border-slate-800 dark:bg-slate-900"
                >
                    <div class="flex items-start gap-3 p-6">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-rose-100 p-3 text-rose-600 dark:bg-rose-950/40 dark:text-rose-300">
                            <AppIcon name="trash" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <h2 id="service-delete-title" class="text-lg font-bold text-slate-800 dark:text-slate-100">
                                {{ offering.canDelete ? copy.delete?.title : copy.delete?.blockedTitle }}
                            </h2>
                            <p class="mt-1 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                                {{ offering.canDelete ? copy.delete?.body : copy.delete?.blockedBody }}
                            </p>
                            <p class="mt-2 truncate text-xs font-semibold text-slate-600 dark:text-slate-300">
                                {{ offering.title }} · {{ offering.priceLabel }}
                            </p>

                            <form
                                v-if="offering.canDelete"
                                method="POST"
                                :action="offering.deleteUrl"
                                class="mt-6 flex flex-wrap justify-end gap-2"
                            >
                                <input type="hidden" name="_token" :value="csrfToken">
                                <input type="hidden" name="_method" value="DELETE">

                                <button
                                    ref="cancelButton"
                                    type="button"
                                    class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                                    @click="emit('close')"
                                >
                                    {{ copy.delete?.cancel }}
                                </button>

                                <button
                                    type="submit"
                                    class="inline-flex items-center gap-1.5 rounded-xl bg-rose-600 px-4 py-2 text-sm font-bold text-white shadow-md shadow-rose-600/20 transition hover:bg-rose-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-400/40"
                                >
                                    {{ copy.delete?.confirm }}
                                </button>
                            </form>

                            <div v-else class="mt-6 flex justify-end">
                                <button
                                    ref="cancelButton"
                                    type="button"
                                    class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                                    @click="emit('close')"
                                >
                                    {{ copy.delete?.cancel }}
                                </button>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="-mr-1 -mt-1 shrink-0 rounded-lg p-1.5 text-slate-400 transition-colors hover:text-slate-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 dark:hover:text-slate-200"
                            :aria-label="copy.composer?.close"
                            @click="emit('close')"
                        >
                            <span class="block h-4 w-4"><AppIcon name="close" /></span>
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
