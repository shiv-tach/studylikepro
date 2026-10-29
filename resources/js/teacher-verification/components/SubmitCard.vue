<script setup>
/**
 * The final, locking action. It repeats the checklist inline, so the button is
 * never disabled without saying why: the line beside it names the one thing left
 * to do. Submitting goes through a confirmation dialog because it locks the
 * documents; acceptance of the terms is still validated server-side.
 */
import { computed, ref, watch } from 'vue';
import { usePanel } from '../../shared/composables/usePanel';
import AppIcon from '../../shared/components/AppIcon.vue';
import UiSectionHeading from '../../shared/components/UiSectionHeading.vue';
import { useCopy, useCsrfToken, useUrls } from '../composables/usePageContext';
import { useVerification } from '../composables/useVerification';
import { DONE_RING, EMPTY_RING, PRIMARY_BUTTON } from '../styles';
import ConfirmDialog from './ConfirmDialog.vue';

const copy = useCopy();
const csrfToken = useCsrfToken();
const urls = useUrls();
const panel = usePanel();

const { agree, requirements, canSubmit, blockingReason, errors } = useVerification();

const form = ref(null);
const confirming = ref(false);

// A rejected submission re-renders the page with the server's message, so the
// dialog must not reopen over it — and ticking the box clears the complaint.
watch(agree, (accepted) => {
    if (accepted) {
        errors.agree = [];
    } else {
        confirming.value = false;
    }
});

function openConfirm() {
    if (!canSubmit.value) {
        return;
    }

    confirming.value = true;
}

function submit() {
    confirming.value = false;
    form.value?.submit();
}
</script>

<template>
    <section id="submit" :class="panel" class="scroll-mt-40">
        <UiSectionHeading :title="copy.submitTitle" :description="copy.submitHint" icon="shield" level="h3" />

        <!-- The three stages sit above the button so submitting reads as the
             start of the review, never as the end of setup. -->
        <ol class="mt-5 grid gap-2 sm:grid-cols-3">
            <li
                v-for="(stage, index) in copy.submitStages ?? []"
                :key="stage.title"
                class="rounded-xl border border-slate-200/80 bg-white p-3 dark:border-slate-800 dark:bg-slate-900"
            >
                <p class="flex items-center gap-1.5 text-[11px] font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400">
                    <span class="flex h-4 w-4 items-center justify-center rounded-full bg-slate-100 text-[9px] font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-400">{{ index + 1 }}</span>
                    {{ stage.title }}
                </p>
                <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ stage.body }}</p>
            </li>
        </ol>

        <ul class="mt-5 space-y-2.5 text-sm">
            <li v-for="item in requirements" :key="item.key" class="flex items-center gap-2.5">
                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border" :class="item.done ? DONE_RING : EMPTY_RING">
                    <span v-if="item.done" class="h-3 w-3 text-emerald-600 dark:text-emerald-400"><AppIcon name="check" /></span>
                </span>
                <span :class="item.done ? 'text-slate-600 dark:text-slate-300' : 'text-slate-500 dark:text-slate-400'">{{ item.label }}</span>
            </li>
        </ul>

        <form ref="form" method="POST" :action="urls.submit" class="mt-5 space-y-3">
            <input type="hidden" name="_token" :value="csrfToken">

            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200/80 bg-slate-50/60 p-3 text-sm text-slate-600 transition-colors hover:border-primary/40 dark:border-slate-800 dark:bg-slate-800/40 dark:text-slate-300">
                <input
                    v-model="agree"
                    type="checkbox"
                    name="agree"
                    value="1"
                    class="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300 text-primary shadow-sm focus:ring-primary dark:border-slate-600 dark:bg-slate-900"
                >
                <span>
                    {{ copy.termsLead }}
                    <a :href="urls.terms" target="_blank" rel="noopener" class="font-semibold text-primary hover:underline">{{ copy.termsLink }}</a>
                    {{ copy.termsAnd }}
                    <a :href="urls.privacy" target="_blank" rel="noopener" class="font-semibold text-primary hover:underline">{{ copy.privacyLink }}</a>{{ copy.termsTail }}
                </span>
            </label>

            <p class="px-1 text-xs leading-relaxed text-amber-700 dark:text-amber-400">
                {{ copy.termsAcknowledge }}
            </p>

            <ul v-if="errors.agree?.length" class="space-y-1" role="alert">
                <li v-for="(message, index) in errors.agree" :key="index" class="text-sm font-medium text-rose-600 dark:text-rose-400">
                    {{ message }}
                </li>
            </ul>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p
                    id="submit-requirements"
                    class="text-xs font-semibold"
                    :class="blockingReason ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'"
                    role="status"
                >
                    {{ blockingReason ?? copy.submitReady }}
                </p>

                <button
                    type="button"
                    :class="[PRIMARY_BUTTON, 'w-full shrink-0 sm:w-auto']"
                    :disabled="!canSubmit"
                    :aria-describedby="'submit-requirements'"
                    @click="openConfirm"
                >
                    {{ copy.submitButton }}
                </button>
            </div>
        </form>

        <ConfirmDialog
            :open="confirming"
            :title="copy.confirmTitle"
            :body="copy.confirmBody"
            :confirm-label="copy.confirmYes"
            :cancel-label="copy.confirmNo"
            :close-label="copy.confirmClose"
            @confirm="submit"
            @close="confirming = false"
        />
    </section>
</template>
