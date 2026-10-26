<script setup>
/**
 * The subjects this teacher takes at the student's grade: each subject with the
 * lessons that apply and the rate that grade pays. Both come from Blade already
 * scoped, so a lesson from another grade can never leak onto the page.
 */
import { usePanel } from '../../shared/composables/usePanel';
import UiChip from '../../shared/components/UiChip.vue';
import UiEmptyState from '../../shared/components/UiEmptyState.vue';
import UiSectionHeading from '../../shared/components/UiSectionHeading.vue';
import { useCopy } from '../composables/usePageContext';

defineProps({
    subjects: { type: Array, default: () => [] },
    grade: { type: Object, default: null },
});

const copy = useCopy();
const panel = usePanel();
</script>

<template>
    <section :class="panel">
        <UiSectionHeading :title="copy.subjectsTitle" icon="book" />

        <UiEmptyState v-if="!subjects.length" class="mt-4" icon="book" :message="copy.subjectsEmpty" />

        <div v-else class="mt-4 grid gap-4 xl:grid-cols-2">
            <div
                v-for="subject in subjects"
                :key="subject.id"
                class="rounded-xl border border-slate-200/80 p-4 dark:border-slate-800"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p class="text-sm font-bold text-slate-800 dark:text-slate-100">
                            {{ subject.icon }} {{ subject.name }}
                        </p>
                        <p v-if="subject.level" class="text-xs text-slate-400">{{ subject.level }}</p>
                    </div>
                    <p class="text-sm font-semibold text-primary">
                        {{ subject.rate }}<span class="text-xs font-medium text-slate-400">{{ copy.perHourShort }}</span>
                    </p>
                </div>

                <div v-if="grade" class="mt-2 flex flex-wrap gap-1.5">
                    <UiChip>{{ grade.label }}</UiChip>
                </div>

                <div v-if="subject.lessons.length" class="mt-3 flex flex-wrap gap-1.5">
                    <UiChip v-for="lesson in subject.lessons" :key="lesson" tone="primary">{{ lesson }}</UiChip>
                </div>
            </div>
        </div>
    </section>
</template>
