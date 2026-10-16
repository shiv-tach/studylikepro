<script setup>
/**
 * One lesson in the list: where it stands, who teaches it, when it runs and
 * what it cost. Everything shown here arrives formatted from Blade, so the card
 * never guesses a label, a timezone or a URL.
 */
import { computed } from 'vue';
import { usePanel } from '../../shared/composables/usePanel';
import { CHIP } from '../../shared/styles';
import AppIcon from '../../shared/components/AppIcon.vue';
import { useCopy } from '../composables/usePageContext';

const props = defineProps({
    lesson: { type: Object, required: true },
});

// Booking statuses carry their own Tailwind classes in the PHP enum, which sits
// outside Tailwind's scanned paths, so the card keeps its own copy here.
const statusTones = {
    pending_payment: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    confirmed: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    in_progress: 'bg-primary/10 text-primary',
    completed: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    cancelled: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
    expired: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
    no_show: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
    disputed: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
    resolved: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
};

const amberChip = 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400';
const slateChip = 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400';

const copy = useCopy();
const panel = usePanel(true);
const statusTone = computed(() => statusTones[props.lesson.status] ?? statusTones.cancelled);
</script>

<template>
    <a :href="lesson.url" :class="[panel, 'group block focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40']">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <span :class="[CHIP, statusTone]">{{ lesson.statusLabel }}</span>

                    <span v-if="lesson.payByLabel" :class="[CHIP, amberChip]">{{ lesson.payByLabel }}</span>

                    <span :class="[CHIP, slateChip]">{{ lesson.durationLabel }}</span>

                    <span v-if="lesson.joinNow" :class="[CHIP, 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400']">
                        <span class="h-3.5 w-3.5 shrink-0"><AppIcon name="video" /></span>
                        {{ copy.joinNow }}
                    </span>

                    <span v-if="lesson.reviewPrompt" :class="[CHIP, amberChip]">{{ copy.reviewPrompt }}</span>
                </div>

                <h3 class="mt-3 truncate text-sm font-bold text-slate-800 dark:text-slate-100">{{ lesson.title }}</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ lesson.subtitle }}</p>
            </div>

            <div class="text-right">
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                    {{ lesson.startsLabel }}–{{ lesson.endsLabel }}
                </p>
                <p class="mt-1 text-xs text-slate-400">{{ lesson.amountLabel }} {{ lesson.amount }}</p>
            </div>
        </div>
    </a>
</template>
