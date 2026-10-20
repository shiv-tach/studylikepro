<script setup>
import AppIcon from './AppIcon.vue';
import { profileHighlights, profilePreview } from '../content';

defineProps({
    urls: { type: Object, required: true },
});
</script>

<template>
    <section id="teachers" class="scroll-mt-20 border-b border-line bg-white py-16 lg:py-24">
        <div class="mx-auto grid w-full max-w-6xl gap-10 px-5 lg:grid-cols-[0.85fr_1.15fr] lg:items-center lg:gap-14">
            <div class="min-w-0">
                <p class="text-sm font-medium text-brand-600">Teachers</p>
                <h2 class="mt-3 text-display-lg font-semibold">Every tutor is checked before they teach</h2>
                <p class="mt-5 max-w-xl text-lg leading-relaxed text-ink-700">
                    Teacher accounts are created by invitation. Each tutor completes their profile, submits
                    verification documents, and is approved by our team before a single lesson can be booked. On the
                    student side that means one thing: the teachers you see are the teachers we vetted.
                </p>

                <ul class="mt-7 space-y-3">
                    <li
                        v-for="highlight in profileHighlights"
                        :key="highlight.label"
                        class="flex gap-3 text-sm leading-relaxed text-ink-700"
                    >
                        <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-go/10 text-go-ink">
                            <AppIcon :name="highlight.icon" class="h-3 w-3" />
                        </span>
                        {{ highlight.label }}
                    </li>
                </ul>

                <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <a
                        :href="urls.teachers"
                        class="shrink-0 rounded-full bg-brand-500 px-7 py-3.5 text-center font-medium whitespace-nowrap text-white transition hover:bg-brand-600"
                    >
                        Browse teacher profiles
                    </a>
                    <a
                        :href="urls.contact"
                        class="hairline inline-flex shrink-0 items-center justify-center rounded-full bg-white px-7 py-3.5 text-center font-medium whitespace-nowrap text-ink transition hover:border-ink-500"
                    >
                        Apply for a teacher invite
                    </a>
                </div>
            </div>

            <figure
                v-reveal="100"
                class="hairline w-full overflow-hidden rounded-2xl bg-white"
                style="box-shadow: var(--shadow-lg)"
            >
                <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 border-b border-line px-6 py-4">
                    <figcaption class="text-sm font-semibold text-ink">Tutor profile preview</figcaption>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-go/10 px-2.5 py-1 text-xs font-semibold text-go-ink">
                        <AppIcon name="shield-check" class="h-3.5 w-3.5" />
                        Verified
                    </span>
                </div>

                <div class="p-6">
                    <div class="flex items-center gap-4">
                        <span class="flex size-14 items-center justify-center rounded-2xl bg-brand-50 font-display text-lg font-semibold text-brand-700">
                            {{ profilePreview.headline.charAt(0) }}
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-ink">{{ profilePreview.headline }}</p>
                            <p class="text-xs text-ink-500">{{ profilePreview.grades }}</p>
                        </div>
                    </div>

                    <dl class="mt-5 divide-y divide-line">
                        <div class="flex items-start justify-between gap-4 py-3">
                            <dt class="text-xs font-medium text-ink-500">Lessons</dt>
                            <dd class="max-w-[60%] text-right text-sm text-ink-700">{{ profilePreview.lessons }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4 py-3">
                            <dt class="text-xs font-medium text-ink-500">Pricing</dt>
                            <dd class="max-w-[60%] text-right text-sm text-ink-700">{{ profilePreview.rate }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4 py-3">
                            <dt class="text-xs font-medium text-ink-500">Availability</dt>
                            <dd class="max-w-[60%] text-right text-sm text-ink-700">{{ profilePreview.availability }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4 py-3">
                            <dt class="text-xs font-medium text-ink-500">Reputation</dt>
                            <dd class="max-w-[60%] text-right text-sm text-ink-700">{{ profilePreview.rating }}</dd>
                        </div>
                    </dl>

                    <p class="mt-4 text-xs text-ink-500">
                        Sample layout of a tutor profile page — not a specific teacher.
                    </p>
                </div>
            </figure>
        </div>
    </section>
</template>
