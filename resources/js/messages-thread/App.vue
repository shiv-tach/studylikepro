<script setup>
/**
 * One lesson thread: the messages, the composer and the poller that keeps the
 * two sides in step. Blade decides the perspective (student or teacher), the
 * lesson line, the first page of messages and the report form's state, and this
 * app only composes the pieces that render them.
 */
import { provide, ref } from 'vue';
import { THEME_PRESET_KEY, useThemePreset } from '../shared/composables/useThemePreset';
import RevealOnScroll from '../shared/components/RevealOnScroll.vue';
import UiAlert from '../shared/components/UiAlert.vue';
import { COPY_KEY, CSRF_KEY, THREAD_KEY, URLS_KEY } from './composables/usePageContext';
import { createThread } from './composables/useThread';
import ThreadPanel from './components/ThreadPanel.vue';
import ThreadToolbar from './components/ThreadToolbar.vue';
import ReportConversationModal from './components/ReportConversationModal.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

const copy = props.config.copy ?? {};
const urls = props.config.urls ?? {};

// The Alpine shell owns the preset; sharing it here keeps every surface in step.
provide(THEME_PRESET_KEY, useThemePreset(props.config.theme?.preset ?? 'classic'));
provide(COPY_KEY, copy);
provide(URLS_KEY, urls);
provide(CSRF_KEY, props.config.csrfToken ?? '');
provide(THREAD_KEY, createThread({
    initialMessages: props.config.messages ?? [],
    pollUrl: urls.poll,
    storeUrl: urls.store,
    csrfToken: props.config.csrfToken ?? '',
    labels: {
        writeFirst: copy.writeFirst,
        failed: copy.failed,
        offline: copy.offline,
    },
}));

// A rejected report bounces straight back to this page, and the island carries
// which field failed, so the dialog reopens already populated.
const reportOpen = ref(Boolean(props.config.report?.errors?.reason || props.config.report?.errors?.details));
</script>

<template>
    <section class="mx-auto max-w-3xl space-y-4" :aria-label="config.counterpart">
        <RevealOnScroll v-if="config.flash">
            <UiAlert :tone="config.flash.tone" :icon="config.flash.icon">
                {{ config.flash.message }}
            </UiAlert>
        </RevealOnScroll>

        <RevealOnScroll>
            <ThreadToolbar
                :lesson="config.lesson"
                :report-open="Boolean(config.report?.open)"
                @report="reportOpen = true"
            />
        </RevealOnScroll>

        <RevealOnScroll :delay="20">
            <ThreadPanel />
        </RevealOnScroll>

        <p class="px-2 text-xs text-slate-500 dark:text-slate-400">{{ copy.footer }}</p>
    </section>

    <!-- A second report on the same lesson is refused, so the form is replaced by
         the toolbar's "under review" chip once support already has it. -->
    <ReportConversationModal
        v-if="!config.report?.open"
        :open="reportOpen"
        :report="config.report"
        @close="reportOpen = false"
    />
</template>
