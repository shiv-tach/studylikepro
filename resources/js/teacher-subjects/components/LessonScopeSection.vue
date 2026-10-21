<script setup>
/**
 * Step 2: the lessons per grade. Only the grades ticked in step 1 can be
 * narrowed here — the server trims any lesson outside the selected grades on
 * save, so a disabled checkbox is also a safe one.
 */
import { computed } from 'vue';
import { useCopy, useWorkspace } from '../composables/usePageContext';
import SetupSectionHeading from './SetupSectionHeading.vue';
import { GHOST_BUTTON } from '../styles';

const props = defineProps({
    subject: { type: Object, required: true },
});

const workspace = useWorkspace();
const copy = useCopy();

const totalLessons = computed(
    () => props.subject.grades.reduce((total, grade) => total + grade.lessons.length, 0),
);

const gradesWithLessons = computed(
    () => props.subject.grades.filter((grade) => grade.lessons.length > 0),
);

function selectedCount(grade) {
    return grade.lessons.filter((lesson) => lesson.selected).length;
}
</script>

<template>
    <section aria-labelledby="subject-lessons-heading">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <SetupSectionHeading
                id="subject-lessons-heading"
                :step="copy.lessons?.step"
                :title="copy.lessons?.title"
                :hint="copy.lessons?.hint"
            />

            <div v-if="totalLessons" class="flex items-center gap-1">
                <button type="button" :class="GHOST_BUTTON" @click="workspace.selectAllLessons(subject)">
                    {{ copy.lessons?.selectAll }}
                </button>
                <button type="button" :class="GHOST_BUTTON" @click="workspace.clearLessons(subject)">
                    {{ copy.lessons?.clear }}
                </button>
            </div>
        </div>

        <p v-if="!totalLessons" class="rounded-xl border border-dashed border-slate-300 p-4 text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
            {{ copy.lessons?.noneForSubject }}
        </p>

        <div v-else class="space-y-3">
            <div
                v-for="grade in gradesWithLessons"
                :key="grade.id"
                class="overflow-hidden rounded-xl border transition-colors"
                :class="grade.selected
                    ? 'border-slate-200/80 dark:border-slate-800/80'
                    : 'border-slate-200/60 opacity-60 dark:border-slate-800/60'"
            >
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200/70 bg-slate-50/60 px-3 py-2 dark:border-slate-800/70 dark:bg-slate-800/30">
                    <p class="flex items-center gap-2 text-xs font-bold text-slate-700 dark:text-slate-200">
                        {{ grade.label }}
                        <span class="rounded-full bg-white px-2 py-0.5 text-[10px] font-semibold text-slate-500 dark:bg-slate-900 dark:text-slate-400">
                            {{ selectedCount(grade) }}/{{ grade.lessons.length }}
                        </span>
                    </p>

                    <div class="flex items-center gap-2">
                        <span v-if="!grade.selected" class="text-[11px] font-medium text-amber-600 dark:text-amber-400">
                            {{ copy.lessons?.gradeDisabled }}
                        </span>
                        <button
                            type="button"
                            class="rounded-lg px-2 py-1 text-[11px] font-semibold text-slate-500 transition-colors hover:bg-white hover:text-slate-800 disabled:cursor-not-allowed disabled:opacity-40 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100"
                            :disabled="!grade.selected"
                            @click="workspace.setGradeLessons(grade, true)"
                        >
                            {{ copy.lessons?.selectAll }}
                        </button>
                        <button
                            type="button"
                            class="rounded-lg px-2 py-1 text-[11px] font-semibold text-slate-500 transition-colors hover:bg-white hover:text-slate-800 disabled:cursor-not-allowed disabled:opacity-40 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100"
                            :disabled="!grade.selected"
                            @click="workspace.setGradeLessons(grade, false)"
                        >
                            {{ copy.lessons?.clear }}
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 p-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                    <label
                        v-for="lesson in grade.lessons"
                        :key="lesson.id"
                        class="flex cursor-pointer items-center gap-2 rounded-lg border px-2.5 py-2 text-sm transition-colors focus-within:ring-2 focus-within:ring-primary/40"
                        :class="lesson.selected && grade.selected
                            ? 'border-primary/40 bg-primary/5 text-slate-800 dark:bg-primary/10 dark:text-slate-100'
                            : 'border-slate-200/70 text-slate-600 hover:border-primary/30 dark:border-slate-800 dark:text-slate-300'"
                    >
                        <input
                            type="checkbox"
                            name="lessons[]"
                            :value="lesson.id"
                            v-model="lesson.selected"
                            :disabled="!grade.selected"
                            class="shrink-0 rounded border-slate-300 text-primary focus:ring-primary disabled:cursor-not-allowed dark:border-slate-700 dark:bg-slate-900"
                        >
                        <span class="min-w-0 truncate">{{ lesson.name }}</span>
                    </label>
                </div>
            </div>
        </div>
    </section>
</template>
