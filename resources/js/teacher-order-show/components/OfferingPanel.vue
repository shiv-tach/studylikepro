<script setup>
/**
 * The catalog entry this order was bought from, so the teacher can check what
 * they promised (scope, session length, limits) without leaving the order, and
 * jump to the console to change it for future buyers.
 */
import { usePanel } from '../../shared/composables/usePanel';
import { HEADING_TEXT, MUTED_TEXT } from '../../shared/styles';
import AppIcon from '../../shared/components/AppIcon.vue';
import { useCopy } from '../composables/usePageContext';

defineProps({
    offering: { type: Object, required: true },
});

const copy = useCopy();
const panel = usePanel();
</script>

<template>
    <section :class="panel" aria-labelledby="teacher-order-offering-heading">
        <h2 id="teacher-order-offering-heading" :class="['text-sm font-bold', HEADING_TEXT]">
            {{ copy.panels?.offering }}
        </h2>

        <p class="mt-3 text-[11px] font-bold uppercase tracking-wider text-primary">{{ offering.typeLabel }}</p>
        <p :class="['mt-0.5 text-sm font-bold', HEADING_TEXT]">{{ offering.title }}</p>
        <p v-if="offering.description" :class="['mt-1 text-xs leading-relaxed', MUTED_TEXT]">
            {{ offering.description }}
        </p>

        <dl class="mt-4 space-y-2 text-xs">
            <div v-if="offering.gradeLabel" class="flex items-center justify-between gap-3">
                <dt class="text-slate-400">{{ copy.offering?.grade }}</dt>
                <dd class="font-semibold text-slate-600 dark:text-slate-300">{{ offering.gradeLabel }}</dd>
            </div>
            <div v-if="offering.subjectLabel" class="flex items-center justify-between gap-3">
                <dt class="text-slate-400">{{ copy.offering?.subject }}</dt>
                <dd class="font-semibold text-slate-600 dark:text-slate-300">{{ offering.subjectLabel }}</dd>
            </div>
            <div v-if="offering.lessonLabel" class="flex items-center justify-between gap-3">
                <dt class="text-slate-400">{{ copy.offering?.lesson }}</dt>
                <dd class="text-right font-semibold text-slate-600 dark:text-slate-300">{{ offering.lessonLabel }}</dd>
            </div>
        </dl>

        <ul v-if="offering.chips.length" class="mt-4 flex flex-wrap gap-1.5">
            <li
                v-for="chip in offering.chips"
                :key="chip"
                class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300"
            >
                {{ chip }}
            </li>
        </ul>

        <a
            :href="offering.manageUrl"
            class="mt-4 inline-flex items-center gap-1 text-xs font-semibold text-slate-500 transition-colors hover:text-primary dark:text-slate-400"
        >
            <span class="h-3.5 w-3.5" aria-hidden="true"><AppIcon name="arrow-right" /></span>
            {{ copy.offering?.manage }}
        </a>
    </section>
</template>
