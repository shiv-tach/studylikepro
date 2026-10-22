<script setup>
import { computed } from 'vue';
import { usePanel } from '../../shared/composables/usePanel';
import { CHIP } from '../../shared/styles';
import AppIcon from '../../shared/components/AppIcon.vue';

const props = defineProps({
    lesson: { type: Object, default: null },
    urls: { type: Object, required: true },
});

// Booking statuses carry their own Tailwind classes in the PHP enum, which sits
// outside Tailwind's scanned paths, so the dashboard keeps its own copy here.
const statusClasses = {
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

const panel = usePanel(Boolean(props.lesson));
const status = computed(() => statusClasses[props.lesson?.status] ?? statusClasses.cancelled);
</script>

<template>
    <section :class="panel" aria-labelledby="dashboard-next-lesson">
        <div class="flex items-center justify-between gap-3">
            <h2 id="dashboard-next-lesson" class="text-base font-bold text-slate-800 dark:text-slate-200">
                {{ lesson ? 'Next lesson' : 'Your lessons' }}
            </h2>
            <a :href="urls.bookings" class="text-xs font-semibold text-primary hover:underline">All lessons</a>
        </div>

        <template v-if="lesson">
            <div class="mt-4 flex flex-wrap items-center gap-2">
                <span :class="[CHIP, status]">{{ lesson.statusLabel }}</span>
                <span v-if="lesson.payBy" :class="[CHIP, 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400']">
                    Pay within {{ lesson.payBy }}
                </span>
                <span :class="[CHIP, 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400']">
                    {{ lesson.durationMinutes }} min
                </span>
            </div>

            <h3 class="mt-3 text-lg font-bold text-slate-800 dark:text-slate-100">{{ lesson.title }}</h3>
            <p v-if="lesson.teacher" class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                with {{ lesson.teacher }}
            </p>

            <div class="mt-4 flex flex-wrap items-center gap-4 text-sm text-slate-600 dark:text-slate-300">
                <span class="inline-flex items-center gap-2">
                    <span class="h-4 w-4 text-primary"><AppIcon name="clock" /></span>
                    {{ lesson.startsAt }}–{{ lesson.endsAt }}
                </span>
            </div>

            <div class="mt-5 flex flex-wrap gap-3">
                <a
                    v-if="lesson.joinUrl"
                    :href="lesson.joinUrl"
                    class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/20 transition-transform hover:-translate-y-0.5"
                >
                    <span class="h-4 w-4"><AppIcon name="video" /></span>
                    Join now
                </a>
                <a
                    :href="lesson.url"
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-200/80 px-4 py-2.5 text-sm font-semibold text-slate-600 transition-colors hover:border-primary/40 hover:text-primary dark:border-slate-800/80 dark:text-slate-300"
                >
                    Lesson details
                </a>
            </div>
        </template>

        <template v-else>
            <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">
                Nothing booked yet. Pick a verified teacher, choose one of their open slots, and your lesson appears
                here the moment you book it.
            </p>
            <div class="mt-5 flex flex-wrap gap-3">
                <a
                    :href="urls.teachers"
                    class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/20 transition-transform hover:-translate-y-0.5"
                >
                    <span class="h-4 w-4"><AppIcon name="users" /></span>
                    Find a teacher
                </a>
            </div>
        </template>
    </section>
</template>
