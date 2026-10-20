<script setup>
import AppIcon from './AppIcon.vue';
import { features } from '../content';
</script>

<template>
    <section id="features" class="scroll-mt-20 border-b border-line bg-ink-50 py-16 lg:py-24">
        <div class="mx-auto w-full max-w-6xl px-5">
            <p class="text-sm font-medium text-brand-600">Why Studylikepro</p>
            <h2 class="mt-3 max-w-3xl text-display-lg font-semibold">Built for real 1-on-1 learning</h2>
            <p class="mt-5 max-w-2xl text-lg leading-relaxed text-ink-700">
                Every part of the journey is designed to get you unstuck quickly — and to keep quality high for
                students and teachers alike.
            </p>

            <div class="mt-10 grid gap-px overflow-hidden rounded-2xl border border-line bg-line sm:grid-cols-2 lg:grid-cols-3">
                <article
                    v-for="(feature, index) in features"
                    :key="feature.title"
                    v-reveal="index * 60"
                    class="lift flex flex-col bg-white p-7"
                >
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700">
                        <AppIcon :name="feature.icon" class="h-5 w-5" />
                    </span>
                    <h3 class="mt-5 font-display text-lg font-semibold text-ink">{{ feature.title }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-ink-700">{{ feature.description }}</p>
                </article>
            </div>
        </div>
    </section>
</template>
