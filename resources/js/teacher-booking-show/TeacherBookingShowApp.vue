<script setup>
/**
 * One lesson at /teacher/schedule/{booking}.
 *
 * Blade decides every permission, time, price and route (and whether the
 * classroom is open); this app answers the teacher's two questions in order —
 * "what do I do next?" and "what else is waiting?" — before showing the
 * paperwork.
 */
import { onBeforeUnmount, onMounted, provide, ref } from 'vue';
import { THEME_PRESET_KEY, useThemePreset } from '../shared/composables/useThemePreset';
import { useNotices } from '../shared/composables/useNotices';
import RevealOnScroll from '../shared/components/RevealOnScroll.vue';
import UiAlert from '../shared/components/UiAlert.vue';
import UiToastStack from '../shared/components/UiToastStack.vue';
import { COPY_KEY, CSRF_KEY, PAGE_KEY, URLS_KEY } from './composables/usePageContext';
import BookingHero from './components/BookingHero.vue';
import CancelLessonDialog from './components/CancelLessonDialog.vue';
import ClassroomPanel from './components/ClassroomPanel.vue';
import DetailsPanel from './components/DetailsPanel.vue';
import EarningsPanel from './components/EarningsPanel.vue';
import LessonActions from './components/LessonActions.vue';
import LessonJourney from './components/LessonJourney.vue';
import NextStepCard from './components/NextStepCard.vue';
import PriorityQueue from './components/PriorityQueue.vue';
import TimelinePanel from './components/TimelinePanel.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

// The shell owns the preset; sharing it keeps every panel in step with the
// opaque/frosted switch.
provide(THEME_PRESET_KEY, useThemePreset(props.config.theme?.preset ?? 'classic'));
provide(COPY_KEY, props.config.copy ?? {});
provide(URLS_KEY, props.config.urls ?? {});
provide(PAGE_KEY, props.config);
provide(CSRF_KEY, props.config.csrfToken ?? '');

const { toasts, dismiss } = useNotices(props.config.flash ? [props.config.flash] : []);

// A rejected cancellation bounces back with the reason field's error, so the
// dialog reopens already carrying it.
const cancelOpen = ref(Boolean(props.config.cancel?.error));

// The page's answer changes when the start window opens, the room opens/closes
// or the hold lapses. Reload once at that instant instead of making the teacher
// guess that a refresh is needed.
let refreshTimer = null;

onMounted(() => {
    const at = props.config.refreshAt;

    if (!at) {
        return;
    }

    const ms = Date.parse(at) - Date.now();

    if (!Number.isFinite(ms) || ms <= 1500 || ms > 24 * 60 * 60 * 1000) {
        return;
    }

    const key = `teacher-booking-refresh:${at}`;

    try {
        if (window.sessionStorage.getItem(key)) {
            return;
        }
    } catch {
        // Session storage can be blocked; the reload is an enhancement only.
    }

    refreshTimer = window.setTimeout(() => {
        try {
            window.sessionStorage.setItem(key, '1');
        } catch {
            // ignore
        }

        window.location.reload();
    }, ms);
});

onBeforeUnmount(() => {
    if (refreshTimer !== null) {
        window.clearTimeout(refreshTimer);
    }
});
</script>

<template>
    <div class="space-y-6">
        <UiToastStack :toasts="toasts" @dismiss="dismiss" />

        <RevealOnScroll>
            <BookingHero :header="config.header" :lesson="config.lesson" />
        </RevealOnScroll>

        <RevealOnScroll v-if="config.error" :delay="10">
            <UiAlert tone="rose" icon="alert" role="alert">{{ config.error }}</UiAlert>
        </RevealOnScroll>

        <RevealOnScroll :delay="20">
            <NextStepCard :next="config.next" @cancel="cancelOpen = true" />
        </RevealOnScroll>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem] xl:items-start">
            <div class="space-y-6">
                <RevealOnScroll :delay="30">
                    <LessonJourney :steps="config.journey" @cancel="cancelOpen = true" />
                </RevealOnScroll>

                <RevealOnScroll :delay="40">
                    <PriorityQueue :items="config.priority" @cancel="cancelOpen = true" />
                </RevealOnScroll>

                <RevealOnScroll :delay="50">
                    <TimelinePanel :timeline="config.timeline" />
                </RevealOnScroll>
            </div>

            <aside class="space-y-6">
                <RevealOnScroll :delay="30">
                    <DetailsPanel :lesson="config.lesson" />
                </RevealOnScroll>

                <RevealOnScroll :delay="40">
                    <ClassroomPanel :join="config.join" />
                </RevealOnScroll>

                <RevealOnScroll :delay="50">
                    <EarningsPanel :money="config.money" :urls="config.urls" />
                </RevealOnScroll>

                <RevealOnScroll :delay="60">
                    <LessonActions
                        :actions="config.actions"
                        :join-url="config.join.joinUrl"
                        @cancel="cancelOpen = true"
                    />
                </RevealOnScroll>
            </aside>
        </div>

        <CancelLessonDialog
            v-if="config.cancel"
            :open="cancelOpen"
            :cancel="config.cancel"
            @close="cancelOpen = false"
        />
    </div>
</template>
