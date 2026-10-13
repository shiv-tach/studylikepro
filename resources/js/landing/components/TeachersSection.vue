<script setup>
import AppIcon from './AppIcon.vue';
import { profileHighlights, profilePreview } from '../content';

defineProps({
    urls: { type: Object, required: true },
});
</script>

<template>
    <section id="teachers" class="border-t border-slate-200/80 dark:border-slate-800/80">
        <div class="mx-auto w-full max-w-6xl px-4 py-16 sm:px-6 lg:px-8 lg:py-24">
            <div class="grid gap-12 lg:grid-cols-2 lg:items-center lg:gap-16">
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest text-primary">Teachers</p>
                    <h2 class="mt-3 text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl dark:text-white">
                        Every tutor is checked before they teach
                    </h2>
                    <p class="mt-3 text-slate-600 dark:text-slate-400">
                        Teacher accounts are created by invitation. Each tutor completes their profile, submits
                        verification documents, and is approved by our team before a single lesson can be booked. On the
                        student side that means one thing: the teachers you see are the teachers we vetted.
                    </p>

                    <ul class="mt-6 flex flex-col gap-3">
                        <li
                            v-for="highlight in profileHighlights"
                            :key="highlight.label"
                            class="flex items-start gap-3 text-sm text-slate-600 dark:text-slate-400"
                        >
                            <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <AppIcon :name="highlight.icon" class="h-3.5 w-3.5" />
                            </span>
                            {{ highlight.label }}
                        </li>
                    </ul>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <a
                            :href="urls.teachers"
                            class="rounded-xl bg-primary px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-primary/25 transition hover:bg-primary/90"
                        >
                            Browse teacher profiles
                        </a>
                        <a
                            :href="urls.contact"
                            class="rounded-xl border border-slate-200/80 bg-white px-6 py-3 text-sm font-semibold text-slate-700 transition hover:border-primary/40 hover:text-primary dark:border-slate-800/80 dark:bg-slate-900 dark:text-slate-300"
                        >
                            Apply for a teacher invite
                        </a>
                    </div>
                </div>

                <div v-reveal="100" class="relative">
                    <div class="absolute -inset-4 -z-10 rounded-3xl bg-gradient-to-tr from-primary/10 via-transparent to-purple-500/10 blur-2xl"></div>

                    <div
                        class="rounded-3xl border border-slate-200/80 bg-white/90 p-6 shadow-xl shadow-primary/5 backdrop-blur-md dark:border-slate-800/80 dark:bg-slate-900/90"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-xs font-bold uppercase tracking-widest text-slate-400">Profile preview</h3>
                            <span class="flex items-center gap-1 rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-600 dark:text-emerald-400">
                                <AppIcon name="shield-check" class="h-3 w-3" />
                                Verified
                            </span>
                        </div>

                        <div class="mt-5 flex items-center gap-4">
                            <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-primary to-purple-600 text-lg font-bold text-white">
                                {{ profilePreview.headline.charAt(0) }}
                            </span>
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-slate-900 dark:text-white">{{ profilePreview.headline }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ profilePreview.grades }}</p>
                            </div>
                        </div>

                        <dl class="mt-5 divide-y divide-slate-200/80 dark:divide-slate-800/80">
                            <div class="flex items-start justify-between gap-4 py-3">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Lessons</dt>
                                <dd class="text-right text-sm text-slate-600 dark:text-slate-400">{{ profilePreview.lessons }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-4 py-3">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Pricing</dt>
                                <dd class="text-right text-sm text-slate-600 dark:text-slate-400">{{ profilePreview.rate }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-4 py-3">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Availability</dt>
                                <dd class="text-right text-sm text-slate-600 dark:text-slate-400">{{ profilePreview.availability }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-4 py-3">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Reputation</dt>
                                <dd class="text-right text-sm text-slate-600 dark:text-slate-400">{{ profilePreview.rating }}</dd>
                            </div>
                        </dl>

                        <p class="mt-4 text-[11px] text-slate-400 dark:text-slate-500">
                            Sample layout of a tutor profile page — not a specific teacher.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
