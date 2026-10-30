<script setup>
/**
 * The last screen of the invite survey, in two states on one URL: identity +
 * password before the SMS code is sent, and the code itself afterwards.
 *
 * Both states are real forms — the Vue layer only decides which one is visible
 * and repeats the recap of the answers the new account is about to keep.
 */
import { computed, provide } from 'vue';
import { SURVEY_KEY } from './context';
import { createSurveyForm } from './composables/useSurveyForm';
import { format } from './format';
import ErrorSummary from './components/ErrorSummary.vue';
import SummaryPanel from './components/SummaryPanel.vue';
import MobileActionBar from './components/MobileActionBar.vue';
import IdentityForm from './components/account/IdentityForm.vue';
import CodeForm from './components/account/CodeForm.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

const form = createSurveyForm(props.config);

provide(SURVEY_KEY, { config: props.config, form });

const copy = computed(() => props.config.copy);
const pending = computed(() => Boolean(props.config.pending));

const hasErrors = computed(() => form.hasErrors.value);
const errorList = computed(() => form.errorList.value);

const chip = computed(() => (pending.value ? copy.value.almostDone : copy.value.lastStep));
const title = computed(() => (pending.value ? copy.value.confirmTitle : copy.value.createTitle));

const body = computed(() => {
    if (pending.value) {
        return format(copy.value.codeBody, {
            length: props.config.length,
            mobile: props.config.mobile,
        });
    }

    return props.config.otpEnabled ? copy.value.otpOnBody : copy.value.otpOffBody;
});

const submitLabel = computed(() => {
    if (pending.value) {
        return copy.value.verify;
    }

    return props.config.otpEnabled ? copy.value.sendMyCode : copy.value.createMyAccount;
});

const mobileSubmitLabel = computed(() => {
    if (pending.value) {
        return copy.value.verifyShort;
    }

    return props.config.otpEnabled ? copy.value.sendCode : copy.value.createAccount;
});
</script>

<template>
    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start">
        <div class="space-y-5">
            <div class="survey-enter">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wider text-primary">
                    {{ chip }}
                </span>

                <h1 class="mt-2 text-2xl font-bold leading-tight text-slate-900 dark:text-slate-100 sm:text-3xl">
                    {{ title }}
                </h1>
                <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                    {{ body }}
                </p>
            </div>

            <ErrorSummary v-if="hasErrors" :title="copy.accountErrorTitle" :messages="errorList" />

            <CodeForm v-if="pending" />
            <IdentityForm v-else :submit-label="submitLabel" />
        </div>

        <SummaryPanel
            :groups="config.summary"
            :answered="config.answered"
            :questions="config.questions"
            :copy="config.copy"
        />
    </div>

    <MobileActionBar
        :form-id="config.formId"
        :back-url="pending ? null : config.urls.back"
        :submit-label="mobileSubmitLabel"
    />
</template>
