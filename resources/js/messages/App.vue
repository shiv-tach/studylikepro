<script setup>
/**
 * The lesson-chat inbox: the unread banner, one card per booking thread and the
 * empty state. It is mounted inside the shared shell, so it only renders what
 * Blade already computed — the counterpart names, the unread counts, the
 * relative timestamps and the pre-formatted lesson lines. Every row is a real
 * link and the pagination below the island stays server-rendered.
 */
import { provide } from 'vue';
import { THEME_PRESET_KEY, useThemePreset } from '../shared/composables/useThemePreset';
import RevealOnScroll from '../shared/components/RevealOnScroll.vue';
import { COPY_KEY, URLS_KEY } from './composables/usePageContext';
import ConversationCard from './components/ConversationCard.vue';
import ConversationsEmptyState from './components/ConversationsEmptyState.vue';
import UnreadBanner from './components/UnreadBanner.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

// The Alpine shell owns the preset; sharing it here keeps every card in step.
provide(THEME_PRESET_KEY, useThemePreset(props.config.theme?.preset ?? 'classic'));
provide(COPY_KEY, props.config.copy ?? {});
provide(URLS_KEY, props.config.urls ?? {});
</script>

<template>
    <section class="w-full space-y-4" :aria-label="config.copy.threadsLabel">
        <RevealOnScroll v-if="config.totalUnread > 0 && config.copy.unreadSummary">
            <UnreadBanner :label="config.copy.unreadSummary" />
        </RevealOnScroll>

        <RevealOnScroll
            v-for="(thread, index) in config.threads"
            :key="thread.id"
            :delay="Math.min(index, 5) * 25"
        >
            <ConversationCard :thread="thread" />
        </RevealOnScroll>

        <RevealOnScroll v-if="config.threads.length === 0">
            <ConversationsEmptyState />
        </RevealOnScroll>
    </section>
</template>
