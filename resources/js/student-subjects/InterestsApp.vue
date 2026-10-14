<script setup>
import { computed, provide } from 'vue';
import { THEME_PRESET_KEY, useThemePreset } from '../shared/composables/useThemePreset';
import AppIcon from '../shared/components/AppIcon.vue';
import RevealOnScroll from '../shared/components/RevealOnScroll.vue';
import UiEmptyState from '../shared/components/UiEmptyState.vue';
import { COPY_KEY } from './composables/usePageContext';
import SubjectGroupSection from './components/SubjectGroupSection.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

// The Alpine shell owns the preset; sharing it here keeps every card in step.
provide(THEME_PRESET_KEY, useThemePreset(props.config.theme?.preset ?? 'classic'));
provide(COPY_KEY, props.config.copy);

const groups = computed(() => props.config.groups ?? []);
const hasGroups = computed(() => groups.value.length > 0);
const urls = computed(() => props.config.urls ?? {});
const stats = computed(() => props.config.stats ?? {});

// The hero chips summarise the page: the student's grade plus how many
// subjects and subject areas they are focusing on.
const heroChips = computed(() => {
    const chips = [];

    if (props.config.gradeLabel) {
        chips.push({ key: 'grade', icon: 'graduation', value: props.config.gradeLabel });
    }

    if (stats.value.subjects > 0) {
        chips.push({ key: 'subjects', icon: 'book', value: stats.value.subjectsLabel });
    }

    if (stats.value.areas > 0) {
        chips.push({ key: 'areas', icon: 'palette', value: stats.value.areasLabel });
    }

    return chips;
});
</script>

<template>
    <div class="space-y-8">
        <RevealOnScroll>
            <section
                aria-labelledby="interests-hero"
                class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-primary via-primary/80 to-purple-600 p-6 text-white shadow-lg shadow-primary/10 dark:shadow-primary/5 sm:p-8"
            >
                <div class="relative z-10 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-2xl">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-white/70">
                            {{ config.appName }}
                        </p>
                        <h1 id="interests-hero" class="mt-1 text-2xl font-extrabold tracking-tight sm:text-3xl">
                            {{ config.copy.heroTitle }} 🎯
                        </h1>
                        <p class="mt-2 text-sm leading-relaxed text-white/90">
                            {{ config.copy.heroIntro }}
                        </p>

                        <ul v-if="heroChips.length" class="mt-4 flex flex-wrap gap-2">
                            <li
                                v-for="chip in heroChips"
                                :key="chip.key"
                                class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold backdrop-blur-sm"
                            >
                                <span class="h-3.5 w-3.5 shrink-0" aria-hidden="true"><AppIcon :name="chip.icon" /></span>
                                {{ chip.value }}
                            </li>
                        </ul>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <a
                            v-if="urls.teachers"
                            :href="urls.teachers"
                            class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-primary shadow-sm transition-transform hover:-translate-y-0.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-primary"
                        >
                            <span class="h-4 w-4"><AppIcon name="users" /></span>
                            {{ config.copy.findTeacher }}
                        </a>
                        <a
                            v-if="urls.dashboard"
                            :href="urls.dashboard"
                            class="inline-flex items-center gap-2 rounded-xl border border-white/40 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white backdrop-blur-sm transition-colors hover:bg-white/20"
                        >
                            <span class="h-4 w-4"><AppIcon name="chevron-left" /></span>
                            {{ config.copy.backToDashboard }}
                        </a>
                    </div>
                </div>

                <div class="pointer-events-none absolute -right-6 -top-6 h-36 w-36 rounded-full bg-white/10 blur-xl" aria-hidden="true"></div>
                <div class="pointer-events-none absolute -bottom-10 right-20 h-28 w-28 rounded-full bg-white/10 blur-lg" aria-hidden="true"></div>
            </section>
        </RevealOnScroll>

        <RevealOnScroll v-if="config.flash.updated" :delay="20">
            <div
                class="flex items-start gap-3 rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300"
                role="status"
            >
                <span class="mt-0.5 h-4 w-4 shrink-0"><AppIcon name="check-circle" /></span>
                <span>{{ config.copy.updated }}</span>
            </div>
        </RevealOnScroll>

        <RevealOnScroll v-if="config.flash.basketReminder" :delay="30">
            <div
                class="flex items-start gap-3 rounded-2xl border border-amber-200/80 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300"
                role="status"
            >
                <span class="mt-0.5 h-4 w-4 shrink-0"><AppIcon name="sparkles" /></span>
                <span>{{ config.flash.basketReminder }}</span>
            </div>
        </RevealOnScroll>

        <div v-if="!hasGroups" class="mx-auto max-w-2xl">
            <UiEmptyState icon="book" :message="config.copy.empty" />
        </div>

        <div v-else class="space-y-10">
            <RevealOnScroll
                v-for="(group, index) in groups"
                :key="group.key"
                :delay="40 + index * 30"
            >
                <SubjectGroupSection :group="group" />
            </RevealOnScroll>
        </div>
    </div>
</template>
