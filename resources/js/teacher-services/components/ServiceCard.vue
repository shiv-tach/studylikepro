<script setup>
/**
 * One service in the catalog. The card carries everything a teacher needs to
 * decide what to do next: type, availability, scope, price, what is included,
 * how many were sold, and the three actions. Pause/resume is a real POST form;
 * edit and delete open the page's dialogs.
 */
import { computed } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';
import UiChip from '../../shared/components/UiChip.vue';
import { CARD, CARD_HOVER } from '../../shared/styles';
import { format, plural, useCopy, useCsrfToken } from '../composables/usePageContext';
import { DANGER_GHOST_BUTTON, GHOST_BUTTON, TYPE_TILE } from '../styles';

const props = defineProps({
    offering: { type: Object, required: true },
});

const emit = defineEmits(['edit', 'delete']);

const copy = useCopy();
const csrfToken = useCsrfToken();

const soldLabel = computed(() => format(
    plural(props.offering.purchasesCount, copy.card?.soldOne, copy.card?.sold),
    { count: props.offering.purchasesCount },
));
</script>

<template>
    <article :class="[CARD, CARD_HOVER, 'flex flex-col p-5']">
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

        <div v-if="offering.gradeLabel || offering.subjectName || offering.lessonName" class="mt-3 flex flex-wrap gap-1.5">
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

        <div class="mt-auto flex flex-wrap items-center gap-1 border-t border-slate-200/70 pt-3 dark:border-slate-800/70">
            <form method="POST" :action="offering.toggleUrl">
                <input type="hidden" name="_token" :value="csrfToken">
                <button type="submit" :class="GHOST_BUTTON">
                    <span class="h-4 w-4" aria-hidden="true">
                        <AppIcon :name="offering.isActive ? 'pause' : 'play'" />
                    </span>
                    {{ offering.isActive ? copy.card?.pause : copy.card?.resume }}
                </button>
            </form>

            <button type="button" :class="GHOST_BUTTON" @click="emit('edit', offering)">
                <span class="h-4 w-4" aria-hidden="true"><AppIcon name="pencil" /></span>
                {{ copy.card?.edit }}
            </button>

            <button
                type="button"
                :class="[DANGER_GHOST_BUTTON, 'ml-auto']"
                :disabled="!offering.canDelete"
                :title="offering.canDelete ? copy.card?.delete : copy.card?.deleteBlocked"
                @click="emit('delete', offering)"
            >
                <span class="h-4 w-4" aria-hidden="true"><AppIcon name="trash" /></span>
                {{ copy.card?.delete }}
            </button>
        </div>
    </article>
</template>
