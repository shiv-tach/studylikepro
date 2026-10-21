<script setup>
/**
 * What a student reads before booking: the headline the directory card shows,
 * the biography, the years of experience and the education line. The headline
 * and bio counters mirror the limits the request validates, so the field can
 * never hand the server more than it accepts.
 */
import { computed } from 'vue';
import { usePanel } from '../../shared/composables/usePanel';
import UiFormField from '../../shared/components/UiFormField.vue';
import UiSectionHeading from '../../shared/components/UiSectionHeading.vue';
import { useCopy } from '../composables/usePageContext';
import { useProfileForm } from '../composables/useProfileForm';
import { FIELD } from '../styles';

const copy = useCopy();
const panel = usePanel();

const { values, errors, limits } = useProfileForm();

const headlineLength = computed(() => (values.headline ?? '').length);
const bioLength = computed(() => (values.bio ?? '').length);
</script>

<template>
    <section :class="panel">
        <UiSectionHeading :title="copy.professionalTitle" :description="copy.professionalHint" icon="user" level="h3" />

        <div class="mt-5">
            <UiFormField
                :label="copy.headlineLabel"
                for-id="headline"
                required
                :errors="errors.headline"
            >
                <input
                    id="headline"
                    v-model="values.headline"
                    name="headline"
                    type="text"
                    :maxlength="limits.headline"
                    :placeholder="copy.headlinePlaceholder"
                    :class="FIELD"
                >

                <div class="mt-2 flex items-center justify-end">
                    <p class="text-xs tabular-nums text-slate-400">{{ headlineLength }} / {{ limits.headline }}</p>
                </div>
            </UiFormField>
        </div>

        <div class="mt-5">
            <UiFormField :label="copy.bioLabel" for-id="bio" :errors="errors.bio">
                <textarea
                    id="bio"
                    v-model="values.bio"
                    name="bio"
                    rows="4"
                    :maxlength="limits.bio"
                    :placeholder="copy.bioPlaceholder"
                    :class="FIELD"
                ></textarea>

                <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs text-slate-400">{{ copy.bioHint }}</p>
                    <p class="shrink-0 text-xs tabular-nums text-slate-400">{{ bioLength }} / {{ limits.bio }}</p>
                </div>
            </UiFormField>
        </div>

        <div class="mt-5 grid gap-5 sm:grid-cols-2">
            <UiFormField
                :label="copy.experienceLabel"
                for-id="experience_years"
                required
                :errors="errors.experienceYears"
            >
                <input
                    id="experience_years"
                    v-model="values.experienceYears"
                    name="experience_years"
                    type="number"
                    min="0"
                    :max="limits.experienceYears"
                    step="1"
                    :class="FIELD"
                >
            </UiFormField>

            <UiFormField
                :label="copy.educationLabel"
                for-id="education"
                required
                :errors="errors.education"
            >
                <input
                    id="education"
                    v-model="values.education"
                    name="education"
                    type="text"
                    :maxlength="limits.education"
                    :placeholder="copy.educationPlaceholder"
                    :class="FIELD"
                >
            </UiFormField>
        </div>
    </section>
</template>
