<script setup>
/**
 * A focus-trapped confirmation dialog for the two actions that are worth a
 * second look: sending the application in (it locks the documents) and removing
 * an uploaded file. It renders no form of its own — the caller owns the real
 * request — so the same dialog never ends up with a nested submit.
 */
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, required: true },
    body: { type: String, default: null },
    confirmLabel: { type: String, required: true },
    cancelLabel: { type: String, required: true },
    closeLabel: { type: String, default: 'Close' },
    tone: { type: String, default: 'primary' },
    busy: { type: Boolean, default: false },
});

const emit = defineEmits(['confirm', 'close']);

const panel = ref(null);
const confirmButton = ref(null);
let previousActive = null;

const CONFIRM_CLASSES = {
    primary: 'bg-primary text-white shadow-md shadow-primary/20 hover:bg-primary/90 focus-visible:ring-primary/40',
    rose: 'bg-rose-600 text-white shadow-md shadow-rose-600/20 hover:bg-rose-500 focus-visible:ring-rose-400/40',
};

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
    () => props.open,
    async (open) => {
        if (open) {
            previousActive = document.activeElement;
            document.body.classList.add('overflow-y-hidden');
            await nextTick();
            confirmButton.value?.focus();
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
                aria-labelledby="confirm-dialog-title"
                @keydown="onKeydown"
            >
                <div class="fixed inset-0 bg-slate-500 opacity-75 dark:bg-slate-900" @click="emit('close')"></div>

                <div
                    ref="panel"
                    class="relative mb-6 rounded-2xl border border-slate-200 bg-white shadow-xl sm:mx-auto sm:w-full sm:max-w-lg dark:border-slate-800 dark:bg-slate-900"
                >
                    <div class="flex items-start gap-3 p-6">
                        <span
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl p-3"
                            :class="tone === 'rose'
                                ? 'bg-rose-100 text-rose-600 dark:bg-rose-950/40 dark:text-rose-300'
                                : 'bg-primary/10 text-primary'"
                        >
                            <AppIcon :name="tone === 'rose' ? 'trash' : 'shield'" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <h2 id="confirm-dialog-title" class="text-base font-bold text-slate-800 dark:text-slate-100">{{ title }}</h2>
                            <p v-if="body" class="mt-1 text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ body }}</p>
                            <slot />
                        </div>

                        <button
                            type="button"
                            class="-mr-1 -mt-1 shrink-0 rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 dark:hover:bg-slate-800"
                            :aria-label="closeLabel"
                            @click="emit('close')"
                        >
                            <span class="block h-4 w-4"><AppIcon name="close" /></span>
                        </button>
                    </div>

                    <div class="flex flex-wrap justify-end gap-3 border-t border-slate-200/80 px-6 py-4 dark:border-slate-800/80">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                            @click="emit('close')"
                        >
                            {{ cancelLabel }}
                        </button>

                        <button
                            ref="confirmButton"
                            type="button"
                            class="inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-sm font-bold transition-colors focus:outline-none focus-visible:ring-2 disabled:cursor-not-allowed disabled:opacity-60"
                            :class="CONFIRM_CLASSES[tone] ?? CONFIRM_CLASSES.primary"
                            :disabled="busy"
                            @click="emit('confirm')"
                        >
                            {{ confirmLabel }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
