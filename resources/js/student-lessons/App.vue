<script setup>
/**
 * "My lessons": the upcoming/past tabs, the history filters, the lesson cards
 * and the empty state. Filtering, paging and the tab switch stay server-side —
 * the tabs are real links and the filters a real GET form — so Laravel keeps
 * validating the query string and this app only renders the result. The filter
 * panel itself only appears once the active tab holds more than five lessons,
 * a call Blade already made from the tab counts.
 */
import { provide } from 'vue';
import { THEME_PRESET_KEY, useThemePreset } from '../shared/composables/useThemePreset';
import RevealOnScroll from '../shared/components/RevealOnScroll.vue';
import { COPY_KEY, URLS_KEY } from './composables/usePageContext';
import LessonCard from './components/LessonCard.vue';
import LessonFilters from './components/LessonFilters.vue';
import LessonsEmptyState from './components/LessonsEmptyState.vue';
import LessonTabs from './components/LessonTabs.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

// The Alpine shell owns the preset; sharing it here keeps every card in step.
provide(THEME_PRESET_KEY, useThemePreset(props.config.theme?.preset ?? 'classic'));
provide(COPY_KEY, props.config.copy ?? {});
provide(URLS_KEY, props.config.urls ?? {});
</script>

<template>
    <RevealOnScroll v-if="config.flash?.rescheduled">
        <div class="rounded-2xl border border-primary/30 bg-primary/5 p-4 text-sm text-primary" role="status">
            {{ config.copy.rescheduled }}
        </div>
    </RevealOnScroll>

    <RevealOnScroll>
        <LessonTabs :tabs="config.tabs" :active="config.tab" />
    </RevealOnScroll>

    <RevealOnScroll v-if="config.showFilters" :delay="40">
        <LessonFilters
            :tab="config.tab"
            :filters="config.filters"
            :status-options="config.statusOptions"
            :subject-options="config.subjectOptions"
            :teacher-options="config.teacherOptions"
            :has-active-filters="config.hasActiveFilters"
            :match-label="config.matchLabel"
        />
    </RevealOnScroll>

    <RevealOnScroll
        v-for="(lesson, index) in config.lessons"
        :key="lesson.id"
        :delay="Math.min(index, 4) * 20"
    >
        <LessonCard :lesson="lesson" />
    </RevealOnScroll>

    <RevealOnScroll v-if="config.lessons.length === 0" :delay="40">
        <LessonsEmptyState :tab="config.tab" />
    </RevealOnScroll>
</template>
