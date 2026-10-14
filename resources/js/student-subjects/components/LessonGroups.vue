<script setup>
/**
 * The lessons behind a subject, grouped by grade so a student sees exactly the
 * lessons their grade covers.
 */
import { CARD } from '../../shared/styles';
import { useCopy } from '../composables/usePageContext';
import UiEmptyState from '../../shared/components/UiEmptyState.vue';
import UiSectionHeading from '../../shared/components/UiSectionHeading.vue';

defineProps({
    groups: { type: Array, default: () => [] },
    grade: { type: Object, default: null },
});

const copy = useCopy();</script>

<template>
    <section>
        <UiSectionHeading :title="copy.lessonsTitle" icon="book" />

        <UiEmptyState
            v-if="groups.length === 0"
            class="mt-4"
            icon="book"
            :message="grade ? copy.lessonsEmptyForGrade : copy.lessonsEmpty"
            :link-label="grade ? copy.viewAllGrades : null"
            :link-href="grade ? grade.allGradesUrl : null"
        />

        <div v-else class="mt-4 space-y-6">
            <div v-for="group in groups" :key="group.label">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">
                    {{ group.label }} · {{ group.countLabel }}
                </h3>

                <div class="mt-2 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="lesson in group.items"
                        :key="lesson.id"
                        :class="[
                            CARD,
                            'flex items-center gap-3 px-4 py-3.5 hover:border-primary/40 dark:hover:border-primary/40',
                        ]"
                    >
                        <span class="h-2 w-2 shrink-0 rounded-full bg-primary"></span>
                        <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ lesson.name }}</span>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
