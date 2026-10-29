import { computed, inject, onBeforeUnmount, reactive, ref } from 'vue';
import { uploadWithProgress } from './uploadWithProgress';

export const VERIFICATION_KEY = Symbol('teacher-verification.state');

/** The shape every upload slot starts from. */
export function emptySlotState() {
    return {
        status: 'idle', // idle | uploading | saved | error | invalid | cancelled
        progress: 0,
        fileName: null,
        fileSize: 0,
        previewUrl: null,
        message: null,
        messages: [],
    };
}

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
    const removingId = ref(null);
    // The first type with nothing on file. It drives the "Start here" hint, so an
    // empty page points at one slot instead of four equal-looking ones.
    const selectedType = ref(config.selectedType ?? documentTypes[0]?.value ?? 'id_proof');

    // One upload slot per document type. A file starts uploading the moment it is
    // chosen, so its progress, its file and its error belong to that slot.
    const slotStates = reactive(
        Object.fromEntries(documentTypes.map((type) => [type.value, emptySlotState()])),
    );

    // Every slot is folded away until it is asked for, so the card reads as a short
    // list of document types instead of four open drop zones. A locked application
    // opens the types it already has documents in — there is nothing left to pick.
    const openTypes = reactive(Object.fromEntries(documentTypes.map((type) => [
        type.value,
        !canEdit.value && documents.value.some((document) => document.type === type.value),
    ])));

    const isSlotOpen = (type) => openTypes[type] === true;

    function openSlot(type) {
        if (Object.prototype.hasOwnProperty.call(openTypes, type)) {
            openTypes[type] = true;
        }
    }

    function toggleSlot(type) {
        if (Object.prototype.hasOwnProperty.call(openTypes, type)) {
            openTypes[type] = !openTypes[type];
        }
    }

    // A full-page bounce (no JS) comes back with the server's messages attached
    // to the type that failed, so that slot shows them instead of a loose alert.
    const bounced = config.errors?.byType ?? null;

    if (bounced?.type && slotStates[bounced.type]) {
        slotStates[bounced.type].status = 'error';
        slotStates[bounced.type].message = copy.uploadFailed;
        slotStates[bounced.type].messages = [...(bounced.messages ?? [])];
        openSlot(bounced.type);
    }

    // The row the page should point at right after it was added.
    const highlightId = ref(config.highlightDocumentId ?? null);

    if (highlightId.value !== null) {
        const highlighted = documents.value.find((document) => document.id === highlightId.value);

        if (highlighted) {
            openSlot(highlighted.type);
        }
    }

    // The File objects stay out of reactive state (the browser owns them) but are
    // kept per type so "Try again" does not ask the teacher to pick it twice.
    const pendingFiles = new Map();
    const requests = new Map();
    const savedTimers = new Map();

    const typeOrder = new Map(documentTypes.map((type, index) => [type.value, index]));

    const sortedDocuments = computed(() => [...documents.value].sort((left, right) => {
        const leftIndex = typeOrder.get(left.type) ?? 99;
        const rightIndex = typeOrder.get(right.type) ?? 99;

        return leftIndex === rightIndex ? left.id - right.id : leftIndex - rightIndex;
    }));

    const hasIdProof = computed(() => documents.value.some((document) => document.type === 'id_proof'));
    const hasSubjects = computed(() => subjectIds.value.length > 0);
    const uploadingAny = computed(() => documentTypes.some((type) => slotStates[type.value]?.status === 'uploading'));

    // The files grouped into the slot that owns them, in the same order the page
    // lists the types, so a row never appears under the wrong heading.
    const documentsByType = computed(() => {
        const grouped = Object.fromEntries(documentTypes.map((type) => [type.value, []]));

        sortedDocuments.value.forEach((document) => {
            (grouped[document.type] ??= []).push(document);
        });

        return grouped;
    });

    const documentsFor = (type) => documentsByType.value[type] ?? [];

    const slotFor = (type) => slotStates[type] ?? emptySlotState();

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
                : { label: copy.reqIdAction, href: '#upload-documents', slot: 'id_proof' },
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

    const canSubmit = computed(() => canEdit.value
        && !uploadingAny.value
        && hasIdProof.value
        && hasSubjects.value
        && agree.value);

    // Why the submit button is still disabled — shown next to it, so the teacher
    // never has to work it out from a greyed-out button.
    const blockingReason = computed(() => {
        if (uploadingAny.value) {
            return copy.submitBlockedUpload;
        }

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

    function releasePreview(state) {
        if (state?.previewUrl) {
            URL.revokeObjectURL(state.previewUrl);
            state.previewUrl = null;
        }
    }

    function clearSavedTimer(type) {
        const timer = savedTimers.get(type);

        if (timer) {
            clearTimeout(timer);
            savedTimers.delete(type);
        }
    }

    /** Back to a clean slot: no file, no error, no leftover thumbnail. */
    function resetSlot(type) {
        const state = slotStates[type];

        if (!state) {
            return;
        }

        releasePreview(state);
        clearSavedTimer(type);
        pendingFiles.delete(type);
        Object.assign(state, emptySlotState());
    }

    /**
     * `invalid` means the chosen file is the problem (wrong type, too big, refused
     * by the server); `error` means the transfer never completed, so the same file
     * is worth another try.
     */
    function failSlot(type, message, messages = [], status = 'invalid') {
        const state = slotStates[type];

        if (!state) {
            return;
        }

        state.status = status;
        state.progress = 0;
        state.message = message ?? null;
        state.messages = [...messages];
        // A failure is only useful where it can be read: open the slot it is in.
        openSlot(type);
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
     * Validate one file and upload it into its own slot. Called straight from the
     * slot's picker — there is no separate "upload" step left to forget.
     *
     * @returns {Promise<'ok'|'invalid'|'failed'|'cancelled'|'fallback'>}
     */
    async function startUpload(type, file) {
        const state = slotStates[type];

        if (!state || !file || requests.has(type)) {
            return 'invalid';
        }

        resetSlot(type);
        openSlot(type);

        const extension = (file.name.split('.').pop() ?? '').toLowerCase();

        // Checked here as well as in the request, so a wrong file is called out
        // the moment it is picked instead of after an upload round-trip.
        if (!extensions.includes(extension)) {
            failSlot(type, copy.fileTypeError);

            return 'invalid';
        }

        if (file.size > maxBytes) {
            failSlot(type, (copy.fileSizeError ?? '').replace(':limit', limits.maxSize ?? '5 MB'));

            return 'invalid';
        }

        pendingFiles.set(type, file);
        state.status = 'uploading';
        state.progress = 0;
        state.fileName = file.name;
        state.fileSize = file.size;

        if ((file.type ?? '').startsWith('image/')) {
            state.previewUrl = URL.createObjectURL(file);
        }

        const body = new FormData();
        body.append('_token', config.csrfToken ?? '');
        body.append('type', type);
        body.append('document', file);

        let request;

        try {
            request = uploadWithProgress({
                url: config.urls.upload,
                formData: body,
                onProgress: (percent) => {
                    if (state.status === 'uploading') {
                        state.progress = percent;
                    }
                },
            });
        } catch (error) {
            // No XMLHttpRequest at all: let the slot's real form post the file.
            failSlot(type, copy.uploadFailed, [], 'error');

            return 'fallback';
        }

        requests.set(type, request);

        try {
            const { status, payload } = await request.promise;

            if (status === 'aborted') {
                releasePreview(state);
                state.status = 'cancelled';
                state.progress = 0;
                state.message = copy.uploadCancelled;

                return 'cancelled';
            }

            if (status === 422) {
                errors.type = payload?.errors?.type ?? [];
                errors.document = payload?.errors?.document ?? [];
                failSlot(type, null, [...errors.type, ...errors.document]);

                return 'invalid';
            }

            if (status < 200 || status >= 300 || !payload?.document) {
                const notice = payload?.notices?.[0] ?? null;
                (payload?.notices ?? []).forEach(pushNotice);

                // A 4xx is about this file or this application (locked, refused);
                // a 5xx is worth retrying with the same file.
                failSlot(
                    type,
                    payload?.message ?? notice?.message ?? copy.uploadFailed,
                    [],
                    status >= 500 ? 'error' : 'invalid',
                );

                return 'failed';
            }

            documents.value = [...documents.value, payload.document];
            highlightId.value = payload.document.id;
            selectedType.value = nextType.value;
            clearServerErrors();

            releasePreview(state);
            pendingFiles.delete(type);
            state.status = 'saved';
            state.progress = 100;
            state.message = copy.uploadSuccess;

            // Flash the confirmation, then hand the slot back to its file list.
            savedTimers.set(type, setTimeout(() => {
                savedTimers.delete(type);
                state.status = 'idle';
                state.progress = 0;
                state.message = null;
            }, 3000));

            return 'ok';
        } catch (error) {
            // The request never completed (offline, timeout). Keep the slot on the
            // page with a Try again — a plain form post would fail just the same.
            failSlot(type, copy.uploadFailed, [], 'error');

            return 'failed';
        } finally {
            requests.delete(type);
        }
    }

    function cancelUpload(type) {
        requests.get(type)?.abort();
    }

    /** @returns {Promise<'ok'|'invalid'|'failed'|'cancelled'|'fallback'>} */
    function retryUpload(type) {
        const file = pendingFiles.get(type);

        return file ? startUpload(type, file) : Promise.resolve('invalid');
    }

    /**
     * @returns {Promise<'ok'|'failed'|'fallback'>}
     */
    async function remove(document) {
        removingId.value = document.id;

        const body = new FormData();
        body.append('_token', config.csrfToken ?? '');
        body.append('_method', 'DELETE');

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

    onBeforeUnmount(() => {
        requests.forEach((request) => request.abort());
        requests.clear();
        savedTimers.forEach((timer) => clearTimeout(timer));
        savedTimers.clear();
        Object.values(slotStates).forEach(releasePreview);
    });

    return {
        copy,
        limits,
        documentTypes,
        documents: sortedDocuments,
        documentsByType,
        documentsFor,
        slotStates,
        slotFor,
        openTypes,
        isSlotOpen,
        openSlot,
        toggleSlot,
        uploadingAny,
        errors,
        canEdit,
        agree,
        removingId,
        selectedType,
        highlightId,
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
        startUpload,
        retryUpload,
        cancelUpload,
        resetSlot,
        remove,
        syncSubjects,
    };
}

export function useVerification() {
    return inject(VERIFICATION_KEY, null);
}
