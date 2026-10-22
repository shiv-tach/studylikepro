<script setup>
/**
 * The marking work this order created. It states what the teacher has to do
 * next, how long is left and the one link into the marking console — the order
 * page itself never accepts a paper or files a result.
 */
import { usePanel } from '../../shared/composables/usePanel';
import { HEADING_TEXT, MUTED_TEXT } from '../../shared/styles';
import AppIcon from '../../shared/components/AppIcon.vue';
import UiChip from '../../shared/components/UiChip.vue';
import { useCopy } from '../composables/usePageContext';

defineProps({
    marking: { type: Object, required: true },
});

const copy = useCopy();
const panel = usePanel();
</script>

<template>
    <section :class="panel" aria-labelledby="teacher-order-marking-heading">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <h2 id="teacher-order-marking-heading" :class="['flex items-center gap-2 text-sm font-bold', HEADING_TEXT]">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary/10 p-1.5 text-primary" aria-hidden="true">
                    <AppIcon name="clipboard" />
                </span>
                {{ marking.title }}
            </h2>

            <div class="flex items-center gap-2">
                <UiChip v-if="marking.overdue" tone="rose">{{ copy.marking?.overdue }}</UiChip>
                <UiChip :tone="marking.status.tone">{{ marking.status.label }}</UiChip>
            </div>
        </div>

        <p :class="['mt-3 text-sm', MUTED_TEXT]">{{ marking.body }}</p>
        <p v-if="marking.deadlineLabel" class="mt-1 text-xs font-semibold text-slate-400">{{ marking.deadlineLabel }}</p>

        <a
            :href="marking.url"
            class="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-primary/20 transition-colors hover:bg-primary/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
        >
            {{ marking.actionLabel }}
            <span class="h-4 w-4" aria-hidden="true"><AppIcon name="arrow-right" /></span>
        </a>
    </section>
</template>
