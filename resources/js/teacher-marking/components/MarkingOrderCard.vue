<script setup>
/**
 * One paper in the inbox: what it is, who sent it, what it is worth and how long
 * the teacher has left. Blade has already decided the status tone, the action
 * wording and every formatted label, so this card only arranges them.
 */
import { usePanel } from '../../shared/composables/usePanel';
import AppIcon from '../../shared/components/AppIcon.vue';
import UiChip from '../../shared/components/UiChip.vue';
import { useCopy } from '../composables/usePageContext';

defineProps({
    order: { type: Object, required: true },
});

const copy = useCopy();
const panel = usePanel(true);
</script>

<template>
    <article :class="panel">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-2.5 py-0.5 text-[11px] font-semibold text-primary">
                        <span aria-hidden="true">{{ order.subjectIcon }}</span>
                        {{ order.subjectName }}
                    </span>

                    <UiChip :tone="order.status.tone" :icon="order.status.icon">
                        {{ order.status.label }}
                    </UiChip>

                    <UiChip v-if="order.overdue" tone="rose" icon="alert">
                        {{ copy.card?.overdue }}
                    </UiChip>
                </div>

                <div class="mt-3 flex items-center gap-2">
                    <span
                        aria-hidden="true"
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-300"
                    >
                        {{ order.studentInitials }}
                    </span>
                    <p class="min-w-0 truncate text-sm font-bold text-slate-800 dark:text-slate-100">
                        {{ order.studentName }}
                    </p>
                </div>

                <p class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                    <span class="font-semibold text-slate-400 dark:text-slate-500">{{ order.reference }}</span>
                    <span v-if="order.pagesLabel">· {{ order.pagesLabel }}</span>
                    <span>· {{ order.priceLabel }}</span>
                    <span v-if="order.submittedLabel">· {{ order.submittedLabel }}</span>
                </p>
            </div>

            <p
                v-if="order.deadlineLabel"
                class="shrink-0 text-right text-xs font-semibold"
                :class="order.overdue ? 'text-rose-600 dark:text-rose-400' : 'text-slate-400'"
            >
                {{ order.deadlineLabel }}
            </p>
        </div>

        <div class="mt-4 flex items-center justify-end border-t border-slate-200/80 pt-4 dark:border-slate-800">
            <a
                :href="order.url"
                class="inline-flex items-center gap-1.5 rounded-xl bg-primary px-3.5 py-2 text-xs font-bold text-white shadow-sm shadow-primary/20 transition-colors hover:bg-primary/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
            >
                {{ order.actionLabel }}
                <span class="h-4 w-4" aria-hidden="true"><AppIcon name="arrow-right" /></span>
            </a>
        </div>
    </article>
</template>
