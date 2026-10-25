<script setup>
/**
 * "Find a teacher": the grade warning, the subject/language/date filters, the
 * teacher cards and the empty state. Filtering, sorting and paging stay
 * server-side — the filter panel is a real GET form — so Laravel keeps
 * validating the query string and this app only renders the result.
 */
import { provide } from 'vue';
import { THEME_PRESET_KEY, useThemePreset } from '../shared/composables/useThemePreset';
import RevealOnScroll from '../shared/components/RevealOnScroll.vue';
import { COPY_KEY, URLS_KEY } from './composables/usePageContext';
import FinderEmptyState from './components/FinderEmptyState.vue';
import GradeNotice from './components/GradeNotice.vue';
import TeacherCard from './components/TeacherCard.vue';
import TeacherFinderFilters from './components/TeacherFinderFilters.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

// The Alpine shell owns the preset; sharing it here keeps every card in step.
provide(THEME_PRESET_KEY, useThemePreset(props.config.theme?.preset ?? 'classic'));
provide(COPY_KEY, props.config.copy ?? {});
provide(URLS_KEY, props.config.urls ?? {});
</script>

<template>
    <GradeNotice :grade="config.grade" />

    <RevealOnScroll>
        <TeacherFinderFilters
            :filters="config.filters"
            :subject-options="config.subjectOptions"
            :language-options="config.languageOptions"
            :sort-options="config.sortOptions"
            :today="config.today"
            :total-label="config.totalLabel"
            :error-message="config.errorMessage"
            :has-active-filters="config.hasActiveFilters"
        />
    </RevealOnScroll>

    <div
        v-if="config.teachers.length"
        class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 min-[1800px]:grid-cols-5 min-[2100px]:grid-cols-6"
    >
        <RevealOnScroll
            v-for="(teacher, index) in config.teachers"
            :key="teacher.id"
            :delay="Math.min(index, 5) * 30"
            class="h-full"
        >
            <TeacherCard :teacher="teacher" />
        </RevealOnScroll>
    </div>

    <RevealOnScroll v-else :delay="40">
        <FinderEmptyState
            :has-date="config.hasDateFilter"
            :has-filters="config.hasActiveFilters"
        />
    </RevealOnScroll>
</template>
