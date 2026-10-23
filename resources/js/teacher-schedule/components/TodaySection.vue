<script setup>
/**
 * The teacher's day at a glance: every lesson on today's calendar — delivered,
 * running and still to come — with the same actions the agenda offers. Blade
 * counts the day and formats every time, so this section only lays it out.
 */
import AppIcon from '../../shared/components/AppIcon.vue';
import UiSectionHeading from '../../shared/components/UiSectionHeading.vue';
import { usePanel } from '../../shared/composables/usePanel';
import { useCopy, useUrls } from '../composables/usePageContext';
import LessonRow from './LessonRow.vue';

defineProps({
    today: { type: Object, required: true },
});

const copy = useCopy();
const urls = useUrls();
const panel = usePanel();
</script>

<template>
    <section class="space-y-3">
        <UiSectionHeading
            :title="copy.today?.title"
            :description="copy.today?.description"
            icon="calendar"
        >
            <template #action>
                <p v-if="today.count" class="text-xs font-semibold text-slate-400">
                    <span class="text-slate-600 dark:text-slate-300">{{ today.dateLabel }}</span>
                    · {{ today.countLabel }} · {{ today.minutesLabel }}
                </p>
            </template>
        </UiSectionHeading>

        <ol v-if="today.lessons.length" class="space-y-2">
            <LessonRow v-for="lesson in today.lessons" :key="lesson.id" :lesson="lesson" />
        </ol>

        <div v-else :class="[panel, 'flex flex-col items-center gap-2 text-center']">
            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-100 p-2.5 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                <AppIcon name="check-circle" />
            </span>
            <p class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ copy.today?.emptyTitle }}</p>
            <p class="max-w-md text-sm text-slate-500 dark:text-slate-400">{{ copy.today?.emptyBody }}</p>

            <a
                :href="urls.availability"
                class="mt-1 inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:underline"
            >
                {{ copy.today?.availability }}
                <span class="h-4 w-4" aria-hidden="true"><AppIcon name="arrow-right" /></span>
            </a>
        </div>
    </section>
</template>
