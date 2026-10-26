<script setup>
/**
 * The banner that owns the lesson's money: a slot still held (pay before it
 * lapses), a hold that lapsed, or a cancelled lesson. Blade decides which of the
 * three applies — the status, the expiry and the cancellation window all have to
 * line up — and pre-formats the slot, the price split, the total and the note.
 */
import { usePanel } from '../../shared/composables/usePanel';
import { useCsrfToken } from '../composables/usePageContext';

defineProps({
    banner: { type: Object, required: true },
});

const csrfToken = useCsrfToken();
const panel = usePanel();

const primaryClass =
    'inline-flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary/90';
const outlineClass =
    'inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800';
</script>

<template>
    <!-- A slot still racing the payment clock. -->
    <div
        v-if="banner.kind === 'hold'"
        :class="[panel, 'border-amber-200/80 bg-amber-50/60 dark:border-amber-900/40 dark:bg-amber-950/20']"
    >
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex items-start gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-xl dark:bg-amber-900/40">⏳</span>
                <div>
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ banner.title }}</h3>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ banner.slotLine }}</p>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ banner.note }}</p>
                </div>
            </div>

            <div class="text-right">
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ banner.priceLine }}</p>
                <p class="mt-1 text-lg font-bold text-slate-800 dark:text-slate-100">{{ banner.total }}</p>
                <form method="POST" :action="banner.payUrl" class="mt-2">
                    <input type="hidden" name="_token" :value="csrfToken">
                    <button type="submit" :class="primaryClass">{{ banner.payLabel }}</button>
                </form>
                <p class="mt-2 text-[11px] text-amber-700 dark:text-amber-400">{{ banner.secureNote }}</p>
            </div>
        </div>
    </div>

    <!-- The uploaded slip is with the admin team. -->
    <div
        v-else-if="banner.kind === 'verifying'"
        :class="[panel, 'border-sky-200/80 bg-sky-50/60 dark:border-sky-900/40 dark:bg-sky-950/20']"
    >
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex items-start gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-sky-100 text-xl dark:bg-sky-900/40">🧾</span>
                <div>
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ banner.title }}</h3>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ banner.body }}</p>
                    <p v-if="banner.submittedAt" class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {{ banner.submittedAt }}
                    </p>
                    <p v-if="banner.note" class="mt-2 text-xs text-sky-800 dark:text-sky-300">{{ banner.note }}</p>
                </div>
            </div>

            <div class="text-right">
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ banner.total }}</p>
                <a v-if="banner.slipUrl" :href="banner.slipUrl" target="_blank" rel="noopener" :class="[outlineClass, 'mt-2']">
                    {{ banner.slipLabel }}
                </a>
                <a v-if="banner.action" :href="banner.action.url" :class="[outlineClass, 'mt-2']">
                    {{ banner.action.label }}
                </a>
            </div>
        </div>
    </div>

    <!-- The hold lapsed, the slip was rejected, or the lesson was called off. -->
    <div
        v-else
        :class="[panel, banner.kind === 'rejected'
            ? 'border-rose-200/80 bg-rose-50/60 dark:border-rose-900/40 dark:bg-rose-950/20'
            : 'border-slate-200/80 bg-slate-50 dark:border-slate-800 dark:bg-slate-900/60']"
    >
        <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ banner.title }}</h3>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
            {{ banner.body }}
            <span v-if="banner.reason" class="italic">“{{ banner.reason }}”</span>
        </p>
        <div v-if="banner.action" class="mt-4">
            <a :href="banner.action.url" :class="banner.action.variant === 'primary' ? primaryClass : outlineClass">
                {{ banner.action.label }}
            </a>
        </div>
    </div>
</template>
