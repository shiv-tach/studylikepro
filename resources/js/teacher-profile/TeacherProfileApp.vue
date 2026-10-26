<script setup>
/**
 * The teaching profile screens.
 *
 * In `workspace` mode this is the post-onboarding edit page (one form, live
 * preview). In `onboarding` mode it renders one focused wizard step — "About
 * you" or "Teaching preferences" — inside the onboarding layout, with a draft
 * that autosaves while the teacher types.
 */
import { computed, onMounted, provide, ref, watch } from 'vue';
import { THEME_PRESET_KEY, useThemePreset } from '../shared/composables/useThemePreset';
import RevealOnScroll from '../shared/components/RevealOnScroll.vue';
import UiAlert from '../shared/components/UiAlert.vue';
import ResumeDraftBanner from '../shared/onboarding/components/ResumeDraftBanner.vue';
import WizardActions from '../shared/onboarding/components/WizardActions.vue';
import { createOnboardingDraft, localDraftIsNewer } from '../shared/onboarding/useOnboardingDraft';
import { COPY_KEY, CSRF_KEY, URLS_KEY, format } from './composables/usePageContext';
import { PROFILE_FORM_KEY, createProfileForm } from './composables/useProfileForm';
import PhotoCard from './components/PhotoCard.vue';
import ProfessionalDetailsCard from './components/ProfessionalDetailsCard.vue';
import ProfileSaveBar from './components/ProfileSaveBar.vue';
import PublicCardPreview from './components/PublicCardPreview.vue';
import TeachingPreferencesCard from './components/TeachingPreferencesCard.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

const copy = computed(() => props.config.copy ?? {});
const profile = computed(() => props.config.profile ?? {});
const urls = computed(() => props.config.urls ?? {});
const isOnboarding = computed(() => (props.config.mode ?? 'workspace') === 'onboarding');
const stepKey = computed(() => props.config.stepKey ?? 'all');

// The Alpine shell owns the preset; sharing it here keeps every card in step.
provide(THEME_PRESET_KEY, useThemePreset(props.config.theme?.preset ?? 'classic'));
provide(COPY_KEY, props.config.copy ?? {});
provide(URLS_KEY, urls.value);
provide(CSRF_KEY, props.config.csrfToken ?? '');

const form = createProfileForm(props.config);
provide(PROFILE_FORM_KEY, form);

const { submitting, hasErrors } = form;

// Every message Laravel sent back, in the order the island lists the fields.
const messages = computed(() => Object.values(props.config.errors ?? {}).flat());

const restoredLocally = ref(false);
const restoredAt = ref(props.config.draft?.savedAtLabel ?? null);

// Autosave only exists inside the wizard; the workspace page saves on submit.
const draft = isOnboarding.value
    ? createOnboardingDraft({
        url: urls.value.draft ?? null,
        deleteUrl: urls.value.draftDelete ?? null,
        token: props.config.csrfToken ?? '',
        step: stepKey.value,
        snapshot: form.snapshot,
        initialSavedAt: props.config.draft?.savedAt ?? null,
    })
    : null;

onMounted(() => {
    if (!draft) {
        return;
    }

    // A local snapshot newer than the server draft means the last autosave was
    // interrupted (offline, crash, closed tab). Restore it before anything else.
    const local = draft.readLocal();

    if (localDraftIsNewer(local, props.config.draft?.savedAt)) {
        form.applyValues(local.data);
        restoredLocally.value = true;
        restoredAt.value = local.savedAt ? new Date(local.savedAt).toLocaleString() : restoredAt.value;
    }
});

if (draft) {
    watch(
        () => JSON.stringify(form.values),
        () => draft.schedule(),
    );
}

const restored = computed(() => Boolean(props.config.draft?.restored) || restoredLocally.value);

const restoredBody = computed(() => {
    const template = stepKey.value === 'preferences'
        ? (copy.value.draftRestoredBody ?? '')
        : (copy.value.draftRestoredBodyNoPhoto ?? '');

    return format(template, { time: restoredAt.value ?? '' });
});

const draftLabels = computed(() => ({
    dirty: copy.value.draftDirty,
    saving: copy.value.draftSaving,
    saved: copy.value.draftSaved,
    offline: copy.value.draftOffline,
    error: copy.value.draftError,
}));

const stepHeading = computed(() => ({
    title: stepKey.value === 'about' ? copy.value.aboutStepTitle : copy.value.preferencesStepTitle,
    body: stepKey.value === 'about' ? copy.value.aboutStepBody : copy.value.preferencesStepBody,
}));

const stepOf = computed(() => format(copy.value.stepOf ?? '', {
    current: stepKey.value === 'about' ? 2 : 3,
    total: (props.config.steps ?? []).length || 5,
}));

const continueLabel = computed(() => copy.value.continue ?? copy.value.save ?? 'Save & continue');
</script>

<template>
    <div class="w-full space-y-6">
        <RevealOnScroll v-if="config.status?.incomplete">
            <UiAlert tone="amber" icon="alert">{{ copy.incomplete }}</UiAlert>
        </RevealOnScroll>

        <RevealOnScroll v-if="config.status?.updated">
            <UiAlert tone="emerald" icon="check-circle">{{ copy.updated }}</UiAlert>
        </RevealOnScroll>

        <RevealOnScroll v-if="config.status?.aboutSaved">
            <UiAlert tone="emerald" icon="check-circle">
                <span class="font-semibold">{{ copy.aboutSavedTitle }}.</span>
                {{ copy.aboutSavedBody }}
            </UiAlert>
        </RevealOnScroll>

        <RevealOnScroll v-if="config.status?.preferencesSaved">
            <UiAlert tone="emerald" icon="check-circle">
                <span class="font-semibold">{{ copy.preferencesSavedTitle }}.</span>
                {{ copy.preferencesSavedBody }}
            </UiAlert>
        </RevealOnScroll>

        <RevealOnScroll v-if="config.status?.preferencesMissing">
            <UiAlert tone="amber" icon="alert">{{ copy.preferencesMissingBody }}</UiAlert>
        </RevealOnScroll>

        <RevealOnScroll v-if="hasErrors">
            <UiAlert tone="rose" icon="alert" role="alert">
                <span class="font-semibold">{{ copy.errorTitle }}</span>
                <ul class="mt-1 space-y-0.5">
                    <li v-for="(message, index) in messages" :key="index">{{ message }}</li>
                </ul>
            </UiAlert>
        </RevealOnScroll>

        <!-- Onboarding: one focused step, with the same fields as the workspace. -->
        <template v-if="isOnboarding">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-primary">{{ stepOf }}</p>
                <h1 class="mt-1 text-xl font-bold leading-tight text-slate-900 dark:text-slate-100 sm:text-2xl">
                    {{ stepHeading.title }}
                </h1>
                <p class="mt-1.5 text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ stepHeading.body }}</p>
            </div>

            <ResumeDraftBanner
                v-if="restored"
                :title="copy.draftRestoredTitle"
                :body="restoredBody"
                :discard-label="copy.draftDiscard"
                @discard="draft?.discard()"
            />

            <form
                method="POST"
                :action="urls.update"
                enctype="multipart/form-data"
                class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]"
                @submit="submitting = true; draft?.clearLocal()"
            >
                <input type="hidden" name="_token" :value="config.csrfToken">
                <input type="hidden" name="_method" value="PUT">

                <div class="space-y-6">
                    <RevealOnScroll v-if="stepKey === 'about'">
                        <ProfessionalDetailsCard />
                    </RevealOnScroll>

                    <template v-else>
                        <RevealOnScroll>
                            <TeachingPreferencesCard />
                        </RevealOnScroll>

                        <RevealOnScroll :delay="20">
                            <PhotoCard />
                        </RevealOnScroll>
                    </template>

                    <WizardActions
                        :back="urls.back"
                        :back-label="copy.back"
                        :submit-label="continueLabel"
                        :saving-label="copy.saving"
                        :submitting="submitting"
                        :draft-status="draft?.status.value ?? 'idle'"
                        :draft-labels="draftLabels"
                    />
                </div>

                <aside class="hidden lg:block lg:self-start" :class="isOnboarding ? '' : 'lg:sticky lg:top-24'">
                    <RevealOnScroll :delay="20">
                        <PublicCardPreview />
                    </RevealOnScroll>
                </aside>
            </form>

            <details class="rounded-2xl border border-slate-200/80 bg-white p-1 dark:border-slate-800/80 dark:bg-slate-900 lg:hidden">
                <summary class="cursor-pointer list-none px-4 py-3 text-sm font-semibold text-slate-700 marker:hidden dark:text-slate-200">
                    {{ copy.previewToggle }}
                </summary>
                <div class="p-3 pt-0">
                    <PublicCardPreview />
                </div>
            </details>
        </template>

        <!-- Workspace: the whole profile in one form. -->
        <form
            v-else
            method="POST"
            :action="urls.update"
            enctype="multipart/form-data"
            class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]"
            @submit="submitting = true"
        >
            <input type="hidden" name="_token" :value="config.csrfToken">
            <input type="hidden" name="_method" value="PUT">

            <div class="space-y-6">
                <RevealOnScroll>
                    <ProfessionalDetailsCard />
                </RevealOnScroll>

                <RevealOnScroll :delay="20">
                    <TeachingPreferencesCard />
                </RevealOnScroll>

                <RevealOnScroll :delay="40">
                    <PhotoCard />
                </RevealOnScroll>
            </div>

            <aside class="space-y-6 lg:sticky lg:top-24 lg:self-start">
                <RevealOnScroll :delay="20">
                    <PublicCardPreview />
                </RevealOnScroll>

                <ProfileSaveBar :complete="profile.isComplete" />
            </aside>
        </form>
    </div>
</template>
