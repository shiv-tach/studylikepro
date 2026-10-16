<script setup>
/**
 * The notification centre at /notifications: the flash banner, the email-status
 * banner and the list of notifications. Blade owns the rows (title, body, time,
 * read state and the URL each one opens) and the route map; the "mark all as
 * read" action stays in the page header because the shell renders it.
 */
import { provide } from 'vue';
import { THEME_PRESET_KEY, useThemePreset } from '../shared/composables/useThemePreset';
import RevealOnScroll from '../shared/components/RevealOnScroll.vue';
import UiAlert from '../shared/components/UiAlert.vue';
import { COPY_KEY, CSRF_KEY, URLS_KEY } from './composables/usePageContext';
import EmailPreferenceBanner from './components/EmailPreferenceBanner.vue';
import NotificationRow from './components/NotificationRow.vue';
import NotificationsEmptyState from './components/NotificationsEmptyState.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

provide(THEME_PRESET_KEY, useThemePreset(props.config.theme?.preset ?? 'classic'));
provide(COPY_KEY, props.config.copy ?? {});
provide(URLS_KEY, props.config.urls ?? {});
provide(CSRF_KEY, props.config.csrfToken ?? '');
</script>

<template>
    <div class="mx-auto max-w-3xl space-y-4">
        <RevealOnScroll v-if="config.status?.read">
            <UiAlert tone="emerald" icon="check-circle">{{ config.copy.readBanner }}</UiAlert>
        </RevealOnScroll>

        <RevealOnScroll>
            <EmailPreferenceBanner :email-enabled="config.emailEnabled" />
        </RevealOnScroll>

        <template v-if="config.notifications.length === 0">
            <NotificationsEmptyState />
        </template>

        <template v-else>
            <RevealOnScroll
                v-for="(notification, index) in config.notifications"
                :key="notification.id"
                :delay="Math.min(index, 6) * 15"
            >
                <NotificationRow :notification="notification" />
            </RevealOnScroll>
        </template>
    </div>
</template>
