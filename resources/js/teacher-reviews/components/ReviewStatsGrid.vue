<script setup>
/**
 * The three numbers a teacher checks first: the public average, the lessons
 * behind it and how many reviews support is looking at. Blade formats the
 * average (it owns the rating maths), so this grid never rounds anything.
 */
import { computed } from 'vue';
import { usePanel } from '../../shared/composables/usePanel';
import { useCopy, usePage } from '../composables/usePageContext';

const copy = useCopy();
const page = usePage();
const panel = usePanel();

const stats = computed(() => page.stats ?? {});
const reported = computed(() => Number(stats.value.reported ?? 0));
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-3">
        <div :class="panel">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ copy.stats?.rating }}</p>
            <p class="mt-2 text-3xl font-extrabold tracking-tight text-slate-900 dark:text-slate-100">
                {{ stats.rating }}
                <span class="text-base font-medium text-slate-400">/5</span>
            </p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ stats.reviewsLabel }}</p>
        </div>

        <div :class="panel">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ copy.stats?.lessons }}</p>
            <p class="mt-2 text-3xl font-extrabold tracking-tight text-slate-900 dark:text-slate-100">{{ stats.lessons }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ copy.stats?.lessonsCaption }}</p>
        </div>

        <div :class="panel">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ copy.stats?.reported }}</p>
            <p
                class="mt-2 text-3xl font-extrabold tracking-tight"
                :class="reported > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-900 dark:text-slate-100'"
            >
                {{ stats.reported }}
            </p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ copy.stats?.reportedCaption }}</p>
        </div>
    </div>
</template>
