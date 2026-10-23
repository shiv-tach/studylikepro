<script setup>
/**
 * The availability manager at /teacher/availability.
 *
 * Blade decides the saved week, the default price, every formatted time and
 * price, the validation messages and the route map; this app only lays the
 * answer out: the hero, one real PUT form for the whole week (the editor and
 * the summary card side by side), then the pricing, the bookable preview and
 * the time off. Each panel posts its own form, so no request ever nests inside
 * another one.
 */
import { computed, provide } from 'vue';
import { THEME_PRESET_KEY, useThemePreset } from '../shared/composables/useThemePreset';
import { useNotices } from '../shared/composables/useNotices';
import RevealOnScroll from '../shared/components/RevealOnScroll.vue';
import UiAlert from '../shared/components/UiAlert.vue';
import UiToastStack from '../shared/components/UiToastStack.vue';
import { WEEK_KEY, createWeekEditor } from '../shared/availability/useWeekEditor';
import {
    COPY_KEY as WEEK_COPY_KEY,
    CSRF_KEY as WEEK_CSRF_KEY,
    URLS_KEY as WEEK_URLS_KEY,
} from '../shared/availability/context';
import PresetCards from '../shared/availability/components/PresetCards.vue';
import WeekEditor from '../shared/availability/components/WeekEditor.vue';
import WeeklySummaryCard from '../shared/availability/components/WeeklySummaryCard.vue';
import { COPY_KEY, CSRF_KEY, PAGE_KEY, URLS_KEY } from './composables/usePageContext';
import AvailabilityHero from './components/AvailabilityHero.vue';
import AvailabilityPreviewCard from './components/AvailabilityPreviewCard.vue';
import PricingCard from './components/PricingCard.vue';
import TimeOffCard from './components/TimeOffCard.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

provide(THEME_PRESET_KEY, useThemePreset(props.config.theme?.preset ?? 'classic'));
provide(COPY_KEY, props.config.copy ?? {});
provide(URLS_KEY, props.config.urls ?? {});
provide(CSRF_KEY, props.config.csrfToken ?? '');
provide(PAGE_KEY, props.config);

// The shared week editor injects its own keys (so the onboarding step and this
// page can each hand it a different copy block).
provide(WEEK_COPY_KEY, props.config.copy ?? {});
provide(WEEK_URLS_KEY, props.config.urls ?? {});
provide(WEEK_CSRF_KEY, props.config.csrfToken ?? '');

const editor = createWeekEditor(props.config);
provide(WEEK_KEY, editor);

const copy = computed(() => props.config.copy ?? {});
const urls = computed(() => props.config.urls ?? {});
const messages = computed(() => props.config.errorMessages ?? []);

const { submitting } = editor;
const { toasts, dismiss } = useNotices(props.config.flash ? [props.config.flash] : []);
</script>

<template>
    <div class="space-y-6">
        <UiToastStack
            :toasts="toasts"
            :label="copy.toastLabel ?? 'Notifications'"
            :dismiss-label="copy.toastDismiss ?? 'Dismiss'"
            @dismiss="dismiss"
        />

        <RevealOnScroll>
            <AvailabilityHero />
        </RevealOnScroll>

        <RevealOnScroll v-if="messages.length" :delay="10">
            <UiAlert tone="rose" icon="alert" role="alert">
                <span class="font-semibold">{{ copy.errorTitle }}</span>
                <ul class="mt-1 space-y-0.5">
                    <li v-for="(message, index) in messages" :key="index">{{ message }}</li>
                </ul>
            </UiAlert>
        </RevealOnScroll>

        <!-- One real PUT form for the whole week: the editor and its summary
             travel together, so the save button always describes the grid. -->
        <form
            method="POST"
            :action="urls.week"
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

        <RevealOnScroll>
            <PricingCard />
        </RevealOnScroll>

        <RevealOnScroll>
            <AvailabilityPreviewCard />
        </RevealOnScroll>

        <RevealOnScroll>
            <TimeOffCard />
        </RevealOnScroll>
    </div>
</template>
