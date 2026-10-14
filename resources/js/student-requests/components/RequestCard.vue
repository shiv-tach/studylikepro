<script setup>
/**
 * One tutoring request: where it stands, what was asked, and the times the
 * student offered. Everything shown here arrives formatted from Blade, so the
 * card never guesses a label or a URL.
 */
import { computed } from 'vue';
import { usePanel } from '../../shared/composables/usePanel';
import { CHIP } from '../../shared/styles';
import AppIcon from '../../shared/components/AppIcon.vue';
import { useCopy } from '../composables/usePageContext';

const props = defineProps({
    request: { type: Object, required: true },
});

// The statuses carry their own Tailwind classes in the PHP enums, which sit
// outside Tailwind's scanned paths, so the card keeps its own copy here.
const statusTones = {
    open: 'bg-primary/10 text-primary',
    matched: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    cancelled: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
    expired: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    closed: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
};

const classificationTones = {
    pending: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
    completed: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    low_confidence: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    failed: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
};

// Two windows is enough to see the intent; the rest wait on the detail page.
const WINDOWS_SHOWN = 2;

const copy = useCopy();
const panel = usePanel(true);
const statusTone = computed(() => statusTones[props.request.status] ?? statusTones.closed);
const classificationTone = computed(
    () => classificationTones[props.request.classification?.status] ?? classificationTones.pending,
);
const windows = computed(() => props.request.windows ?? []);
const hiddenWindows = computed(() => Math.max(windows.value.length - WINDOWS_SHOWN, 0));
</script>

<template>
    <article :class="[panel, 'group flex flex-col gap-3']">
        <div class="flex flex-wrap items-center gap-2">
            <span :class="[CHIP, statusTone]">{{ request.statusLabel }}</span>

            <span v-if="request.subject" :class="[CHIP, 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300']">
                <span aria-hidden="true">{{ request.subject.icon }}</span>
                {{ request.subject.name }}
            </span>

            <span
                v-if="request.classification"
                :class="[CHIP, classificationTone]"
            >
                <span class="h-3.5 w-3.5 shrink-0"><AppIcon name="sparkles" /></span>
                {{ request.classification.label }}
            </span>

            <span v-if="request.proposals" :class="[CHIP, 'bg-primary/10 text-primary']">
                <span class="h-3.5 w-3.5 shrink-0"><AppIcon name="users" /></span>
                {{ request.proposals.label }}
            </span>
        </div>

        <div>
            <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">
                <a
                    :href="request.url"
                    class="rounded-md transition-colors hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
                >
                    {{ request.title }}
                </a>
            </h3>

            <p
                v-if="request.showDescription"
                class="mt-2 line-clamp-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400"
            >
                {{ request.description }}
            </p>
        </div>

        <div v-if="windows.length">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">
                {{ copy.preferredTimes }}
            </p>
            <ul class="mt-2 flex flex-wrap gap-1.5">
                <li
                    v-for="window in windows.slice(0, WINDOWS_SHOWN)"
                    :key="window"
                    :class="[CHIP, 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300']"
                >
                    <span class="h-3.5 w-3.5 shrink-0"><AppIcon name="clock" /></span>
                    {{ window }}
                </li>
                <li v-if="hiddenWindows" :class="[CHIP, 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400']">
                    +{{ hiddenWindows }}
                </li>
            </ul>
        </div>

        <div class="mt-auto flex flex-wrap items-center justify-between gap-3 pt-1">
            <p class="flex flex-wrap items-center gap-2 text-xs text-slate-400">
                <span>{{ request.createdLabel }}</span>
                <span
                    v-if="request.expiresLabel"
                    :class="[CHIP, 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300']"
                >
                    {{ request.expiresLabel }}
                </span>
            </p>

            <a
                :href="request.url"
                class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary transition-colors hover:text-primary/80"
            >
                {{ request.proposals ? copy.reviewProposals : copy.viewRequest }}
                <span class="h-3.5 w-3.5 transition-transform duration-300 group-hover:translate-x-0.5">
                    <AppIcon name="arrow-right" />
                </span>
            </a>
        </div>
    </article>
</template>
