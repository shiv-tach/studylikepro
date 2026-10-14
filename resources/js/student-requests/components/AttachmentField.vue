<script setup>
/**
 * The optional photo field. The native file input stays the source of truth so
 * the browser still posts `attachments[]`; Vue adds the drop target, the
 * thumbnails and the client-side cap that keeps a submission under the limit
 * before it ever reaches the server.
 */
import { onBeforeUnmount, ref } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';
import FieldMessages from './FieldMessages.vue';
import FormPanel from './FormPanel.vue';
import { useCopy } from '../composables/usePageContext';

const props = defineProps({
    max: { type: Number, default: 3 },
    accept: { type: String, default: 'image/jpeg,image/png,image/webp' },
    errors: { type: Object, default: () => ({}) },
});

const copy = useCopy();

const input = ref(null);
const previews = ref([]);
const dragActive = ref(false);
const trimmed = ref(false);

// Trimming a selection means writing a new FileList back into the input, which
// only browsers that expose DataTransfer can do; everywhere else the server
// rules stay the only limit, so nothing is silently dropped.
const canEditSelection = typeof DataTransfer !== 'undefined';

function formatSize(bytes) {
    return bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

function revokeThumbnails() {
    previews.value.forEach((preview) => {
        if (preview.url) {
            URL.revokeObjectURL(preview.url);
        }
    });
}

function showPreviews(files) {
    revokeThumbnails();

    previews.value = files.map((file, index) => ({
        key: `${file.name}-${file.size}-${index}`,
        file,
        name: file.name,
        size: formatSize(file.size),
        url: file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
    }));
}

function writeSelection(files) {
    const transfer = new DataTransfer();

    files.forEach((file) => transfer.items.add(file));

    input.value.files = transfer.files;
}

function setSelection(files) {
    const selection = files.slice(0, props.max);

    trimmed.value = files.length > props.max;

    if (canEditSelection) {
        writeSelection(selection);
    }

    showPreviews(selection);
}

function onChange() {
    setSelection(Array.from(input.value?.files ?? []));
}

function removeAt(index) {
    const remaining = previews.value.filter((_, position) => position !== index).map((preview) => preview.file);

    trimmed.value = false;
    writeSelection(remaining);
    showPreviews(remaining);
}

function onDrop(event) {
    dragActive.value = false;

    if (!canEditSelection) {
        return;
    }

    const accepted = props.accept.split(',').map((type) => type.trim());
    const dropped = Array.from(event.dataTransfer?.files ?? []).filter((file) => accepted.includes(file.type));

    if (dropped.length) {
        setSelection([...previews.value.map((preview) => preview.file), ...dropped]);
    }
}

onBeforeUnmount(revokeThumbnails);
</script>

<template>
    <FormPanel :title="copy.attachmentTitle" :badge="copy.optional" :description="copy.attachmentHint">
        <label
            class="mt-4 flex cursor-pointer flex-col items-center gap-1.5 rounded-xl border border-dashed px-4 py-6 text-center transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-primary/30"
            :class="dragActive
                ? 'border-primary/60 bg-primary/5'
                : 'border-slate-300 hover:border-primary/40 hover:bg-primary/5 dark:border-slate-700'"
            @dragover.prevent="dragActive = true"
            @dragleave.prevent="dragActive = false"
            @drop.prevent="onDrop"
        >
            <input
                ref="input"
                type="file"
                name="attachments[]"
                :accept="accept"
                multiple
                class="sr-only"
                @change="onChange"
            />

            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 p-2.5 text-primary">
                <AppIcon name="camera" />
            </span>
            <span class="text-sm font-semibold text-slate-600 dark:text-slate-300">{{ copy.attachmentChoose }}</span>
            <span class="text-xs text-slate-400">{{ copy.attachmentDrop }}</span>
        </label>

        <ul v-if="previews.length" class="mt-3 grid gap-3 sm:grid-cols-2">
            <li
                v-for="(preview, index) in previews"
                :key="preview.key"
                class="flex items-center gap-3 rounded-xl border border-slate-200/80 p-3 dark:border-slate-800"
            >
                <img
                    v-if="preview.url"
                    :src="preview.url"
                    :alt="preview.name"
                    class="h-12 w-12 shrink-0 rounded-lg object-cover"
                />
                <span v-else class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-slate-100 p-3 text-slate-400 dark:bg-slate-800">
                    <AppIcon name="camera" />
                </span>

                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-700 dark:text-slate-200">{{ preview.name }}</p>
                    <p class="text-xs text-slate-400">{{ preview.size }}</p>
                </div>

                <button
                    v-if="canEditSelection"
                    type="button"
                    :aria-label="`${copy.attachmentRemove}: ${preview.name}`"
                    class="ml-auto flex h-8 w-8 shrink-0 items-center justify-center rounded-xl text-slate-400 transition-colors hover:bg-rose-50 hover:text-rose-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-300 dark:hover:bg-rose-950/30"
                    @click="removeAt(index)"
                >
                    &times;
                </button>
            </li>
        </ul>

        <p v-else class="mt-3 text-xs text-slate-400">{{ copy.attachmentEmpty }}</p>

        <p v-if="trimmed" class="mt-3 text-xs font-semibold text-amber-600 dark:text-amber-400">
            {{ copy.attachmentLimit }}
        </p>

        <FieldMessages :messages="errors.attachments" />
        <FieldMessages
            v-for="index in max"
            :key="index"
            :messages="errors[`attachments.${index - 1}`]"
        />
    </FormPanel>
</template>
