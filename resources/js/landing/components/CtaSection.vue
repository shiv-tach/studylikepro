<script setup>
import AppIcon from './AppIcon.vue';

defineProps({
    urls: { type: Object, required: true },
});
</script>

<template>
    <section class="mx-auto w-full max-w-6xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20" aria-labelledby="landing-cta-title">
        <div
            class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-primary via-primary/90 to-purple-600 p-8 text-center text-white shadow-xl shadow-primary/20 sm:p-12"
        >
            <div class="pointer-events-none absolute -top-16 right-8 h-48 w-48 rounded-full bg-white/10 blur-2xl"></div>
            <div class="pointer-events-none absolute -bottom-20 left-0 h-52 w-52 rounded-full bg-white/10 blur-3xl"></div>

            <h2 id="landing-cta-title" class="relative text-2xl font-extrabold tracking-tight sm:text-3xl">
                Ready to learn like a pro?
            </h2>
            <p class="relative mx-auto mt-3 max-w-xl text-sm leading-relaxed text-white/90">
                Join as a student to get help with the questions that slow you down. Teachers join by invitation — tell
                us what you teach and we will send you an onboarding link.
            </p>

            <div class="relative mt-8 flex flex-wrap justify-center gap-3">
                <a
                    :href="urls.registerStudent"
                    class="inline-flex items-center gap-2 rounded-xl bg-white px-6 py-3 text-sm font-semibold text-primary shadow-md transition hover:bg-slate-100"
                >
                    Get started as a student
                    <AppIcon name="arrow-right" class="h-4 w-4" />
                </a>
                <a
                    :href="urls.contact"
                    class="rounded-xl border border-white/40 bg-white/10 px-6 py-3 text-sm font-semibold text-white backdrop-blur-sm transition hover:bg-white/20"
                >
                    Apply for a teacher invite
                </a>
            </div>

            <p class="relative mt-5 text-xs text-white/80">
                Free to join · Verified teachers only · Secure payments &amp; refunds
            </p>
        </div>
    </section>
</template>
