<script setup>
/**
 * The teaching profile at /teacher/profile.
 *
 * One form owns the headline, bio, education, languages, hourly rate and photo,
 * next to a live preview of the card students see in the public directory.
 */
import { computed, provide } from 'vue';
import { THEME_PRESET_KEY, useThemePreset } from '../shared/composables/useThemePreset';
import RevealOnScroll from '../shared/components/RevealOnScroll.vue';
import UiAlert from '../shared/components/UiAlert.vue';
import { COPY_KEY, CSRF_KEY, URLS_KEY } from './composables/usePageContext';
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
</script>

<template>
    <div class="w-full space-y-6">
        <RevealOnScroll v-if="config.status?.incomplete">
            <UiAlert tone="amber" icon="alert">{{ copy.incomplete }}</UiAlert>
        </RevealOnScroll>

        <RevealOnScroll v-if="config.status?.updated">
            <UiAlert tone="emerald" icon="check-circle">{{ copy.updated }}</UiAlert>
        </RevealOnScroll>

        <RevealOnScroll v-if="hasErrors">
            <UiAlert tone="rose" icon="alert" role="alert">
                <span class="font-semibold">{{ copy.errorTitle }}</span>
                <ul class="mt-1 space-y-0.5">
                    <li v-for="(message, index) in messages" :key="index">{{ message }}</li>
                </ul>
            </UiAlert>
        </RevealOnScroll>

        <form
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
