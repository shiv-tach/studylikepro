<script setup>
/**
 * Wizard step 4 at /teacher/onboarding/availability: one real PUT form holding
 * the preset picker, the editable week and the summary. The week autosaves into
 * the onboarding draft while it is being edited.
 */
import { computed, onMounted, provide, ref, watch } from 'vue';
import { THEME_PRESET_KEY, useThemePreset } from '../shared/composables/useThemePreset';
import RevealOnScroll from '../shared/components/RevealOnScroll.vue';
import UiAlert from '../shared/components/UiAlert.vue';
import ResumeDraftBanner from '../shared/onboarding/components/ResumeDraftBanner.vue';
import WizardActions from '../shared/onboarding/components/WizardActions.vue';
import { createOnboardingDraft, localDraftIsNewer } from '../shared/onboarding/useOnboardingDraft';
// The week editor, its presets and the summary card are shared with the
// availability manager at /teacher/availability.
import PresetCards from '../shared/availability/components/PresetCards.vue';
import WeekEditor from '../shared/availability/components/WeekEditor.vue';
import WeeklySummaryCard from '../shared/availability/components/WeeklySummaryCard.vue';
import { COPY_KEY, CSRF_KEY, URLS_KEY, format } from '../shared/availability/context';
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

const { submitting, canSave } = editor;

const restoredLocally = ref(false);
const restoredAt = ref(props.config.draft?.savedAtLabel ?? null);

const draft = createOnboardingDraft({
    url: urls.value.draft ?? null,
    deleteUrl: urls.value.draftDelete ?? null,
    token: props.config.csrfToken ?? '',
    step: 'availability',
    snapshot: editor.draftSnapshot,
    initialSavedAt: props.config.draft?.savedAt ?? null,
});

onMounted(() => {
    const local = draft.readLocal();

    if (localDraftIsNewer(local, props.config.draft?.savedAt)) {
        editor.applyDays(local.data?.days ?? []);
        restoredLocally.value = true;
        restoredAt.value = local.savedAt ? new Date(local.savedAt).toLocaleString() : restoredAt.value;
    }
});

watch(
    () => JSON.stringify(editor.days),
    () => draft.schedule(),
);

const restored = computed(() => Boolean(props.config.draft?.restored) || restoredLocally.value);

const restoredBody = computed(() => format(copy.value.draftRestoredBody ?? '', {
    time: restoredAt.value ?? '',
}));

const draftLabels = computed(() => ({
    dirty: copy.value.draftDirty,
    saving: copy.value.draftSaving,
    saved: copy.value.draftSaved,
    offline: copy.value.draftOffline,
    error: copy.value.draftError,
}));

const stepOf = computed(() => format(copy.value.stepOf ?? '', {
    current: 4,
    total: (props.config.steps ?? []).length || 5,
}));
</script>

<template>
    <div class="space-y-6">
        <RevealOnScroll v-if="config.status?.preferencesCompleted">
            <UiAlert tone="emerald" icon="check-circle">
                <span class="font-semibold">{{ copy.preferencesCompletedTitle }}.</span>
                {{ copy.preferencesCompletedBody }}
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

        <div>
            <p class="text-[11px] font-bold uppercase tracking-wider text-primary">{{ stepOf }}</p>
            <h1 class="mt-1 text-xl font-bold leading-tight text-slate-900 dark:text-slate-100 sm:text-2xl">
                {{ copy.stepTitle }}
            </h1>
            <p class="mt-1.5 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                {{ copy.stepBody }}
            </p>
        </div>

        <ResumeDraftBanner
            v-if="restored"
            :title="copy.draftRestoredTitle"
            :body="restoredBody"
            :discard-label="copy.draftDiscard"
            @discard="draft.discard()"
        />

        <form
            method="POST"
            :action="urls.update"
            class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]"
            @submit="submitting = true; draft.clearLocal()"
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

                <WizardActions
                    :back="urls.back"
                    :back-label="copy.back"
                    :submit-label="copy.save"
                    :saving-label="copy.saving"
                    :submitting="submitting"
                    :disabled="!canSave"
                    :note="canSave ? null : copy.emptySubmit"
                    :draft-status="draft.status.value"
                    :draft-labels="draftLabels"
                />
            </div>

            <aside class="space-y-6 lg:sticky lg:top-36 lg:self-start">
                <RevealOnScroll :delay="20">
                    <WeeklySummaryCard :show-submit="false" />
                </RevealOnScroll>
            </aside>
        </form>
    </div>
</template>
