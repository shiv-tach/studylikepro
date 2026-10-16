<script setup>
/**
 * Name and email, saved with a real PATCH to /profile. The email-verification
 * notice and its re-send button only appear while the address is unverified.
 */
import { reactive, ref } from 'vue';
import UiFormField from '../../shared/components/UiFormField.vue';
import { useCopy, useCsrfToken, useUrls } from '../composables/usePageContext';
import AccountCard from './AccountCard.vue';

const props = defineProps({
    values: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    status: { type: Object, default: () => ({}) },
    showsVerificationNotice: { type: Boolean, default: false },
});

const copy = useCopy();
const urls = useUrls();
const csrfToken = useCsrfToken();

// The form's own draft, seeded from what Blade sent back.
const form = reactive({ name: props.values.name ?? '', email: props.values.email ?? '' });
const submitting = ref(false);
</script>

<template>
    <AccountCard :title="copy.infoTitle" :description="copy.infoHint">
        <form id="send-verification" method="POST" :action="urls.verificationSend" class="hidden">
            <input type="hidden" name="_token" :value="csrfToken">
        </form>

        <form method="POST" :action="urls.update" class="space-y-6" @submit="submitting = true">
            <input type="hidden" name="_token" :value="csrfToken">
            <input type="hidden" name="_method" value="PATCH">

            <UiFormField :label="copy.nameLabel" for-id="name" :errors="errors.name ?? []" required>
                <input
                    id="name"
                    v-model="form.name"
                    name="name"
                    type="text"
                    required
                    autofocus
                    autocomplete="name"
                    class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                >
            </UiFormField>

            <UiFormField :label="copy.emailLabel" for-id="email" :errors="errors.email ?? []" required>
                <input
                    id="email"
                    v-model="form.email"
                    name="email"
                    type="email"
                    required
                    autocomplete="username"
                    class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                >

                <div v-if="showsVerificationNotice" class="mt-2">
                    <p class="text-sm text-slate-800 dark:text-slate-200">
                        {{ copy.unverifiedNotice }}
                        <button
                            form="send-verification"
                            class="rounded-md text-sm text-slate-500 underline hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary/20 dark:text-slate-400 dark:hover:text-slate-100"
                        >
                            {{ copy.resendVerification }}
                        </button>
                    </p>

                    <p v-if="status.verificationLinkSent" class="mt-2 text-sm font-medium text-emerald-600 dark:text-emerald-400">
                        {{ copy.verificationSent }}
                    </p>
                </div>
            </UiFormField>

            <div class="flex items-center gap-4">
                <button
                    type="submit"
                    :disabled="submitting"
                    class="inline-flex items-center justify-center rounded-xl bg-primary px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:opacity-60 dark:focus:ring-offset-slate-900"
                >
                    {{ submitting ? copy.saving : copy.save }}
                </button>

                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition duration-200 ease-in"
                    leave-to-class="opacity-0"
                >
                    <p v-if="status.profileUpdated" class="text-sm font-semibold text-emerald-600 dark:text-emerald-400">
                        {{ copy.saved }}
                    </p>
                </Transition>
            </div>
        </form>
    </AccountCard>
</template>
