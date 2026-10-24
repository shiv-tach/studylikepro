<script setup>
/**
 * The same ledger row as a stacked card, for screens too narrow to compare
 * five columns. Nothing is dropped: the date, the amount, the reversal and the
 * payout reference all move into a definition list under the title.
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
    <li class="rounded-2xl border border-slate-200/80 bg-white p-4 transition-colors dark:border-slate-800/80 dark:bg-slate-900">
        <div class="flex items-start justify-between gap-3">
            <div class="flex min-w-0 items-start gap-3">
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
                        class="block truncate text-sm font-bold text-slate-800 hover:text-primary dark:text-slate-100"
                    >
                        {{ row.title }}
                    </a>
                    <p v-else class="truncate text-sm font-bold text-slate-800 dark:text-slate-100">{{ row.title }}</p>

                    <p class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">
                        {{ [row.subtitle, row.typeLabel].filter(Boolean).join(' · ') }}
                    </p>
                </div>
            </div>

            <UiChip :tone="row.statusTone" :icon="row.statusIcon">{{ row.statusLabel }}</UiChip>
        </div>

        <dl class="mt-3 grid grid-cols-2 gap-2 border-t border-slate-100 pt-3 text-xs dark:border-slate-800/60">
            <div>
                <dt class="text-slate-400">{{ copy.ledger?.headers?.when }}</dt>
                <dd class="mt-0.5 font-semibold text-slate-700 dark:text-slate-200">{{ row.dateLabel || '—' }}</dd>
            </div>

            <div class="text-right">
                <dt class="text-slate-400">{{ copy.ledger?.headers?.earned }}</dt>
                <dd class="mt-0.5 font-extrabold tabular-nums text-slate-800 dark:text-slate-100">{{ row.amountLabel }}</dd>
            </div>

            <div v-if="row.reversedLabel">
                <dt class="text-slate-400">{{ copy.ledger?.headers?.reversed }}</dt>
                <dd class="mt-0.5 font-semibold tabular-nums text-rose-600 dark:text-rose-400">{{ row.reversedLabel }}</dd>
            </div>

            <div v-if="row.payoutReference" class="text-right">
                <dt class="text-slate-400">{{ copy.ledger?.payout }}</dt>
                <dd class="mt-0.5 font-mono text-slate-600 dark:text-slate-300">{{ row.payoutReference }}</dd>
            </div>
        </dl>
    </li>
</template>
