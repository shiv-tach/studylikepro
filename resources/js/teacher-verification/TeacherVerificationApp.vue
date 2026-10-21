<script setup>
/**
 * Onboarding step 2 at /teacher/verification: the documents, the checklist that
 * says what is still missing, and the final submit. Blade decides the status, the
 * document types, the limits and every message — this app arranges them and keeps
 * the checklist, the document list and the notices in step while the teacher
 * works.
 */
import { computed, provide, ref } from 'vue';
import { THEME_PRESET_KEY, useThemePreset } from '../shared/composables/useThemePreset';
import RevealOnScroll from '../shared/components/RevealOnScroll.vue';
import UiAlert from '../shared/components/UiAlert.vue';
import UiToastStack from '../shared/components/UiToastStack.vue';
import { COPY_KEY, CSRF_KEY, URLS_KEY } from './composables/usePageContext';
import { VERIFICATION_KEY, createVerification } from './composables/useVerification';
import { useNotices } from '../shared/composables/useNotices';
import CompletedCard from './components/CompletedCard.vue';
import ConfirmDialog from './components/ConfirmDialog.vue';
import DocumentsCard from './components/DocumentsCard.vue';
import OnboardingSteps from './components/OnboardingSteps.vue';
import RequirementsCard from './components/RequirementsCard.vue';
import StatusCard from './components/StatusCard.vue';
import SubjectsCard from './components/SubjectsCard.vue';
import SubmitCard from './components/SubmitCard.vue';
import UploadCard from './components/UploadCard.vue';

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

const { canEdit, remove } = verification;

// Every message Laravel sent back that is not already attached to a field below.
const messages = computed(() => Object.values(props.config.errors ?? {}).flat());

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
    <div class="mx-auto max-w-5xl space-y-6">
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

        <RevealOnScroll>
            <OnboardingSteps :steps="config.steps ?? []" :current="config.currentStep ?? 2" />
        </RevealOnScroll>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="space-y-6">
                <RevealOnScroll>
                    <StatusCard :status="config.status ?? {}" :profile="config.profile ?? {}" />
                </RevealOnScroll>

                <RevealOnScroll :delay="20">
                    <DocumentsCard @request-remove="pendingRemoval = $event" />
                </RevealOnScroll>

                <RevealOnScroll :delay="30">
                    <SubjectsCard />
                </RevealOnScroll>

                <RevealOnScroll v-if="canEdit" :delay="40">
                    <UploadCard />
                </RevealOnScroll>

                <RevealOnScroll v-if="canEdit" :delay="60">
                    <SubmitCard />
                </RevealOnScroll>

                <RevealOnScroll v-else :delay="40">
                    <CompletedCard />
                </RevealOnScroll>
            </div>

            <aside class="space-y-6 lg:sticky lg:top-24 lg:self-start">
                <RevealOnScroll :delay="20">
                    <RequirementsCard />
                </RevealOnScroll>
            </aside>
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
        </form>
    </div>
</template>
