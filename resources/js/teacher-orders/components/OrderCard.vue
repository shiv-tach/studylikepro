<script setup>
/**
 * One order in the book. It answers the four things a teacher scans for: what
 * was bought, who bought it, what is still owed on it (sessions, plan balance,
 * a paper to mark) and what it pays. The page opens on the compact list row and
 * the toolbar can switch every order to the taller card. The whole order is the
 * link, so there is nothing to aim at beyond the row itself.
 */
import { computed } from 'vue';
import { usePanel } from '../../shared/composables/usePanel';
import AppIcon from '../../shared/components/AppIcon.vue';
import UiChip from '../../shared/components/UiChip.vue';
import { useCopy } from '../composables/usePageContext';

const props = defineProps({
    order: { type: Object, required: true },
    view: { type: String, default: 'list' },
});

const copy = useCopy();
const panel = usePanel(true);

const isList = computed(() => props.view !== 'card');

// The next session is the most actionable line on a paid order, so the list row
// folds it into the chip rail instead of giving it a line of its own.
const listChips = computed(() => {
    const chips = [...props.order.chips];

    if (props.order.nextSession) {
        chips.push({
            icon: 'calendar',
            label: `${props.order.nextSession.label}: ${props.order.nextSession.value}`,
            tone: 'primary',
        });
    } else if (props.order.isLiveService) {
        chips.push({
            icon: 'calendar',
            label: props.order.sessionCount ? copy.card?.noUpcoming : copy.card?.noSessions,
            tone: 'slate',
        });
    }

    return chips;
});
</script>

<template>
    <a
        :href="order.url"
        :class="isList
            ? [panel, 'group flex flex-col gap-4 lg:flex-row lg:items-center lg:gap-6 lg:p-4']
            : [panel, 'group flex h-full flex-col gap-4']"
        :aria-label="`${copy.card?.open}: ${order.scopeTitle}`"
    >
        <template v-if="isList">
            <div class="flex min-w-0 flex-1 items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 p-2 text-primary">
                    <AppIcon :name="order.typeIcon" />
                </span>

                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="truncate text-sm font-bold text-slate-800 transition-colors group-hover:text-primary dark:text-slate-100">
                            {{ order.scopeTitle }}
                        </h3>
                        <UiChip :tone="order.statusTone">{{ order.statusLabel }}</UiChip>
                    </div>

                    <p class="mt-0.5 truncate text-xs font-medium text-slate-400">
                        {{ order.typeLabel }} · {{ order.reference }} · {{ order.orderedLabel }}
                    </p>

                    <p class="mt-1 flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                        <span
                            class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-slate-100 text-[10px] font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-300"
                            aria-hidden="true"
                        >
                            {{ order.studentInitials }}
                        </span>
                        <span class="truncate">{{ order.studentName }}</span>
                    </p>
                </div>
            </div>

            <ul v-if="listChips.length" class="flex flex-wrap gap-1.5 lg:max-w-xs lg:shrink-0">
                <li v-for="chip in listChips" :key="chip.label">
                    <UiChip :tone="chip.tone || 'slate'" :icon="chip.icon">{{ chip.label }}</UiChip>
                </li>
            </ul>

            <p class="shrink-0 lg:w-36 lg:text-right">
                <span class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ order.payoutCaption }}</span>
                <span class="block text-base font-extrabold tracking-tight text-slate-900 dark:text-slate-100">{{ order.payoutLabel }}</span>
                <span class="block text-[11px] text-slate-400">{{ copy.card?.price }} {{ order.priceLabel }}</span>
            </p>

            <span class="hidden shrink-0 text-slate-300 transition-colors group-hover:text-primary dark:text-slate-600 lg:inline-flex">
                <span class="h-4 w-4" aria-hidden="true"><AppIcon name="chevron-right" /></span>
            </span>
        </template>

        <template v-else>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 p-2.5 text-primary">
                        <AppIcon :name="order.typeIcon" />
                    </span>

                    <div class="min-w-0">
                        <p class="truncate text-[11px] font-bold uppercase tracking-wider text-primary">
                            {{ order.typeLabel }}
                        </p>
                        <p class="truncate text-xs text-slate-400">
                            {{ order.reference }} · {{ order.orderedLabel }}
                        </p>
                    </div>
                </div>

                <UiChip :tone="order.statusTone">{{ order.statusLabel }}</UiChip>
            </div>

            <div class="min-w-0">
                <h3 class="truncate text-base font-bold text-slate-800 transition-colors group-hover:text-primary dark:text-slate-100">
                    {{ order.scopeTitle }}
                </h3>

                <p class="mt-1 flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                    <span
                        class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-100 text-[10px] font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-300"
                        aria-hidden="true"
                    >
                        {{ order.studentInitials }}
                    </span>
                    <span class="truncate">{{ order.studentName }}</span>
                </p>

                <p v-if="order.nextSession" class="mt-2 flex items-center gap-1.5 text-xs font-semibold text-slate-600 dark:text-slate-300">
                    <span class="h-3.5 w-3.5 shrink-0 text-primary" aria-hidden="true"><AppIcon name="calendar" /></span>
                    {{ order.nextSession.label }}: {{ order.nextSession.value }}
                </p>
                <p v-else-if="order.sessionCount" class="mt-2 text-xs text-slate-400">{{ copy.card?.noUpcoming }}</p>
                <p v-else-if="order.isLiveService" class="mt-2 text-xs text-slate-400">{{ copy.card?.noSessions }}</p>
            </div>

            <ul v-if="order.chips.length" class="flex flex-wrap gap-1.5">
                <li v-for="chip in order.chips" :key="chip.label">
                    <UiChip :tone="chip.tone || 'slate'" :icon="chip.icon">{{ chip.label }}</UiChip>
                </li>
            </ul>

            <div class="mt-auto flex flex-wrap items-end justify-between gap-3 border-t border-slate-200/80 pt-4 dark:border-slate-800/80">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ order.payoutCaption }}</p>
                    <p class="mt-0.5 text-lg font-extrabold tracking-tight text-slate-900 dark:text-slate-100">
                        {{ order.payoutLabel }}
                    </p>
                    <p class="text-[11px] text-slate-400">{{ copy.card?.price }} {{ order.priceLabel }}</p>
                </div>

                <span class="inline-flex items-center gap-1 text-xs font-semibold text-slate-400 transition-colors group-hover:text-primary">
                    {{ copy.card?.open }}
                    <span class="h-3.5 w-3.5" aria-hidden="true"><AppIcon name="chevron-right" /></span>
                </span>
            </div>
        </template>
    </a>
</template>
