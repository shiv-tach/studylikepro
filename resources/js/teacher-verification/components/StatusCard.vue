<script setup>
/**
 * Where the application stands right now: not submitted yet, under review,
 * approved, or sent back for changes. The rejected state repeats the reviewer's
 * notes, because that is the only place the teacher can read them.
 */
import { computed } from 'vue';
import { usePanel } from '../../shared/composables/usePanel';
import AppIcon from '../../shared/components/AppIcon.vue';
import UiChip from '../../shared/components/UiChip.vue';
import { useCopy } from '../composables/usePageContext';

const props = defineProps({
    status: { type: Object, required: true },
    profile: { type: Object, default: () => ({}) },
});

const copy = useCopy();
const panel = usePanel();

const STATES = {
    draft: { icon: 'shield', tile: 'bg-primary/10 text-primary', chip: 'primary', key: 'draft' },
    pending: { icon: 'clock', tile: 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300', chip: 'amber', key: 'pending' },
    approved: { icon: 'shield-check', tile: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300', chip: 'emerald', key: 'approved' },
    rejected: { icon: 'alert', tile: 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300', chip: 'rose', key: 'rejected' },
};

const state = computed(() => STATES[props.status?.current] ?? STATES.draft);

const title = computed(() => copy.statusTitles?.[state.value.key] ?? copy.statusTitles?.draft);
const body = computed(() => {
    const template = copy.statusBodies?.[state.value.key] ?? '';

    return template
        .replace(':submitted', props.profile?.submittedAt ?? '')
        .replace(':verified', props.profile?.verifiedAt ?? '');
});
</script>

<template>
    <section :class="panel">
        <div class="flex flex-wrap items-start gap-4">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl p-3" :class="state.tile">
                <AppIcon :name="state.icon" />
            </span>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ title }}</h3>
                    <UiChip :tone="state.chip">{{ status.label }}</UiChip>
                </div>

                <p class="mt-1 text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ body }}</p>

                <div
                    v-if="status.current === 'rejected' && profile.notes"
                    class="mt-4 rounded-xl border border-rose-200/80 bg-rose-50 p-4 text-sm leading-relaxed text-rose-800 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300"
                    role="note"
                >
                    <p class="text-xs font-bold uppercase tracking-wider">{{ copy.reviewerNotes }}</p>
                    <p class="mt-1">{{ profile.notes }}</p>
                </div>
            </div>
        </div>
    </section>
</template>
