<script setup>
/**
 * The bottom action bar every wizard step shares. On phones it sticks to the
 * bottom of the viewport (thumb reach, safe-area aware); from `lg` it becomes a
 * normal card at the end of the form.
 *
 * The submit button lives here but the surrounding step owns the real <form>,
 * so the browser submits exactly one form with Laravel's own validation.
 */
import AppIcon from '../../components/AppIcon.vue';
import DraftStatus from './DraftStatus.vue';

defineProps({
    back: { type: String, default: null },
    backLabel: { type: String, default: 'Back' },
    submitLabel: { type: String, default: 'Save & continue' },
    savingLabel: { type: String, default: 'Saving…' },
    submitting: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    note: { type: String, default: null },
    draftStatus: { type: String, default: 'idle' },
    draftLabels: { type: Object, default: () => ({}) },
});
</script>

<template>
    <div
        class="sticky bottom-0 z-20 -mx-4 mt-6 border-t border-slate-200/80 bg-white/95 px-4 py-3 backdrop-blur-md dark:border-slate-800/80 dark:bg-slate-950/95 lg:static lg:mx-0 lg:rounded-2xl lg:border lg:px-5 lg:py-4"
        style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));"
    >
        <div class="flex items-center justify-between gap-3">
            <a
                v-if="back"
                :href="back"
                class="inline-flex min-h-11 items-center gap-1 rounded-xl px-2 text-sm font-semibold text-slate-500 transition-colors hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-100"
            >
                <span class="h-4 w-4 shrink-0"><AppIcon name="chevron-left" /></span>
                {{ backLabel }}
            </a>
            <span v-else></span>

            <div class="flex min-w-0 items-center gap-3">
                <DraftStatus class="hidden sm:flex" :status="draftStatus" :labels="draftLabels" />

                <button
                    type="submit"
                    class="inline-flex min-h-11 shrink-0 items-center justify-center gap-1.5 rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary/20 transition-colors hover:bg-primary/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="submitting || disabled"
                >
                    {{ submitting ? savingLabel : submitLabel }}
                    <span v-if="!submitting" class="h-4 w-4 shrink-0"><AppIcon name="arrow-right" /></span>
                </button>
            </div>
        </div>

        <DraftStatus class="mt-1.5 sm:hidden" :status="draftStatus" :labels="draftLabels" />

        <p v-if="note" class="mt-1 text-[11px] text-slate-400">{{ note }}</p>
    </div>
</template>
