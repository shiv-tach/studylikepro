<script setup>
/**
 * The two request queues: what is waiting for an admin decision, and what was
 * not approved (with the note the admin wrote).
 */
import { usePanel } from '../../shared/composables/usePanel';
import UiSectionHeading from '../../shared/components/UiSectionHeading.vue';
import { useCopy, useWorkspace } from '../composables/usePageContext';
import RequestRow from './RequestRow.vue';

defineProps({
    pending: { type: Array, default: () => [] },
    rejected: { type: Array, default: () => [] },
});

const workspace = useWorkspace();
const copy = useCopy();
const panel = usePanel();
</script>

<template>
    <div class="space-y-6">
        <section :class="panel">
            <UiSectionHeading
                :title="copy.requests?.pendingTitle"
                :description="copy.requests?.pendingHint"
                icon="clock"
                icon-tone="bg-amber-100 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400"
                level="h2"
            />

            <ul v-if="pending.length" class="mt-4 space-y-2">
                <RequestRow v-for="item in pending" :key="item.key" :item="item" />
            </ul>

            <p v-else class="mt-4 rounded-xl border border-dashed border-slate-300 p-4 text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                {{ copy.requests?.empty }}
            </p>
        </section>

        <section v-if="rejected.length" :class="panel">
            <UiSectionHeading
                :title="copy.requests?.rejectedTitle"
                :description="copy.requests?.rejectedHint"
                icon="alert"
                icon-tone="bg-rose-100 text-rose-600 dark:bg-rose-950/40 dark:text-rose-400"
                level="h2"
            />

            <ul class="mt-4 space-y-2">
                <RequestRow
                    v-for="item in rejected"
                    :key="item.key"
                    :item="item"
                    @reapply="workspace.reapply($event)"
                />
            </ul>
        </section>
    </div>
</template>
