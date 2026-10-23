<script setup>
/**
 * One day in the agenda: its label ("Today", "Tomorrow", the weekday), how many
 * lessons and how many minutes, then the lessons themselves.
 */
import LessonRow from './LessonRow.vue';

defineProps({
    day: { type: Object, required: true },
});
</script>

<template>
    <section class="space-y-2">
        <header class="flex flex-wrap items-baseline justify-between gap-2 px-1">
            <p class="flex items-baseline gap-2">
                <span class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ day.label }}</span>
                <span class="text-xs text-slate-400">{{ day.dateLabel }}</span>
            </p>
            <p class="text-xs font-medium text-slate-400">
                {{ day.countLabel }} · {{ day.minutesLabel }}
            </p>
        </header>

        <ol class="space-y-2">
            <LessonRow v-for="lesson in day.lessons" :key="lesson.id" :lesson="lesson" />
        </ol>
    </section>
</template>
