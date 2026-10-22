<script setup>
/**
 * The closing nudge on the subject page: find a verified teacher who teaches
 * this subject for the student's grade.
 */
import { usePanel } from '../../shared/composables/usePanel';
import { useCopy } from '../composables/usePageContext';

defineProps({
    teachersUrl: { type: String, default: null },
});

const panel = usePanel();
const copy = useCopy();
</script>

<template>
    <section :class="panel">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-base font-bold text-slate-800 dark:text-slate-100">
                    {{ copy.ctaTitle }}
                </h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ copy.ctaBody }}</p>
            </div>

            <a
                :href="teachersUrl"
                class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-primary/20 transition hover:bg-primary/90"
            >
                {{ copy.ctaAction }}
            </a>
        </div>
    </section>
</template>
