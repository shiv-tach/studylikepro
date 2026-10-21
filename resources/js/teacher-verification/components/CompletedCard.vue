<script setup>
/**
 * Once the documents are in review there is nothing left to edit on this page,
 * so it closes the flow: what happens next, and the way into the dashboard the
 * rest of the teacher area lives behind.
 */
import { usePanel } from '../../shared/composables/usePanel';
import AppIcon from '../../shared/components/AppIcon.vue';
import { useCopy, useUrls } from '../composables/usePageContext';

const copy = useCopy();
const urls = useUrls();
const panel = usePanel();
</script>

<template>
    <section :class="panel">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-start gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-100 p-2.5 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                    <AppIcon name="check-circle" />
                </span>

                <div>
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ copy.completeTitle }}</h3>
                    <p class="mt-1 text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ copy.completeBody }}</p>
                </div>
            </div>

            <a
                v-if="urls.dashboard"
                :href="urls.dashboard"
                class="inline-flex shrink-0 items-center gap-1.5 rounded-xl bg-primary px-4 py-2.5 text-sm font-bold text-white shadow-md shadow-primary/20 transition-all duration-200 hover:bg-primary/90 hover:shadow-lg active:scale-[0.98]"
            >
                {{ copy.completeCta }}
                <span class="h-4 w-4"><AppIcon name="arrow-right" /></span>
            </a>
        </div>
    </section>
</template>
