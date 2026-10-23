<script setup>
/**
 * Onboarding step 2 at /teacher/availability/setup: one real PUT form holding
 * the preset picker, the editable week and the summary/save card. Blade sends
 * the starting week, the presets, the validation messages and the route map, so
 * nothing about the schedule is hardcoded here.
 */
import { computed, provide } from 'vue';
import { THEME_PRESET_KEY, useThemePreset } from '../shared/composables/useThemePreset';
import OnboardingSteps from '../shared/components/OnboardingSteps.vue';
import RevealOnScroll from '../shared/components/RevealOnScroll.vue';
import UiAlert from '../shared/components/UiAlert.vue';
// The week editor, its presets and the summary card are shared with the
// availability manager at /teacher/availability.
import PresetCards from '../shared/availability/components/PresetCards.vue';
import WeekEditor from '../shared/availability/components/WeekEditor.vue';
import WeeklySummaryCard from '../shared/availability/components/WeeklySummaryCard.vue';
import { COPY_KEY, CSRF_KEY, URLS_KEY } from '../shared/availability/context';
import { WEEK_KEY, createWeekEditor } from '../shared/availability/useWeekEditor';

const props = defineProps({
    config: { type: Object, required: true },
});

const copy = computed(() => props.config.copy ?? {});
const urls = computed(() => props.config.urls ?? {});
const messages = computed(() => props.config.errorMessages ?? []);

// The Alpine shell owns the preset; sharing it here keeps every card in step.
provide(THEME_PRESET_KEY, useThemePreset(props.config.theme?.preset ?? 'classic'));
provide(COPY_KEY, props.config.copy ?? {});
provide(URLS_KEY, urls.value);
provide(CSRF_KEY, props.config.csrfToken ?? '');

const editor = createWeekEditor(props.config);
provide(WEEK_KEY, editor);

const { submitting } = editor;
</script>

<template>
    <div class="mx-auto max-w-6xl space-y-6">
        <RevealOnScroll v-if="config.status?.profileCompleted">
            <UiAlert tone="emerald" icon="check">
                <span class="font-semibold">{{ copy.profileCompletedTitle }}.</span>
                {{ copy.profileCompletedBody }}
            </UiAlert>
        </RevealOnScroll>

        <RevealOnScroll v-if="messages.length">
            <UiAlert tone="rose" icon="alert" role="alert">
                <span class="font-semibold">{{ copy.errorTitle }}</span>
                <ul class="mt-1 space-y-0.5">
                    <li v-for="(message, index) in messages" :key="index">{{ message }}</li>
                </ul>
            </UiAlert>
        </RevealOnScroll>

        <RevealOnScroll>
            <OnboardingSteps
                :steps="config.steps ?? []"
                :current="config.currentStep ?? 2"
                :copy="copy"
            />
        </RevealOnScroll>

        <form
            method="POST"
            :action="urls.update"
            class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]"
            @submit="submitting = true"
        >
            <input type="hidden" name="_token" :value="config.csrfToken">
            <input type="hidden" name="_method" value="PUT">

            <div class="space-y-6">
                <RevealOnScroll>
                    <PresetCards />
                </RevealOnScroll>

                <RevealOnScroll :delay="20">
                    <WeekEditor />
                </RevealOnScroll>
            </div>

            <aside class="space-y-6 lg:sticky lg:top-24 lg:self-start">
                <RevealOnScroll :delay="20">
                    <WeeklySummaryCard />
                </RevealOnScroll>
            </aside>
        </form>
    </div>
</template>
