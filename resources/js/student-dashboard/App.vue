<script setup>
import { computed, provide } from 'vue';
import { roadmapSteps } from './content';
import { THEME_PRESET_KEY, useThemePreset } from './composables/useThemePreset';
import LearningRoadmap from './components/LearningRoadmap.vue';
import NextLessonCard from './components/NextLessonCard.vue';
import PlatformFlow from './components/PlatformFlow.vue';
import QuickActionCard from './components/QuickActionCard.vue';
import RevealOnScroll from './components/RevealOnScroll.vue';
import ReviewPrompt from './components/ReviewPrompt.vue';
import StatTile from './components/StatTile.vue';
import WelcomeBanner from './components/WelcomeBanner.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

const urls = computed(() => props.config.urls);
const stats = computed(() => props.config.stats);

// The Alpine shell owns the preset; sharing it here keeps every card in step.
provide(THEME_PRESET_KEY, useThemePreset(props.config.theme.preset));

const statTiles = computed(() => [
    {
        key: 'subjects',
        label: 'Subjects you follow',
        value: stats.value.subjects,
        icon: 'book',
        tone: 'violet',
        href: urls.value.subjects,
    },
    {
        key: 'requests',
        label: 'Open requests',
        value: stats.value.openRequests,
        icon: 'clipboard',
        tone: 'primary',
        href: urls.value.requests,
    },
    {
        key: 'upcoming',
        label: 'Upcoming lessons',
        value: stats.value.upcomingLessons,
        icon: 'calendar',
        tone: 'emerald',
        href: urls.value.bookings,
    },
    {
        key: 'completed',
        label: 'Lessons completed',
        value: stats.value.completedLessons,
        icon: 'check-circle',
        tone: 'amber',
        href: urls.value.pastBookings,
    },
]);

// The onboarding gate already ran, so the profile step is always behind us.
const completedSteps = computed(() => {
    const counts = stats.value;

    return [
        'profile',
        counts.subjects > 0 ? 'subjects' : null,
        counts.openRequests > 0 || counts.upcomingLessons > 0 || counts.completedLessons > 0 ? 'question' : null,
        counts.upcomingLessons > 0 || counts.completedLessons > 0 ? 'booking' : null,
        counts.completedLessons > 0 ? 'lesson' : null,
        counts.reviewedLessons > 0 ? 'review' : null,
    ].filter(Boolean);
});

const progressPercent = computed(() => Math.round((completedSteps.value.length / roadmapSteps.length) * 100));
</script>

<template>
    <div
        v-if="config.flash.profileCompleted"
        class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300"
        role="status"
    >
        Your profile is complete — welcome to Studylikepro! 🎉
    </div>

    <RevealOnScroll>
        <WelcomeBanner :app-name="config.appName" :user="config.user" :urls="urls" />
    </RevealOnScroll>

    <RevealOnScroll :delay="60">
        <section aria-label="Your learning at a glance" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatTile
                v-for="tile in statTiles"
                :key="tile.key"
                :label="tile.label"
                :value="tile.value"
                :icon="tile.icon"
                :tone="tile.tone"
                :href="tile.href"
            />
        </section>
    </RevealOnScroll>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <RevealOnScroll :delay="40">
                <NextLessonCard :lesson="config.nextLesson" :urls="urls" />
            </RevealOnScroll>

            <RevealOnScroll v-if="config.awaitingReview" :delay="60">
                <ReviewPrompt :lesson="config.awaitingReview" />
            </RevealOnScroll>

            <RevealOnScroll :delay="80">
                <section aria-labelledby="dashboard-actions" class="space-y-4">
                    <h2 id="dashboard-actions" class="text-base font-bold text-slate-800 dark:text-slate-200">
                        Quick actions
                    </h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <QuickActionCard
                            v-for="action in config.quickActions"
                            :key="action.key"
                            :label="action.label"
                            :description="action.description"
                            :eyebrow="action.eyebrow"
                            :icon="action.icon"
                            :tone="action.tone"
                            :badge="action.badge"
                            :url="urls[action.urlKey]"
                        />
                    </div>
                </section>
            </RevealOnScroll>
        </div>

        <div class="space-y-6">
            <RevealOnScroll :delay="40">
                <LearningRoadmap :completed="completedSteps" :percent="progressPercent" />
            </RevealOnScroll>

            <RevealOnScroll :delay="60">
                <PlatformFlow />
            </RevealOnScroll>
        </div>
    </div>
</template>
