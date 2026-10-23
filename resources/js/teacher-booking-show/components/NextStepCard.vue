<script setup>
/**
 * The one answer to "what now": why it matters, how long is left and the single
 * action the teacher should take. Off-button work (cancel) is a dialog rather
 * than a navigation, so the parent opens it instead.
 */
import { computed } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';
import CountdownChip from '../../shared/components/CountdownChip.vue';
import { usePanel } from '../../shared/composables/usePanel';
import { useCopy, useCsrfToken } from '../composables/usePageContext';
import InlineActionForm from './InlineActionForm.vue';

const props = defineProps({
    next: { type: Object, required: true },
});

const emit = defineEmits(['cancel']);

const copy = useCopy();
const token = useCsrfToken();
const panel = usePanel();

const TONES = {
    primary: { border: 'border-primary', tile: 'bg-primary/10 text-primary', eyebrow: 'text-primary' },
    rose: { border: 'border-rose-400', tile: 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300', eyebrow: 'text-rose-600 dark:text-rose-300' },
    amber: { border: 'border-amber-400', tile: 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300', eyebrow: 'text-amber-600 dark:text-amber-300' },
    emerald: { border: 'border-emerald-400', tile: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300', eyebrow: 'text-emerald-600 dark:text-emerald-300' },
    sky: { border: 'border-sky-400', tile: 'bg-sky-100 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300', eyebrow: 'text-sky-600 dark:text-sky-300' },
    slate: { border: 'border-slate-300 dark:border-slate-700', tile: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300', eyebrow: 'text-slate-500 dark:text-slate-400' },
};

const LINK_TONES = {
    primary: 'inline-flex items-center justify-center gap-1.5 rounded-xl bg-primary px-4 py-2.5 text-sm font-bold text-white shadow-sm shadow-primary/20 transition-transform hover:-translate-y-0.5',
    ghost: 'inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-200/80 px-4 py-2.5 text-sm font-semibold text-slate-600 transition-colors hover:border-primary/40 hover:text-primary dark:border-slate-800/80 dark:text-slate-300',
    danger: 'inline-flex items-center justify-center gap-1.5 rounded-xl border border-rose-200/80 px-4 py-2.5 text-sm font-semibold text-rose-600 transition-colors hover:bg-rose-50 dark:border-rose-900/50 dark:text-rose-300 dark:hover:bg-rose-950/40',
};

const tone = computed(() => TONES[props.next.tone] ?? TONES.slate);

const actions = computed(() => [props.next.primary, ...(props.next.secondary ?? [])].filter(Boolean));
const hasActions = computed(() => actions.value.length > 0);
</script>

<template>
    <section :class="[panel, 'relative overflow-hidden border-l-4', tone.border]" aria-labelledby="booking-next-step">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex min-w-0 items-start gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl p-2.5" :class="tone.tile" aria-hidden="true">
                    <AppIcon :name="next.icon" />
                </span>

                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-wider" :class="tone.eyebrow">
                        {{ copy.next?.title }}
                    </p>

                    <h2 id="booking-next-step" class="mt-1 text-lg font-extrabold tracking-tight text-slate-900 dark:text-slate-100">
                        {{ next.kind === 'closed' ? copy.next?.doneTitle : next.eyebrow }}
                    </h2>

                    <p class="mt-1.5 max-w-2xl text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ next.body }}</p>
                </div>
            </div>

            <CountdownChip
                v-if="next.deadline && next.countdownPrefix"
                :deadline="next.deadline"
                :prefix="next.countdownPrefix"
                :zero-label="next.countdownZero ?? next.countdownPrefix"
            />
        </div>

        <div v-if="hasActions" class="mt-4 flex flex-wrap items-center gap-2">
            <template v-for="(action, index) in actions" :key="`${action.kind}-${action.label}-${index}`">
                <InlineActionForm
                    v-if="action.kind === 'form'"
                    :action="action.url"
                    :token="token"
                    :label="action.label"
                    :tone="action.tone"
                    :size="'md'"
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
                    :class="LINK_TONES[action.tone] ?? LINK_TONES.ghost"
                    @click="emit('cancel')"
                >
                    {{ action.label }}
                </button>

                <a v-else :href="action.url" :class="LINK_TONES[action.tone] ?? LINK_TONES.ghost">
                    {{ action.label }}
                </a>
            </template>
        </div>
    </section>
</template>
