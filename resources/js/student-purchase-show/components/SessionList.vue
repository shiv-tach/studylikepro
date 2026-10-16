<script setup>
/**
 * The sessions booked against this purchase. Each row is a real link to the
 * lesson it belongs to, so what the plan bought is one tap away.
 */
import { usePanel } from '../../shared/composables/usePanel';
import UiChip from '../../shared/components/UiChip.vue';

defineProps({
    sessions: { type: Object, required: true },
});

const panel = usePanel();
</script>

<template>
    <section :class="panel" aria-labelledby="purchase-sessions-heading">
        <h2 id="purchase-sessions-heading" class="text-sm font-bold text-slate-800 dark:text-slate-100">
            {{ sessions.title }}
        </h2>

        <ul class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
            <li
                v-for="session in sessions.items"
                :key="session.id"
                class="flex flex-wrap items-center justify-between gap-3 py-3"
            >
                <a :href="session.url" class="group flex-1">
                    <p class="text-sm font-semibold text-slate-700 group-hover:text-primary dark:text-slate-200">
                        {{ session.when }} · {{ session.durationLabel }}
                    </p>
                    <p v-if="session.scope" class="text-xs text-slate-400">{{ session.scope }}</p>
                </a>

                <UiChip :tone="session.status.tone">{{ session.status.label }}</UiChip>
            </li>
        </ul>
    </section>
</template>
