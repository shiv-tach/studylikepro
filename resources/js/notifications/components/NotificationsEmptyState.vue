<script setup>
/**
 * The empty state: nothing has arrived yet, so say what will.
 */
import { usePanel } from '../../shared/composables/usePanel';
import { useCopy } from '../composables/usePageContext';

const copy = useCopy();
const panel = usePanel();
</script>

<template>
    <div :class="[panel, 'text-center']">
        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ copy.emptyTitle }}</p>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ copy.emptyHint }}</p>
    </div>
</template>
