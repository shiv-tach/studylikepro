<script setup>
/**
 * Two different dead ends, told apart: a teacher with no orders at all is
 * pointed at the services console, while a filter that matched nothing is
 * pointed back at the unfiltered list.
 */
import { computed } from 'vue';
import { usePanel } from '../../shared/composables/usePanel';
import AppIcon from '../../shared/components/AppIcon.vue';
import { useCopy, useUrls } from '../composables/usePageContext';

const props = defineProps({
    filtered: { type: Boolean, default: false },
});

const copy = useCopy();
const urls = useUrls();
const panel = usePanel();

const title = computed(() => (props.filtered ? copy.empty?.filteredTitle : copy.empty?.title));
const body = computed(() => (props.filtered ? copy.empty?.filteredBody : copy.empty?.body));
const cta = computed(() => (props.filtered ? copy.empty?.filteredCta : copy.empty?.cta));
const href = computed(() => (props.filtered ? urls.index : urls.services));
const icon = computed(() => (props.filtered ? 'search' : 'wallet'));
</script>

<template>
    <div :class="[panel, 'flex flex-col items-center gap-3 py-12 text-center']">
        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 p-3 text-slate-400 dark:bg-slate-800 dark:text-slate-500">
            <AppIcon :name="icon" />
        </span>

        <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ title }}</h3>
        <p class="max-w-md text-sm text-slate-500 dark:text-slate-400">{{ body }}</p>

        <a
            :href="href"
            class="mt-1 inline-flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-primary/20 transition-colors hover:bg-primary/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
        >
            {{ cta }}
            <span class="h-4 w-4" aria-hidden="true"><AppIcon name="arrow-right" /></span>
        </a>
    </div>
</template>
