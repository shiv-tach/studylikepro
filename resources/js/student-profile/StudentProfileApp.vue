<script setup>
/**
 * The learning profile at /student/profile: one real PUT form spread over the
 * cards that collect it, plus the strength meter and the save bar. Blade
 * decides the catalog, the stored answers and the validation messages — this
 * app only groups them, so CSRF, validation and the redirect stay server-side.
 */
import { computed, provide } from 'vue';
import { THEME_PRESET_KEY, useThemePreset } from '../shared/composables/useThemePreset';
import RevealOnScroll from '../shared/components/RevealOnScroll.vue';
import UiAlert from '../shared/components/UiAlert.vue';
import { COPY_KEY, CSRF_KEY, URLS_KEY } from './composables/usePageContext';
import { PROFILE_FORM_KEY, createProfileForm } from './composables/useProfileForm';
import AboutYouCard from './components/AboutYouCard.vue';
import GuardianCard from './components/GuardianCard.vue';
import PhotoCard from './components/PhotoCard.vue';
import ProfileHeroCard from './components/ProfileHeroCard.vue';
import ProfileSaveBar from './components/ProfileSaveBar.vue';
import ProfileStrengthCard from './components/ProfileStrengthCard.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

// The Alpine shell owns the preset; sharing it here keeps every card in step.
provide(THEME_PRESET_KEY, useThemePreset(props.config.theme?.preset ?? 'classic'));
provide(COPY_KEY, props.config.copy ?? {});
provide(URLS_KEY, props.config.urls ?? {});
provide(CSRF_KEY, props.config.csrfToken ?? '');

const form = createProfileForm(props.config);
provide(PROFILE_FORM_KEY, form);

const { submitting, hasErrors } = form;

// Every message Laravel sent back, in the order the island lists the fields.
const messages = computed(() => Object.values(props.config.errors ?? {}).flat());
</script>

<template>
    <div class="w-full space-y-6">
        <RevealOnScroll v-if="config.status?.updated">
            <UiAlert tone="emerald" icon="check-circle">{{ config.copy.updated }}</UiAlert>
        </RevealOnScroll>

        <RevealOnScroll v-if="hasErrors">
            <UiAlert tone="rose" icon="alert" role="alert">
                <span class="font-semibold">{{ config.copy.errorTitle }}</span>
                <ul class="mt-1 space-y-0.5">
                    <li v-for="(message, index) in messages" :key="index">{{ message }}</li>
                </ul>
            </UiAlert>
        </RevealOnScroll>

        <RevealOnScroll>
            <ProfileHeroCard />
        </RevealOnScroll>

        <form
            method="POST"
            :action="config.urls.update"
            enctype="multipart/form-data"
            class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]"
            @submit="submitting = true"
        >
            <input type="hidden" name="_token" :value="config.csrfToken">
            <input type="hidden" name="_method" value="PUT">

            <div class="space-y-6">
                <RevealOnScroll>
                    <AboutYouCard />
                </RevealOnScroll>

                <RevealOnScroll :delay="20">
                    <GuardianCard />
                </RevealOnScroll>

                <RevealOnScroll :delay="40">
                    <PhotoCard />
                </RevealOnScroll>
            </div>

            <aside class="space-y-6 lg:sticky lg:top-24 lg:self-start">
                <RevealOnScroll :delay="20">
                    <ProfileStrengthCard />
                </RevealOnScroll>

                <ProfileSaveBar />
            </aside>
        </form>
    </div>
</template>
