<script setup>
/**
 * The forward-looking rail: confirmed → classroom → delivered → settled. The
 * current stage is highlighted and carries its own real action, so "where are
 * we and what comes next" is readable without opening anything else.
 */
import { computed } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';
import { usePanel } from '../../shared/composables/usePanel';
import { useCopy, useCsrfToken } from '../composables/usePageContext';
import InlineActionForm from './InlineActionForm.vue';

const props = defineProps({
    steps: { type: Array, default: () => [] },
});

const emit = defineEmits(['cancel']);

const copy = useCopy();
const token = useCsrfToken();
const panel = usePanel();

const STATE_CLASSES = {
    done: {
        card: '',
        tile: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300',
        label: 'text-slate-800 dark:text-slate-100',
    },
    current: {
        card: 'ring-2 ring-primary/30',
        tile: 'bg-primary text-white shadow-sm shadow-primary/20',
        label: 'text-slate-900 dark:text-slate-50',
    },
    upcoming: {
        card: '',
        tile: 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
        label: 'text-slate-600 dark:text-slate-300',
    },
    skipped: {
        card: 'opacity-60',
        tile: 'bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500',
        label: 'text-slate-500 line-through dark:text-slate-400',
    },
};

function classes(step) {
    return STATE_CLASSES[step.state] ?? STATE_CLASSES.upcoming;
}

const closed = computed(() => props.steps.every((step) => step.state === 'skipped' || step.state === 'done'));
</script>

<template>
    <section class="space-y-4" aria-labelledby="booking-journey-title">
        <div class="flex flex-wrap items-end justify-between gap-2">
            <div>
                <h2 id="booking-journey-title" class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ copy.journey?.title }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ copy.journey?.description }}</p>
            </div>
            <p v-if="closed" class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ copy.journey?.closed }}</p>
        </div>

        <ol class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <li
                v-for="(step, index) in steps"
                :key="step.key"
                :class="[panel, 'flex flex-col gap-2 !p-4', classes(step).card]"
            >
                <div class="flex items-center justify-between gap-2">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg p-1.5" :class="classes(step).tile" aria-hidden="true">
                        <AppIcon :name="step.state === 'done' ? 'check' : step.state === 'current' ? 'play' : 'clock'" />
                    </span>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ index + 1 }}</span>
                </div>

                <p class="text-sm font-bold" :class="classes(step).label">{{ step.label }}</p>
                <p class="text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ step.description }}</p>

                <p v-if="step.at" class="text-xs font-semibold tabular-nums text-slate-400">{{ step.at }}</p>

                <p v-if="step.state === 'current'" class="mt-auto text-[11px] font-bold uppercase tracking-wider text-primary">
                    {{ copy.next?.title }}
                </p>

                <div v-if="step.action" class="mt-2">
                    <InlineActionForm
                        v-if="step.action.kind === 'form'"
                        :action="step.action.url"
                        :token="token"
                        :label="step.action.label"
                        :tone="step.action.tone"
                        :confirm="Boolean(step.action.confirm)"
                        :confirm-title="copy.actions?.confirmNoShowTitle"
                        :confirm-body="copy.actions?.confirmNoShowBody"
                        :confirm-label="copy.actions?.confirm"
                        :cancel-label="copy.actions?.keep"
                        :busy-label="copy.actions?.busy"
                    />
                    <button
                        v-else
                        type="button"
                        class="text-xs font-semibold text-rose-600 hover:underline dark:text-rose-300"
                        @click="emit('cancel')"
                    >
                        {{ step.action.label }}
                    </button>
                </div>
            </li>
        </ol>
    </section>
</template>
