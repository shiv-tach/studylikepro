<script setup>
/**
 * The delivery form: one real multipart POST to `teacher.marking.mark`, so the
 * files, the feedback and the marks all arrive the way the server validates
 * them. The submit button only reflects progress — it never replaces the POST
 * with a fetch, so a rejected upload still bounces back with old input.
 */
import { computed, ref } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';
import UiAlert from '../../shared/components/UiAlert.vue';
import UiFormField from '../../shared/components/UiFormField.vue';
import { usePanel } from '../../shared/composables/usePanel';
import { useCopy, useCsrfToken, usePage } from '../composables/usePageContext';
import MarkedCopyField from './MarkedCopyField.vue';
import MarksRowsField from './MarksRowsField.vue';

const page = usePage();
const copy = useCopy();
const csrfToken = useCsrfToken();
const panel = usePanel();

const form = computed(() => page.form ?? {});
const errors = computed(() => form.value.errors ?? {});

const feedback = ref(form.value.feedback ?? '');
const marks = ref(
    (form.value.marks?.length ? form.value.marks : [{ label: '', value: '' }])
        .map((row) => ({ ...row })),
);
const submitting = ref(false);

function addRow() {
    if (marks.value.length >= (form.value.maxMarks ?? 50)) {
        return;
    }

    marks.value.push({ label: '', value: '' });
}

function removeRow(index) {
    marks.value.splice(index, 1);

    // The form always keeps one row so the first field never disappears.
    if (marks.value.length === 0) {
        addRow();
    }
}
</script>

<template>
    <form
        :action="form.action"
        method="POST"
        enctype="multipart/form-data"
        :class="[panel, 'space-y-6']"
        aria-labelledby="teacher-marking-delivery-form-heading"
        @submit="submitting = true"
    >
        <input type="hidden" name="_token" :value="csrfToken">

        <div>
            <h2 id="teacher-marking-delivery-form-heading" class="flex items-center gap-2 text-sm font-bold text-slate-800 dark:text-slate-100">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary/10 p-1.5 text-primary" aria-hidden="true">
                    <AppIcon name="pencil" />
                </span>
                {{ copy.form?.title }}
            </h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ copy.form?.intro }}</p>
        </div>

        <MarkedCopyField
            :max-files="form.maxFiles"
            :accept="form.accept"
            :errors="errors.marked_files"
        />

        <UiFormField
            :label="copy.form?.feedbackLabel"
            for-id="marked-feedback"
            :hint="copy.form?.feedbackHint"
            :errors="errors.feedback"
        >
            <textarea
                id="marked-feedback"
                v-model="feedback"
                name="feedback"
                rows="5"
                :maxlength="form.maxFeedback"
                :placeholder="copy.form?.feedbackPlaceholder"
                class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
            ></textarea>
        </UiFormField>

        <MarksRowsField
            :rows="marks"
            :errors="errors.marks"
            :max="form.maxMarks"
            @add="addRow"
            @remove="removeRow"
        />

        <UiAlert v-if="errors.order" tone="rose" icon="alert">
            {{ errors.order }}
        </UiAlert>

        <div class="flex justify-end border-t border-slate-200 pt-5 dark:border-slate-800">
            <button
                type="submit"
                class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-bold text-white shadow-sm shadow-primary/20 transition-colors hover:bg-primary/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 disabled:cursor-wait disabled:opacity-70"
                :disabled="submitting"
            >
                {{ submitting ? copy.form?.submitting : copy.form?.submit }}
            </button>
        </div>
    </form>
</template>
