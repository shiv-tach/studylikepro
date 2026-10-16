<script setup>
/**
 * The strip above the thread: which lesson this chat belongs to and the way out
 * to the booking, plus the "Report a problem" action — or the chip that says
 * support already has an open report and a second one would be refused.
 */
import AppIcon from '../../shared/components/AppIcon.vue';
import { useCopy } from '../composables/usePageContext';

defineProps({
    lesson: { type: Object, default: null },
    reportOpen: { type: Boolean, default: false },
});

const emit = defineEmits(['report']);

const copy = useCopy();
</script>

<template>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p v-if="lesson" class="text-xs text-slate-500 dark:text-slate-400">
            <span class="font-semibold text-slate-700 dark:text-slate-200">{{ lesson.title }}</span>
            · {{ lesson.schedule }} ·
            <a :href="lesson.url" class="font-semibold text-primary hover:underline">{{ copy.openLesson }}</a>
        </p>
        <span v-else class="text-xs text-slate-400">{{ copy.lessonFallback }}</span>

        <span
            v-if="reportOpen"
            class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-400"
            role="status"
        >
            <span class="h-3.5 w-3.5"><AppIcon name="shield-check" /></span>
            {{ copy.reportOpen }}
        </span>

        <button
            v-else
            type="button"
            class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-600 transition-colors hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
            @click="emit('report')"
        >
            <span class="h-3.5 w-3.5"><AppIcon name="alert" /></span>
            {{ copy.reportCta }}
        </button>
    </div>
</template>
