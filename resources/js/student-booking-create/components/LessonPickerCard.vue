<script setup>
/**
 * Step one of the stepper: subject, optional lesson and lesson length. This
 * stays a real GET form, so the query string Laravel validates is built by the
 * browser and changing a select reloads the page — which re-scopes the lessons,
 * the price and the open slots. Blade only pre-labels the options.
 */
import { usePanel } from '../../shared/composables/usePanel';
import UiSectionHeading from '../../shared/components/UiSectionHeading.vue';
import { useCopy, useUrls } from '../composables/usePageContext';

defineProps({
    selection: { type: Object, required: true },
    subjects: { type: Array, default: () => [] },
    lessons: { type: Array, default: () => [] },
    durations: { type: Array, default: () => [] },
    notice: { type: String, default: null },
});

const copy = useCopy();
const urls = useUrls();
const panel = usePanel();

const labelClass = 'block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400';
const fieldClass =
    'mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';

// Changing any of the three selects commits the picker straight away.
const submit = (event) => event.target.form?.submit();
</script>

<template>
    <form method="GET" :action="urls.filter" :class="panel">
        <UiSectionHeading :title="copy.stepPick" icon="search" level="h3" />

        <div class="mt-4 grid gap-4 sm:grid-cols-3">
            <div>
                <label for="booking-subject" :class="labelClass">{{ copy.subject }}</label>
                <select id="booking-subject" name="subject_id" :value="selection.subjectId" :class="fieldClass" @change="submit">
                    <option v-for="option in subjects" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
            </div>

            <div>
                <label for="booking-lesson" :class="labelClass">{{ copy.lesson }}</label>
                <select id="booking-lesson" name="lesson_id" :value="selection.lessonId" :class="fieldClass" @change="submit">
                    <option value="">{{ copy.anyLesson }}</option>
                    <option v-for="option in lessons" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
            </div>

            <div>
                <label for="booking-duration" :class="labelClass">{{ copy.duration }}</label>
                <select id="booking-duration" name="duration" :value="selection.duration" :class="fieldClass" @change="submit">
                    <option v-for="option in durations" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
            </div>
        </div>

        <p v-if="notice" class="mt-3 text-xs text-slate-400">{{ notice }}</p>
    </form>
</template>
