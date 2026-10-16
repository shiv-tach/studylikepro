<script setup>
/**
 * One lesson at /student/lessons/{booking}. Blade decides everything that needs
 * a permission check, a clock or a price — whether the room is open, whether the
 * hold lapsed, which actions the student may take, the money breakdown and the
 * review state — and this app only composes the panels that render them.
 */
import { provide, ref } from 'vue';
import { THEME_PRESET_KEY, useThemePreset } from '../shared/composables/useThemePreset';
import RevealOnScroll from '../shared/components/RevealOnScroll.vue';
import UiAlert from '../shared/components/UiAlert.vue';
import { COPY_KEY, CSRF_KEY, URLS_KEY } from './composables/usePageContext';
import CancelBookingDialog from './components/CancelBookingDialog.vue';
import HoldPanel from './components/HoldPanel.vue';
import JoinCallout from './components/JoinCallout.vue';
import LessonDetailsPanel from './components/LessonDetailsPanel.vue';
import PaymentPanel from './components/PaymentPanel.vue';
import ReviewPanel from './components/ReviewPanel.vue';
import StatusTimelinePanel from './components/StatusTimelinePanel.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

// The Alpine shell owns the preset; sharing it here keeps every panel in step.
provide(THEME_PRESET_KEY, useThemePreset(props.config.theme?.preset ?? 'classic'));
provide(COPY_KEY, props.config.copy ?? {});
provide(URLS_KEY, props.config.urls ?? {});
provide(CSRF_KEY, props.config.csrfToken ?? '');

// A rejected cancellation bounces back with the reason field's error, so the
// dialog reopens already carrying it.
const cancelOpen = ref(Boolean(props.config.cancel?.error));
</script>

<template>
    <div class="space-y-6">
        <RevealOnScroll v-if="config.flash">
            <UiAlert :tone="config.flash.tone" :icon="config.flash.icon">{{ config.flash.message }}</UiAlert>
        </RevealOnScroll>

        <RevealOnScroll v-if="config.error">
            <UiAlert tone="rose" icon="alert" role="alert">{{ config.error }}</UiAlert>
        </RevealOnScroll>

        <RevealOnScroll v-if="config.join">
            <JoinCallout :join="config.join" />
        </RevealOnScroll>

        <RevealOnScroll v-if="config.banner">
            <HoldPanel :banner="config.banner" />
        </RevealOnScroll>

        <RevealOnScroll :delay="20">
            <LessonDetailsPanel :details="config.details" />
        </RevealOnScroll>

        <RevealOnScroll :delay="40">
            <StatusTimelinePanel :timeline="config.timeline" />
        </RevealOnScroll>

        <RevealOnScroll :delay="60">
            <PaymentPanel
                :money="config.money"
                :actions="config.actions"
                :cancel-notice="config.cancelNotice"
                @cancel="cancelOpen = true"
            />
        </RevealOnScroll>

        <RevealOnScroll v-if="config.review" :delay="80">
            <ReviewPanel :review="config.review" />
        </RevealOnScroll>
    </div>

    <CancelBookingDialog
        v-if="config.cancel"
        :open="cancelOpen"
        :cancel="config.cancel"
        @close="cancelOpen = false"
    />
</template>
