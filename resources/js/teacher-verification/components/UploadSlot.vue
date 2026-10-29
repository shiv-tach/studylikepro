<script setup>
/**
 * One document type = one upload slot.
 *
 * The teacher never has to say what they are uploading and never presses an
 * "Upload" button: the type is the slot, and choosing (or dropping) a file starts
 * the upload on the spot. The slot then reports its own state — a live percentage,
 * then "Saved", or the reason it failed with a Try again button — and its own
 * files stay inside it, so a second ID page has an obvious home.
 *
 * The slot is folded away until it is opened: four open drop zones pushed the
 * heading, the checklist and the submit card far apart, so the card now lists the
 * types and shows one picker at a time. Anything the slot has to say (a live
 * transfer, a failure, a file that just landed) opens it on its own.
 */
import { computed, ref } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';
import UiChip from '../../shared/components/UiChip.vue';
import { format, useCopy, useCsrfToken, useUrls } from '../composables/usePageContext';
import { formatBytes, useVerification } from '../composables/useVerification';
import {
    GHOST_BUTTON,
    PROGRESS_BAR,
    PROGRESS_TRACK,
    SECONDARY_BUTTON,
    UPLOAD_ZONE,
} from '../styles';
import DocumentRow from './DocumentRow.vue';

const props = defineProps({
    type: { type: Object, required: true },
    documents: { type: Array, default: () => [] },
    state: { type: Object, required: true },
    canEdit: { type: Boolean, default: false },
    startHint: { type: Boolean, default: false },
    highlightId: { type: Number, default: null },
    removingId: { type: Number, default: null },
});

const emit = defineEmits(['request-remove']);

const copy = useCopy();
const urls = useUrls();
const csrfToken = useCsrfToken();
const { limits, startUpload, retryUpload, cancelUpload, isSlotOpen, toggleSlot } = useVerification();

const form = ref(null);
const input = ref(null);
const dragging = ref(false);
// Kept outside the reactive slot state: only the browser owns File objects, but
// it is needed for the plain-form fallback and for "Try again".
const heldFile = ref(null);

const open = computed(() => isSlotOpen(props.type.value));
const panelId = computed(() => `upload-panel-${props.type.value}`);

const status = computed(() => props.state?.status ?? 'idle');
const uploading = computed(() => status.value === 'uploading');
const saved = computed(() => status.value === 'saved');
const cancelled = computed(() => status.value === 'cancelled');
const failed = computed(() => status.value === 'error' || status.value === 'invalid');
const hasDocuments = computed(() => props.documents.length > 0);

const statusChip = computed(() => {
    if (uploading.value) {
        return { tone: 'primary', label: copy.uploadSlotUploading };
    }

    if (status.value === 'error') {
        return { tone: 'rose', label: copy.uploadFailedShort };
    }

    if (status.value === 'invalid') {
        return { tone: 'rose', label: copy.uploadSlotInvalid };
    }

    if (hasDocuments.value) {
        return { tone: 'emerald', label: copy.uploadedDocument };
    }

    return { tone: 'slate', label: copy.uploadSlotEmpty };
});

// Only a broken transfer is worth repeating: a refused file needs a new pick.
const canRetry = computed(() => status.value === 'error' || cancelled.value);

const statusText = computed(() => {
    if (uploading.value) {
        return format(copy.uploadProgress, { percent: props.state.progress ?? 0 });
    }

    if (failed.value) {
        return props.state.message ?? copy.uploadFailed;
    }

    if (saved.value) {
        return props.state.message ?? copy.uploadSuccess;
    }

    return props.state.message ?? copy.uploadCancelled;
});

const toneText = computed(() => {
    if (failed.value) {
        return 'text-rose-600 dark:text-rose-400';
    }

    return saved.value
        ? 'text-emerald-600 dark:text-emerald-400'
        : 'text-slate-500 dark:text-slate-400';
});

const ringClass = computed(() => {
    if (uploading.value) {
        return 'border-primary/50 bg-primary/5';
    }

    if (failed.value) {
        return 'border-rose-300/80 bg-rose-50/50 dark:border-rose-900/50 dark:bg-rose-950/20';
    }

    return hasDocuments.value
        ? 'border-emerald-200/80 bg-emerald-50/30 dark:border-emerald-900/40 dark:bg-emerald-950/10'
        : 'border-slate-200/80 dark:border-slate-800/80';
});

const zoneClass = computed(() => (dragging.value
    ? 'border-primary bg-primary/5'
    : 'border-slate-300 hover:border-primary/50 hover:bg-primary/5 dark:border-slate-700 dark:hover:border-primary/40 dark:hover:bg-primary/10'));

/**
 * The picker is the input itself (the dashed area is its label), so clear the
 * old selection first: choosing the same file again still fires `change`. A
 * click while a file is uploading is simply refused.
 */
function onInputClick(event) {
    if (uploading.value) {
        event.preventDefault();

        return;
    }

    event.target.value = '';
}

/**
 * No XMLHttpRequest (or the request never reached the server): hand the same
 * file to this slot's real form so the document still lands.
 */
function submitFallback(file) {
    const formElement = form.value;

    if (!formElement || !file) {
        return;
    }

    try {
        if (input.value && (!input.value.files || input.value.files.length === 0)) {
            const transfer = new DataTransfer();
            transfer.items.add(file);
            input.value.files = transfer.files;
        }
    } catch (error) {
        // The picker may already hold the file; the form still has what it needs.
    }

    formElement.submit();
}

function run(file) {
    if (!file) {
        return;
    }

    heldFile.value = file;

    void startUpload(props.type.value, file).then((result) => {
        if (result === 'fallback') {
            submitFallback(file);
        }
    });
}

function onPick(event) {
    run(event.target.files?.[0] ?? null);
}

function onDrop(event) {
    dragging.value = false;
    run(event.dataTransfer?.files?.[0] ?? null);
}

function retry() {
    void retryUpload(props.type.value).then((result) => {
        if (result === 'fallback' && heldFile.value) {
            submitFallback(heldFile.value);
        }
    });
}
</script>

<template>
    <article
        class="min-w-0 rounded-xl border transition-colors duration-300"
        :class="ringClass"
        :aria-busy="uploading ? 'true' : 'false'"
    >
        <!-- The heading is the control: the whole row opens and folds the slot, so
             the click target is the thing the teacher is reading. -->
        <button
            type="button"
            class="flex w-full items-start gap-2.5 rounded-xl p-3 text-left hover:bg-slate-50/70 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 sm:p-4 dark:hover:bg-slate-800/40"
            :aria-expanded="open ? 'true' : 'false'"
            :aria-controls="panelId"
            @click="toggleSlot(type.value)"
        >
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 p-2 text-primary">
                <AppIcon :name="type.icon || 'folder'" />
            </span>

            <span class="min-w-0 flex-1">
                <span class="flex flex-wrap items-center gap-1.5">
                    <span class="break-words text-sm font-bold text-slate-800 dark:text-slate-100">{{ type.label }}</span>
                    <UiChip :tone="type.required ? 'amber' : 'slate'">
                        {{ type.required ? copy.requiredDocument : copy.optionalDocument }}
                    </UiChip>
                    <UiChip :tone="statusChip.tone">{{ statusChip.label }}</UiChip>
                    <UiChip v-if="documents.length > 1" tone="slate">
                        {{ format(copy.documentsCount, { count: documents.length }) }}
                    </UiChip>
                    <UiChip v-if="startHint && !hasDocuments" tone="primary">{{ copy.uploadStartHere }}</UiChip>
                </span>

                <span class="mt-0.5 block break-words text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ type.hint }}</span>

                <!-- Folded away, the "Required" chip already says this; the line is
                     only worth the row height once the slot is open. -->
                <span
                    v-if="open"
                    class="mt-1 block text-[11px] font-semibold"
                    :class="type.required ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400'"
                >
                    {{ type.required ? copy.uploadRequiredHint : copy.uploadOptionalHint }}
                </span>
            </span>

            <span
                class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-lg text-slate-400 transition-transform duration-200 motion-reduce:transition-none"
                :class="open ? 'rotate-180' : ''"
            >
                <AppIcon name="chevron-down" />
            </span>
        </button>

        <div v-show="open" :id="panelId" class="px-3 pb-3 sm:px-4 sm:pb-4">
            <ul v-if="hasDocuments" class="space-y-2">
                <DocumentRow
                    v-for="document in documents"
                    :key="document.id"
                    :document="document"
                    :highlighted="document.id === highlightId"
                    :can-edit="canEdit"
                    :removing="document.id === removingId"
                    :show-type="false"
                    @remove="emit('request-remove', $event)"
                />
            </ul>

            <!-- The real form: hidden fields carry the type, so the picker has
                 nothing to configure — and a bounce still posts the file if JS
                 cannot. -->
            <form
                v-if="canEdit"
                ref="form"
                method="POST"
                :action="urls.upload"
                enctype="multipart/form-data"
                :class="hasDocuments ? 'mt-3' : ''"
                @submit.prevent
                @dragover.prevent="dragging = true"
                @dragenter.prevent="dragging = true"
                @dragleave.prevent="dragging = false"
                @drop.prevent="onDrop"
            >
                <input type="hidden" name="_token" :value="csrfToken">
                <input type="hidden" name="type" :value="type.value">

                <input
                    :id="`document-${type.value}`"
                    ref="input"
                    type="file"
                    name="document"
                    :accept="limits.accept"
                    class="sr-only"
                    @click="onInputClick"
                    @change="onPick"
                >

                <label
                    :for="`document-${type.value}`"
                    :class="[UPLOAD_ZONE, zoneClass, uploading ? 'cursor-not-allowed opacity-60' : 'cursor-pointer']"
                    :aria-disabled="uploading ? 'true' : 'false'"
                >
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 p-1.5 text-primary">
                        <AppIcon :name="hasDocuments ? 'plus' : 'upload'" />
                    </span>
                    <span class="text-xs font-bold text-slate-800 dark:text-slate-100">
                        {{ hasDocuments ? copy.uploadAddAnother : copy.fileChoose }}
                    </span>
                    <span class="text-[11px] text-slate-500 sm:hidden dark:text-slate-400">{{ copy.fileTapHint }}</span>
                    <span class="hidden text-[11px] text-slate-500 sm:inline dark:text-slate-400">{{ copy.fileDropHint }}</span>
                    <span class="text-[11px] text-slate-400">{{ copy.fileTypes }}</span>
                </label>
            </form>

            <div v-if="uploading" class="mt-2 min-w-0">
                <div class="flex items-center gap-2">
                    <img
                        v-if="state.previewUrl"
                        :src="state.previewUrl"
                        alt=""
                        class="h-9 w-9 shrink-0 rounded-lg border border-slate-200 object-cover dark:border-slate-700"
                    >

                    <div class="min-w-0 flex-1">
                        <p class="break-words text-xs font-semibold text-slate-800 dark:text-slate-100">{{ state.fileName }}</p>
                        <p class="break-words text-[11px] text-slate-500 dark:text-slate-400">
                            {{ formatBytes(state.fileSize) }} ·
                            <span role="status" aria-live="polite">{{ statusText }}</span>
                        </p>
                    </div>

                    <button type="button" :class="[GHOST_BUTTON, 'shrink-0 text-xs']" @click="cancelUpload(type.value)">
                        {{ copy.uploadCancel }}
                    </button>
                </div>

                <div
                    :class="PROGRESS_TRACK"
                    role="progressbar"
                    :aria-label="copy.uploadSlotUploading"
                    :aria-valuenow="state.progress"
                    :aria-valuetext="statusText"
                    aria-valuemin="0"
                    aria-valuemax="100"
                >
                    <div :class="PROGRESS_BAR" :style="{ width: `${state.progress}%` }"></div>
                </div>
            </div>

            <div v-else-if="failed || saved || cancelled" class="mt-2 flex flex-wrap items-center gap-2">
                <p class="min-w-0 flex-1 break-words text-xs font-semibold" :class="toneText" role="status" aria-live="polite">
                    {{ statusText }}
                </p>

                <button v-if="canRetry" type="button" :class="[SECONDARY_BUTTON, 'min-h-9 py-1.5 text-xs']" @click="retry">
                    {{ copy.uploadRetry }}
                </button>
            </div>

            <ul v-if="state.messages?.length" class="mt-2 space-y-1" role="alert">
                <li
                    v-for="(message, index) in state.messages"
                    :key="index"
                    class="break-words text-xs font-medium text-rose-600 dark:text-rose-400"
                >
                    {{ message }}
                </li>
            </ul>
        </div>
    </article>
</template>
