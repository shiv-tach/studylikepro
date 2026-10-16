import { computed, inject, onBeforeUnmount, reactive, ref } from 'vue';

export const PROFILE_FORM_KEY = Symbol('student-profile.form');

/**
 * The learning profile is one form spread over several cards, so the answers,
 * the picked photo and the errors live here: StudentProfileApp.vue creates this
 * once, provides it, and every card injects it instead of prop-drilling the
 * same refs. Nothing here re-implements a rule — the grade, the language list
 * and the field messages all still come from Laravel.
 */
export function createProfileForm(config) {
    const student = config.student ?? {};
    const levels = config.levels ?? [];
    const languages = config.languages ?? [];
    const limits = config.limits ?? {};

    const initialValues = { ...(config.values ?? {}) };
    const values = reactive({ ...initialValues });
    const errors = reactive({ ...(config.errors ?? {}) });

    const submitting = ref(false);
    const avatarFile = ref(null);
    const avatarPreview = ref(student.avatarUrl ?? null);
    // `reset()` cannot clear a file input through state, so it bumps this and
    // PhotoCard clears the input it owns.
    const resetToken = ref(0);

    let livePreviewUrl = null;

    // The level a grade belongs to travels with it, so a chip never has to
    // search the whole catalog again.
    const gradesById = new Map(levels.flatMap((level) => (level.grades ?? []).map((grade) => [grade.id, {
        ...grade,
        levelId: level.id,
        levelName: level.name,
        levelIcon: level.icon,
    }])));

    const selectedGrade = computed(() => gradesById.get(values.gradeId) ?? null);

    const errorCount = computed(() => Object.values(errors)
        .reduce((total, messages) => total + (messages?.length ?? 0), 0));

    const hasErrors = computed(() => errorCount.value > 0);

    // A step counts as done the way the server reads the field: the guardian
    // block wants a name and a phone, not one of the two.
    const steps = computed(() => [
        { key: 'grade', filled: values.gradeId !== null && values.gradeId !== undefined && values.gradeId !== '' },
        { key: 'language', filled: (values.learningLanguage ?? '').trim() !== '' },
        { key: 'goals', filled: (values.learningGoals ?? '').trim() !== '' },
        {
            key: 'guardian',
            filled: (values.guardianName ?? '').trim() !== '' && (values.guardianPhone ?? '').trim() !== '',
        },
        { key: 'photo', filled: Boolean(avatarPreview.value) },
    ]);

    const strength = computed(() => {
        const filled = steps.value.filter((step) => step.filled).length;

        return {
            percent: Math.round((filled / steps.value.length) * 100),
            filled,
            total: steps.value.length,
            steps: steps.value,
        };
    });

    // Anything the student changed since the page loaded, in the fields or in
    // the file picker. It only ever drives the "unsaved changes" hint.
    const dirty = computed(() => avatarFile.value !== null
        || Object.keys(initialValues).some((key) => (values[key] ?? '') !== (initialValues[key] ?? '')));

    function pickAvatar(file) {
        if (livePreviewUrl) {
            URL.revokeObjectURL(livePreviewUrl);
            livePreviewUrl = null;
        }

        if (file) {
            livePreviewUrl = URL.createObjectURL(file);
        }

        avatarFile.value = file ?? null;
        avatarPreview.value = livePreviewUrl ?? student.avatarUrl ?? null;

        // A new pick supersedes whatever the server complained about last time.
        delete errors.avatar;
    }

    function reset() {
        Object.keys(initialValues).forEach((key) => {
            values[key] = initialValues[key];
        });
        Object.keys(errors).forEach((key) => {
            delete errors[key];
        });

        pickAvatar(null);
        resetToken.value += 1;
    }

    onBeforeUnmount(() => {
        if (livePreviewUrl) {
            URL.revokeObjectURL(livePreviewUrl);
        }
    });

    return {
        student,
        levels,
        languages,
        limits,
        values,
        errors,
        avatarFile,
        avatarPreview,
        submitting,
        resetToken,
        selectedGrade,
        errorCount,
        hasErrors,
        steps,
        strength,
        dirty,
        pickAvatar,
        reset,
    };
}

export function useProfileForm() {
    return inject(PROFILE_FORM_KEY, null);
}
