<script setup>
/**
 * The subject page masthead: the subject's own emoji, its description and the
 * chips that scope the page to a basket and a grade.
 */
import UiChip from '../../shared/components/UiChip.vue';
import { useCopy } from '../composables/usePageContext';

defineProps({
    subject: { type: Object, required: true },
});

const copy = useCopy();
</script>

<template>
    <div class="flex flex-wrap items-start justify-between gap-6">
        <div class="flex items-start gap-4">
            <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-3xl">
                {{ subject.icon }}
            </span>

            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-slate-800 dark:text-slate-100 sm:text-3xl">
                    {{ subject.name }}
                </h1>

                <p v-if="subject.description" class="mt-1.5 max-w-2xl text-slate-600 dark:text-slate-400">
                    {{ subject.description }}
                </p>

                <div v-if="subject.basket || subject.grade" class="mt-3 flex flex-wrap items-center gap-2">
                    <UiChip v-if="subject.basket" tone="amber">
                        {{ subject.basket.label }} · {{ copy.pickOne }}
                    </UiChip>

                    <UiChip v-if="subject.grade" tone="primary" icon="graduation">
                        {{ subject.grade.chip }}
                    </UiChip>

                    <a
                        v-if="subject.grade"
                        :href="subject.grade.allGradesUrl"
                        class="text-xs font-semibold text-slate-400 transition-colors hover:text-primary"
                    >
                        {{ copy.viewAllGrades }}
                    </a>
                </div>
            </div>
        </div>

        <UiChip>{{ subject.lessonLabel }}</UiChip>
    </div>
</template>
