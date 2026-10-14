<script setup>
/**
 * The "Ask a question" form. It stays a real HTML form that posts to Blade's
 * store route, so CSRF, validation, redirects and the queued AI classification
 * are unchanged; Vue handles the repeatable windows, the photo picker, the live
 * character count and the errors Blade sends back.
 */
import { computed, provide, ref } from 'vue';
import { THEME_PRESET_KEY, useThemePreset } from '../shared/composables/useThemePreset';
import AppIcon from '../shared/components/AppIcon.vue';
import RevealOnScroll from '../shared/components/RevealOnScroll.vue';
import { COPY_KEY, CSRF_KEY, URLS_KEY } from './composables/usePageContext';
import AttachmentField from './components/AttachmentField.vue';
import BudgetField from './components/BudgetField.vue';
import FieldMessages from './components/FieldMessages.vue';
import FormPanel from './components/FormPanel.vue';
import PreferredWindows from './components/PreferredWindows.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

// The Alpine shell owns the preset; sharing it here keeps every card in step.
provide(THEME_PRESET_KEY, useThemePreset(props.config.theme?.preset ?? 'classic'));
provide(COPY_KEY, props.config.copy ?? {});
provide(URLS_KEY, props.config.urls ?? {});
provide(CSRF_KEY, props.config.csrfToken ?? '');

const copy = computed(() => props.config.copy ?? {});
const urls = computed(() => props.config.urls ?? {});
const errors = computed(() => props.config.errors ?? {});
const hasErrors = computed(() => Object.keys(errors.value).length > 0);

const description = ref(props.config.values?.description ?? '');
const submitting = ref(false);

// The server still enforces both bounds; the counter just warns early.
const tooShort = computed(() => description.value.length > 0
    && description.value.length < props.config.minDescription);

const textareaClass =
    'mt-4 w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';
</script>

<template>
    <div class="space-y-6">
        <RevealOnScroll v-if="hasErrors">
            <div
                class="flex items-start gap-3 rounded-2xl border border-rose-200/80 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300"
                role="alert"
            >
                <span class="mt-0.5 h-4 w-4 shrink-0"><AppIcon name="shield-check" /></span>
                <div>
                    <p class="font-semibold">{{ copy.errorTitle }}</p>
                    <p class="mt-0.5">{{ copy.errorIntro }}</p>
                </div>
            </div>
        </RevealOnScroll>

        <RevealOnScroll>
            <div class="flex items-start gap-3 rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-xl" aria-hidden="true">🤖</span>
                <div>
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ copy.introTitle }}</h3>
                    <p class="mt-1 text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ copy.introBody }}</p>
                </div>
            </div>
        </RevealOnScroll>

        <RevealOnScroll :delay="20">
            <form
                method="POST"
                :action="urls.store"
                enctype="multipart/form-data"
                class="space-y-6"
                @submit="submitting = true"
            >
                <input type="hidden" name="_token" :value="config.csrfToken" />

                <FormPanel :title="copy.questionTitle" :description="copy.questionHint">
                    <textarea
                        id="request-description"
                        v-model="description"
                        name="description"
                        rows="5"
                        :minlength="config.minDescription"
                        :maxlength="config.maxDescription"
                        required
                        :class="textareaClass"
                        :placeholder="copy.questionPlaceholder"
                    ></textarea>

                    <div class="mt-2 flex flex-wrap items-start justify-between gap-2">
                        <FieldMessages :messages="errors.description" />

                        <p
                            class="ml-auto shrink-0 text-xs tabular-nums"
                            :class="tooShort ? 'font-semibold text-amber-600 dark:text-amber-400' : 'text-slate-400'"
                        >
                            <span v-if="tooShort">{{ copy.descriptionMin }} </span>
                            {{ description.length }} / {{ config.maxDescription }}
                        </p>
                    </div>

                    <div class="mt-5 sm:max-w-xs">
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ copy.gradeLabel }}</p>
                        <p class="mt-1 rounded-xl border border-slate-200/80 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-200">
                            {{ config.learnerGrade ?? copy.gradeNotSet }}
                        </p>
                        <p class="mt-1 text-xs text-slate-400">
                            {{ copy.gradeHint }}
                            <a :href="urls.profile" class="font-semibold text-primary hover:underline">{{ copy.gradeChange }}</a>
                        </p>
                    </div>
                </FormPanel>

                <PreferredWindows
                    :windows="config.values.windows"
                    :max="config.maxWindows"
                    :min-date="config.minWindowDate"
                    :errors="errors"
                />

                <AttachmentField
                    :max="config.maxAttachments"
                    :accept="config.acceptedImageTypes"
                    :errors="errors"
                />

                <BudgetField
                    :value="config.values.budget"
                    :symbol="config.currencySymbol"
                    :messages="errors.budget"
                />

                <div class="flex flex-wrap items-center justify-end gap-3">
                    <a
                        :href="urls.index"
                        class="text-sm font-semibold text-slate-500 transition-colors hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 dark:text-slate-400"
                    >
                        {{ copy.cancel }}
                    </a>
                    <button
                        type="submit"
                        :disabled="submitting"
                        class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/20 transition-colors hover:bg-primary/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span class="h-4 w-4"><AppIcon name="check-circle" /></span>
                        {{ submitting ? copy.submitting : copy.submit }}
                    </button>
                </div>
            </form>
        </RevealOnScroll>
    </div>
</template>
