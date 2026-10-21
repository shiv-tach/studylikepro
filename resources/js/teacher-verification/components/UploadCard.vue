<script setup>
/**
 * Pick a file and send it. The picked file is checked here the moment it is
 * chosen — extension and size — and shown back with its real size and an image
 * thumbnail, so a wrong scan is caught before it is uploaded. The upload then
 * posts in the background and confirms on the spot; if that request cannot be
 * made the card falls back to a normal form submit, so nothing is ever lost.
 */
import { ref } from 'vue';
import { usePanel } from '../../shared/composables/usePanel';
import AppIcon from '../../shared/components/AppIcon.vue';
import UiChip from '../../shared/components/UiChip.vue';
import UiSectionHeading from '../../shared/components/UiSectionHeading.vue';
import { useCopy, useCsrfToken, useUrls } from '../composables/usePageContext';
import { formatBytes, useVerification } from '../composables/useVerification';
import { GHOST_BUTTON, PRIMARY_BUTTON } from '../styles';

const copy = useCopy();
const urls = useUrls();
const csrfToken = useCsrfToken();
const panel = usePanel();

const {
    documentTypes,
    documents,
    selectedType,
    pickedFile,
    fileError,
    previewUrl,
    isDragging,
    uploadResult,
    uploading,
    errors,
    limits,
    pickFile,
    clearFile,
    upload,
} = useVerification();

const form = ref(null);
const input = ref(null);

function isUploaded(value) {
    return documents.value.some((document) => document.type === value);
}

function onPick(event) {
    pickFile(event.target.files?.[0] ?? null);
}

function onDrop(event) {
    isDragging.value = false;
    pickFile(event.dataTransfer?.files?.[0] ?? null);
}

function clear() {
    if (input.value) {
        input.value.value = '';
    }

    clearFile();
}

async function onSubmit() {
    if (!pickedFile.value) {
        // Nothing picked yet: point at the picker instead of a silent no-op.
        input.value?.click();

        return;
    }

    const result = await upload();

    if (result === 'fallback') {
        form.value?.submit();
    }
}
</script>

<template>
    <section id="upload-documents" :class="panel" class="scroll-mt-24">
        <UiSectionHeading :title="copy.uploadTitle" :description="copy.uploadHint" icon="clipboard" level="h3" />

        <form
            ref="form"
            method="POST"
            :action="urls.upload"
            enctype="multipart/form-data"
            class="mt-5 space-y-5"
            @submit.prevent="onSubmit"
        >
            <input type="hidden" name="_token" :value="csrfToken">

            <fieldset>
                <legend class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ copy.uploadTypeLabel }}</legend>

                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    <label
                        v-for="type in documentTypes"
                        :key="type.value"
                        class="flex cursor-pointer items-start gap-2.5 rounded-xl border px-3 py-2.5 transition-all duration-200"
                        :class="selectedType === type.value
                            ? 'border-primary bg-primary/5 ring-1 ring-primary/30'
                            : 'border-slate-200/80 hover:border-primary/40 dark:border-slate-800 dark:hover:border-primary/30'"
                    >
                        <input
                            v-model="selectedType"
                            type="radio"
                            name="type"
                            :value="type.value"
                            class="mt-0.5 h-4 w-4 shrink-0 border-slate-300 text-primary focus:ring-primary dark:border-slate-600 dark:bg-slate-900"
                        >

                        <span class="min-w-0">
                            <span class="flex flex-wrap items-center gap-1.5">
                                <span class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ type.label }}</span>
                                <UiChip :tone="type.required ? 'amber' : 'slate'">
                                    {{ type.required ? copy.requiredDocument : copy.optionalDocument }}
                                </UiChip>
                                <UiChip v-if="isUploaded(type.value)" tone="emerald">{{ copy.uploadedDocument }}</UiChip>
                            </span>
                            <span class="mt-0.5 block text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ type.hint }}</span>
                        </span>
                    </label>
                </div>

                <ul v-if="errors.type?.length" class="mt-2 space-y-1" role="alert">
                    <li v-for="(message, index) in errors.type" :key="index" class="text-sm font-medium text-rose-600 dark:text-rose-400">
                        {{ message }}
                    </li>
                </ul>
            </fieldset>

            <div>
                <p class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ copy.uploadFileLabel }}</p>

                <div
                    class="mt-2 rounded-2xl border-2 border-dashed transition-colors duration-200"
                    :class="isDragging
                        ? 'border-primary bg-primary/5'
                        : 'border-slate-300 hover:border-primary/50 dark:border-slate-700 dark:hover:border-primary/40'"
                    @dragover.prevent="isDragging = true"
                    @dragenter.prevent="isDragging = true"
                    @dragleave.prevent="isDragging = false"
                    @drop.prevent="onDrop"
                >
                    <input
                        id="document"
                        ref="input"
                        type="file"
                        name="document"
                        :accept="limits.accept"
                        class="sr-only"
                        @change="onPick"
                    >

                    <label
                        v-if="!pickedFile"
                        for="document"
                        class="flex cursor-pointer flex-col items-center gap-2 px-6 py-8 text-center"
                    >
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 p-2.5 text-primary">
                            <AppIcon name="clipboard" />
                        </span>
                        <span class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ copy.fileChoose }}</span>
                        <span class="text-xs text-slate-500 dark:text-slate-400">{{ copy.fileDropHint }}</span>
                        <span class="text-xs text-slate-400">{{ copy.fileTypes }}</span>
                    </label>

                    <div v-else class="flex items-center gap-3 p-4">
                        <img
                            v-if="previewUrl"
                            :src="previewUrl"
                            alt=""
                            class="h-14 w-14 shrink-0 rounded-xl border border-slate-200 object-cover dark:border-slate-700"
                        >
                        <span v-else class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-primary/10 p-3.5 text-primary">
                            <AppIcon name="clipboard" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ pickedFile.name }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ formatBytes(pickedFile.size) }} · {{ copy.fileReady }}
                            </p>
                        </div>

                        <button type="button" :class="[GHOST_BUTTON, 'shrink-0 text-xs']" @click="clear">
                            {{ copy.fileChange }}
                        </button>
                    </div>
                </div>

                <p v-if="fileError" class="mt-2 flex items-start gap-1.5 text-sm font-medium text-rose-600 dark:text-rose-400" role="alert">
                    <span class="mt-0.5 h-4 w-4 shrink-0"><AppIcon name="alert" /></span>
                    {{ fileError }}
                </p>

                <ul v-if="errors.document?.length" class="mt-2 space-y-1" role="alert">
                    <li v-for="(message, index) in errors.document" :key="index" class="text-sm font-medium text-rose-600 dark:text-rose-400">
                        {{ message }}
                    </li>
                </ul>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200/80 pt-4 dark:border-slate-800/80">
                <p
                    class="text-xs font-semibold"
                    :class="uploadResult?.tone === 'rose' ? 'text-rose-600 dark:text-rose-400' : (uploadResult ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400')"
                    role="status"
                >
                    {{ uploadResult?.message ?? copy.uploadDraftNote }}
                </p>

                <button type="submit" :class="[PRIMARY_BUTTON, 'shrink-0']" :disabled="uploading">
                    <span v-if="uploading" class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white motion-reduce:animate-none"></span>
                    {{ uploading ? copy.uploadBusy : copy.uploadButton }}
                </button>
            </div>
        </form>
    </section>
</template>
