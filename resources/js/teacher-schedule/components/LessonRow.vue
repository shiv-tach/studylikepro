<script setup>
/**
 * One booked lesson in the agenda: when it runs, who it is with, how long it
 * lasts, what it pays, and the actions that are legal right now. Blade decides
 * which actions exist; this row never re-derives a permission.
 */
import AppIcon from '../../shared/components/AppIcon.vue';
import UiChip from '../../shared/components/UiChip.vue';
import { usePanel } from '../../shared/composables/usePanel';
import { CHIP, CHIP_TONES } from '../../shared/styles';
import { useCopy, usePage } from '../composables/usePageContext';
import InlineActionForm from './InlineActionForm.vue';

defineProps({
    lesson: { type: Object, required: true },
});

const copy = useCopy();
const page = usePage();
const panel = usePanel();
</script>

<template>
    <li :class="[panel, 'flex flex-col gap-4 lg:flex-row lg:items-center lg:gap-6']">
        <div class="flex shrink-0 items-baseline gap-2 lg:w-24 lg:flex-col lg:items-start lg:gap-0">
            <span class="text-sm font-bold tabular-nums text-slate-800 dark:text-slate-100">{{ lesson.timeLabel }}</span>
            <span class="text-xs text-slate-400">– {{ lesson.endTimeLabel }}</span>
        </div>

        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <UiChip :tone="lesson.statusTone">{{ lesson.statusLabel }}</UiChip>

                <span v-if="lesson.learnerGrade" :class="[CHIP, CHIP_TONES.slate]">{{ lesson.learnerGrade }}</span>

                <span :class="[CHIP, CHIP_TONES.slate]">{{ lesson.durationLabel }}</span>
            </div>

            <p class="mt-2 flex flex-wrap items-baseline gap-x-2 gap-y-1">
                <a
                    :href="lesson.url"
                    class="truncate text-sm font-bold text-slate-800 transition-colors hover:text-primary dark:text-slate-100"
                >
                    {{ lesson.learnerName }}
                </a>
                <span class="truncate text-xs text-slate-400">{{ lesson.title }}</span>
            </p>
        </div>

        <div class="flex shrink-0 flex-col gap-2 lg:items-end">
            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                <span class="mr-1 text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ copy.agenda?.payout }}</span>
                {{ lesson.payoutLabel }}
            </p>

            <div class="flex flex-wrap items-center gap-2">
                <a
                    v-if="lesson.canJoin"
                    :href="lesson.joinUrl"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-primary px-3 py-1.5 text-xs font-semibold text-white shadow-sm shadow-primary/20 transition-colors hover:bg-primary/90"
                >
                    <span class="h-3.5 w-3.5" aria-hidden="true"><AppIcon name="video" /></span>
                    {{ copy.actions?.join }}
                </a>

                <InlineActionForm
                    v-if="!lesson.canJoin && lesson.actions.canComplete"
                    :action="lesson.actions.complete"
                    :token="page.csrfToken"
                    :label="copy.actions?.complete"
                    :busy-label="copy.actions?.busy"
                />

                <InlineActionForm
                    v-else-if="!lesson.canJoin && lesson.actions.canStart"
                    :action="lesson.actions.start"
                    :token="page.csrfToken"
                    :label="copy.actions?.start"
                    :busy-label="copy.actions?.busy"
                />

                <InlineActionForm
                    v-if="!lesson.canJoin && lesson.actions.canNoShow"
                    :action="lesson.actions.noShow"
                    :token="page.csrfToken"
                    :label="copy.actions?.noShow"
                    tone="danger"
                    :confirm="true"
                    :confirm-title="copy.actions?.confirmNoShowTitle"
                    :confirm-body="copy.actions?.confirmNoShowBody"
                    :confirm-label="copy.actions?.confirm"
                    :cancel-label="copy.actions?.cancel"
                    :busy-label="copy.actions?.busy"
                />

                <a
                    :href="lesson.url"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200/80 px-3 py-1.5 text-xs font-semibold text-slate-600 transition-colors hover:border-primary/40 hover:text-primary dark:border-slate-800/80 dark:text-slate-300"
                >
                    {{ copy.actions?.open }}
                </a>
            </div>
        </div>
    </li>
</template>
