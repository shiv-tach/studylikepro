<script setup>
/**
 * The parent or guardian who books on the student's behalf. Both fields are
 * optional, and the strength checklist counts them as one answer.
 */
import { usePanel } from '../../shared/composables/usePanel';
import UiFormField from '../../shared/components/UiFormField.vue';
import UiSectionHeading from '../../shared/components/UiSectionHeading.vue';
import { useCopy } from '../composables/usePageContext';
import { useProfileForm } from '../composables/useProfileForm';
import { FIELD } from '../styles';

const copy = useCopy();
const panel = usePanel();

const { values, errors, limits } = useProfileForm();
</script>

<template>
    <section :class="panel">
        <UiSectionHeading
            :title="copy.guardianTitle"
            :description="copy.guardianHint"
            icon="users"
            level="h3"
        />

        <div class="mt-5 grid gap-5 sm:grid-cols-2">
            <UiFormField :label="copy.guardianNameLabel" for-id="guardian_name" :errors="errors.guardianName">
                <input
                    id="guardian_name"
                    v-model="values.guardianName"
                    name="guardian_name"
                    type="text"
                    autocomplete="name"
                    :maxlength="limits.guardianName"
                    :class="FIELD"
                >
            </UiFormField>

            <UiFormField :label="copy.guardianPhoneLabel" for-id="guardian_phone" :errors="errors.guardianPhone">
                <input
                    id="guardian_phone"
                    v-model="values.guardianPhone"
                    name="guardian_phone"
                    type="tel"
                    inputmode="tel"
                    autocomplete="tel"
                    :maxlength="limits.guardianPhone"
                    :class="FIELD"
                >
            </UiFormField>
        </div>
    </section>
</template>
