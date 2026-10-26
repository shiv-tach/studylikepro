<script setup>
/**
 * One teacher, seen from the student's workspace and scoped to the grade on
 * their learning profile. Blade decides every part of that — which subjects,
 * lessons and rates apply, which services are on sale, which slots are open and
 * which action the student may take — and this app only composes the panels
 * that render them.
 */
import { provide } from 'vue';
import { THEME_PRESET_KEY, useThemePreset } from '../shared/composables/useThemePreset';
import RevealOnScroll from '../shared/components/RevealOnScroll.vue';
import UiAlert from '../shared/components/UiAlert.vue';
import UiBreadcrumbs from '../shared/components/UiBreadcrumbs.vue';
import { COPY_KEY, CSRF_KEY, URLS_KEY } from './composables/usePageContext';
import AvailabilityPanel from './components/AvailabilityPanel.vue';
import BookingCtaCard from './components/BookingCtaCard.vue';
import ReviewsPanel from './components/ReviewsPanel.vue';
import ServicesPanel from './components/ServicesPanel.vue';
import SubjectsPanel from './components/SubjectsPanel.vue';
import TeacherHeroCard from './components/TeacherHeroCard.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

// The Alpine shell owns the preset; sharing it here keeps every panel in step.
provide(THEME_PRESET_KEY, useThemePreset(props.config.theme?.preset ?? 'classic'));
provide(COPY_KEY, props.config.copy ?? {});
provide(URLS_KEY, props.config.urls ?? {});
provide(CSRF_KEY, props.config.csrfToken ?? '');
</script>

<template>
    <div class="w-full space-y-6">
        <UiBreadcrumbs :items="config.breadcrumbs" />

        <!-- No grade on the learning profile: nothing can be priced or scoped yet. -->
        <RevealOnScroll v-if="!config.grade">
            <UiAlert tone="amber" icon="alert">
                {{ config.copy.addGrade }}
                <a :href="config.urls.profile" class="font-semibold underline">{{ config.copy.completeProfile }}</a>
            </UiAlert>
        </RevealOnScroll>

        <!-- The profile has a grade, but this teacher does not take it. -->
        <RevealOnScroll v-else-if="!config.gradeScoped">
            <UiAlert tone="amber" icon="alert">
                <span class="flex flex-wrap items-center gap-x-3 gap-y-2">
                    <span class="font-semibold">{{ config.copy.gradeMismatch }}</span>
                    <a :href="config.urls.index" class="font-semibold underline sm:ml-auto">
                        {{ config.copy.findGradeTeachers }}
                    </a>
                </span>
            </UiAlert>
        </RevealOnScroll>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_16rem] xl:grid-cols-[minmax(0,1fr)_20rem] 2xl:grid-cols-[minmax(0,1fr)_22rem]">
            <!-- Main column -->
            <div class="min-w-0 space-y-6">
                <RevealOnScroll>
                    <TeacherHeroCard :teacher="config.teacher" />
                </RevealOnScroll>

                <RevealOnScroll v-if="config.gradeScoped" :delay="20">
                    <SubjectsPanel :subjects="config.subjects" :grade="config.grade" />
                </RevealOnScroll>

                <RevealOnScroll v-if="config.serviceGroups.length" :delay="40">
                    <ServicesPanel :groups="config.serviceGroups" />
                </RevealOnScroll>

                <RevealOnScroll :delay="60">
                    <AvailabilityPanel :availability="config.availability" />
                </RevealOnScroll>

                <RevealOnScroll :delay="80">
                    <ReviewsPanel :reviews="config.reviews" />
                </RevealOnScroll>
            </div>

            <!-- Sidebar -->
            <div class="lg:sticky lg:top-24 lg:self-start">
                <RevealOnScroll>
                    <BookingCtaCard :booking="config.booking" />
                </RevealOnScroll>
            </div>
        </div>
    </div>
</template>
