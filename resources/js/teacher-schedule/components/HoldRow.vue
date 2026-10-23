<script setup>
/**
 * A slot a student is still paying for. It is not a lesson yet, so it carries a
 * countdown instead of start/complete actions: when it runs out the slot is
 * released automatically.
 */
import AppIcon from '../../shared/components/AppIcon.vue';
import CountdownChip from '../../shared/components/CountdownChip.vue';
import UiChip from '../../shared/components/UiChip.vue';
import { usePanel } from '../../shared/composables/usePanel';
import { CHIP, CHIP_TONES } from '../../shared/styles';
import { useCopy } from '../composables/usePageContext';

defineProps({
    hold: { type: Object, required: true },
});

const copy = useCopy();
const panel = usePanel();
</script>

<template>
    <li :class="[panel, 'flex flex-col gap-4 lg:flex-row lg:items-center lg:gap-6']">
        <div class="flex shrink-0 items-baseline gap-2 lg:w-24 lg:flex-col lg:items-start lg:gap-0">
            <span class="text-sm font-bold tabular-nums text-slate-800 dark:text-slate-100">{{ hold.timeLabel }}</span>
            <span class="text-xs text-slate-400">– {{ hold.endTimeLabel }}</span>
        </div>

        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <UiChip :tone="hold.statusTone">{{ hold.statusLabel }}</UiChip>

                <span v-if="hold.learnerGrade" :class="[CHIP, CHIP_TONES.slate]">{{ hold.learnerGrade }}</span>

                <span :class="[CHIP, CHIP_TONES.slate]">{{ hold.durationLabel }}</span>

                <CountdownChip
                    v-if="hold.expiresAt"
                    :deadline="hold.expiresAt"
                    :prefix="copy.holds?.payWithin"
                    :zero-label="copy.holds?.expired"
                />
            </div>

            <p class="mt-2 flex flex-wrap items-baseline gap-x-2 gap-y-1">
                <a
                    :href="hold.url"
                    class="truncate text-sm font-bold text-slate-800 transition-colors hover:text-primary dark:text-slate-100"
                >
                    {{ hold.learnerName }}
                </a>
                <span class="truncate text-xs text-slate-400">{{ hold.title }}</span>
            </p>
        </div>

        <div class="flex shrink-0 items-center gap-2 lg:flex-col lg:items-end">
            <p class="text-xs font-medium text-slate-400">
                {{ copy.agenda?.payout }} {{ hold.payoutLabel }}
            </p>

            <a
                :href="hold.url"
                class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200/80 px-3 py-1.5 text-xs font-semibold text-slate-600 transition-colors hover:border-primary/40 hover:text-primary dark:border-slate-800/80 dark:text-slate-300"
            >
                <span class="h-3.5 w-3.5" aria-hidden="true"><AppIcon name="arrow-right" /></span>
                {{ copy.actions?.open }}
            </a>
        </div>
    </li>
</template>
