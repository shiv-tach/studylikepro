<script setup>
/**
 * Every session booked against the order, newest last, exactly as Blade ordered
 * them. Each row is the way into the session console — where the classroom,
 * start/complete controls and the student's thread live — so this page never
 * duplicates those actions.
 */
import { usePanel } from '../../shared/composables/usePanel';
import { HEADING_TEXT, MUTED_TEXT } from '../../shared/styles';
import AppIcon from '../../shared/components/AppIcon.vue';
import UiChip from '../../shared/components/UiChip.vue';
import { useCopy } from '../composables/usePageContext';

defineProps({
    sessions: { type: Array, default: () => [] },
});

const copy = useCopy();
const panel = usePanel();
</script>

<template>
    <section :class="panel" aria-labelledby="teacher-order-sessions-heading">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
                <h2 id="teacher-order-sessions-heading" :class="['text-sm font-bold', HEADING_TEXT]">
                    {{ copy.panels?.sessions }}
                </h2>
                <p :class="['mt-1 text-xs', MUTED_TEXT]">{{ copy.panels?.sessionsIntro }}</p>
            </div>

            <UiChip tone="slate">{{ sessions.length }}</UiChip>
        </div>

        <p v-if="!sessions.length" class="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
            {{ copy.sessions?.empty }}
        </p>

        <ul v-else class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
            <li v-for="session in sessions" :key="session.id" class="flex flex-wrap items-start justify-between gap-3 py-4">
                <div class="min-w-0 flex-1">
                    <a :href="session.url" class="group block focus:outline-none focus-visible:underline">
                        <p class="text-sm font-semibold text-slate-700 transition-colors group-hover:text-primary dark:text-slate-200">
                            {{ session.when }} · {{ session.durationLabel }}
                        </p>
                        <p class="mt-0.5 text-xs text-slate-400">
                            {{ session.learnerName }}<template v-if="session.scope"> · {{ session.scope }}</template>
                        </p>
                    </a>

                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <a
                            :href="session.url"
                            class="inline-flex items-center gap-1 rounded-lg border border-slate-200/80 px-2.5 py-1 text-[11px] font-semibold text-slate-600 transition-colors hover:border-primary/40 hover:text-primary dark:border-slate-800 dark:text-slate-300"
                        >
                            {{ copy.sessions?.open }}
                            <span class="h-3 w-3" aria-hidden="true"><AppIcon name="chevron-right" /></span>
                        </a>

                        <a
                            v-if="session.joinable"
                            :href="session.classroomUrl"
                            class="inline-flex items-center gap-1 rounded-lg bg-primary px-2.5 py-1 text-[11px] font-semibold text-white shadow-sm transition-colors hover:bg-primary/90"
                        >
                            <span class="h-3 w-3" aria-hidden="true"><AppIcon name="video" /></span>
                            {{ copy.sessions?.join }}
                        </a>

                        <a
                            v-if="session.messagesUrl"
                            :href="session.messagesUrl"
                            class="inline-flex items-center gap-1 rounded-lg border border-slate-200/80 px-2.5 py-1 text-[11px] font-semibold text-slate-600 transition-colors hover:border-primary/40 hover:text-primary dark:border-slate-800 dark:text-slate-300"
                        >
                            <span class="h-3 w-3" aria-hidden="true"><AppIcon name="chat" /></span>
                            {{ copy.sessions?.message }}
                        </a>
                    </div>
                </div>

                <UiChip :tone="session.status.tone">{{ session.status.label }}</UiChip>
            </li>
        </ul>
    </section>
</template>
