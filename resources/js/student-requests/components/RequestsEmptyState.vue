<script setup>
/**
 * What a student sees before they have asked anything: what the form expects
 * and the two ways out of an empty page.
 */
import { usePanel } from '../../shared/composables/usePanel';
import AppIcon from '../../shared/components/AppIcon.vue';
import { useCopy, useUrls } from '../composables/usePageContext';

const copy = useCopy();
const urls = useUrls();
const panel = usePanel();

const primaryClass =
    'inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/20 transition-transform hover:-translate-y-0.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40';

const secondaryClass =
    'inline-flex items-center gap-2 rounded-xl border border-slate-200/80 px-4 py-2.5 text-sm font-semibold text-slate-600 transition-colors hover:border-primary/40 hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 dark:border-slate-800 dark:text-slate-300';
</script>

<template>
    <section :class="[panel, 'flex flex-col items-center gap-4 text-center']" aria-labelledby="requests-empty">
        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 p-3 text-primary">
            <AppIcon name="clipboard" />
        </span>

        <div>
            <h2 id="requests-empty" class="text-lg font-bold text-slate-800 dark:text-slate-100">
                {{ copy.emptyTitle }}
            </h2>
            <p class="mx-auto mt-1 max-w-md text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                {{ copy.emptyBody }}
            </p>
            <p class="mx-auto mt-2 max-w-md text-xs text-slate-400">{{ copy.emptyHint }}</p>
        </div>

        <div class="flex flex-wrap justify-center gap-3">
            <a v-if="urls.create" :href="urls.create" :class="primaryClass">
                <span class="h-4 w-4"><AppIcon name="sparkles" /></span>
                {{ copy.emptyCta }}
            </a>
            <a v-if="urls.teachers" :href="urls.teachers" :class="secondaryClass">
                <span class="h-4 w-4"><AppIcon name="users" /></span>
                {{ copy.findTeacher }}
            </a>
        </div>
    </section>
</template>
