<script setup>
/**
 * The one action worth taking: book a slot, or send the teacher a request to
 * match a time. Blade has already checked the student may book this teacher, so
 * the card only decides which of the two shapes to render — the offer, or why
 * it is not available.
 */
import { usePanel } from '../../shared/composables/usePanel';
import { useCopy } from '../composables/usePageContext';

defineProps({
    booking: { type: Object, required: true },
});

const copy = useCopy();
const panel = usePanel();

const primaryClass =
    'mt-5 block rounded-xl bg-primary px-4 py-2.5 text-center text-sm font-semibold text-white shadow-md shadow-primary/20 transition hover:bg-primary/90';

const outlineClass =
    'mt-2 block rounded-xl border border-slate-300 px-4 py-2.5 text-center text-sm font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800';
</script>

<template>
    <aside :class="panel">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ booking.scopeLabel }}</p>

        <template v-if="booking.rate">
            <p class="mt-1 text-3xl font-extrabold tracking-tight text-slate-800 dark:text-slate-100">
                {{ booking.rate }}<span class="text-sm font-medium text-slate-400">{{ booking.perHour }}</span>
            </p>
            <p v-if="booking.lessonLength" class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                {{ booking.lessonLength }}
            </p>
        </template>

        <template v-if="booking.canBook">
            <a :href="booking.bookUrl" :class="primaryClass">{{ copy.bookSlot }}</a>
            <a :href="booking.requestUrl" :class="outlineClass">{{ copy.sendRequest }}</a>
            <p class="mt-3 text-xs text-slate-400">{{ copy.pickSlotHint }}</p>
        </template>

        <template v-else-if="booking.notice">
            <p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ booking.notice }}</p>
            <a :href="booking.fallbackUrl" :class="primaryClass">{{ copy.findTeacher }}</a>
        </template>
    </aside>
</template>
