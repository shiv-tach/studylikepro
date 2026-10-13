<script setup>
import { ref } from 'vue';
import AppIcon from './AppIcon.vue';
import { trustPoints } from '../content';

defineProps({
    urls: { type: Object, required: true },
});

const slots = [
    { id: 'today', label: 'Today', time: '4:30 PM' },
    { id: 'tomorrow', label: 'Tomorrow', time: '9:00 AM' },
    { id: 'saturday', label: 'Saturday', time: '10:00 AM' },
];

const selectedSlot = ref(slots[0]);
</script>

<template>
    <section id="top" class="relative overflow-hidden">
        <div class="bg-pattern-grid absolute inset-0"></div>
        <div class="pointer-events-none absolute -top-32 right-0 h-80 w-80 rounded-full bg-primary/10 blur-3xl"></div>
        <div class="pointer-events-none absolute -left-24 bottom-0 h-72 w-72 rounded-full bg-purple-500/10 blur-3xl"></div>

        <div
            class="relative mx-auto grid w-full max-w-6xl gap-14 px-4 pb-16 pt-12 sm:px-6 lg:grid-cols-[1.05fr_1fr] lg:items-center lg:gap-16 lg:px-8 lg:pb-24 lg:pt-20"
        >
            <div>
                <span
                    class="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-xs font-semibold text-primary"
                >
                    <span class="relative flex h-1.5 w-1.5">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-primary opacity-70"></span>
                        <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-primary"></span>
                    </span>
                    AI-powered tutor matching
                </span>

                <h1 class="mt-5 text-4xl font-extrabold leading-[1.08] tracking-tight text-slate-900 sm:text-5xl dark:text-white">
                    Stuck on a question?
                    <span class="bg-gradient-to-r from-primary to-purple-600 bg-clip-text text-transparent">
                        Get a verified tutor
                    </span>
                    instead of a guess.
                </h1>

                <p class="mt-5 max-w-xl text-lg leading-relaxed text-slate-600 dark:text-slate-400">
                    Upload a photo of the question, let AI pinpoint the lesson, and book a live 1-on-1 lesson with a
                    verified Sri Lankan teacher. Pay securely in rupees — and keep the receipt.
                </p>

                <div class="mt-8 flex flex-wrap gap-3">
                    <a
                        :href="urls.registerStudent"
                        class="rounded-xl bg-primary px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-primary/25 transition hover:bg-primary/90"
                    >
                        I need a tutor
                    </a>
                    <a
                        :href="urls.teachers"
                        class="rounded-xl border border-slate-200/80 bg-white px-6 py-3 text-sm font-semibold text-slate-700 transition hover:border-primary/40 hover:text-primary dark:border-slate-800/80 dark:bg-slate-900 dark:text-slate-300"
                    >
                        Browse verified teachers
                    </a>
                </div>

                <ul class="mt-8 grid gap-2.5 sm:grid-cols-2">
                    <li v-for="point in trustPoints" :key="point" class="flex items-center gap-2.5 text-sm text-slate-600 dark:text-slate-400">
                        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <AppIcon name="check" class="h-3 w-3" />
                        </span>
                        {{ point }}
                    </li>
                </ul>

                <p class="mt-6 text-sm text-slate-500 dark:text-slate-400">
                    Already have an account?
                    <a :href="urls.login" class="font-semibold text-primary hover:underline">Log in</a>
                </p>
            </div>

            <div v-reveal="120" class="relative">
                <div class="absolute -inset-4 -z-10 rounded-3xl bg-gradient-to-br from-primary/15 via-transparent to-purple-500/15 blur-2xl"></div>

                <div
                    class="rounded-3xl border border-slate-200/80 bg-white/90 p-5 shadow-xl shadow-primary/5 backdrop-blur-md dark:border-slate-800/80 dark:bg-slate-900/90 sm:p-6"
                >
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-xs font-bold uppercase tracking-widest text-slate-400">Booking preview</h2>
                        <span
                            class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400"
                        >
                            Illustration
                        </span>
                    </div>

                    <div class="mt-5 flex items-center gap-3 rounded-2xl border border-dashed border-slate-300/80 bg-slate-50/60 p-4 dark:border-slate-700/80 dark:bg-slate-950/40">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white text-primary shadow-sm dark:bg-slate-900">
                            <AppIcon name="camera" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">question-photo.jpg</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Uploaded · waiting for a match</p>
                        </div>
                    </div>

                    <div class="relative py-3">
                        <span class="absolute left-1/2 top-0 h-full w-px -translate-x-1/2 bg-gradient-to-b from-transparent via-primary/30 to-transparent"></span>
                    </div>

                    <div class="flex items-start gap-3 rounded-2xl border border-primary/20 bg-primary/5 p-4">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary text-white">
                            <AppIcon name="sparkles" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-primary">AI match</p>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">O/L Mathematics · Grade 10</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Lesson: Quadratic equations</p>
                        </div>
                    </div>

                    <div class="mt-4 flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-primary to-purple-600 text-sm font-bold text-white">
                            T
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 dark:text-slate-100">
                                Verified teacher
                                <span class="flex h-4 w-4 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400">
                                    <AppIcon name="check" class="h-2.5 w-2.5" />
                                </span>
                            </p>
                            <div class="flex items-center gap-1 text-amber-500">
                                <AppIcon v-for="star in 5" :key="star" name="star" class="h-3 w-3" />
                                <span class="ml-1 text-xs text-slate-500 dark:text-slate-400">from completed lessons</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Published availability</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button
                                v-for="slot in slots"
                                :key="slot.id"
                                type="button"
                                :aria-pressed="selectedSlot.id === slot.id"
                                class="rounded-xl border px-3 py-1.5 text-xs font-semibold transition"
                                :class="selectedSlot.id === slot.id
                                    ? 'border-primary bg-primary/10 text-primary'
                                    : 'border-slate-200/80 text-slate-600 hover:border-primary/40 hover:text-primary dark:border-slate-800/80 dark:text-slate-300'"
                                @click="selectedSlot = slot"
                            >
                                {{ slot.label }} · {{ slot.time }}
                            </button>
                        </div>
                    </div>

                    <a
                        :href="urls.registerStudent"
                        class="mt-5 flex items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-100"
                    >
                        Book {{ selectedSlot.label }} at {{ selectedSlot.time }}
                        <AppIcon name="arrow-right" class="h-4 w-4" />
                    </a>
                    <p class="mt-2 text-center text-[11px] text-slate-400 dark:text-slate-500">
                        Sample layout — real matches come from the catalog.
                    </p>
                </div>
            </div>
        </div>
    </section>
</template>
