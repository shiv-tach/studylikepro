<script setup>
/**
 * Profile settings at /profile: the account details, the password and the
 * danger zone. Three independent real forms, so CSRF, validation and the
 * redirects all stay server-side; Blade owns the values and every message.
 */
import { provide } from 'vue';
import { THEME_PRESET_KEY, useThemePreset } from '../shared/composables/useThemePreset';
import RevealOnScroll from '../shared/components/RevealOnScroll.vue';
import { COPY_KEY, CSRF_KEY, URLS_KEY } from './composables/usePageContext';
import DeleteAccountCard from './components/DeleteAccountCard.vue';
import ProfileInformationCard from './components/ProfileInformationCard.vue';
import UpdatePasswordCard from './components/UpdatePasswordCard.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

provide(THEME_PRESET_KEY, useThemePreset(props.config.theme?.preset ?? 'classic'));
provide(COPY_KEY, props.config.copy ?? {});
provide(URLS_KEY, props.config.urls ?? {});
provide(CSRF_KEY, props.config.csrfToken ?? '');
</script>

<template>
    <div class="mx-auto max-w-5xl space-y-8">
        <RevealOnScroll>
            <ProfileInformationCard
                :values="config.values"
                :errors="config.errors"
                :status="config.status"
                :shows-verification-notice="config.showsVerificationNotice"
            />
        </RevealOnScroll>

        <RevealOnScroll :delay="20">
            <UpdatePasswordCard :errors="config.passwordErrors" :status="config.status" />
        </RevealOnScroll>

        <RevealOnScroll :delay="40">
            <DeleteAccountCard :errors="config.deleteErrors" :force-open="config.deleteDialogOpen" />
        </RevealOnScroll>
    </div>
</template>
