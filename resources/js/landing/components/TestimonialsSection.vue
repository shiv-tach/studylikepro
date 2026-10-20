<script setup>
import AppIcon from './AppIcon.vue';
import { testimonials } from '../content';
</script>

<template>
    <section id="reviews" class="scroll-mt-20 border-b border-line bg-ink-50 py-16 lg:py-24">
        <div class="mx-auto w-full max-w-6xl px-5">
            <p class="text-sm font-medium text-brand-600">Reviews</p>
            <h2 class="mt-3 max-w-3xl text-display-lg font-semibold">Ratings come from finished lessons</h2>
            <p class="mt-5 max-w-2xl text-lg leading-relaxed text-ink-700">
                After every lesson the student rates the teacher and can leave a review, so the next student can
                choose with real feedback in front of them.
            </p>

            <div class="mt-10 grid gap-5 lg:grid-cols-3">
                <figure
                    v-for="(testimonial, index) in testimonials"
                    :key="testimonial.quote"
                    v-reveal="index * 80"
                    class="lift hairline flex h-full flex-col justify-between rounded-2xl bg-white p-6"
                >
                    <div>
                        <div class="flex items-center gap-1 text-amber-400">
                            <AppIcon v-for="star in 5" :key="star" name="star" class="h-3.5 w-3.5" />
                        </div>
                        <blockquote class="mt-4 text-sm leading-relaxed text-ink-700">
                            “{{ testimonial.quote }}”
                        </blockquote>
                    </div>
                    <figcaption class="mt-6 flex items-center gap-3">
                        <span class="flex size-9 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700">
                            {{ testimonial.name.charAt(0) }}
                        </span>
                        <span>
                            <span class="block text-sm font-semibold text-ink">{{ testimonial.name }}</span>
                            <span class="block text-xs text-ink-500">{{ testimonial.meta }}</span>
                        </span>
                    </figcaption>
                </figure>
            </div>
        </div>
    </section>
</template>
