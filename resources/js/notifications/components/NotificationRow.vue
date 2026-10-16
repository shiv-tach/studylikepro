<script setup>
/**
 * One notification: a real POST form that marks it read and follows its link.
 * Unread rows carry the accent left border and a "New" chip; read rows stay
 * muted. The button fills the row so the whole card is one hit target.
 */
import { computed } from 'vue';
import UiChip from '../../shared/components/UiChip.vue';
import { usePanel } from '../../shared/composables/usePanel';
import { useCopy, useCsrfToken } from '../composables/usePageContext';

const props = defineProps({
    notification: { type: Object, required: true },
});

const copy = useCopy();
const csrfToken = useCsrfToken();
const panel = usePanel(true);

const rowClass = computed(() => [
    panel.value,
    'flex w-full items-start gap-4 text-left transition-colors hover:border-primary/40',
    props.notification.unread ? 'border-l-4 border-l-primary' : '',
]);
</script>

<template>
    <form method="POST" :action="notification.openUrl">
        <input type="hidden" name="_token" :value="csrfToken">

        <button type="submit" :class="rowClass">
            <span
                class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full"
                :class="notification.unread ? 'bg-primary' : 'bg-slate-300 dark:bg-slate-600'"
                aria-hidden="true"
            ></span>

            <span class="min-w-0 flex-1">
                <span class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ notification.title }}</span>
                    <span class="text-xs text-slate-400">{{ notification.time }}</span>
                </span>

                <span v-if="notification.body" class="mt-1 block text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                    {{ notification.body }}
                </span>

                <UiChip v-if="notification.unread" tone="primary" class="mt-2 uppercase tracking-wide">{{ copy.newBadge }}</UiChip>
            </span>
        </button>
    </form>
</template>
