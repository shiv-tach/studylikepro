<script setup>
/**
 * Why the list looks the way it does: every teacher below takes the grade from
 * the student's profile, so the panel names that grade and links to the profile
 * where it can be changed. Without a grade the finder would have nothing to
 * scope by, so it asks for the profile instead.
 */
import { usePanel } from '../../shared/composables/usePanel';
import AppIcon from '../../shared/components/AppIcon.vue';
import { useCopy, useUrls } from '../composables/usePageContext';

defineProps({
    grade: { type: Object, default: null },
});

const copy = useCopy();
const urls = useUrls();
const panel = usePanel();

// The amber notice is a warning surface, not a theme card, so it keeps its own
// translucent border and the dark-mode pair the Blade page used.
const noticeClass =
    'rounded-2xl border border-amber-200/80 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300';
</script>

<template>
    <section v-if="grade" :class="panel" aria-labelledby="finder-grade">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-start gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 p-3 text-primary">
                    <AppIcon name="shield-check" />
                </span>
                <div>
                    <h3 id="finder-grade" class="text-base font-bold text-slate-800 dark:text-slate-100">
                        {{ copy.gradeTitle }}
                    </h3>
                    <p class="mt-1 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                        {{ copy.gradeBody }}
                    </p>
                </div>
            </div>

            <a
                :href="urls.profile"
                class="inline-flex items-center gap-1 text-sm font-semibold text-primary transition-colors hover:text-primary/80 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
            >
                {{ copy.changeGrade }}
                <span class="h-4 w-4 transition-transform hover:translate-x-0.5"><AppIcon name="arrow-right" /></span>
            </a>
        </div>
    </section>

    <div v-else :class="noticeClass" role="status">
        {{ copy.noGrade }}
        <a :href="urls.profile" class="font-semibold underline">{{ copy.completeProfile }}</a>
    </div>
</template>
