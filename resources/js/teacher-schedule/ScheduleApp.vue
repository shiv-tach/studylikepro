<script setup>
/**
 * The teacher's schedule at /teacher/schedule.
 *
 * Blade decides what is on today, what is next, what needs an action, every
 * formatted time and price, and which transitions are legal; this app only lays
 * the answer out: the hero, today's lessons, the next-lesson spotlight, the
 * ranked "Needs your attention" queue, and three instant views (upcoming
 * agenda, unpaid holds, recent history).
 */
import { computed, onBeforeUnmount, onMounted, provide, ref, watch } from 'vue';
import { THEME_PRESET_KEY, useThemePreset } from '../shared/composables/useThemePreset';
import { useNotices } from '../shared/composables/useNotices';
import RevealOnScroll from '../shared/components/RevealOnScroll.vue';
import UiAlert from '../shared/components/UiAlert.vue';
import UiSectionHeading from '../shared/components/UiSectionHeading.vue';
import UiToastStack from '../shared/components/UiToastStack.vue';
import { COPY_KEY, PAGE_KEY, URLS_KEY } from './composables/usePageContext';
import AgendaDay from './components/AgendaDay.vue';
import HistoryRow from './components/HistoryRow.vue';
import HoldRow from './components/HoldRow.vue';
import NextLessonSpotlight from './components/NextLessonSpotlight.vue';
import PriorityStack from './components/PriorityStack.vue';
import ScheduleEmptyState from './components/ScheduleEmptyState.vue';
import ScheduleHero from './components/ScheduleHero.vue';
import ScheduleTabs from './components/ScheduleTabs.vue';
import TodaySection from './components/TodaySection.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

// The shell owns the preset; sharing it keeps every panel in step with the
// opaque/frosted switch.
provide(THEME_PRESET_KEY, useThemePreset(props.config.theme?.preset ?? 'classic'));
provide(COPY_KEY, props.config.copy ?? {});
provide(URLS_KEY, props.config.urls ?? {});
provide(PAGE_KEY, props.config);

const copy = computed(() => props.config.copy ?? {});
const urls = computed(() => props.config.urls ?? {});
const { toasts, dismiss } = useNotices(props.config.flash ? [props.config.flash] : []);

// The island carries all three collections, so the tabs are pure client state;
// the hash keeps the active one across a reload.
const tabKeys = (props.config.tabs ?? []).map((tab) => tab.key);
const initial = window.location.hash.replace('#', '');
const active = ref(tabKeys.includes(initial) ? initial : (tabKeys[0] ?? 'upcoming'));

watch(active, (key) => {
    if (window.location.hash !== `#${key}`) {
        window.history.replaceState(null, '', `#${key}`);
    }
});

function syncFromHash() {
    const next = window.location.hash.replace('#', '');

    if (tabKeys.includes(next)) {
        active.value = next;
    }
}

onMounted(() => window.addEventListener('hashchange', syncFromHash));
onBeforeUnmount(() => window.removeEventListener('hashchange', syncFromHash));
</script>

<template>
    <div class="space-y-6">
        <UiToastStack :toasts="toasts" @dismiss="dismiss" />

        <RevealOnScroll>
            <ScheduleHero />
        </RevealOnScroll>

        <RevealOnScroll v-if="config.errors?.status" :delay="10">
            <UiAlert tone="rose" icon="alert" role="alert">{{ config.errors.status }}</UiAlert>
        </RevealOnScroll>

        <RevealOnScroll :delay="20">
            <TodaySection :today="config.today" />
        </RevealOnScroll>

        <RevealOnScroll v-if="config.next" :delay="30">
            <NextLessonSpotlight :lesson="config.next" />
        </RevealOnScroll>

        <RevealOnScroll :delay="40">
            <PriorityStack :items="config.priority" :idle="config.agenda.length === 0" />
        </RevealOnScroll>

        <RevealOnScroll :delay="50">
            <ScheduleTabs v-model:active="active" :tabs="config.tabs" :label="copy.tabs?.label" />
        </RevealOnScroll>

        <section
            v-show="active === 'upcoming'"
            id="schedule-panel-upcoming"
            role="tabpanel"
            aria-labelledby="schedule-tab-upcoming"
            class="space-y-5"
        >
            <UiSectionHeading
                :title="copy.agenda?.title"
                :description="copy.agenda?.description"
                icon="calendar"
            />

            <template v-if="config.agenda.length">
                <RevealOnScroll
                    v-for="(day, index) in config.agenda"
                    :key="day.key"
                    :delay="Math.min(index, 4) * 20"
                >
                    <AgendaDay :day="day" />
                </RevealOnScroll>
            </template>

            <ScheduleEmptyState
                v-else
                icon="calendar"
                :title="copy.agenda?.emptyTitle"
                :body="copy.agenda?.emptyBody"
                :link-label="copy.agenda?.emptyCta"
                :link-href="urls.availability"
            />
        </section>

        <section
            v-show="active === 'holds'"
            id="schedule-panel-holds"
            role="tabpanel"
            aria-labelledby="schedule-tab-holds"
            class="space-y-5"
        >
            <UiSectionHeading
                :title="copy.holds?.title"
                :description="copy.holds?.description"
                icon="alert"
                icon-tone="bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300"
            />

            <ol v-if="config.holds.length" class="space-y-2">
                <HoldRow v-for="hold in config.holds" :key="hold.id" :hold="hold" />
            </ol>

            <ScheduleEmptyState
                v-else
                icon="check-circle"
                :title="copy.holds?.emptyTitle"
                :body="copy.holds?.emptyBody"
            />
        </section>

        <section
            v-show="active === 'history'"
            id="schedule-panel-history"
            role="tabpanel"
            aria-labelledby="schedule-tab-history"
            class="space-y-5"
        >
            <UiSectionHeading
                :title="copy.history?.title"
                :description="copy.history?.description"
                icon="clock"
                icon-tone="bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400"
            />

            <ol v-if="config.history.length" class="space-y-2">
                <HistoryRow v-for="row in config.history" :key="row.id" :row="row" />
            </ol>

            <ScheduleEmptyState
                v-else
                icon="clock"
                :title="copy.history?.emptyTitle"
                :body="copy.history?.emptyBody"
            />
        </section>
    </div>
</template>
