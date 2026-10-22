<script setup>
/**
 * The scope cascade. Picking a grade narrows the subjects to the ones the
 * teacher covers at that grade; the subject narrows the lessons. A stale pick
 * is cleared instead of silently posting a scope the server will reject.
 */
import { computed } from 'vue';
import UiFormField from '../../shared/components/UiFormField.vue';
import { useCopy, useWorkspace } from '../composables/usePageContext';
import { FIELD } from '../styles';

const copy = useCopy();
const workspace = useWorkspace();

const {
    values,
    errors,
    currentType,
    gradeChoices,
    subjectChoices,
    lessonChoices,
} = workspace;

const gradeRequired = computed(() => Boolean(currentType.value?.requiresLesson));
const subjectRequired = computed(() => Boolean(currentType.value) && currentType.value.value !== 'qna');
const showLesson = computed(() => Boolean(currentType.value) && (
    currentType.value.requiresLesson || currentType.value.allowsLessonOrSubject
));
const lessonRequired = computed(() => Boolean(currentType.value?.requiresLesson));

const gradeHint = computed(() => (
    gradeRequired.value ? copy.fields?.gradeHintRequired : copy.fields?.gradeHintOptional
));

const subjectHint = computed(() => {
    if (currentType.value?.value === 'qna') {
        return copy.fields?.subjectHintQna;
    }

    return copy.fields?.subjectHintRequired;
});

const lessonHint = computed(() => (
    lessonRequired.value ? copy.fields?.lessonHintRequired : copy.fields?.lessonHintOptional
));
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2">
        <UiFormField
            :label="copy.fields?.grade"
            for-id="service-grade"
            :hint="gradeHint"
            :errors="errors.grade_id ?? []"
            :required="gradeRequired"
        >
            <select
                id="service-grade"
                v-model="values.gradeId"
                name="grade_id"
                :class="FIELD"
                :required="gradeRequired"
            >
                <option value="">
                    {{ gradeRequired ? copy.fields?.gradePick : copy.fields?.gradeAll }}
                </option>
                <option v-for="grade in gradeChoices" :key="grade.id" :value="grade.id">
                    {{ grade.label }}
                </option>
            </select>
        </UiFormField>

        <UiFormField
            :label="copy.fields?.subject"
            for-id="service-subject"
            :hint="subjectHint"
            :errors="errors.subject_id ?? []"
            :required="subjectRequired"
        >
            <select
                id="service-subject"
                v-model="values.subjectId"
                name="subject_id"
                :class="FIELD"
                :required="subjectRequired"
            >
                <option value="">
                    {{ currentType?.allowsLessonOrSubject ? copy.fields?.subjectNone : copy.fields?.subjectPick }}
                </option>
                <option v-for="subject in subjectChoices" :key="subject.id" :value="subject.id">
                    {{ subject.name }}{{ subject.level_name ? ` — ${subject.level_name}` : '' }}
                </option>
            </select>
        </UiFormField>

        <UiFormField
            v-if="showLesson"
            :label="copy.fields?.lesson"
            for-id="service-lesson"
            :hint="lessonHint"
            :errors="errors.lesson_id ?? []"
            :required="lessonRequired"
            class="sm:col-span-2"
        >
            <select
                id="service-lesson"
                v-model="values.lessonId"
                name="lesson_id"
                :class="FIELD"
                :required="lessonRequired"
            >
                <option value="">
                    {{ currentType?.allowsLessonOrSubject ? copy.fields?.lessonWhole : copy.fields?.lessonPick }}
                </option>
                <option v-for="lesson in lessonChoices" :key="lesson.id" :value="lesson.id">
                    {{ lesson.name }}
                </option>
            </select>
        </UiFormField>
    </div>
</template>
