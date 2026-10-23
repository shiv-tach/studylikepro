<script setup>
/**
 * The answer to "what is next": the live lesson or the soonest one still to
 * come, with a ticking clock and the one action that matters (join, start or
 * close it). Everything but the countdown arrives formatted from Blade.
 */
import AppIcon from '../../shared/components/AppIcon.vue';
import UiChip from '../../shared/components/UiChip.vue';
import { usePanel } from '../../shared/composables/usePanel';
import { CHIP } from '../../shared/styles';
import { useCopy, usePage } from '../composables/usePageContext';
import CountdownChip from './CountdownChip.vue';
import InlineActionForm from './InlineActionForm.vue';

const props = defineProps({
    lesson: { type: Object, required: true },
});

const copy = useCopy();
const page = usePage();
const panel = usePanel();
</script>

<template>
    <section :class="[panel, 'relative overflow-hidden']" aria-labelledby="schedule-next-lesson">
        <div class="flex flex-wrap items-start justify-between gap-5">
            <div class="min-w-0 flex-1">
                <p class="text-[11px] font-bold uppercase tracking-wider text-primary">{{ lesson.eyebrow }}</p>

                <div class="mt-1.5 flex flex-wrap items-center gap-2">
                    <h2 id="schedule-next-lesson" class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-slate-100">
                        {{ lesson.learnerName }}
                    </h2>

                    <UiChip :tone="lesson.statusTone">{{ lesson.statusLabel }}</UiChip>

                    <span v-if="lesson.learnerGrade" :class="[CHIP, 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400']">
                        {{ lesson.learnerGrade }}
                    </span>

                    <span v-if="lesson.isLive" :class="[CHIP, 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300']">
                        <span class="h-3.5 w-3.5 shrink-0" aria-hidden="true"><AppIcon name="video" /></span>
                        {{ copy.next?.started }}
                    </span>
                </div>

                <p class="mt-2 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ lesson.title }}</p>

                <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="h-4 w-4 text-primary" aria-hidden="true"><AppIcon name="calendar" /></span>
                        {{ lesson.startsLabel }}–{{ lesson.endTimeLabel }}
                    </span>
                    <span class="text-slate-300 dark:text-slate-700" aria-hidden="true">·</span>
                    <span>{{ lesson.durationLabel }}</span>
                    <span class="text-slate-300 dark:text-slate-700" aria-hidden="true">·</span>
                    <span>{{ copy.agenda?.payout }} {{ lesson.payoutLabel }}</span>
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <CountdownChip
                    v-if="!lesson.isLive"
                    :deadline="lesson.startsAt"
                    :prefix="copy.next?.startsIn"
                    :zero-label="copy.next?.started"
                    tone="primary"
                />

                <a
                    v-if="lesson.canJoin"
                    :href="lesson.joinUrl"
                    class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-bold text-white shadow-sm shadow-primary/20 transition-transform hover:-translate-y-0.5"
                >
                    <span class="h-4 w-4" aria-hidden="true"><AppIcon name="video" /></span>
                    {{ copy.next?.join }}
                </a>

                <InlineActionForm
                    v-if="lesson.actions.canComplete"
                    :action="lesson.actions.complete"
                    :token="page.csrfToken"
                    :label="copy.actions?.complete"
                    tone="primary"
                    size="md"
                    :busy-label="copy.actions?.busy"
                />

                <InlineActionForm
                    v-else-if="lesson.actions.canStart"
                    :action="lesson.actions.start"
                    :token="page.csrfToken"
                    :label="copy.next?.start"
                    tone="primary"
                    size="md"
                    :busy-label="copy.actions?.busy"
                />

                <a
                    :href="lesson.url"
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-200/80 px-4 py-2.5 text-sm font-semibold text-slate-600 transition-colors hover:border-primary/40 hover:text-primary dark:border-slate-800/80 dark:text-slate-300"
                >
                    {{ copy.next?.open }}
                </a>
            </div>
        </div>
    </section>
</template>
