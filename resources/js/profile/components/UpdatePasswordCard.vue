<script setup>
/**
 * The password change form: a real PUT to password.update with Laravel's
 * `updatePassword` bag wired to each field.
 */
import { reactive, ref } from 'vue';
import UiFormField from '../../shared/components/UiFormField.vue';
import { useCopy, useCsrfToken, useUrls } from '../composables/usePageContext';
import AccountCard from './AccountCard.vue';
import PasswordInput from './PasswordInput.vue';

const props = defineProps({
    errors: { type: Object, default: () => ({}) },
    status: { type: Object, default: () => ({}) },
});

const copy = useCopy();
const urls = useUrls();
const csrfToken = useCsrfToken();

const form = reactive({ currentPassword: '', password: '', passwordConfirmation: '' });
const submitting = ref(false);
</script>

<template>
    <AccountCard :title="copy.passwordTitle" :description="copy.passwordHint">
        <form method="POST" :action="urls.password" class="space-y-6" @submit="submitting = true">
            <input type="hidden" name="_token" :value="csrfToken">
            <input type="hidden" name="_method" value="PUT">

            <UiFormField
                :label="copy.currentPasswordLabel"
                for-id="update_password_current_password"
                :errors="errors.currentPassword ?? []"
            >
                <PasswordInput
                    id="update_password_current_password"
                    v-model="form.currentPassword"
                    name="current_password"
                    autocomplete="current-password"
                />
            </UiFormField>

            <UiFormField :label="copy.newPasswordLabel" for-id="update_password_password" :errors="errors.password ?? []">
                <PasswordInput
                    id="update_password_password"
                    v-model="form.password"
                    name="password"
                    autocomplete="new-password"
                />
            </UiFormField>

            <UiFormField
                :label="copy.confirmPasswordLabel"
                for-id="update_password_password_confirmation"
                :errors="errors.passwordConfirmation ?? []"
            >
                <PasswordInput
                    id="update_password_password_confirmation"
                    v-model="form.passwordConfirmation"
                    name="password_confirmation"
                    autocomplete="new-password"
                />
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
                    <p v-if="status.passwordUpdated" class="text-sm font-semibold text-emerald-600 dark:text-emerald-400">
                        {{ copy.saved }}
                    </p>
                </Transition>
            </div>
        </form>
    </AccountCard>
</template>
