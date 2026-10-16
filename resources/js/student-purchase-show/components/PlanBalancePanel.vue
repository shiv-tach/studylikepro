<script setup>
/**
 * What is left of a lesson plan: the balance Blade counted, how much of the
 * allocation has been used and the way to spend another session. A capped plan
 * whose sessions are gone says so instead of offering a button the booking
 * screen would refuse.
 */
import { computed } from 'vue';
import { usePanel } from '../../shared/composables/usePanel';
import AppIcon from '../../shared/components/AppIcon.vue';

const props = defineProps({
    plan: { type: Object, required: true },
});

const panel = usePanel();

// The accent marks a plan with time left; a finished balance reads as done.
const valueTone = computed(() => (props.plan.remainingTone === 'primary'
    ? 'text-primary'
    : 'text-emerald-600 dark:text-emerald-400'));
</script>

<template>
    <section :class="panel" aria-labelledby="purchase-plan-heading">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 id="purchase-plan-heading" class="text-sm font-bold text-slate-800 dark:text-slate-100">
                    {{ plan.title }}
                </h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ plan.intro }}</p>
            </div>

            <div class="text-right">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ plan.remainingTitle }}</p>
                <p :class="[valueTone, 'mt-1 text-2xl font-extrabold tracking-tight']">{{ plan.remainingValue }}</p>
            </div>
        </div>

        <div class="mt-4">
            <div class="flex items-center justify-between text-xs text-slate-400">
                <span>{{ plan.progress.label }}</span>
                <span class="tabular-nums">{{ plan.progress.percent }}%</span>
            </div>
            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                <div
                    class="h-full rounded-full transition-all duration-300"
                    :class="plan.remainingTone === 'primary' ? 'bg-primary' : 'bg-emerald-500'"
                    :style="{ width: `${plan.progress.percent}%` }"
                />
            </div>
        </div>

        <div v-if="plan.exhausted || plan.bookUrl" class="mt-5">
            <p v-if="plan.exhausted" class="text-sm text-amber-700 dark:text-amber-400">{{ plan.limitLabel }}</p>

            <a
                v-else
                :href="plan.bookUrl"
                class="inline-flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-primary/20 transition-colors hover:bg-primary/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
            >
                {{ plan.bookLabel }}
                <span class="h-4 w-4"><AppIcon name="chevron-right" /></span>
            </a>
        </div>
    </section>
</template>
