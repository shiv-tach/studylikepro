<script setup>
import AppIcon from './AppIcon.vue';

defineProps({
    urls: { type: Object, required: true },
});
</script>

<template>
    <section class="mx-auto w-full max-w-6xl px-5 py-16 lg:py-20" aria-labelledby="lp-cta-title">
        <div class="relative overflow-hidden rounded-3xl px-6 py-14 text-center text-white sm:px-12" style="background: var(--color-ink)">
            <div class="lp-aurora lp-aurora-dark" aria-hidden="true"><span></span><span></span><span></span></div>
            <div class="lp-grid-lines lp-grid-lines-dark pointer-events-none absolute inset-0" aria-hidden="true"></div>

            <h2 id="lp-cta-title" class="relative text-display-lg font-semibold">Ready to learn like a pro?</h2>
            <p class="relative mx-auto mt-5 max-w-xl text-lg leading-relaxed text-white/70">
                Join as a student to get help with the questions that slow you down. Teachers join by invitation — tell
                us what you teach and we will send you an onboarding link.
            </p>

            <div class="relative mt-8 flex flex-col justify-center gap-3 sm:flex-row sm:items-center">
                <a
                    :href="urls.registerStudent"
                    class="inline-flex shrink-0 items-center justify-center gap-2 rounded-full bg-brand-500 px-7 py-3.5 font-medium whitespace-nowrap text-white transition hover:bg-brand-600"
                    style="box-shadow: var(--shadow-brand)"
                >
                    Get started as a student
                    <AppIcon name="arrow-right" class="h-4 w-4" />
                </a>
                <a
                    :href="urls.contact"
                    class="shrink-0 rounded-full border border-white/20 bg-white/10 px-7 py-3.5 font-medium whitespace-nowrap text-white backdrop-blur transition hover:bg-white/20"
                >
                    Apply for a teacher invite
                </a>
            </div>

            <p class="relative mt-6 text-xs text-white/55">
                Free to join · Verified teachers only · Secure payments &amp; refunds
            </p>
        </div>
    </section>
</template>
