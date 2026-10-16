<script setup>
/**
 * What a student sees when a tab holds nothing: either the wallet is still
 * empty and the teacher directory is the way out, or a service-type filter is
 * hiding everything and clearing it is.
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

const title = computed(() => (props.filtered ? copy.filteredTitle : copy.emptyTitle));
const body = computed(() => (props.filtered ? copy.filteredBody : copy.emptyBody));
const cta = computed(() => (props.filtered ? copy.filteredCta : copy.emptyCta));
const href = computed(() => (props.filtered ? urls.reset : urls.teachers));
</script>

<template>
    <section :class="[panel, 'flex flex-col items-center gap-4 text-center']" aria-labelledby="purchases-empty">
        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 p-3 text-primary">
            <AppIcon :name="filtered ? 'search' : 'tag'" />
        </span>

        <div>
            <h2 id="purchases-empty" class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ title }}</h2>
            <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ body }}</p>
        </div>

        <a
            v-if="href"
            :href="href"
            class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/20 transition-transform hover:-translate-y-0.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
        >
            <span class="h-4 w-4"><AppIcon :name="filtered ? 'search' : 'users'" /></span>
            {{ cta }}
        </a>
    </section>
</template>
