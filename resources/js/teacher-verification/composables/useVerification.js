import { computed, inject, onBeforeUnmount, reactive, ref } from 'vue';

export const VERIFICATION_KEY = Symbol('teacher-verification.state');

/** Turn a byte count into the same "4.2 MB" shape the upload limits are stated in. */
export function formatBytes(bytes) {
    if (!Number.isFinite(bytes) || bytes <= 0) {
        return '';
    }

    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(0)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

/**
 * All of the verification page's state: the documents on file, the file the
 * teacher picked, the terms box and everything the checklist derives from them.
 *
 * The page is server-rendered either way — Blade still owns the document types,
 * the limits, the current status and every message. Uploads and removals post to
 * the same routes as the no-JS fallback and read the JSON reply, so the page can
 * confirm them without a reload; if that request cannot be made the card falls
 * back to a normal form submit and nothing is lost.
 */
export function createVerification(config, { onNotice } = {}) {
    const copy = config.copy ?? {};
    const limits = config.limits ?? {};
    const documentTypes = config.documentTypes ?? [];

    const maxBytes = limits.maxBytes ?? 5 * 1024 * 1024;
    const extensions = limits.extensions ?? ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

    const documents = ref([...(config.documents ?? [])]);
    const subjectGroups = config.subjectGroups ?? [];
    const subjectIds = ref([...(config.selectedSubjectIds ?? [])].map((id) => Number(id)));
    const syncingSubjects = ref(false);
    const errors = reactive({
        type: [...(config.errors?.type ?? [])],
        document: [...(config.errors?.document ?? [])],
        agree: [...(config.errors?.agree ?? [])],
        subjects: [...(config.errors?.subjects ?? [])],
    });

    const canEdit = computed(() => config.canEditDocuments === true);
    const agree = ref(Boolean(config.values?.agree));
    const uploading = ref(false);
    const removingId = ref(null);
    const selectedType = ref(config.selectedType ?? documentTypes[0]?.value ?? 'id_proof');

    const pickedFile = ref(null);
    const fileError = ref(null);
    const previewUrl = ref(null);
    const isDragging = ref(false);
    // The row the page should point at right after it was added.
    const highlightId = ref(config.highlightDocumentId ?? null);
    // The "uploaded" line under the upload button — the confirmation the teacher
    // sees without hunting for a banner somewhere else on the page.
    const uploadResult = ref(null);

    let previewObjectUrl = null;

    const typeOrder = new Map(documentTypes.map((type, index) => [type.value, index]));

    const sortedDocuments = computed(() => [...documents.value].sort((left, right) => {
        const leftIndex = typeOrder.get(left.type) ?? 99;
        const rightIndex = typeOrder.get(right.type) ?? 99;

        return leftIndex === rightIndex ? left.id - right.id : leftIndex - rightIndex;
    }));

    const hasIdProof = computed(() => documents.value.some((document) => document.type === 'id_proof'));
    const hasSubjects = computed(() => subjectIds.value.length > 0);

    // The id document is the only one the server insists on, so the checklist
    // lists the three things submission actually waits for.
    const requirements = computed(() => [
        {
            key: 'profile',
            label: copy.reqProfile,
            hint: copy.reqProfileHint,
            done: true,
            required: true,
        },
        {
            key: 'id',
            label: copy.reqId,
            hint: hasIdProof.value ? copy.reqIdHintDone : copy.reqIdHintMissing,
            done: hasIdProof.value,
            required: true,
            action: hasIdProof.value
                ? null
                : { label: copy.reqIdAction, href: '#upload-documents' },
        },
        {
            key: 'subjects',
            label: copy.reqSubjects,
            hint: hasSubjects.value ? copy.reqSubjectsHintDone : copy.reqSubjectsHintMissing,
            done: hasSubjects.value,
            required: true,
            action: hasSubjects.value
                ? null
                : { label: copy.reqSubjectsAction, href: '#subjects' },
        },
        {
            key: 'terms',
            label: copy.reqTerms,
            hint: agree.value ? copy.reqTermsHintDone : copy.reqTermsHintMissing,
            done: agree.value,
            required: true,
        },
    ]);

    const progress = computed(() => {
        const done = requirements.value.filter((requirement) => requirement.done).length;
        const total = requirements.value.length;

        return { done, total, percent: Math.round((done / total) * 100) };
    });

    const canSubmit = computed(() => canEdit.value && hasIdProof.value && hasSubjects.value && agree.value);

    // Why the submit button is still disabled — shown next to it, so the teacher
    // never has to work it out from a greyed-out button.
    const blockingReason = computed(() => {
        if (!hasIdProof.value) {
            return copy.submitBlockedId;
        }

        if (!hasSubjects.value) {
            return copy.submitBlockedSubjects;
        }

        if (!agree.value) {
            return copy.submitBlockedTerms;
        }

        return null;
    });

    const nextType = computed(() => {
        const uploaded = new Set(documents.value.map((document) => document.type));

        return documentTypes.find((type) => !uploaded.has(type.value))?.value
            ?? documentTypes[0]?.value
            ?? 'id_proof';
    });

    function releasePreview() {
        if (previewObjectUrl) {
            URL.revokeObjectURL(previewObjectUrl);
            previewObjectUrl = null;
        }

        previewUrl.value = null;
    }

    function pickFile(file) {
        fileError.value = null;
        errors.document = [];
        uploadResult.value = null;
        pickedFile.value = null;
        releasePreview();

        if (!file) {
            return;
        }

        const extension = (file.name.split('.').pop() ?? '').toLowerCase();

        // Checked here as well as in the request, so a wrong file is called out
        // the moment it is picked instead of after an upload round-trip.
        if (!extensions.includes(extension)) {
            fileError.value = copy.fileTypeError;

            return;
        }

        if (file.size > maxBytes) {
            fileError.value = (copy.fileSizeError ?? '').replace(':limit', limits.maxSize ?? '5 MB');

            return;
        }

        pickedFile.value = file;

        if ((file.type ?? '').startsWith('image/')) {
            previewObjectUrl = URL.createObjectURL(file);
            previewUrl.value = previewObjectUrl;
        }
    }

    function clearFile() {
        pickFile(null);
    }

    function clearServerErrors() {
        errors.type = [];
        errors.document = [];
    }

    async function send(url, options) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(options.headers ?? {}),
            },
            ...options,
        });

        const payload = await response.json().catch(() => null);

        return { response, payload };
    }

    /**
     * @returns {Promise<'ok'|'invalid'|'failed'|'fallback'>}
     */
    async function upload() {
        if (!pickedFile.value) {
            errors.document = [copy.fileRequired];

            return 'invalid';
        }

        uploading.value = true;
        errors.type = [];
        errors.document = [];
        uploadResult.value = null;

        const body = new FormData();
        body.append('_token', config.csrfToken ?? '');
        body.append('type', selectedType.value);
        body.append('document', pickedFile.value);

        if (config.urls?.return) {
            body.append('return', config.urls.return);
        }

        try {
            const { response, payload } = await send(config.urls.upload, { method: 'POST', body });

            if (response.status === 422) {
                errors.type = payload?.errors?.type ?? [];
                errors.document = payload?.errors?.document ?? [];

                return 'invalid';
            }

            if (!response.ok || !payload?.document) {
                (payload?.notices ?? []).forEach(pushNotice);
                uploadResult.value = { tone: 'rose', message: payload?.message ?? copy.uploadFailed };

                return 'failed';
            }

            documents.value = [...documents.value, payload.document];
            highlightId.value = payload.document.id;
            pickFile(null);
            uploadResult.value = { tone: 'emerald', message: copy.uploadSuccess };
            selectedType.value = nextType.value;
            (payload.notices ?? []).forEach(pushNotice);
            clearServerErrors();

            return 'ok';
        } catch (error) {
            // No fetch or no JSON: let the real form post so the file still lands.
            return 'fallback';
        } finally {
            uploading.value = false;
        }
    }

    /**
     * @returns {Promise<'ok'|'failed'|'fallback'>}
     */
    async function remove(document) {
        removingId.value = document.id;

        const body = new FormData();
        body.append('_token', config.csrfToken ?? '');
        body.append('_method', 'DELETE');

        if (config.urls?.return) {
            body.append('return', config.urls.return);
        }

        try {
            const { response, payload } = await send(document.destroyUrl, { method: 'POST', body });

            if (!response.ok) {
                (payload?.notices ?? []).forEach(pushNotice);
                pushNotice({ tone: 'rose', message: payload?.message ?? copy.removeFailed });

                return 'failed';
            }

            documents.value = documents.value.filter((item) => item.id !== document.id);

            if (highlightId.value === document.id) {
                highlightId.value = null;
            }

            selectedType.value = nextType.value;
            (payload?.notices ?? []).forEach(pushNotice);
            clearServerErrors();

            return 'ok';
        } catch (error) {
            return 'fallback';
        } finally {
            removingId.value = null;
        }
    }

    function pushNotice(notice) {
        onNotice?.(notice);
    }

    /**
     * Persist the full subject selection. The picker updates optimistically;
     * a failed request rolls the list back to what the server last reported.
     *
     * @returns {Promise<'ok'|'failed'|'fallback'>}
     */
    async function syncSubjects(ids) {
        subjectIds.value = [...ids];
        syncingSubjects.value = true;
        errors.subjects = [];

        const body = new FormData();
        body.append('_token', config.csrfToken ?? '');
        ids.forEach((id) => body.append('subjects[]', String(id)));

        if (config.urls?.return) {
            body.append('return', config.urls.return);
        }

        try {
            const { response, payload } = await send(config.urls.subjects, { method: 'POST', body });

            if (response.status === 422) {
                errors.subjects = payload?.errors?.subjects ?? [];

                return 'invalid';
            }

            if (!response.ok) {
                (payload?.notices ?? []).forEach(pushNotice);
                pushNotice({ tone: 'rose', message: payload?.message ?? copy.subjectsSaveFailed });
                subjectIds.value = [...(payload?.subjectIds ?? config.selectedSubjectIds ?? [])].map((id) => Number(id));

                return 'failed';
            }

            subjectIds.value = [...(payload?.subjectIds ?? [])].map((id) => Number(id));
            (payload?.notices ?? []).forEach(pushNotice);

            return 'ok';
        } catch (error) {
            // No fetch or no JSON: let the real form post so the picks still land.
            return 'fallback';
        } finally {
            syncingSubjects.value = false;
        }
    }

    onBeforeUnmount(releasePreview);

    return {
        copy,
        limits,
        documentTypes,
        documents: sortedDocuments,
        errors,
        canEdit,
        agree,
        uploading,
        removingId,
        selectedType,
        pickedFile,
        fileError,
        previewUrl,
        isDragging,
        highlightId,
        uploadResult,
        hasIdProof,
        subjectGroups,
        subjectIds,
        syncingSubjects,
        hasSubjects,
        requirements,
        progress,
        canSubmit,
        blockingReason,
        nextType,
        pickFile,
        clearFile,
        upload,
        remove,
        syncSubjects,
    };
}

export function useVerification() {
    return inject(VERIFICATION_KEY, null);
}
