<script setup>
/**
 * Onboarding step 2 at /teacher/verification: the documents, the checklist that
 * says what is still missing, and the final submit. Blade decides the status, the
 * document types, the limits and every message — this app arranges them and keeps
 * the checklist, the document list and the notices in step while the teacher
 * works.
 */
import { computed, onMounted, provide, ref, watch } from 'vue';
import { THEME_PRESET_KEY, useThemePreset } from '../shared/composables/useThemePreset';
import RevealOnScroll from '../shared/components/RevealOnScroll.vue';
import UiAlert from '../shared/components/UiAlert.vue';
import UiToastStack from '../shared/components/UiToastStack.vue';
import { COPY_KEY, CSRF_KEY, URLS_KEY, format } from './composables/usePageContext';
import { VERIFICATION_KEY, createVerification } from './composables/useVerification';
import { useNotices } from '../shared/composables/useNotices';
import { createOnboardingDraft, localDraftIsNewer } from '../shared/onboarding/useOnboardingDraft';
import CompletedCard from './components/CompletedCard.vue';
import ConfirmDialog from './components/ConfirmDialog.vue';
import DocumentsCard from './components/DocumentsCard.vue';
import RequirementsCard from './components/RequirementsCard.vue';
import StatusCard from './components/StatusCard.vue';
import SubjectsCard from './components/SubjectsCard.vue';
import SubmitCard from './components/SubmitCard.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

const copy = computed(() => props.config.copy ?? {});

provide(THEME_PRESET_KEY, useThemePreset(props.config.theme?.preset ?? 'classic'));
provide(COPY_KEY, props.config.copy ?? {});
provide(URLS_KEY, props.config.urls ?? {});
provide(CSRF_KEY, props.config.csrfToken ?? '');

const { toasts, push, dismiss } = useNotices(props.config.notices ?? []);

const verification = createVerification(props.config, { onNotice: push });
provide(VERIFICATION_KEY, verification);

const { canEdit, remove, agree, progress, blockingReason } = verification;

// The terms tick is the only unsubmitted field on this step; keep it in the
// same draft store as the other wizard steps.
const draft = props.config.mode === 'onboarding'
    ? createOnboardingDraft({
        url: props.config.urls?.draft ?? null,
        deleteUrl: props.config.urls?.draftDelete ?? null,
        token: props.config.csrfToken ?? '',
        step: 'verification',
        snapshot: () => ({ agree: agree.value ? 1 : 0 }),
        initialSavedAt: props.config.draft?.savedAt ?? null,
    })
    : null;

onMounted(() => {
    if (!draft) {
        return;
    }

    const local = draft.readLocal();

    if (localDraftIsNewer(local, props.config.draft?.savedAt)) {
        agree.value = Boolean(local.data?.agree);
    }
});

if (draft) {
    watch(agree, () => draft.schedule());
}

// Every message Laravel sent back that is not already attached to a field below.
// Listed explicitly: `errors` also carries `byType` (an object for the slot that
// failed), which must not leak into this list as "[object Object]".
const messages = computed(() => [
    ...(props.config.errors?.type ?? []),
    ...(props.config.errors?.document ?? []),
    ...(props.config.errors?.agree ?? []),
    ...(props.config.errors?.subjects ?? []),
]);

// Nothing is submitted yet, so there is no status to report above the form.
const showStatus = computed(() => (props.config.status?.current ?? 'draft') !== 'draft');

const pendingRemoval = ref(null);
const deleteForm = ref(null);

async function confirmRemoval() {
    const document = pendingRemoval.value;
    pendingRemoval.value = null;

    if (!document) {
        return;
    }

    if ((await remove(document)) === 'fallback') {
        // No fetch: post the real DELETE form so the document still goes away.
        deleteForm.value.action = document.destroyUrl;
        deleteForm.value.submit();
    }
}
</script>

<template>
    <div class="w-full space-y-6">
        <UiToastStack
            :toasts="toasts"
            :label="copy.toastLabel"
            :dismiss-label="copy.toastDismiss"
            @dismiss="dismiss"
        />

        <RevealOnScroll v-if="messages.length">
            <UiAlert tone="rose" icon="alert" role="alert">
                <span class="font-semibold">{{ copy.errorTitle }}</span>
                <ul class="mt-1 space-y-0.5">
                    <li v-for="(message, index) in messages" :key="index">{{ message }}</li>
                </ul>
            </UiAlert>
        </RevealOnScroll>

        <RevealOnScroll v-if="config.mode === 'onboarding'">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-primary">
                    {{ format(copy.stepOf, { current: 5, total: (config.steps ?? []).length || 5 }) }}
                </p>
                <h1 class="mt-1 text-xl font-bold leading-tight text-slate-900 dark:text-slate-100 sm:text-2xl">
                    {{ copy.stepTitle }}
                </h1>
                <p class="mt-1.5 text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ copy.stepBody }}</p>
            </div>
        </RevealOnScroll>

        <div class="grid gap-5 sm:gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="min-w-0 space-y-5 sm:space-y-6">
                <RevealOnScroll v-if="showStatus">
                    <StatusCard :status="config.status ?? {}" :profile="config.profile ?? {}" />
                </RevealOnScroll>

                <!-- On a phone the checklist belongs before the long form, not
                     after the submit card where it is only found by scrolling
                     past everything it is meant to explain. -->
                <RevealOnScroll class="lg:hidden">
                    <RequirementsCard />
                </RevealOnScroll>

                <RevealOnScroll :delay="20">
                    <DocumentsCard @request-remove="pendingRemoval = $event" />
                </RevealOnScroll>

                <RevealOnScroll :delay="40">
                    <SubjectsCard />
                </RevealOnScroll>

                <RevealOnScroll v-if="canEdit" :delay="60">
                    <SubmitCard />
                </RevealOnScroll>

                <RevealOnScroll v-else :delay="40">
                    <CompletedCard />
                </RevealOnScroll>
            </div>

            <aside class="hidden space-y-6 lg:sticky lg:block lg:self-start" :class="config.mode === 'onboarding' ? 'lg:top-36' : 'lg:top-24'">
                <RevealOnScroll :delay="20">
                    <RequirementsCard />
                </RevealOnScroll>
            </aside>
        </div>

        <!-- Phone-only action bar: keeps the submit reachable without scrolling
             through every card first. The real button (and the confirmation
             dialog) stays in the submit card; this jumps to it. -->
        <div
            v-if="canEdit"
            class="fixed inset-x-0 bottom-0 z-20 border-t border-slate-200/80 bg-white/95 px-4 py-3 backdrop-blur-md dark:border-slate-800/80 dark:bg-slate-950/95 lg:hidden"
            style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));"
        >
            <div class="mx-auto flex w-full max-w-3xl items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">
                        {{ progress.done }} / {{ progress.total }} {{ copy.checklistProgress }}
                    </p>
                    <p
                        class="mt-0.5 line-clamp-2 text-xs font-semibold leading-snug"
                        :class="blockingReason ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'"
                    >
                        {{ blockingReason ?? copy.submitReadyShort }}
                    </p>
                </div>

                <a
                    href="#submit"
                    class="inline-flex min-h-11 shrink-0 items-center justify-center gap-1.5 rounded-xl bg-primary px-4 py-2.5 text-sm font-bold text-white shadow-md shadow-primary/20 transition-colors hover:bg-primary/90"
                >
                    {{ copy.submitButton }}
                </a>
            </div>
        </div>

        <ConfirmDialog
            :open="Boolean(pendingRemoval)"
            :title="copy.removeTitle"
            :body="copy.removeBody"
            :confirm-label="copy.removeConfirm"
            :cancel-label="copy.removeCancel"
            :close-label="copy.confirmClose"
            tone="rose"
            @confirm="confirmRemoval"
            @close="pendingRemoval = null"
        />

        <form ref="deleteForm" method="POST" class="hidden">
            <input type="hidden" name="_token" :value="config.csrfToken">
            <input type="hidden" name="_method" value="DELETE">
            <input v-if="config.urls?.return" type="hidden" name="return" :value="config.urls.return">
        </form>
    </div>
</template>
