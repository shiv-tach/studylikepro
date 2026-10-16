<script setup>
/**
 * What a student sees when the tab is empty. The copy follows the tab: nothing
 * booked yet sends them to the teacher directory, an empty history only explains
 * what will land there.
 */
import { computed } from 'vue';
import { usePanel } from '../../shared/composables/usePanel';
import AppIcon from '../../shared/components/AppIcon.vue';
import { useCopy, useUrls } from '../composables/usePageContext';

const props = defineProps({
    tab: { type: String, required: true },
});

const copy = useCopy();
const urls = useUrls();
const panel = usePanel();

const isUpcoming = computed(() => props.tab !== 'past');
</script>

<template>
    <section :class="[panel, 'flex flex-col items-center gap-4 text-center']" aria-labelledby="lessons-empty">
        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 p-3 text-primary">
            <AppIcon :name="isUpcoming ? 'calendar' : 'folder'" />
        </span>

        <div>
            <h2 id="lessons-empty" class="text-lg font-bold text-slate-800 dark:text-slate-100">
                {{ isUpcoming ? copy.emptyUpcomingTitle : copy.emptyPastTitle }}
            </h2>
            <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                {{ isUpcoming ? copy.emptyUpcomingBody : copy.emptyPastBody }}
            </p>
        </div>

        <a
            v-if="isUpcoming && urls.teachers"
            :href="urls.teachers"
            class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/20 transition-transform hover:-translate-y-0.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
        >
            <span class="h-4 w-4"><AppIcon name="users" /></span>
            {{ copy.browseTeachers }}
        </a>
    </section>
</template>
