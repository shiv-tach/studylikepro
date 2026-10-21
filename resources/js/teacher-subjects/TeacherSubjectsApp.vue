<script setup>
/**
 * The teacher's subject workspace at /teacher/subjects: the approved subjects
 * with their grade/rate/lesson setup, the requests waiting for an admin, the
 * rejected rows, and the catalog + propose flows. Blade decides every label and
 * URL, and each setup card is still a real POST form, so CSRF, validation and
 * the redirect stay server-side.
 */
import { computed, nextTick, onMounted, provide } from 'vue';
import { THEME_PRESET_KEY, useThemePreset } from '../shared/composables/useThemePreset';
import { usePanel } from '../shared/composables/usePanel';
import { useNotices } from '../shared/composables/useNotices';
import AppIcon from '../shared/components/AppIcon.vue';
import RevealOnScroll from '../shared/components/RevealOnScroll.vue';
import UiAlert from '../shared/components/UiAlert.vue';
import UiToastStack from '../shared/components/UiToastStack.vue';
import { COPY_KEY, CSRF_KEY, PAGE_KEY, URLS_KEY, WORKSPACE_KEY, format } from './composables/usePageContext';
import { createSubjectsWorkspace } from './composables/useSubjectsWorkspace';
import AddSubjectsPanel from './components/AddSubjectsPanel.vue';
import RequestQueue from './components/RequestQueue.vue';
import SubjectSetupCard from './components/SubjectSetupCard.vue';
import SubjectsHero from './components/SubjectsHero.vue';
import SubjectsTabs from './components/SubjectsTabs.vue';
import { PRIMARY_BUTTON } from './styles';

const props = defineProps({
    config: { type: Object, required: true },
});

// The Alpine shell owns the preset; sharing it here keeps every card in step.
provide(THEME_PRESET_KEY, useThemePreset(props.config.theme?.preset ?? 'classic'));
provide(COPY_KEY, props.config.copy ?? {});
provide(URLS_KEY, props.config.urls ?? {});
provide(CSRF_KEY, props.config.csrfToken ?? '');
provide(PAGE_KEY, props.config);

const workspace = createSubjectsWorkspace(props.config);
provide(WORKSPACE_KEY, workspace);

const panel = usePanel();
const copy = computed(() => props.config.copy ?? {});

const { toasts, dismiss } = useNotices(props.config.notices ?? []);

const {
    activeTab,
    subjects,
    pending,
    rejected,
    dirtyCount,
    failedSubject,
    pageErrors,
} = workspace;

const tabCounts = computed(() => ({
    subjects: subjects.length,
    requests: pending.length + rejected.length,
}));

const failureMessage = computed(() => (failedSubject.value
    ? format(copy.value.errors?.saveFailed, { subject: failedSubject.value.name })
    : null));

onMounted(() => {
    const hash = window.location.hash.replace('#', '');

    if (['requests', 'add'].includes(hash)) {
        activeTab.value = hash;
    }

    if (props.config.editingSubjectId) {
        nextTick(() => {
            document
                .getElementById(`subject-setup-${props.config.editingSubjectId}`)
                ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }
});
</script>

<template>
    <div class="space-y-6">
        <UiToastStack
            :toasts="toasts"
            :label="copy.toastLabel ?? 'Notifications'"
            :dismiss-label="copy.toastDismiss ?? 'Dismiss'"
            @dismiss="dismiss"
        />

        <RevealOnScroll v-if="pageErrors.length">
            <UiAlert tone="rose" icon="alert" role="alert">
                <span class="font-semibold">{{ copy.errors?.title }}</span>
                <ul class="mt-1 space-y-0.5">
                    <li v-for="(message, index) in pageErrors" :key="index">{{ message }}</li>
                </ul>
            </UiAlert>
        </RevealOnScroll>

        <RevealOnScroll v-if="failureMessage">
            <UiAlert tone="rose" icon="alert" role="alert">
                {{ failureMessage }}
            </UiAlert>
        </RevealOnScroll>

        <RevealOnScroll>
            <SubjectsHero
                :stats="config.stats ?? {}"
                :teacher="config.teacher ?? {}"
                :copy="copy"
            />
        </RevealOnScroll>

        <SubjectsTabs
            v-model="activeTab"
            :counts="tabCounts"
            :dirty="dirtyCount"
        />

        <div
            v-show="activeTab === 'my'"
            id="teacher-subjects-panel-my"
            role="tabpanel"
            aria-labelledby="teacher-subjects-tab-my"
            class="space-y-4"
        >
            <div v-if="!subjects.length" :class="panel" class="text-center">
                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 p-3 text-slate-400 dark:bg-slate-800 dark:text-slate-500">
                    <AppIcon name="book" />
                </span>
                <h2 class="mt-3 text-lg font-bold text-slate-800 dark:text-slate-100">{{ copy.empty?.title }}</h2>
                <p class="mx-auto mt-1 max-w-lg text-sm text-slate-500 dark:text-slate-400">{{ copy.empty?.body }}</p>
                <button type="button" :class="[PRIMARY_BUTTON, 'mt-4']" @click="workspace.goToAdd()">
                    <span class="h-4 w-4"><AppIcon name="book" /></span>
                    {{ copy.empty?.cta }}
                </button>
            </div>

            <RevealOnScroll
                v-for="(subject, index) in subjects"
                :key="subject.id"
                :delay="index * 20"
            >
                <SubjectSetupCard :subject="subject" />
            </RevealOnScroll>
        </div>

        <div
            v-show="activeTab === 'requests'"
            id="teacher-subjects-panel-requests"
            role="tabpanel"
            aria-labelledby="teacher-subjects-tab-requests"
        >
            <RequestQueue :pending="pending" :rejected="rejected" />
        </div>

        <div
            v-show="activeTab === 'add'"
            id="teacher-subjects-panel-add"
            role="tabpanel"
            aria-labelledby="teacher-subjects-tab-add"
        >
            <AddSubjectsPanel />
        </div>
    </div>
</template>
