<script setup>
/**
 * The answers that scope the whole workspace: the grade every lesson, teacher
 * and price is matched to, the language lessons are taught in and the goals a
 * teacher reads before the first lesson. The grade picker keeps the onboarding
 * wizard's level → grade tree (one optgroup per level).
 */
import { computed } from 'vue';
import { usePanel } from '../../shared/composables/usePanel';
import UiFormField from '../../shared/components/UiFormField.vue';
import UiSectionHeading from '../../shared/components/UiSectionHeading.vue';
import { useCopy } from '../composables/usePageContext';
import { useProfileForm } from '../composables/useProfileForm';
import { FIELD } from '../styles';
import LanguageChoice from './LanguageChoice.vue';

const copy = useCopy();
const panel = usePanel();

const { values, errors, levels, limits } = useProfileForm();

const goalLength = computed(() => (values.learningGoals ?? '').length);
</script>

<template>
    <section :class="panel">
        <UiSectionHeading :title="copy.aboutTitle" :description="copy.aboutHint" icon="user" level="h3" />

        <div class="mt-5 grid gap-5 sm:grid-cols-2">
            <UiFormField
                :label="copy.gradeLabel"
                for-id="grade_id"
                required
                :errors="errors.gradeId"
                :hint="copy.gradeHint"
            >
                <select id="grade_id" v-model="values.gradeId" name="grade_id" required :class="FIELD">
                    <option :value="null">{{ copy.gradePlaceholder }}</option>
                    <optgroup v-for="level in levels" :key="level.id" :label="level.name">
                        <option v-for="grade in level.grades" :key="grade.id" :value="grade.id">{{ grade.label }}</option>
                    </optgroup>
                </select>
            </UiFormField>

            <LanguageChoice />
        </div>

        <div class="mt-5">
            <UiFormField
                :label="copy.goalsLabel"
                for-id="learning_goals"
                :errors="errors.learningGoals"
            >
                <textarea
                    id="learning_goals"
                    v-model="values.learningGoals"
                    name="learning_goals"
                    rows="3"
                    :maxlength="limits.learningGoals"
                    :placeholder="copy.goalsPlaceholder"
                    :class="FIELD"
                ></textarea>

                <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs text-slate-400">{{ copy.goalsHint }}</p>
                    <p class="shrink-0 text-xs tabular-nums text-slate-400">
                        {{ goalLength }} / {{ limits.learningGoals }}
                    </p>
                </div>
            </UiFormField>
        </div>

        <p class="mt-5 border-t border-slate-200/80 pt-4 text-xs text-slate-400 dark:border-slate-800/80">
            {{ copy.timezoneNote }}
        </p>
    </section>
</template>
