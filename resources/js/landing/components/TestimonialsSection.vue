<script setup>
import AppIcon from './AppIcon.vue';
import { testimonials } from '../content';
</script>

<template>
    <section id="reviews" class="border-t border-slate-200/80 bg-white dark:border-slate-800/80 dark:bg-slate-900">
        <div class="mx-auto w-full max-w-6xl px-4 py-16 sm:px-6 lg:px-8 lg:py-24">
            <div class="max-w-2xl">
                <p class="text-xs font-bold uppercase tracking-widest text-primary">Reviews</p>
                <h2 class="mt-3 text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl dark:text-white">
                    Ratings come from finished lessons
                </h2>
                <p class="mt-3 text-slate-600 dark:text-slate-400">
                    After every lesson the student rates the teacher and can leave a review, so the next student can
                    choose with real feedback in front of them.
                </p>
            </div>

            <div class="mt-10 grid gap-5 lg:grid-cols-3">
                <figure
                    v-for="(testimonial, index) in testimonials"
                    :key="testimonial.quote"
                    v-reveal="index * 80"
                    class="flex h-full flex-col justify-between rounded-2xl border border-slate-200/80 bg-slate-50/50 p-6 dark:border-slate-800/80 dark:bg-slate-950/50"
                >
                    <div>
                        <div class="flex items-center gap-1 text-amber-500">
                            <AppIcon v-for="star in 5" :key="star" name="star" class="h-3.5 w-3.5" />
                        </div>
                        <blockquote class="mt-4 text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                            “{{ testimonial.quote }}”
                        </blockquote>
                    </div>
                    <figcaption class="mt-6 flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">
                            {{ testimonial.name.charAt(0) }}
                        </span>
                        <span>
                            <span class="block text-sm font-semibold text-slate-800 dark:text-slate-100">{{ testimonial.name }}</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">{{ testimonial.meta }}</span>
                        </span>
                    </figcaption>
                </figure>
            </div>
        </div>
    </section>
</template>
