<script setup>
/**
 * Thumb-reachable Back/Continue bar for phones. It is a real submit button
 * wired to the form by id, so the inline desktop row can stay desktop-only and
 * the no-JS twin can keep submitting through the same markup.
 */
import { useSurveyContext } from '../composables/useSurveyContext';
import SubmitButton from './SubmitButton.vue';

defineProps({
    formId: { type: String, required: true },
    backUrl: { type: String, default: null },
    submitLabel: { type: String, default: '' },
});

const { config } = useSurveyContext();
</script>

<template>
    <div class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200/80 bg-white/95 px-4 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-3 backdrop-blur-md dark:border-slate-800/80 dark:bg-slate-950/95 lg:hidden">
        <div class="mx-auto flex w-full max-w-3xl items-center gap-3">
            <a
                v-if="backUrl"
                :href="backUrl"
                class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl border border-slate-200 px-4 text-sm font-semibold text-slate-600 transition-colors hover:border-slate-300 hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 dark:border-slate-700 dark:text-slate-300 dark:hover:border-slate-600"
            >
                {{ config.copy.back }}
            </a>

            <SubmitButton :form-id="formId" :label="submitLabel" class="flex-1" />
        </div>
    </div>
</template>
