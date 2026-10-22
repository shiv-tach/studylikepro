<script setup>
/**
 * Who bought the order. The grade is what a teacher usually needs before the
 * first session; the message button is the only way to reach the student from
 * this page, and it only appears once a session opened a thread.
 */
import { usePanel } from '../../shared/composables/usePanel';
import { HEADING_TEXT, MUTED_TEXT } from '../../shared/styles';
import AppIcon from '../../shared/components/AppIcon.vue';
import UiChip from '../../shared/components/UiChip.vue';
import { useCopy } from '../composables/usePageContext';

defineProps({
    student: { type: Object, required: true },
});

const copy = useCopy();
const panel = usePanel();
</script>

<template>
    <section :class="panel" aria-labelledby="teacher-order-student-heading">
        <h2 id="teacher-order-student-heading" :class="['text-sm font-bold', HEADING_TEXT]">
            {{ copy.panels?.student }}
        </h2>

        <div class="mt-4 flex items-center gap-3">
            <img
                v-if="student.avatarUrl"
                :src="student.avatarUrl"
                alt=""
                class="h-11 w-11 shrink-0 rounded-full object-cover"
            >
            <span
                v-else
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-bold text-primary"
                aria-hidden="true"
            >
                {{ student.initials }}
            </span>

            <div class="min-w-0">
                <p :class="['truncate text-sm font-bold', HEADING_TEXT]">{{ student.name }}</p>
                <p :class="['truncate text-xs', MUTED_TEXT]">{{ student.memberSince }}</p>
            </div>
        </div>

        <div v-if="student.gradeLabel" class="mt-3">
            <UiChip tone="primary" icon="graduation">{{ student.gradeLabel }}</UiChip>
        </div>

        <a
            v-if="student.messagesUrl"
            :href="student.messagesUrl"
            class="mt-4 inline-flex w-full items-center justify-center gap-1.5 rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
        >
            <span class="h-4 w-4" aria-hidden="true"><AppIcon name="chat" /></span>
            {{ copy.student?.message }}
        </a>
        <p v-else class="mt-4 text-xs leading-relaxed text-slate-400">{{ copy.student?.noThread }}</p>
    </section>
</template>
