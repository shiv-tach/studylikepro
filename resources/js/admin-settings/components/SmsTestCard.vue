<script setup>
/**
 * The SMSLenz connection test on /admin/settings: the saved account at a glance,
 * a sample message form and the provider's answer (campaign ID, credits, plan).
 *
 * It is a real POST form, separate from the settings form above it, so a
 * rejected attempt comes back through Blade's `old()` and `$errors` instead of
 * being lost. The gateway call happens server-side; the API key never reaches
 * the browser.
 */
import { computed, ref } from 'vue';
import UiAlert from '../../shared/components/UiAlert.vue';
import UiFormField from '../../shared/components/UiFormField.vue';
import { usePanel } from '../../shared/composables/usePanel';
import { useCopy, useCsrfToken, useUrls } from '../composables/usePageContext';

const props = defineProps({
    sms: { type: Object, required: true },
});

const pageCopy = useCopy();
const urls = useUrls();
const csrfToken = useCsrfToken();
const panel = usePanel();

const copy = computed(() => pageCopy.smsTest ?? {});
const test = computed(() => props.sms.test ?? null);
const warnings = computed(() => test.value?.warnings ?? []);
const sending = ref(false);

// The last attempt wins over the sample message: a rejected mobile number
// should not reset what the operator typed.
const mobile = ref(props.sms.old?.mobile ?? '');
const message = ref(props.sms.old?.message || props.sms.defaultMessage || '');

const controlClasses = 'mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';

// Only the facts the provider actually returned; a missing value is hidden.
const details = computed(() => {
    if (!test.value?.ok) {
        return [];
    }

    return [
        { key: 'campaign', label: copy.value.campaignLabel, value: test.value.campaign_id },
        { key: 'pages', label: copy.value.pagesLabel, value: test.value.pages },
        { key: 'credits', label: copy.value.creditsLabel, value: test.value.credits },
        { key: 'plan', label: copy.value.planLabel, value: test.value.plan },
        { key: 'recipient', label: copy.value.recipientLabel, value: test.value.recipient },
        { key: 'tested', label: copy.value.sentAtLabel, value: test.value.tested_at },
    ].filter((row) => row.value !== null && row.value !== undefined && row.value !== '');
});
</script>

<template>
    <section :class="panel" class="scroll-mt-24" id="smslenz">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ copy.title }}</h3>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ copy.hint }}</p>
            </div>

            <a
                :href="copy.docsUrl"
                target="_blank"
                rel="noopener"
                class="text-xs font-semibold text-primary hover:underline"
            >{{ copy.docsLabel }} &rarr;</a>
        </div>

        <dl class="mt-4 grid gap-3 text-xs sm:grid-cols-3">
            <div>
                <dt class="font-semibold uppercase tracking-wide text-slate-400">{{ copy.providerLabel }}</dt>
                <dd class="mt-1 text-slate-700 dark:text-slate-200">{{ sms.providerLabel }}</dd>
            </div>
            <div>
                <dt class="font-semibold uppercase tracking-wide text-slate-400">{{ copy.senderLabel }}</dt>
                <dd class="mt-1 text-slate-700 dark:text-slate-200">{{ sms.senderId || '—' }}</dd>
            </div>
            <div>
                <dt class="font-semibold uppercase tracking-wide text-slate-400">{{ copy.credentialsLabel }}</dt>
                <dd class="mt-1" :class="sms.configured ? 'text-emerald-700 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-400'">
                    {{ sms.configured ? copy.ready : copy.notReady }}
                </dd>
            </div>
        </dl>

        <p v-if="!sms.configured" class="mt-3 text-xs font-semibold text-amber-700 dark:text-amber-400">
            {{ copy.saveFirst }}
            <span v-if="sms.missing?.length">({{ copy.missingLabel }}: {{ sms.missing.join(', ') }})</span>
        </p>

        <UiAlert v-if="test" :tone="test.ok ? 'emerald' : 'rose'" :icon="test.ok ? 'check-circle' : 'alert'" class="mt-4">
            <p class="font-semibold">{{ test.ok ? copy.successTitle : copy.failureTitle }}</p>
            <p v-if="!test.ok && test.message" class="mt-1">{{ test.message }}</p>

            <dl v-if="details.length" class="mt-2 grid gap-x-4 gap-y-1 text-xs sm:grid-cols-2">
                <div v-for="row in details" :key="row.key" class="flex justify-between gap-3">
                    <dt class="text-slate-500 dark:text-slate-400">{{ row.label }}</dt>
                    <dd class="font-semibold">{{ row.value }}</dd>
                </div>
            </dl>

            <ul v-if="warnings.length" class="mt-2 list-disc space-y-1 pl-4 text-xs">
                <li v-for="(warning, index) in warnings" :key="index">{{ warning }}</li>
            </ul>
        </UiAlert>

        <form
            method="POST"
            :action="urls.smsTest"
            class="mt-5 space-y-4"
            :class="sms.configured ? '' : 'opacity-60'"
            @submit="sending = true"
        >
            <input type="hidden" name="_token" :value="csrfToken">

            <UiFormField
                :label="copy.mobileLabel"
                for-id="sms-test-mobile"
                :hint="copy.mobileHint"
                :errors="sms.errors?.mobile ?? []"
                required
            >
                <input
                    id="sms-test-mobile"
                    v-model="mobile"
                    name="mobile"
                    type="tel"
                    autocomplete="tel"
                    :placeholder="copy.mobilePlaceholder"
                    :class="controlClasses"
                    required
                >
            </UiFormField>

            <UiFormField
                :label="copy.messageLabel"
                for-id="sms-test-message"
                :errors="sms.errors?.message ?? []"
                required
            >
                <textarea
                    id="sms-test-message"
                    v-model="message"
                    name="message"
                    rows="2"
                    maxlength="300"
                    :class="controlClasses"
                    required
                ></textarea>
            </UiFormField>

            <div class="flex justify-end">
                <button
                    type="submit"
                    :disabled="sending"
                    class="inline-flex items-center justify-center rounded-xl bg-primary px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:opacity-60 dark:focus:ring-offset-slate-900"
                >
                    {{ sending ? copy.sending : copy.send }}
                </button>
            </div>
        </form>
    </section>
</template>
