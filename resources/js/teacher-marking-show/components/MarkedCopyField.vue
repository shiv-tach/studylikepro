<script setup>
/**
 * The marked-copy chooser. It owns a real `<input type="file" multiple>` (so the
 * browser submits the files and the server validates them), and layers a
 * dropzone and a picked-file list over it. Sizes are formatted here because the
 * files never left the browser — every server-side label still comes from Blade.
 */
import { computed, ref } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';
import { useCopy } from '../composables/usePageContext';

const props = defineProps({
    maxFiles: { type: Number, default: 10 },
    accept: { type: String, default: '' },
    errors: { type: Array, default: () => [] },
});

const copy = useCopy();
const input = ref(null);
const picked = ref([]);
const dragging = ref(false);

// Assigning `input.files` needs a DataTransfer, which every evergreen browser
// has; without it a removal cannot reach the real input, so the button hides.
const canRebuild = (() => {
    try {
        return typeof DataTransfer !== 'undefined' && Boolean(new DataTransfer());
    } catch (error) {
        return false;
    }
})();

const tooMany = computed(() => picked.value.length > props.maxFiles);

function sizeLabel(bytes) {
    if (!bytes) {
        return '';
    }

    if (bytes >= 1048576) {
        return `${(bytes / 1048576).toFixed(1)} MB`;
    }

    return `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

function rebuild(files) {
    picked.value = files;

    if (!input.value) {
        return;
    }

    const transfer = new DataTransfer();

    files.forEach((file) => transfer.items.add(file));

    input.value.files = transfer.files;
}

function onPick(event) {
    picked.value = Array.from(event.target.files ?? []);
}

function onDrop(event) {
    dragging.value = false;

    const dropped = Array.from(event.dataTransfer?.files ?? []);

    if (dropped.length === 0 || !canRebuild) {
        return;
    }

    rebuild(dropped);
}

function remove(index) {
    rebuild(picked.value.filter((_, position) => position !== index));
}
</script>

<template>
    <div>
        <p class="text-sm font-medium text-slate-700 dark:text-slate-300">
            {{ copy.form?.filesLabel }}
            <span class="text-rose-500" aria-hidden="true">*</span>
        </p>

        <div
            class="mt-2 rounded-2xl border-2 border-dashed transition-colors duration-200"
            :class="dragging
                ? 'border-primary bg-primary/5'
                : 'border-slate-300 hover:border-primary/50 dark:border-slate-700 dark:hover:border-primary/40'"
            @dragover.prevent="dragging = true"
            @dragenter.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
        >
            <input
                id="marked-files"
                ref="input"
                type="file"
                name="marked_files[]"
                multiple
                required
                :accept="accept"
                class="sr-only"
                @change="onPick"
            >

            <label
                v-if="picked.length === 0"
                for="marked-files"
                class="flex cursor-pointer flex-col items-center gap-2 px-6 py-8 text-center"
            >
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 p-2.5 text-primary" aria-hidden="true">
                    <AppIcon name="upload" />
                </span>
                <span class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ copy.form?.fileChoose }}</span>
                <span class="text-xs text-slate-500 dark:text-slate-400">{{ copy.form?.fileDropHint }}</span>
                <span class="text-xs text-slate-400">{{ copy.form?.fileTypes }}</span>
            </label>

            <div v-else class="p-3">
                <ul class="space-y-1.5">
                    <li
                        v-for="(file, index) in picked"
                        :key="`${file.name}-${file.lastModified}-${index}`"
                        class="flex items-center gap-2 rounded-xl border border-slate-200/80 px-3 py-2 dark:border-slate-800"
                    >
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-primary/10 p-1.5 text-primary" aria-hidden="true">
                            <AppIcon name="clipboard" />
                        </span>

                        <span class="min-w-0 flex-1 truncate text-sm text-slate-600 dark:text-slate-300">
                            {{ file.name }}
                            <span class="text-xs text-slate-400">· {{ sizeLabel(file.size) }}</span>
                        </span>

                        <button
                            v-if="canRebuild"
                            type="button"
                            class="shrink-0 rounded-lg p-1 text-slate-400 transition-colors hover:text-rose-500"
                            :aria-label="`${copy.form?.fileRemove} ${file.name}`"
                            @click="remove(index)"
                        >
                            <span class="block h-4 w-4"><AppIcon name="close" /></span>
                        </button>
                    </li>
                </ul>

                <label
                    for="marked-files"
                    class="mt-2 inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold text-primary transition-colors hover:border-primary/40 dark:border-slate-700"
                >
                    <span class="h-3.5 w-3.5" aria-hidden="true"><AppIcon name="plus" /></span>
                    {{ copy.form?.fileChoose }}
                </label>

                <p class="mt-2 text-xs text-slate-400">
                    {{ picked.length }} / {{ maxFiles }}
                </p>
            </div>
        </div>

        <p class="mt-2 text-xs text-slate-400">{{ copy.form?.filesHint }}</p>

        <p v-if="tooMany" class="mt-2 text-xs font-semibold text-amber-600 dark:text-amber-400">
            {{ copy.form?.tooManyFiles }}
        </p>

        <ul v-if="errors.length" class="mt-2 space-y-1" role="alert">
            <li
                v-for="(message, index) in errors"
                :key="index"
                class="text-sm font-medium text-rose-600 dark:text-rose-400"
            >
                {{ message }}
            </li>
        </ul>
    </div>
</template>
