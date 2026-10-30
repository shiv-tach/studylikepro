<script setup>
/**
 * Identity, email, mobile number and password. One real POST to
 * `join.account.store`; the server texts the code and keeps the answers in the
 * session until that code is confirmed.
 */
import { computed } from 'vue';
import { useSurveyContext } from '../../composables/useSurveyContext';
import { CARD, FIELD } from '../../styles';
import UiFormField from '../../../shared/components/UiFormField.vue';
import SubmitButton from '../SubmitButton.vue';
import AgreeCard from './AgreeCard.vue';

defineProps({
    submitLabel: { type: String, default: '' },
});

const { config, form } = useSurveyContext();

const copy = computed(() => config.copy);
const busy = computed(() => form.busy.value);
</script>

<template>
    <form
        :id="config.formId"
        method="POST"
        :action="config.urls.store"
        class="space-y-5"
        @submit="form.guard"
    >
        <input type="hidden" name="_token" :value="config.csrfToken" />

        <div :class="CARD">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                {{ copy.identityTitle }}
            </p>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <UiFormField
                    :label="copy.firstName"
                    for-id="first_name"
                    :errors="form.errorsFor('first_name')"
                    required
                >
                    <input
                        id="first_name"
                        v-model="form.values.first_name"
                        name="first_name"
                        type="text"
                        maxlength="100"
                        required
                        autocomplete="given-name"
                        enterkeyhint="next"
                        :class="FIELD"
                    />
                </UiFormField>

                <UiFormField
                    :label="copy.lastName"
                    for-id="last_name"
                    :errors="form.errorsFor('last_name')"
                    required
                >
                    <input
                        id="last_name"
                        v-model="form.values.last_name"
                        name="last_name"
                        type="text"
                        maxlength="100"
                        required
                        autocomplete="family-name"
                        :class="FIELD"
                    />
                </UiFormField>

                <div class="sm:col-span-2">
                    <UiFormField
                        :label="copy.email"
                        for-id="email"
                        :errors="form.errorsFor('email')"
                        required
                    >
                        <input
                            id="email"
                            v-model="form.values.email"
                            name="email"
                            type="email"
                            maxlength="255"
                            required
                            autocomplete="username"
                            :class="FIELD"
                        />
                    </UiFormField>
                </div>

                <div class="sm:col-span-2">
                    <UiFormField
                        :label="copy.mobileLabel"
                        for-id="mobile"
                        :hint="copy.mobileHint"
                        :errors="form.errorsFor('mobile')"
                        required
                    >
                        <input
                            id="mobile"
                            v-model="form.values.mobile"
                            name="mobile"
                            type="tel"
                            required
                            inputmode="numeric"
                            autocomplete="tel-national"
                            placeholder="07X XXX XXX X"
                            :class="FIELD"
                        />
                    </UiFormField>
                </div>

                <UiFormField
                    :label="copy.password"
                    for-id="password"
                    :errors="form.errorsFor('password')"
                    required
                >
                    <input
                        id="password"
                        name="password"
                        type="password"
                        required
                        autocomplete="new-password"
                        :class="FIELD"
                    />
                </UiFormField>

                <UiFormField :label="copy.passwordConfirmation" for-id="password_confirmation" required>
                    <input
                        id="password_confirmation"
                        name="password_confirmation"
                        type="password"
                        required
                        autocomplete="new-password"
                        :class="FIELD"
                    />
                </UiFormField>
            </div>
        </div>

        <AgreeCard />

        <div class="rounded-2xl border border-primary/25 bg-primary/5 p-4 text-xs leading-relaxed text-slate-600 dark:border-primary/30 dark:bg-primary/10 dark:text-slate-300">
            {{ copy.officialNote }}
        </div>

        <div class="hidden items-center justify-between gap-3 border-t border-slate-200/80 pt-5 dark:border-slate-800/80 lg:flex">
            <a
                :href="config.urls.back"
                v-show="!busy"
                class="text-sm font-semibold text-slate-500 transition-colors hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-100"
            >
                {{ copy.back }}
            </a>

            <SubmitButton :label="submitLabel" />
        </div>
    </form>
</template>
