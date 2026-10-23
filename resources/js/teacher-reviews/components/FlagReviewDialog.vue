<script setup>
/**
 * The "Report this review" dialog. Submitting it is a plain server form POST —
 * support is notified and the page comes back with its toast — so this component
 * only owns the dialog behaviour: the backdrop, Escape, a focus trap and the
 * focus that lands on the first field.
 *
 * A rejected submit bounces back to the same page, and the island carries the
 * typed reason, notes and field messages, so the parent reopens this dialog for
 * the review it came from with everything filled in.
 */
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';
import UiFormField from '../../shared/components/UiFormField.vue';
import { useCopy, useCsrfToken, usePage } from '../composables/usePageContext';

const props = defineProps({
    review: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const copy = useCopy();
const page = usePage();
const csrf = useCsrfToken();

const panel = ref(null);
const reason = ref('');
const notes = ref('');

// Only the review that was rejected carries the replayed values and messages;
// opening any other dialog starts clean.
const isReplay = computed(() => Boolean(props.review) && page.flag?.openId === props.review?.id);
const errors = computed(() => (isReplay.value ? page.flag?.errors ?? {} : {}));
const maxNotes = computed(() => Number(page.flag?.maxNotes ?? 500));

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
        'button, select, textarea, input:not([type="hidden"]), [href]',
    )].filter((element) => !element.hasAttribute('disabled'));

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

watch(
    () => props.review,
    async (review) => {
        document.body.classList.toggle('overflow-y-hidden', Boolean(review));

        if (!review) {
            document.removeEventListener('keydown', onKeydown);

            return;
        }

        // A cancelled draft must not leak into the next report, so the dialog
        // starts from what the server last saw. A rejected reason is not in the
        // options any more, so fall back to the first one instead of leaving the
        // select blank.
        const options = page.flag?.reasons ?? [];
        const replayed = isReplay.value ? page.flag?.reason : null;

        reason.value = options.some((option) => option.value === replayed)
            ? replayed
            : (options[0]?.value ?? '');
        notes.value = (isReplay.value ? page.flag?.notes : null) ?? '';

        document.addEventListener('keydown', onKeydown);

        await nextTick();
        panel.value?.querySelector('select, textarea')?.focus();
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeydown);
    document.body.classList.remove('overflow-y-hidden');
});
</script>

<template>
    <Teleport to="body">
        <Transition
            appear
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="review"
                class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0"
                role="dialog"
                aria-modal="true"
                aria-labelledby="flag-review-title"
            >
                <div class="fixed inset-0 bg-slate-500/75 dark:bg-slate-900/75" @click="close"></div>

                <form
                    ref="panel"
                    method="POST"
                    :action="review.flagUrl"
                    class="relative mb-6 overflow-hidden rounded-2xl bg-white p-6 shadow-xl sm:mx-auto sm:w-full sm:max-w-lg dark:bg-slate-800"
                    @keydown="trapFocus"
                >
                    <input type="hidden" name="_token" :value="csrf" />
                    <input type="hidden" name="editing_review" :value="review.id" />

                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 id="flag-review-title" class="text-lg font-bold text-slate-800 dark:text-slate-100">
                                {{ copy.dialog?.title }}
                            </h2>
                            <p class="mt-1 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                                {{ copy.dialog?.intro }}
                            </p>
                        </div>

                        <button
                            type="button"
                            class="-mr-1 -mt-1 shrink-0 rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 dark:hover:bg-slate-700 dark:hover:text-slate-200"
                            :aria-label="copy.dialog?.close"
                            @click="close"
                        >
                            <span class="block h-4 w-4"><AppIcon name="close" /></span>
                        </button>
                    </div>

                    <dl class="mt-4 rounded-xl border border-slate-200/80 bg-slate-50/60 p-3 text-xs text-slate-600 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-300">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="font-semibold text-slate-700 dark:text-slate-200">{{ review.reviewer }}</dt>
                            <dd class="font-semibold text-amber-600 dark:text-amber-400">{{ review.rating }} ★</dd>
                        </div>
                        <dd v-if="review.comment" class="mt-1 line-clamp-2 italic">{{ review.comment }}</dd>
                    </dl>

                    <div class="mt-4 space-y-4">
                        <UiFormField
                            :label="copy.dialog?.reasonLabel"
                            for-id="flag-review-reason"
                            :errors="errors.reason ? [errors.reason] : []"
                            required
                        >
                            <select
                                id="flag-review-reason"
                                v-model="reason"
                                name="reason"
                                class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                            >
                                <option v-for="option in page.flag?.reasons ?? []" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                        </UiFormField>

                        <UiFormField
                            :label="copy.dialog?.notesLabel"
                            for-id="flag-review-notes"
                            :hint="copy.dialog?.notesHint"
                            :errors="errors.notes ? [errors.notes] : []"
                        >
                            <textarea
                                id="flag-review-notes"
                                v-model="notes"
                                name="notes"
                                rows="3"
                                :maxlength="maxNotes"
                                class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                            ></textarea>
                        </UiFormField>
                    </div>

                    <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-700"
                            @click="close"
                        >
                            {{ copy.dialog?.cancel }}
                        </button>
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-xl bg-rose-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition-colors hover:bg-rose-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-400"
                        >
                            {{ copy.dialog?.submit }}
                        </button>
                    </div>
                </form>
            </div>
        </Transition>
    </Teleport>
</template>
