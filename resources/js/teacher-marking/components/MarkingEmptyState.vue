<script setup>
/**
 * Nothing to mark: explains where the first paper comes from and links straight
 * to the offers that produce them.
 */
import AppIcon from '../../shared/components/AppIcon.vue';
import { usePanel } from '../../shared/composables/usePanel';
import { useCopy, useUrls } from '../composables/usePageContext';

const copy = useCopy();
const urls = useUrls();
const panel = usePanel();
</script>

<template>
    <div :class="[panel, 'text-center']">
        <span
            aria-hidden="true"
            class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10 p-3 text-primary"
        >
            <AppIcon name="clipboard" />
        </span>

        <h3 class="mt-4 text-base font-bold text-slate-800 dark:text-slate-100">{{ copy.empty?.title }}</h3>
        <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-500 dark:text-slate-400">
            {{ copy.empty?.body }}
        </p>

        <a
            :href="urls.services"
            class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-primary transition-colors hover:text-primary/80"
        >
            {{ copy.empty?.cta }}
            <span class="h-4 w-4" aria-hidden="true"><AppIcon name="arrow-right" /></span>
        </a>
    </div>
</template>
