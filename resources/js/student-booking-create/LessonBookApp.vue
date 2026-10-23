<script setup>
/**
 * The direct-booking stepper at /student/lessons/book/{teacher}. Blade decides
 * everything that needs a permission check or a price — which subjects and
 * lessons apply at the learner's grade, the lesson length options, the open
 * slots in the viewer's timezone and the order total — and this app only
 * composes the three steps that collect the choice, plus the order summary.
 */
import { provide } from 'vue';
import { THEME_PRESET_KEY, useThemePreset } from '../shared/composables/useThemePreset';
import RevealOnScroll from '../shared/components/RevealOnScroll.vue';
import { COPY_KEY, CSRF_KEY, URLS_KEY } from './composables/usePageContext';
import BookingFormCard from './components/BookingFormCard.vue';
import LessonPickerCard from './components/LessonPickerCard.vue';
import OrderSummaryCard from './components/OrderSummaryCard.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

// The Alpine shell owns the preset; sharing it here keeps every card in step.
provide(THEME_PRESET_KEY, useThemePreset(props.config.theme?.preset ?? 'classic'));
provide(COPY_KEY, props.config.copy ?? {});
provide(URLS_KEY, props.config.urls ?? {});
provide(CSRF_KEY, props.config.csrfToken ?? '');
</script>

<template>
    <div class="mx-auto max-w-5xl">
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="space-y-6">
                <RevealOnScroll>
                    <LessonPickerCard
                        :selection="config.selection"
                        :subjects="config.subjects"
                        :lessons="config.lessons"
                        :durations="config.durations"
                        :notice="config.lessonsNotice"
                    />
                </RevealOnScroll>

                <RevealOnScroll :delay="20">
                    <BookingFormCard
                        :selection="config.selection"
                        :slots="config.slots"
                        :slots-notice="config.slotsNotice"
                        :slots-window="config.slotsWindow"
                        :slots-timezone="config.slotsTimezone"
                        :timezone="config.timezone"
                        :hold-notice="config.holdNotice"
                        :selected-slot="config.selectedSlot"
                        :learner="config.learner"
                        :errors="config.errors"
                        :summary="config.summary"
                    />
                </RevealOnScroll>
            </div>

            <div class="lg:sticky lg:top-24 lg:self-start">
                <RevealOnScroll :delay="40">
                    <OrderSummaryCard :summary="config.summary" />
                </RevealOnScroll>
            </div>
        </div>
    </div>
</template>
