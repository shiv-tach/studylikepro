<script setup>
/**
 * One row of the queue: a step number so the order is obvious at a glance, the
 * reason it is urgent, and the legal actions. Blade owns the ranking.
 */
import { computed } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';
import { usePanel } from '../../shared/composables/usePanel';
import { useCopy, useCsrfToken } from '../composables/usePageContext';
import InlineActionForm from './InlineActionForm.vue';

const props = defineProps({
    item: { type: Object, required: true },
    step: { type: Number, default: 1 },
});

const emit = defineEmits(['cancel']);

const copy = useCopy();
const token = useCsrfToken();
const panel = usePanel();

const TONES = {
    primary: { tile: 'bg-primary/10 text-primary', eyebrow: 'text-primary' },
    rose: { tile: 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300', eyebrow: 'text-rose-600 dark:text-rose-300' },
    amber: { tile: 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300', eyebrow: 'text-amber-600 dark:text-amber-300' },
    emerald: { tile: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300', eyebrow: 'text-emerald-600 dark:text-emerald-300' },
    sky: { tile: 'bg-sky-100 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300', eyebrow: 'text-sky-600 dark:text-sky-300' },
    slate: { tile: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300', eyebrow: 'text-slate-500 dark:text-slate-400' },
};

const tone = computed(() => TONES[props.item.tone] ?? TONES.slate);

const LINK_CLASSES = {
    primary: 'inline-flex items-center justify-center gap-1.5 rounded-xl bg-primary px-3 py-1.5 text-xs font-semibold text-white shadow-sm shadow-primary/20 transition-colors hover:bg-primary/90',
    ghost: 'inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-200/80 px-3 py-1.5 text-xs font-semibold text-slate-600 transition-colors hover:border-primary/40 hover:text-primary dark:border-slate-800/80 dark:text-slate-300',
    danger: 'inline-flex items-center justify-center gap-1.5 rounded-xl border border-rose-200/80 px-3 py-1.5 text-xs font-semibold text-rose-600 transition-colors hover:bg-rose-50 dark:border-rose-900/50 dark:text-rose-300 dark:hover:bg-rose-950/40',
};
</script>

<template>
    <li :class="[panel, 'flex flex-col gap-4 sm:flex-row sm:items-start']">
        <div class="flex shrink-0 items-center gap-3 sm:flex-col sm:items-center">
            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-900 text-[11px] font-bold text-white dark:bg-white dark:text-slate-900">
                {{ step }}
            </span>
            <span class="flex h-10 w-10 items-center justify-center rounded-xl p-2" :class="tone.tile" aria-hidden="true">
                <AppIcon :name="item.icon" />
            </span>
        </div>

        <div class="min-w-0 flex-1">
            <p class="text-[11px] font-bold uppercase tracking-wider" :class="tone.eyebrow">{{ item.eyebrow }}</p>

            <p class="mt-1 text-sm font-bold text-slate-800 dark:text-slate-100">{{ item.title }}</p>
            <p class="mt-1 text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ item.body }}</p>

            <div v-if="item.actions.length" class="mt-3 flex flex-wrap items-center gap-2">
                <template v-for="(action, index) in item.actions" :key="`${action.kind}-${action.label}-${index}`">
                    <InlineActionForm
                        v-if="action.kind === 'form'"
                        :action="action.url"
                        :token="token"
                        :label="action.label"
                        :tone="action.tone"
                        :confirm="Boolean(action.confirm)"
                        :confirm-title="copy.actions?.confirmNoShowTitle"
                        :confirm-body="copy.actions?.confirmNoShowBody"
                        :confirm-label="copy.actions?.confirm"
                        :cancel-label="copy.actions?.keep"
                        :busy-label="copy.actions?.busy"
                    />

                    <button
                        v-else-if="action.kind === 'dialog'"
                        type="button"
                        :class="LINK_CLASSES[action.tone] ?? LINK_CLASSES.ghost"
                        @click="emit('cancel')"
                    >
                        {{ action.label }}
                    </button>

                    <a v-else :href="action.url" :class="LINK_CLASSES[action.tone] ?? LINK_CLASSES.ghost">
                        {{ action.label }}
                    </a>
                </template>
            </div>
        </div>
    </li>
</template>
