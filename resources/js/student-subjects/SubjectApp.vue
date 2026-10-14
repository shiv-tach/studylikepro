<script setup>
import { provide } from 'vue';
import { THEME_PRESET_KEY, useThemePreset } from '../shared/composables/useThemePreset';
import RevealOnScroll from '../shared/components/RevealOnScroll.vue';
import UiBreadcrumbs from '../shared/components/UiBreadcrumbs.vue';
import { COPY_KEY, CSRF_KEY } from './composables/usePageContext';
import LessonGroups from './components/LessonGroups.vue';
import ServiceCatalog from './components/ServiceCatalog.vue';
import SubjectCta from './components/SubjectCta.vue';
import SubjectHero from './components/SubjectHero.vue';
import TeacherPreview from './components/TeacherPreview.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

provide(THEME_PRESET_KEY, useThemePreset(props.config.theme?.preset ?? 'classic'));
provide(COPY_KEY, props.config.copy);
provide(CSRF_KEY, props.config.csrfToken ?? '');
</script>

<template>
    <div class="mx-auto max-w-6xl space-y-8">
        <UiBreadcrumbs :items="config.breadcrumbs" />

        <RevealOnScroll>
            <SubjectHero :subject="config.subject" />
        </RevealOnScroll>

        <RevealOnScroll :delay="40">
            <ServiceCatalog
                :groups="config.serviceGroups"
                :directory-url="config.teachers.directoryUrl"
            />
        </RevealOnScroll>

        <RevealOnScroll :delay="60">
            <TeacherPreview :teachers="config.teachers" />
        </RevealOnScroll>

        <RevealOnScroll :delay="80">
            <LessonGroups :groups="config.lessonGroups" :grade="config.subject.grade" />
        </RevealOnScroll>

        <RevealOnScroll :delay="100">
            <SubjectCta :ask-url="config.urls.ask" />
        </RevealOnScroll>
    </div>
</template>
