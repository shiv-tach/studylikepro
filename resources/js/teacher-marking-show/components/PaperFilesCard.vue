<script setup>
/**
 * The student's paper. Every row is a download link Blade built, so the console
 * never assembles a storage URL of its own.
 */
import AppIcon from '../../shared/components/AppIcon.vue';
import { usePanel } from '../../shared/composables/usePanel';
import { useCopy } from '../composables/usePageContext';

defineProps({
    files: { type: Array, default: () => [] },
});

const copy = useCopy();
const panel = usePanel();
</script>

<template>
    <section :class="panel" aria-labelledby="teacher-marking-paper-heading">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <h2 id="teacher-marking-paper-heading" class="flex items-center gap-2 text-sm font-bold text-slate-800 dark:text-slate-100">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary/10 p-1.5 text-primary" aria-hidden="true">
                    <AppIcon name="clipboard" />
                </span>
                {{ copy.files?.title }}
            </h2>
            <p class="text-xs text-slate-400">{{ copy.files?.intro }}</p>
        </div>

        <ul v-if="files.length" class="mt-4 space-y-2">
            <li
                v-for="(file, index) in files"
                :key="`${file.name}-${index}`"
                class="flex items-center justify-between gap-3 rounded-xl border border-slate-200/80 px-3 py-2 dark:border-slate-800"
            >
                <span class="min-w-0 truncate text-sm text-slate-600 dark:text-slate-300">
                    {{ file.name }}
                    <span v-if="file.sizeLabel" class="text-xs text-slate-400">· {{ file.sizeLabel }}</span>
                </span>

                <a
                    :href="file.url"
                    class="inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-primary hover:underline"
                >
                    <span class="h-3.5 w-3.5" aria-hidden="true"><AppIcon name="download" /></span>
                    {{ copy.files?.download }}
                </a>
            </li>
        </ul>

        <p v-else class="mt-4 text-xs text-slate-400">{{ copy.files?.empty }}</p>
    </section>
</template>
