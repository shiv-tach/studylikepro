<script setup>
/**
 * The band above the reviews: the teacher's headline numbers and the one link
 * out of the page — the public profile students see. Mirrors the services and
 * subject heroes so the setup screens feel like one product.
 */
import { computed } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';
import { countLabel, format, useCopy, usePage, useUrls } from '../composables/usePageContext';

const copy = useCopy();
const page = usePage();
const urls = useUrls();

const stats = computed(() => page.stats ?? {});

const chips = computed(() => {
    const values = stats.value;
    const list = [];

    // "— /5" says nothing a teacher can use, so an unrated profile only lists
    // the other numbers.
    if (values.ratingCount > 0) {
        list.push({
            key: 'rating',
            icon: 'star',
            value: format(copy.hero?.ratingValue, { rating: values.rating }),
        });
    }

    list.push({
        key: 'reviews',
        icon: 'chat',
        value: countLabel(copy.hero?.reviewsChip, values.ratingCount ?? 0),
    });

    list.push({
        key: 'lessons',
        icon: 'graduation',
        value: countLabel(copy.hero?.lessonsChip, values.lessons ?? 0),
    });

    if (values.reported > 0) {
        list.push({
            key: 'reported',
            icon: 'alert',
            value: countLabel(copy.hero?.reportedChip, values.reported),
        });
    }

    return list;
});
</script>

<template>
    <section
        aria-labelledby="teacher-reviews-hero"
        class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-primary via-primary/80 to-purple-600 p-6 text-white shadow-lg shadow-primary/10 dark:shadow-primary/5 sm:p-8"
    >
        <div class="relative z-10 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-2xl">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-white/70">
                    {{ copy.hero?.eyebrow }}
                </p>
                <h1 id="teacher-reviews-hero" class="mt-1 text-2xl font-extrabold tracking-tight sm:text-3xl">
                    {{ copy.hero?.title }}
                </h1>
                <p class="mt-2 text-sm leading-relaxed text-white/90">
                    {{ copy.hero?.intro }}
                </p>

                <ul v-if="chips.length" class="mt-4 flex flex-wrap gap-2" :aria-label="copy.hero?.statsLabel">
                    <li
                        v-for="chip in chips"
                        :key="chip.key"
                        class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold backdrop-blur-sm"
                    >
                        <span class="h-3.5 w-3.5 shrink-0" aria-hidden="true"><AppIcon :name="chip.icon" /></span>
                        {{ chip.value }}
                    </li>
                </ul>
            </div>

            <div class="flex flex-col items-start gap-3 rounded-2xl border border-white/25 bg-white/10 px-4 py-4 backdrop-blur-sm sm:flex-row sm:items-center">
                <p class="max-w-[15rem] text-xs leading-relaxed text-white/80">
                    {{ copy.hero?.profileNote }}
                </p>

                <a
                    :href="urls.profile"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-white px-4 py-2 text-sm font-bold text-primary shadow-sm shadow-black/10 transition-colors hover:bg-white/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/70"
                >
                    {{ copy.hero?.profileCta }}
                    <span class="h-4 w-4" aria-hidden="true"><AppIcon name="arrow-right" /></span>
                </a>
            </div>
        </div>

        <div class="pointer-events-none absolute -right-6 -top-6 h-36 w-36 rounded-full bg-white/10 blur-xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-10 right-24 h-28 w-28 rounded-full bg-white/10 blur-lg" aria-hidden="true"></div>
    </section>
</template>
