<script setup>
/**
 * The last few batches the platform transferred. Payouts are made manually, so
 * the panel explains that once and then just lists them: reference, how many
 * lessons the batch covered, when it was paid, and what state it is in.
 */
import { computed } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';
import UiChip from '../../shared/components/UiChip.vue';
import UiSectionHeading from '../../shared/components/UiSectionHeading.vue';
import { usePanel } from '../../shared/composables/usePanel';
import { useCopy, usePage } from '../composables/usePageContext';

const copy = useCopy();
const page = usePage();
const panel = usePanel();

const payouts = computed(() => page.payouts ?? []);
</script>

<template>
    <section :class="[panel, 'flex flex-col']" :aria-label="copy.payouts?.title">
        <UiSectionHeading
            :title="copy.payouts?.title ?? ''"
            :description="copy.payouts?.subtitle"
            icon="credit-card"
        />

        <ul v-if="payouts.length" class="mt-2 divide-y divide-slate-100 dark:divide-slate-800/60">
            <li
                v-for="payout in payouts"
                :key="payout.id"
                class="flex flex-wrap items-center justify-between gap-3 py-3"
            >
                <div class="min-w-0">
                    <p class="truncate font-mono text-xs font-semibold text-slate-700 dark:text-slate-200">
                        {{ payout.reference }}
                    </p>
                    <p class="mt-0.5 text-xs text-slate-400">
                        {{ payout.lessonsLabel }} · {{ payout.paidLabel }}
                        <template v-if="payout.methodLabel"> · {{ payout.methodLabel }}</template>
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <UiChip :tone="payout.statusTone">{{ payout.statusLabel }}</UiChip>
                    <p class="text-sm font-extrabold tabular-nums text-slate-800 dark:text-slate-100">
                        {{ payout.amountLabel }}
                    </p>
                </div>
            </li>
        </ul>

        <div
            v-else
            class="mt-4 flex flex-1 flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center dark:border-slate-700"
        >
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 p-2 text-slate-400 dark:bg-slate-800 dark:text-slate-500" aria-hidden="true">
                <AppIcon name="wallet" />
            </span>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ copy.payouts?.empty }}</p>
        </div>
    </section>
</template>
