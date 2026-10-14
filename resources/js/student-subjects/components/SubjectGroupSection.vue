<script setup>
/**
 * One interests block: the compulsory subjects, or the subjects a student
 * picked from a single O/L basket. `title` is null below the basket grades, so
 * those students get a flat grid with no heading at all.
 */
import { HEADING_TEXT, MUTED_TEXT } from '../../shared/styles';
import UiChip from '../../shared/components/UiChip.vue';
import SubjectCard from './SubjectCard.vue';

defineProps({
    group: { type: Object, required: true },
});
</script>

<template>
    <section class="space-y-4">
        <div v-if="group.title" class="flex flex-wrap items-end justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2.5">
                <span class="h-8 w-1.5 shrink-0 rounded-full bg-primary/70" aria-hidden="true"></span>
                <h2 :class="['text-lg font-bold tracking-tight', HEADING_TEXT]">{{ group.title }}</h2>
                <UiChip v-if="group.badge" :tone="group.badgeTone">{{ group.badge }}</UiChip>
            </div>

            <span v-if="group.subjectsLabel" class="text-xs font-semibold uppercase tracking-wider text-slate-400">
                {{ group.subjectsLabel }}
            </span>
        </div>

        <p v-if="group.description" :class="['max-w-3xl text-sm', MUTED_TEXT]">
            {{ group.description }}
        </p>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <SubjectCard v-for="subject in group.subjects" :key="subject.id" :subject="subject" />
        </div>
    </section>
</template>
