import { computed, inject, onBeforeUnmount, reactive, ref } from 'vue';

export const PROFILE_FORM_KEY = Symbol('teacher-profile.form');

/**
 * The teaching profile is one form spread over several cards and a live preview,
 * so the answers, the picked photo and the errors live here: TeacherProfileApp
 * creates this once, provides it, and every card and the preview inject it.
 * Nothing here re-implements a rule — the language catalog, the field limits and
 * every message still come from Laravel.
 */
export function createProfileForm(config) {
    const teacher = config.teacher ?? {};
    const previewConfig = config.preview ?? {};

    const languages = config.languages ?? [];
    const limits = config.limits ?? {};

    const initialValues = {
        headline: '',
        bio: '',
        experienceYears: '',
        education: '',
        languages: [],
        hourlyRate: '',
        ...(config.values ?? {}),
    };

    // The picker owns its own array so checking a box never mutates the
    // snapshot `dirty` compares against.
    const values = reactive({
        ...initialValues,
        languages: [...(initialValues.languages ?? [])],
    });

    const errors = reactive({ ...(config.errors ?? {}) });

    const submitting = ref(false);
    const avatarFile = ref(null);
    const avatarPreview = ref(teacher.avatarUrl ?? null);
    // `reset()` cannot clear a file input through state, so it bumps this and
    // PhotoCard clears the input it owns.
    const resetToken = ref(0);

    let livePreviewUrl = null;

    const errorCount = computed(() => Object.values(errors)
        .reduce((total, messages) => total + (messages?.length ?? 0), 0));

    const hasErrors = computed(() => errorCount.value > 0);

    const rate = computed(() => {
        const parsed = Number(values.hourlyRate);

        return values.hourlyRate !== '' && Number.isFinite(parsed) && parsed > 0 ? parsed : null;
    });

    // The directory shows the platform currency with two decimals, so the preview
    // has to read exactly like the public card.
    const formattedRate = computed(() => {
        if (rate.value === null) {
            return null;
        }

        const symbol = previewConfig.currencySymbol ?? '';

        return `${symbol} ${rate.value.toFixed(2)}`.trim();
    });

    // Anything the teacher changed since the page loaded, in the fields or in
    // the file picker. It only ever drives the "unsaved changes" hint.
    const dirty = computed(() => avatarFile.value !== null
        || Object.keys(initialValues).some((key) => (key === 'languages'
            ? !sameLanguages(values.languages, initialValues.languages ?? [])
            // Number inputs hand back strings, so compare the typed value with
            // the stored one as text — retyping the same rate is not a change.
            : String(values[key] ?? '') !== String(initialValues[key] ?? ''))));

    const selectedLanguages = computed(() => values.languages ?? []);

    function sameLanguages(current, original) {
        const left = [...(current ?? [])].sort();
        const right = [...(original ?? [])].sort();

        return left.length === right.length && left.every((language, index) => language === right[index]);
    }

    function toggleLanguage(language) {
        const index = values.languages.indexOf(language);

        if (index === -1) {
            values.languages.push(language);
        } else {
            values.languages.splice(index, 1);
        }
    }

    function pickAvatar(file) {
        if (livePreviewUrl) {
            URL.revokeObjectURL(livePreviewUrl);
            livePreviewUrl = null;
        }

        if (file) {
            livePreviewUrl = URL.createObjectURL(file);
        }

        avatarFile.value = file ?? null;
        avatarPreview.value = livePreviewUrl ?? teacher.avatarUrl ?? null;

        // A new pick supersedes whatever the server complained about last time.
        delete errors.avatar;
    }

    function reset() {
        Object.keys(initialValues).forEach((key) => {
            if (key === 'languages') {
                values.languages = [...(initialValues.languages ?? [])];

                return;
            }

            values[key] = initialValues[key];
        });
        Object.keys(errors).forEach((key) => {
            delete errors[key];
        });

        pickAvatar(null);
        resetToken.value += 1;
    }

    // Exactly the fields the public directory card reads, refreshed from the
    // form — this is what makes the preview live.
    const card = computed(() => ({
        name: teacher.name ?? '',
        initials: teacher.initials ?? '',
        avatarUrl: avatarPreview.value,
        headline: (values.headline ?? '').trim(),
        rate: formattedRate.value,
        languages: selectedLanguages.value,
        rating: previewConfig.rating ?? null,
        ratingCount: previewConfig.ratingCount ?? 0,
        verified: previewConfig.isApproved === true,
        verificationLabel: previewConfig.verificationLabel ?? '',
        subjects: previewConfig.subjects ?? [],
        extraSubjects: previewConfig.extraSubjects ?? 0,
        metaLabel: previewConfig.metaLabel ?? null,
        nextSlot: previewConfig.nextSlot ?? null,
    }));

    onBeforeUnmount(() => {
        if (livePreviewUrl) {
            URL.revokeObjectURL(livePreviewUrl);
        }
    });

    return {
        teacher,
        languages,
        limits,
        currencySymbol: previewConfig.currencySymbol ?? '',
        values,
        errors,
        avatarFile,
        avatarPreview,
        submitting,
        resetToken,
        hasErrors,
        formattedRate,
        dirty,
        card,
        toggleLanguage,
        pickAvatar,
        reset,
    };
}

export function useProfileForm() {
    return inject(PROFILE_FORM_KEY, null);
}
