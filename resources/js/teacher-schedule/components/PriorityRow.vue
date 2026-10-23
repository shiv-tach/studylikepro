<script setup>
/**
 * One urgent row at the top of the Today section: an "Urgent" badge, why it is
 * urgent, who it is with, and the two or three things the teacher can do about
 * it without leaving the page.
 */
import { computed } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';
import UiChip from '../../shared/components/UiChip.vue';
import { usePanel } from '../../shared/composables/usePanel';
import { useCopy, usePage } from '../composables/usePageContext';
import InlineActionForm from './InlineActionForm.vue';

const props = defineProps({
    item: { type: Object, required: true },
});

const copy = useCopy();
const page = usePage();
const panel = usePanel();

const TONES = {
    primary: { tile: 'bg-primary/10 text-primary', eyebrow: 'text-primary' },
    rose: { tile: 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300', eyebrow: 'text-rose-600 dark:text-rose-300' },
    amber: { tile: 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300', eyebrow: 'text-amber-600 dark:text-amber-300' },
    emerald: { tile: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300', eyebrow: 'text-emerald-600 dark:text-emerald-300' },
    sky: { tile: 'bg-sky-100 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300', eyebrow: 'text-sky-600 dark:text-sky-300' },
    slate: { tile: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300', eyebrow: 'text-slate-500 dark:text-slate-400' },
};

const tone = computed(() => TONES[props.item.tone] ?? TONES.amber);

const LINK_TONES = {
    primary: 'inline-flex items-center justify-center gap-1.5 rounded-xl bg-primary px-3 py-1.5 text-xs font-semibold text-white shadow-sm shadow-primary/20 transition-colors hover:bg-primary/90',
    ghost: 'inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-200/80 px-3 py-1.5 text-xs font-semibold text-slate-600 transition-colors hover:border-primary/40 hover:text-primary dark:border-slate-800/80 dark:text-slate-300',
};
</script>

<template>
    <li :class="[panel, 'flex flex-col gap-4 sm:flex-row sm:items-start']">
        <span
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl p-2"
            :class="tone.tile"
            aria-hidden="true"
        >
            <AppIcon :name="item.icon" />
        </span>

        <div class="min-w-0 flex-1">
            <p class="text-[11px] font-bold uppercase tracking-wider" :class="tone.eyebrow">
                {{ item.eyebrow }}
            </p>

            <p class="mt-1 flex flex-wrap items-center gap-2">
                <UiChip tone="rose" icon="alert">{{ copy.today?.urgent }}</UiChip>
                <a
                    :href="item.url"
                    class="text-base font-bold text-slate-800 transition-colors hover:text-primary dark:text-slate-100"
                >
                    {{ item.title }}
                </a>
                <UiChip :tone="item.statusTone">{{ item.statusLabel }}</UiChip>
            </p>

            <p class="mt-1 text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ item.body }}</p>

            <p v-if="item.meta.length" class="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-400">
                <template v-for="(meta, index) in item.meta" :key="meta">
                    <span v-if="index > 0" class="text-slate-300 dark:text-slate-700" aria-hidden="true">·</span>
                    <span>{{ meta }}</span>
                </template>
            </p>
        </div>

        <div class="flex shrink-0 flex-wrap items-center gap-2 sm:max-w-[15rem] sm:justify-end">
            <template v-for="action in item.actions" :key="action.label">
                <InlineActionForm
                    v-if="action.type === 'form'"
                    :action="action.url"
                    :token="page.csrfToken"
                    :label="action.label"
                    :tone="action.tone"
                    :confirm="Boolean(action.confirm)"
                    :confirm-title="copy.actions?.confirmNoShowTitle"
                    :confirm-body="copy.actions?.confirmNoShowBody"
                    :confirm-label="copy.actions?.confirm"
                    :cancel-label="copy.actions?.cancel"
                    :busy-label="copy.actions?.busy"
                />
                <a v-else :href="action.url" :class="LINK_TONES[action.tone] ?? LINK_TONES.ghost">
                    {{ action.label }}
                </a>
            </template>
        </div>
    </li>
</template>
