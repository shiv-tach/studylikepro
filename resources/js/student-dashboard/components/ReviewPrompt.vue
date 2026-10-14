<script setup>
import { usePanel } from '../../shared/composables/usePanel';
import AppIcon from '../../shared/components/AppIcon.vue';

const props = defineProps({
    lesson: { type: Object, required: true },
});

const panel = usePanel(true);
</script>

<template>
    <section :class="[panel, 'flex flex-wrap items-center justify-between gap-4']" aria-labelledby="dashboard-review">
        <div class="flex items-start gap-3">
            <span
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 p-2.5 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400"
            >
                <AppIcon name="star" />
            </span>
            <div>
                <h2 id="dashboard-review" class="text-sm font-bold text-slate-800 dark:text-slate-100">
                    How was your lesson<span v-if="lesson.teacher"> with {{ lesson.teacher }}</span>?
                </h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    {{ lesson.title }} · {{ lesson.startsAt }} — rate it so other students know what to expect.
                </p>
            </div>
        </div>

        <a
            :href="lesson.reviewUrl"
            class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/20 transition-transform hover:-translate-y-0.5"
        >
            Rate this lesson
        </a>
    </section>
</template>
