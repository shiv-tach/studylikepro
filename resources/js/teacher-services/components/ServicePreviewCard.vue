<script setup>
/**
 * A live preview of the card a student will see while the teacher fills the
 * composer. It updates with the title, price, scope and delivery chips, so a
 * typo or a missing scope is obvious before the form is submitted.
 */
import AppIcon from '../../shared/components/AppIcon.vue';
import UiChip from '../../shared/components/UiChip.vue';
import { usePanel } from '../../shared/composables/usePanel';
import { useCopy, useWorkspace } from '../composables/usePageContext';
import { TYPE_TILE } from '../styles';

const copy = useCopy();
const workspace = useWorkspace();
const panel = usePanel();

const { preview } = workspace;
</script>

<template>
    <aside :class="[panel, 'space-y-3']">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">
                {{ copy.preview?.title }}
            </p>
            <p class="mt-1 text-[11px] leading-relaxed text-slate-400">{{ copy.preview?.hint }}</p>
        </div>

        <div class="rounded-xl border border-slate-200/80 bg-slate-50/60 p-4 dark:border-slate-800/80 dark:bg-slate-950/30">
            <div class="flex items-start gap-3">
                <span :class="TYPE_TILE" aria-hidden="true">
                    <AppIcon :name="preview.icon" />
                </span>

                <div class="min-w-0">
                    <p class="truncate text-sm font-bold text-slate-800 dark:text-slate-100">{{ preview.title }}</p>
                    <p v-if="preview.typeLabel" class="mt-0.5 text-[11px] font-medium text-slate-400">{{ preview.typeLabel }}</p>
                </div>
            </div>

            <div v-if="preview.gradeLabel || preview.subjectName || preview.lessonName" class="mt-3 flex flex-wrap gap-1.5">
                <UiChip v-if="preview.gradeLabel" icon="graduation">{{ preview.gradeLabel }}</UiChip>
                <UiChip v-if="preview.subjectName">{{ preview.subjectIcon }} {{ preview.subjectName }}</UiChip>
                <UiChip v-if="preview.lessonName" icon="book">{{ preview.lessonName }}</UiChip>
            </div>

            <p
                class="mt-3 text-xs leading-relaxed"
                :class="preview.hasDescription ? 'text-slate-500 dark:text-slate-400' : 'italic text-slate-400 dark:text-slate-500'"
            >
                {{ preview.description }}
            </p>

            <div v-if="preview.chips.length" class="mt-3 flex flex-wrap gap-1.5">
                <UiChip v-for="chip in preview.chips" :key="chip" tone="slate">{{ chip }}</UiChip>
            </div>

            <div v-if="preview.priceLabel" class="mt-4 flex items-baseline justify-between gap-3 border-t border-slate-200/70 pt-3 dark:border-slate-800/70">
                <span class="text-lg font-extrabold text-slate-800 dark:text-slate-100">{{ preview.priceLabel }}</span>
                <span class="text-[11px] text-slate-400">{{ preview.priceSuffix }}</span>
            </div>
        </div>

        <p v-if="!preview.hasScope" class="flex items-start gap-1.5 text-[11px] leading-relaxed text-amber-600 dark:text-amber-400">
            <span class="mt-0.5 h-3.5 w-3.5 shrink-0"><AppIcon name="alert" /></span>
            {{ copy.preview?.emptyScope }}
        </p>
    </aside>
</template>
