<script setup>
/**
 * The Settings home at /settings: the intro panel, the card grid (theme,
 * profile, the notification toggle and the not-yet-built sections) and the
 * closing tips. Blade owns the card list, the unread badge and the route map,
 * so this app only decides how each card is drawn.
 */
import { provide } from 'vue';
import { THEME_PRESET_KEY, useThemePreset } from '../shared/composables/useThemePreset';
import RevealOnScroll from '../shared/components/RevealOnScroll.vue';
import UiAlert from '../shared/components/UiAlert.vue';
import { COPY_KEY, CSRF_KEY, URLS_KEY } from './composables/usePageContext';
import ApplicationSettingsHero from './components/ApplicationSettingsHero.vue';
import ComingSoonCard from './components/ComingSoonCard.vue';
import NotificationPreferencesCard from './components/NotificationPreferencesCard.vue';
import QuickTipsCard from './components/QuickTipsCard.vue';
import SettingsLinkCard from './components/SettingsLinkCard.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

// The Alpine shell owns the preset; sharing it here keeps the cards in step.
provide(THEME_PRESET_KEY, useThemePreset(props.config.theme?.preset ?? 'classic'));
provide(COPY_KEY, props.config.copy ?? {});
provide(URLS_KEY, props.config.urls ?? {});
provide(CSRF_KEY, props.config.csrfToken ?? '');

// Every card kind Blade can send, so a new section is one entry in the island.
const CARD_COMPONENTS = {
    link: SettingsLinkCard,
    notifications: NotificationPreferencesCard,
    soon: ComingSoonCard,
};

function cardProps(card) {
    const { kind, key, ...rest } = card;

    // The notification card is the only one that needs live state rather than
    // the static strings the rest of the grid carries.
    return kind === 'notifications'
        ? { ...rest, ...props.config.notifications }
        : rest;
}
</script>

<template>
    <div class="w-full space-y-8">
        <RevealOnScroll v-if="config.status?.notificationsUpdated">
            <UiAlert tone="emerald" icon="check-circle">{{ config.copy.notificationsUpdated }}</UiAlert>
        </RevealOnScroll>

        <RevealOnScroll>
            <ApplicationSettingsHero
                :title="config.copy.heroTitle"
                :description="config.copy.heroDescription"
                icon="cog"
            />
        </RevealOnScroll>

        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
            <RevealOnScroll v-for="(card, index) in config.cards" :key="card.key" :delay="index * 20">
                <component
                    :is="CARD_COMPONENTS[card.kind] ?? SettingsLinkCard"
                    v-bind="cardProps(card)"
                />
            </RevealOnScroll>
        </div>

        <RevealOnScroll>
            <QuickTipsCard :title="config.copy.tipsTitle" icon="sparkles" :tips="config.tips" />
        </RevealOnScroll>
    </div>
</template>
