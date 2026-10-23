<script setup>
/**
 * The purchase this lesson was booked from: what is left of the plan, the
 * sibling sessions (the one being viewed is marked) and the way to spend
 * another session. Blade decides the balance, the permission and the URLs, so
 * this panel never recomputes money or re-checks a rule.
 */
import { usePanel } from '../../shared/composables/usePanel';
import AppIcon from '../../shared/components/AppIcon.vue';
import UiChip from '../../shared/components/UiChip.vue';

defineProps({
    plan: { type: Object, required: true },
});

const panel = usePanel();

const primaryClass =
    'inline-flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-primary/20 transition-colors hover:bg-primary/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40';
const outlineClass =
    'inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800';
</script>

<template>
    <section :class="panel" aria-labelledby="lesson-plan-heading">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h3 id="lesson-plan-heading" class="text-sm font-bold text-slate-800 dark:text-slate-100">
                    {{ plan.title }}
                </h3>
                <p v-if="plan.sessionsLeft" class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    {{ plan.sessionsLeft }}
                </p>
            </div>

            <div v-if="plan.balance" class="text-right">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ plan.balance.label }}</p>
                <p class="mt-1 text-2xl font-extrabold tracking-tight text-primary">{{ plan.balance.value }}</p>
            </div>
        </div>

        <div v-if="plan.balance" class="mt-4">
            <div class="flex items-center justify-between text-xs text-slate-400">
                <span>{{ plan.balance.caption }}</span>
                <span class="tabular-nums">{{ plan.balance.percent }}%</span>
            </div>
            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                <div
                    class="h-full rounded-full bg-primary transition-all duration-300"
                    :style="{ width: `${plan.balance.percent}%` }"
                />
            </div>
        </div>

        <h4 class="mt-6 border-t border-slate-200 pt-5 text-xs font-semibold uppercase tracking-wider text-slate-400 dark:border-slate-800">
            {{ plan.sessionsTitle }}
        </h4>

        <ul class="mt-2 divide-y divide-slate-100 dark:divide-slate-800">
            <li
                v-for="session in plan.sessions"
                :key="session.id"
                class="flex flex-wrap items-center justify-between gap-3 py-3"
            >
                <a :href="session.url" class="group flex-1">
                    <p class="text-sm font-semibold text-slate-700 group-hover:text-primary dark:text-slate-200">
                        {{ session.when }} · {{ session.durationLabel }}
                        <span v-if="session.current" class="text-xs font-bold text-primary">· {{ plan.currentLabel }}</span>
                    </p>
                    <p v-if="session.scope" class="text-xs text-slate-400">{{ session.scope }}</p>
                </a>

                <UiChip :tone="session.status.tone" :icon="session.status.icon">{{ session.status.label }}</UiChip>
            </li>
        </ul>

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <a v-if="plan.bookUrl" :href="plan.bookUrl" :class="primaryClass">
                {{ plan.bookLabel }}
                <span class="h-4 w-4"><AppIcon name="chevron-right" /></span>
            </a>
            <p v-else-if="plan.exhausted" class="text-sm text-amber-700 dark:text-amber-400">{{ plan.limitLabel }}</p>

            <a :href="plan.purchaseUrl" :class="outlineClass">{{ plan.purchaseLabel }}</a>
        </div>
    </section>
</template>
