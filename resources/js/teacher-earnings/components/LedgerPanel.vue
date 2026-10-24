<script setup>
/**
 * The ledger itself: a comparable table on wide screens, stacked cards below
 * `lg`, and the refund footnote once something has actually been reversed.
 *
 * The paginator is NOT here — it stays Blade-rendered under the Vue mount so
 * its links are plain URLs. That is also why the ledger is the last section on
 * the page.
 */
import { computed } from 'vue';
import UiChip from '../../shared/components/UiChip.vue';
import UiSectionHeading from '../../shared/components/UiSectionHeading.vue';
import { usePanel } from '../../shared/composables/usePanel';
import { format, useCopy, usePage } from '../composables/usePageContext';
import LedgerCard from './LedgerCard.vue';
import LedgerEmptyState from './LedgerEmptyState.vue';
import LedgerRow from './LedgerRow.vue';

const copy = useCopy();
const page = usePage();
const panel = usePanel();

const rows = computed(() => page.ledger ?? []);
const pagination = computed(() => page.pagination ?? {});
const reversed = computed(() => page.stats?.reversed ?? { minor: 0, label: null });

const showingLabel = computed(() =>
    format(copy.ledger?.showing, {
        visible: pagination.value.visible ?? 0,
        total: pagination.value.total ?? 0,
    }),
);
</script>

<template>
    <section :class="panel" :aria-label="copy.ledger?.title">
        <UiSectionHeading
            :title="copy.ledger?.title ?? ''"
            :description="copy.ledger?.subtitle"
            icon="list"
        >
            <template #action>
                <UiChip v-if="pagination.total" tone="slate">{{ showingLabel }}</UiChip>
            </template>
        </UiSectionHeading>

        <template v-if="rows.length">
            <div class="mt-4 hidden overflow-x-auto lg:block">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <caption class="sr-only">{{ copy.ledger?.title }}</caption>
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-400">
                            <th scope="col" class="py-3 pr-4">{{ copy.ledger?.headers?.item }}</th>
                            <th scope="col" class="px-4 py-3">{{ copy.ledger?.headers?.when }}</th>
                            <th scope="col" class="px-4 py-3">{{ copy.ledger?.headers?.status }}</th>
                            <th scope="col" class="px-4 py-3 text-right">{{ copy.ledger?.headers?.earned }}</th>
                            <th scope="col" class="py-3 pl-4 text-right">{{ copy.ledger?.headers?.reversed }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                        <LedgerRow v-for="row in rows" :key="row.id" :row="row" />
                    </tbody>
                </table>
            </div>

            <ul class="mt-4 space-y-3 lg:hidden">
                <LedgerCard v-for="row in rows" :key="row.id" :row="row" />
            </ul>

            <p
                v-if="reversed.label"
                class="mt-4 border-t border-slate-200 pt-4 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400"
            >
                {{ reversed.label }}
            </p>
        </template>

        <LedgerEmptyState v-else />
    </section>
</template>
