<script setup>
/**
 * One ledger row on wide screens. The item is a link to the lesson or the order
 * it came from, so the ledger is an index rather than a dead table; the status
 * chip carries both a tone and a glyph, and a payout reference sits under it so
 * a paid row is traceable to its batch.
 */
import AppIcon from '../../shared/components/AppIcon.vue';
import UiChip from '../../shared/components/UiChip.vue';
import { useCopy } from '../composables/usePageContext';

defineProps({
    row: { type: Object, required: true },
});

const copy = useCopy();
</script>

<template>
    <tr>
        <td class="py-4 pr-4">
            <div class="flex items-start gap-3">
                <span
                    class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 p-2 text-primary"
                    aria-hidden="true"
                >
                    <AppIcon :name="row.typeIcon" />
                </span>

                <div class="min-w-0">
                    <a
                        v-if="row.url"
                        :href="row.url"
                        class="font-semibold text-slate-800 transition-colors hover:text-primary dark:text-slate-100"
                    >
                        {{ row.title }}
                    </a>
                    <p v-else class="font-semibold text-slate-800 dark:text-slate-100">{{ row.title }}</p>

                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                        {{ [row.subtitle, row.typeLabel].filter(Boolean).join(' · ') }}
                    </p>
                </div>
            </div>
        </td>

        <td class="px-4 py-4 text-slate-600 dark:text-slate-300">
            {{ row.dateLabel || '—' }}
        </td>

        <td class="px-4 py-4">
            <UiChip :tone="row.statusTone" :icon="row.statusIcon">{{ row.statusLabel }}</UiChip>
            <span v-if="row.payoutReference" class="mt-1 block font-mono text-[11px] text-slate-400">
                {{ row.payoutReference }}
            </span>
        </td>

        <td class="px-4 py-4 text-right font-semibold tabular-nums text-slate-800 dark:text-slate-100">
            {{ row.amountLabel }}
        </td>

        <td
            class="py-4 pl-4 text-right font-semibold tabular-nums"
            :class="row.reversedLabel ? 'text-rose-600 dark:text-rose-400' : 'text-slate-300 dark:text-slate-600'"
        >
            {{ row.reversedLabel || copy.ledger?.noReversal }}
        </td>
    </tr>
</template>
