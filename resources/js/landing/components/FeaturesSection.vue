<script setup>
import AppIcon from './AppIcon.vue';
import { features } from '../content';
</script>

<template>
    <section id="features" class="border-t border-slate-200/80 bg-white dark:border-slate-800/80 dark:bg-slate-900">
        <div class="mx-auto w-full max-w-6xl px-4 py-16 sm:px-6 lg:px-8 lg:py-24">
            <div class="max-w-2xl">
                <p class="text-xs font-bold uppercase tracking-widest text-primary">Why Studylikepro</p>
                <h2 class="mt-3 text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl dark:text-white">
                    Built for real 1-on-1 learning
                </h2>
                <p class="mt-3 text-slate-600 dark:text-slate-400">
                    Every part of the journey is designed to get you unstuck quickly — and to keep quality high for
                    students and teachers alike.
                </p>
            </div>

            <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <article
                    v-for="(feature, index) in features"
                    :key="feature.title"
                    v-reveal="index * 60"
                    class="group rounded-2xl border border-slate-200/80 bg-slate-50/50 p-6 transition-all hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-lg hover:shadow-primary/5 dark:border-slate-800/80 dark:bg-slate-950/50"
                >
                    <span
                        class="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-white"
                    >
                        <AppIcon :name="feature.icon" class="h-5 w-5" />
                    </span>
                    <h3 class="mt-4 text-sm font-bold text-slate-900 dark:text-white">{{ feature.title }}</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ feature.description }}</p>
                </article>
            </div>
        </div>
    </section>
</template>
