<script setup>
/**
 * The first thing a new teacher sees on this page: nothing yet, and what turns
 * that around.
 */
import AppIcon from '../../shared/components/AppIcon.vue';
import { usePanel } from '../../shared/composables/usePanel';
import { useCopy } from '../composables/usePageContext';

const copy = useCopy();
const panel = usePanel();
</script>

<template>
    <section :class="[panel, 'text-center']">
        <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10 p-3.5 text-primary">
            <AppIcon name="star" />
        </span>
        <h3 class="mt-4 text-base font-bold text-slate-800 dark:text-slate-100">{{ copy.empty?.title }}</h3>
        <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-500 dark:text-slate-400">
            {{ copy.empty?.body }}
        </p>
    </section>
</template>
