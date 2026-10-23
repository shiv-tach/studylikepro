<script setup>
/**
 * The teacher's day at a glance: whatever needs a decision right now — a lesson
 * to close, a running room, an open classroom, a hold about to lapse — each
 * carrying an "Urgent" badge, followed by the lessons on today's calendar that
 * still need them. Delivered lessons are left to the history tab. Blade decides
 * which lessons qualify, ranks the urgent ones and formats every time, so this
 * section only lays it out.
 */
import { computed } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';
import UiSectionHeading from '../../shared/components/UiSectionHeading.vue';
import { usePanel } from '../../shared/composables/usePanel';
import { useCopy, useUrls } from '../composables/usePageContext';
import LessonRow from './LessonRow.vue';
import PriorityRow from './PriorityRow.vue';

const props = defineProps({
    today: { type: Object, required: true },
});

const copy = useCopy();
const urls = useUrls();
const panel = usePanel();

const urgent = computed(() => props.today.urgent ?? []);
const lessons = computed(() => props.today.lessons ?? []);
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

        <ol v-if="urgent.length" class="space-y-3" :aria-label="copy.today?.urgent">
            <PriorityRow v-for="item in urgent" :key="`${item.kind}-${item.id}`" :item="item" />
        </ol>

        <ol v-if="lessons.length" class="space-y-2">
            <LessonRow v-for="lesson in lessons" :key="lesson.id" :lesson="lesson" />
        </ol>

        <div
            v-if="!urgent.length && !lessons.length"
            :class="[panel, 'flex flex-col items-center gap-2 text-center']"
        >
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
