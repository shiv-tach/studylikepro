<script setup>
/**
 * The admin platform settings console at /admin/settings: commission, fees,
 * holds and the refund policy. It is one real PUT form; Blade owns the
 * definitions, the current values, the validation messages and the route map.
 */
import { computed, provide } from 'vue';
import { THEME_PRESET_KEY, useThemePreset } from '../shared/composables/useThemePreset';
import RevealOnScroll from '../shared/components/RevealOnScroll.vue';
import UiAlert from '../shared/components/UiAlert.vue';
import { COPY_KEY, CSRF_KEY, META_KEY, URLS_KEY } from './composables/usePageContext';
import { SETTINGS_FORM_KEY, createSettingsForm } from './composables/useSettingsForm';
import SettingsGroupCard from './components/SettingsGroupCard.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

provide(THEME_PRESET_KEY, useThemePreset(props.config.theme?.preset ?? 'classic'));
provide(COPY_KEY, props.config.copy ?? {});
provide(URLS_KEY, props.config.urls ?? {});
provide(CSRF_KEY, props.config.csrfToken ?? '');
provide(META_KEY, props.config.meta ?? {});

const form = createSettingsForm(props.config);
provide(SETTINGS_FORM_KEY, form);

const { submitting } = form;

// Every message Laravel sent back, in the order the island lists the fields.
const messages = computed(() => Object.values(props.config.errors ?? {}).flat());
</script>

<template>
    <div class="mx-auto max-w-3xl space-y-6">
        <RevealOnScroll v-if="config.status?.saved">
            <UiAlert tone="emerald" icon="check-circle">{{ config.copy.saved }}</UiAlert>
        </RevealOnScroll>

        <RevealOnScroll v-if="messages.length">
            <UiAlert tone="rose" icon="alert" role="alert">
                <span class="font-semibold">{{ config.copy.errorTitle }}</span>
                <ul class="mt-1 space-y-0.5">
                    <li v-for="(message, index) in messages" :key="index">{{ message }}</li>
                </ul>
            </UiAlert>
        </RevealOnScroll>

        <form method="POST" :action="config.urls.update" class="space-y-6" @submit="submitting = true">
            <input type="hidden" name="_token" :value="config.csrfToken">
            <input type="hidden" name="_method" value="PUT">

            <SettingsGroupCard
                v-for="group in config.groups"
                :key="group.key"
                :group="group"
                :errors="config.errors"
                :currency-symbol="config.meta.currencySymbol"
            />

            <div class="flex justify-end">
                <button
                    type="submit"
                    :disabled="submitting"
                    class="inline-flex items-center justify-center rounded-xl bg-primary px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:opacity-60 dark:focus:ring-offset-slate-900"
                >
                    {{ submitting ? config.copy.saving : config.copy.save }}
                </button>
            </div>
        </form>
    </div>
</template>
