<script setup>
/**
 * One service in the catalog. The page opens on the list layout — a compact
 * row per service — and the toolbar can switch every row to the taller card.
 * Both layouts carry the same facts: type, availability, scope, price, what is
 * included and how many were sold; the three actions live in ServiceCardActions.
 */
import { computed } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';
import UiChip from '../../shared/components/UiChip.vue';
import { CARD, CARD_HOVER } from '../../shared/styles';
import { format, plural, useCopy } from '../composables/usePageContext';
import { TYPE_TILE } from '../styles';
import ServiceCardActions from './ServiceCardActions.vue';

const props = defineProps({
    offering: { type: Object, required: true },
    view: { type: String, default: 'list' },
});

const emit = defineEmits(['edit', 'delete']);

const copy = useCopy();

const isList = computed(() => props.view !== 'card');

const soldLabel = computed(() => format(
    plural(props.offering.purchasesCount, copy.card?.soldOne, copy.card?.sold),
    { count: props.offering.purchasesCount },
));

const hasScope = computed(() => Boolean(
    props.offering.gradeLabel || props.offering.subjectName || props.offering.lessonName,
));
</script>

<template>
    <article
        :class="isList
            ? [CARD, 'flex flex-col gap-4 p-4 transition-colors hover:border-primary/40 dark:hover:border-primary/40 lg:flex-row lg:items-center lg:gap-6']
            : [CARD, CARD_HOVER, 'flex flex-col p-5']"
    >
        <template v-if="isList">
            <div class="flex min-w-0 flex-1 items-start gap-3">
                <span :class="TYPE_TILE" aria-hidden="true">
                    <AppIcon :name="offering.typeIcon" />
                </span>

                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="truncate text-sm font-bold text-slate-800 dark:text-slate-100">
                            {{ offering.title }}
                        </p>
                        <UiChip :tone="offering.isActive ? 'emerald' : 'slate'">
                            {{ offering.isActive ? copy.card?.active : copy.card?.paused }}
                        </UiChip>
                    </div>
                    <p class="mt-0.5 text-xs font-medium text-slate-400">
                        {{ offering.typeLabel }}
                    </p>
                    <p class="mt-1 line-clamp-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                        {{ offering.description || copy.card?.noDescription }}
                    </p>
                </div>
            </div>

            <div
                v-if="hasScope || offering.detailChips.length || offering.purchasesCount > 0"
                class="flex flex-wrap gap-1.5 lg:max-w-xs lg:shrink-0"
            >
                <UiChip v-if="offering.gradeLabel" icon="graduation">{{ offering.gradeLabel }}</UiChip>
                <UiChip v-if="offering.subjectName">{{ offering.subjectIcon }} {{ offering.subjectName }}</UiChip>
                <UiChip v-if="offering.lessonName" icon="book">{{ offering.lessonName }}</UiChip>
                <UiChip v-for="chip in offering.detailChips" :key="chip" tone="slate">{{ chip }}</UiChip>
                <UiChip v-if="offering.purchasesCount > 0" tone="primary" icon="credit-card">{{ soldLabel }}</UiChip>
            </div>

            <p class="shrink-0 lg:w-32 lg:text-right">
                <span class="block text-base font-extrabold text-slate-800 dark:text-slate-100">{{ offering.priceLabel }}</span>
                <span class="block text-[11px] text-slate-400">{{ offering.priceSuffix }}</span>
            </p>

            <div class="border-t border-slate-200/70 pt-3 dark:border-slate-800/70 lg:shrink-0 lg:border-0 lg:pt-0">
                <ServiceCardActions
                    :offering="offering"
                    @edit="emit('edit', $event)"
                    @delete="emit('delete', $event)"
                />
            </div>
        </template>

        <template v-else>
            <div class="flex items-start justify-between gap-3">
                <div class="flex min-w-0 items-start gap-3">
                    <span :class="TYPE_TILE" aria-hidden="true">
                        <AppIcon :name="offering.typeIcon" />
                    </span>

                    <div class="min-w-0">
                        <p class="truncate text-base font-bold text-slate-800 dark:text-slate-100">
                            {{ offering.title }}
                        </p>
                        <p class="mt-0.5 text-xs font-medium text-slate-400">
                            {{ offering.typeLabel }}
                        </p>
                    </div>
                </div>

                <UiChip :tone="offering.isActive ? 'emerald' : 'slate'">
                    {{ offering.isActive ? copy.card?.active : copy.card?.paused }}
                </UiChip>
            </div>

            <p class="mt-3 line-clamp-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                {{ offering.description || copy.card?.noDescription }}
            </p>

            <div v-if="hasScope" class="mt-3 flex flex-wrap gap-1.5">
                <UiChip v-if="offering.gradeLabel" icon="graduation">{{ offering.gradeLabel }}</UiChip>
                <UiChip v-if="offering.subjectName">{{ offering.subjectIcon }} {{ offering.subjectName }}</UiChip>
                <UiChip v-if="offering.lessonName" icon="book">{{ offering.lessonName }}</UiChip>
            </div>

            <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap gap-1.5">
                    <UiChip v-for="chip in offering.detailChips" :key="chip" tone="slate">{{ chip }}</UiChip>
                    <UiChip v-if="offering.purchasesCount > 0" tone="primary" icon="credit-card">{{ soldLabel }}</UiChip>
                </div>

                <p class="shrink-0 text-right">
                    <span class="block text-lg font-extrabold text-slate-800 dark:text-slate-100">{{ offering.priceLabel }}</span>
                    <span class="block text-[11px] text-slate-400">{{ offering.priceSuffix }}</span>
                </p>
            </div>

            <div class="mt-auto border-t border-slate-200/70 pt-3 dark:border-slate-800/70">
                <ServiceCardActions
                    :offering="offering"
                    @edit="emit('edit', $event)"
                    @delete="emit('delete', $event)"
                />
            </div>
        </template>
    </article>
</template>
