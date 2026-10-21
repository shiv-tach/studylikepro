<script setup>
/**
 * Propose a subject that is not in the catalog yet. Admin approval creates the
 * subject and grants it to the teacher, so this is a real POST form with the
 * same field names the controller has always validated.
 */
import { computed } from 'vue';
import { usePanel } from '../../shared/composables/usePanel';
import UiFormField from '../../shared/components/UiFormField.vue';
import UiSectionHeading from '../../shared/components/UiSectionHeading.vue';
import { format, useCopy, useCsrfToken, useUrls, useWorkspace } from '../composables/usePageContext';
import { FIELD, PRIMARY_BUTTON } from '../styles';

const workspace = useWorkspace();
const copy = useCopy();
const urls = useUrls();
const csrfToken = useCsrfToken();
const panel = usePanel();

const { propose, proposeValues, proposeErrors, submittingPropose } = workspace;

const nameLimit = computed(() => propose.limits?.name ?? 100);
const noteLimit = computed(() => propose.limits?.note ?? 500);

const canSubmit = computed(() => proposeValues.name.trim().length > 0
    && Boolean(proposeValues.levelId)
    && !submittingPropose.value);

const noteHint = computed(() => format(copy.propose?.charsLeft, {
    count: Math.max(0, noteLimit.value - (proposeValues.note?.length ?? 0)),
}));
</script>

<template>
    <section :class="panel">
        <UiSectionHeading
            :title="copy.propose?.title"
            :description="copy.propose?.hint"
            icon="sparkles"
            level="h2"
        />

        <form
            method="POST"
            :action="urls.request"
            class="mt-5 max-w-xl space-y-4"
            @submit="submittingPropose = true"
        >
            <input type="hidden" name="_token" :value="csrfToken">

            <UiFormField
                :label="copy.propose?.nameLabel"
                for-id="proposed_name"
                :errors="proposeErrors.name"
                :hint="format(copy.propose?.charsLeft, { count: Math.max(0, nameLimit - (proposeValues.name?.length ?? 0)) })"
                required
            >
                <input
                    id="proposed_name"
                    name="proposed_name"
                    type="text"
                    maxlength="100"
                    v-model="proposeValues.name"
                    :placeholder="copy.propose?.namePlaceholder"
                    :class="FIELD"
                >
            </UiFormField>

            <UiFormField
                :label="copy.propose?.levelLabel"
                for-id="education_level_id"
                :errors="proposeErrors.levelId"
                required
            >
                <select
                    id="education_level_id"
                    name="education_level_id"
                    v-model="proposeValues.levelId"
                    :class="FIELD"
                >
                    <option :value="null">{{ copy.propose?.levelPlaceholder }}</option>
                    <option v-for="level in propose.levels" :key="level.id" :value="level.id">
                        {{ level.icon }} {{ level.name }}
                    </option>
                </select>
            </UiFormField>

            <UiFormField
                :label="copy.propose?.noteLabel"
                for-id="note"
                :errors="proposeErrors.note"
                :hint="noteHint"
            >
                <textarea
                    id="note"
                    name="note"
                    rows="3"
                    maxlength="500"
                    v-model="proposeValues.note"
                    :placeholder="copy.propose?.notePlaceholder"
                    :class="FIELD"
                ></textarea>
            </UiFormField>

            <div class="flex justify-end">
                <button type="submit" :class="PRIMARY_BUTTON" :disabled="!canSubmit">
                    {{ submittingPropose ? copy.save?.saving : copy.propose?.submit }}
                </button>
            </div>
        </form>
    </section>
</template>
