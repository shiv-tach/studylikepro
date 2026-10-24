<script setup>
/**
 * What the teacher already delivered: the feedback, the mark rows and the marked
 * copy. Read-only — a returned delivery is the student's to accept or dispute.
 */
import AppIcon from '../../shared/components/AppIcon.vue';
import { usePanel } from '../../shared/composables/usePanel';
import { useCopy } from '../composables/usePageContext';

defineProps({
    delivery: { type: Object, required: true },
});

const copy = useCopy();
const panel = usePanel();
</script>

<template>
    <section :class="panel" aria-labelledby="teacher-marking-delivered-heading">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <h2 id="teacher-marking-delivered-heading" class="flex items-center gap-2 text-sm font-bold text-slate-800 dark:text-slate-100">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-100 p-1.5 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400" aria-hidden="true">
                    <AppIcon name="check-circle" />
                </span>
                {{ copy.delivery?.title }}
            </h2>

            <p v-if="delivery.returnedLabel" class="text-xs text-slate-400">{{ delivery.returnedLabel }}</p>
        </div>

        <div v-if="delivery.feedback" class="mt-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ copy.delivery?.feedback }}</p>
            <div class="mt-2 whitespace-pre-line rounded-xl bg-slate-50 p-4 text-sm leading-relaxed text-slate-600 dark:bg-slate-900/60 dark:text-slate-300">
                {{ delivery.feedback }}
            </div>
        </div>

        <div v-if="delivery.marks.length" class="mt-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ copy.delivery?.marks }}</p>

            <dl class="mt-2 divide-y divide-slate-200/80 text-sm dark:divide-slate-800">
                <div
                    v-for="(mark, index) in delivery.marks"
                    :key="`${mark.label}-${index}`"
                    class="flex items-center justify-between gap-3 py-2"
                >
                    <dt class="min-w-0 truncate text-slate-500 dark:text-slate-400">{{ mark.label }}</dt>
                    <dd class="shrink-0 font-semibold text-slate-700 dark:text-slate-200">{{ mark.value }}</dd>
                </div>
            </dl>
        </div>

        <div v-if="delivery.files.length" class="mt-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ copy.delivery?.files }}</p>

            <ul class="mt-2 space-y-2">
                <li
                    v-for="(file, index) in delivery.files"
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
        </div>
    </section>
</template>
