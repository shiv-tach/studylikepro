<script setup>
/**
 * Every legal action for this lesson, in one place. Blade's flags mirror the
 * policy and the transition windows, so this bar never offers a request the
 * server would refuse.
 */
import AppIcon from '../../shared/components/AppIcon.vue';
import { usePanel } from '../../shared/composables/usePanel';
import { useCopy, useCsrfToken } from '../composables/usePageContext';
import InlineActionForm from './InlineActionForm.vue';

defineProps({
    actions: { type: Object, required: true },
    joinUrl: { type: String, default: null },
});

const emit = defineEmits(['cancel']);

const copy = useCopy();
const token = useCsrfToken();
const panel = usePanel();

const LINK_CLASSES = {
    primary: 'inline-flex items-center justify-center gap-1.5 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-primary/20 transition-colors hover:bg-primary/90',
    ghost: 'inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-200/80 px-4 py-2 text-sm font-semibold text-slate-600 transition-colors hover:border-primary/40 hover:text-primary dark:border-slate-800/80 dark:text-slate-300',
};
</script>

<template>
    <section :class="panel" aria-labelledby="booking-actions-title">
        <h2 id="booking-actions-title" class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ copy.actions?.title }}</h2>

        <div class="mt-3 flex flex-wrap items-center gap-2">
            <a v-if="actions.canJoin && actions.joinUrl" :href="actions.joinUrl" :class="LINK_CLASSES.primary">
                <span class="h-4 w-4" aria-hidden="true"><AppIcon name="video" /></span>
                {{ copy.actions?.join }}
            </a>

            <InlineActionForm
                v-if="actions.canStart"
                :action="actions.start"
                :token="token"
                :label="copy.actions?.start"
                :busy-label="copy.actions?.busy"
                tone="ghost"
                size="md"
            />

            <InlineActionForm
                v-if="actions.canComplete"
                :action="actions.complete"
                :token="token"
                :label="copy.actions?.complete"
                :busy-label="copy.actions?.busy"
                tone="ghost"
                size="md"
            />

            <InlineActionForm
                v-if="actions.canNoShow"
                :action="actions.noShow"
                :token="token"
                :label="copy.actions?.noShow"
                :busy-label="copy.actions?.busy"
                tone="ghost"
                size="md"
                confirm
                :confirm-title="copy.actions?.confirmNoShowTitle"
                :confirm-body="copy.actions?.confirmNoShowBody"
                :confirm-label="copy.actions?.confirm"
                :cancel-label="copy.actions?.keep"
            />

            <a v-if="actions.canMessage && actions.messageUrl" :href="actions.messageUrl" :class="LINK_CLASSES.ghost">
                <span class="h-4 w-4" aria-hidden="true"><AppIcon name="chat" /></span>
                {{ copy.actions?.message }}
            </a>

            <button
                v-if="actions.canCancel"
                type="button"
                class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-rose-200/80 px-4 py-2 text-sm font-semibold text-rose-600 transition-colors hover:bg-rose-50 dark:border-rose-900/50 dark:text-rose-300 dark:hover:bg-rose-950/40"
                @click="emit('cancel')"
            >
                {{ copy.actions?.cancel }}
            </button>
        </div>

        <p
            v-if="!actions.canJoin && !actions.canStart && !actions.canComplete && !actions.canNoShow && !actions.canCancel && !actions.canMessage"
            class="mt-3 text-sm text-slate-500 dark:text-slate-400"
        >
            {{ copy.actions?.none }}
        </p>
    </section>
</template>
