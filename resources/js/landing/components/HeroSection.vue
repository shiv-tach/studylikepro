<script setup>
import AppIcon from './AppIcon.vue';
import { stats } from '../content';

defineProps({
    urls: { type: Object, required: true },
    image: { type: Object, required: true },
});
</script>

<template>
    <section id="top" class="relative overflow-hidden border-b border-line bg-white">
        <div class="lp-aurora" aria-hidden="true"><span></span><span></span><span></span></div>
        <div class="lp-grid-lines pointer-events-none absolute inset-0" aria-hidden="true"></div>
        <svg
            class="lp-circuit pointer-events-none absolute inset-0 h-full w-full text-brand-300/50"
            viewBox="0 0 1200 600"
            fill="none"
            preserveAspectRatio="xMidYMid slice"
            aria-hidden="true"
        >
            <path class="trace" d="M0 140 H250 L320 90 H560 L620 150 H900" stroke="currentColor" stroke-width="1.5" />
            <path class="trace" d="M0 300 H180 L240 240 H520" stroke="currentColor" stroke-width="1.5" />
            <path class="trace" d="M0 460 H300 L360 520 H640 L700 470 H980" stroke="currentColor" stroke-width="1.5" />
            <path class="trace" d="M120 600 V420 L200 340 H460" stroke="currentColor" stroke-width="1.5" />
        </svg>

        <div
            class="relative mx-auto grid w-full max-w-6xl gap-y-10 px-5 pb-20 pt-14 sm:gap-y-12 lg:grid-cols-[1.05fr_0.95fr] lg:grid-rows-[auto_auto] lg:gap-x-16 lg:gap-y-9 lg:pb-24 lg:pt-24"
        >
            <div class="min-w-0 lg:col-start-1 lg:row-start-1 lg:self-end">
                <p class="hairline inline-flex items-center gap-2 rounded-full bg-white/70 px-3 py-1.5 text-xs font-medium text-brand-700 backdrop-blur">
                    <span class="relative flex size-1.5">
                        <span class="absolute inline-flex size-full animate-ping rounded-full bg-go opacity-70"></span>
                        <span class="relative inline-flex size-1.5 rounded-full bg-go"></span>
                    </span>
                    Verified 1-on-1 tutoring
                </p>

                <h1 class="mt-6 text-display-hero font-semibold">
                    <span class="block text-balance">Stuck on a topic?</span>
                    <span class="block text-balance">
                        Get a <span class="glitch text-gradient" data-text="verified tutor">verified tutor</span> — not a guess.
                    </span>
                </h1>

                <p class="mt-7 max-w-xl text-lg leading-relaxed text-ink-700">
                    Find the lesson for your grade and book a live 1-on-1 lesson with a verified Sri Lankan teacher.
                    Pay securely in rupees — and keep the receipt.
                </p>

                <div class="mt-9 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <a
                        :href="urls.registerStudent"
                        class="shrink-0 rounded-full bg-brand-500 px-7 py-3.5 text-center font-medium whitespace-nowrap text-white transition hover:bg-brand-600"
                        style="box-shadow: var(--shadow-brand)"
                    >
                        I need a tutor
                    </a>
                    <a
                        :href="urls.teachers"
                        class="hairline inline-flex shrink-0 items-center justify-center gap-2 rounded-full bg-white/80 px-7 py-3.5 text-center font-medium whitespace-nowrap text-ink backdrop-blur transition hover:bg-white"
                    >
                        Browse verified teachers
                        <AppIcon name="arrow-right" class="h-4 w-4" />
                    </a>
                </div>
            </div>

            <figure
                v-reveal="120"
                class="mx-auto w-full max-w-md lg:col-start-2 lg:row-span-2 lg:row-start-1 lg:max-w-[32rem] lg:self-center lg:justify-self-end"
            >
                <div
                    class="relative overflow-hidden rounded-[2rem] bg-gradient-to-br from-brand-50 via-white to-brand-200/50 ring-1 ring-black/5"
                    style="box-shadow: var(--shadow-xl)"
                >
                    <picture>
                        <source type="image/webp" :srcset="image.webpSrcset" :sizes="image.sizes" />
                        <img
                            :src="image.src"
                            :srcset="image.srcset"
                            :sizes="image.sizes"
                            :width="image.width"
                            :height="image.height"
                            :alt="image.alt"
                            class="aspect-[4/5] w-full object-cover object-top mix-blend-multiply"
                            loading="eager"
                            fetchpriority="high"
                            decoding="async"
                        />
                    </picture>

                    <div
                        class="absolute left-4 top-4 inline-flex items-center gap-2 rounded-full bg-white/90 py-1.5 pl-1.5 pr-3.5 text-xs font-semibold text-ink ring-1 ring-black/5 backdrop-blur"
                        style="box-shadow: var(--shadow-md)"
                    >
                        <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-go/15 text-go">
                            <AppIcon name="check" class="h-3.5 w-3.5" />
                        </span>
                        Verified Sri Lankan teachers
                    </div>
                </div>

                <figcaption class="mt-3 text-center text-[11px] font-medium text-ink-500 lg:text-right">
                    Live 1-on-1 lessons · Grades 1–13 · Paid in rupees
                </figcaption>
            </figure>

            <div class="min-w-0 lg:col-start-1 lg:row-start-2 lg:self-start">
                <dl class="flex flex-wrap gap-x-9 gap-y-4">
                    <div v-for="stat in stats" :key="stat.value" class="max-w-[12rem]">
                        <dt class="font-display text-xl font-semibold text-ink">{{ stat.value }}</dt>
                        <dd class="mt-1 text-xs leading-relaxed text-ink-500">{{ stat.label }}</dd>
                    </div>
                </dl>

                <p class="mt-6 text-sm text-ink-500">
                    Already have an account?
                    <a :href="urls.login" class="font-medium text-brand-600 underline underline-offset-4">Log in</a>
                </p>
            </div>
        </div>
    </section>
</template>
