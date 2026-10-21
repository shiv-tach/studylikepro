<script setup>
/**
 * The real form behind one approved subject. Step 1 picks the grades and their
 * rates, step 2 narrows the lessons, step 3 sets the fallback rate, and the save
 * bar submits the whole thing to `teacher.subjects.update`.
 */
import { computed } from 'vue';
import UiAlert from '../../shared/components/UiAlert.vue';
import { useCopy, useCsrfToken, useWorkspace } from '../composables/usePageContext';
import DefaultRateSection from './DefaultRateSection.vue';
import GradeRateSection from './GradeRateSection.vue';
import LessonScopeSection from './LessonScopeSection.vue';
import SubjectSaveBar from './SubjectSaveBar.vue';

const props = defineProps({
    subject: { type: Object, required: true },
});

const workspace = useWorkspace();
const copy = useCopy();
const csrfToken = useCsrfToken();

// The default-rate messages sit under their field; everything else is listed
// once at the top of the form.
const messages = computed(() => ['grades', 'lessons', 'gradeRates']
    .flatMap((key) => props.subject.errors?.[key] ?? []));

function onSubmit() {
    workspace.markSubmitting(props.subject);
}
</script>

<template>
    <form
        method="POST"
        :action="subject.updateUrl"
        class="mt-5 space-y-6"
        @submit="onSubmit"
    >
        <input type="hidden" name="_token" :value="csrfToken">
        <input type="hidden" name="sync_grades" value="1">
        <input type="hidden" name="editing_subject" :value="subject.id">

        <UiAlert v-if="messages.length" tone="rose" icon="alert" role="alert">
            <span class="font-semibold">{{ copy.errors?.title }}</span>
            <ul class="mt-1 space-y-0.5">
                <li v-for="(message, index) in messages" :key="index">{{ message }}</li>
            </ul>
        </UiAlert>

        <GradeRateSection :subject="subject" />

        <hr class="border-slate-200/70 dark:border-slate-800/70">

        <LessonScopeSection :subject="subject" />

        <hr class="border-slate-200/70 dark:border-slate-800/70">

        <DefaultRateSection :subject="subject" />

        <SubjectSaveBar :subject="subject" />
    </form>
</template>
