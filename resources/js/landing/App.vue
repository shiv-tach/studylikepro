<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from './components/AppIcon.vue';
import CtaSection from './components/CtaSection.vue';
import FaqSection from './components/FaqSection.vue';
import FeaturesSection from './components/FeaturesSection.vue';
import HeroSection from './components/HeroSection.vue';
import HowItWorksSection from './components/HowItWorksSection.vue';
import SiteFooter from './components/SiteFooter.vue';
import SiteHeader from './components/SiteHeader.vue';
import StatsBar from './components/StatsBar.vue';
import SubjectsSection from './components/SubjectsSection.vue';
import TeachersSection from './components/TeachersSection.vue';
import TestimonialsSection from './components/TestimonialsSection.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

const urls = computed(() => props.config.urls);
const support = computed(() => props.config.support ?? {});

const showBackToTop = ref(false);

function onScroll() {
    showBackToTop.value = window.scrollY > 700;
}

onMounted(() => {
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
});

onBeforeUnmount(() => window.removeEventListener('scroll', onScroll));
</script>

<template>
    <div class="flex min-h-screen flex-col bg-slate-50 font-sans text-slate-900 dark:bg-slate-950 dark:text-slate-100">
        <a
            href="#top"
            class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-xl focus:bg-primary focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white"
        >
            Skip to content
        </a>

        <SiteHeader :app-name="config.appName" :authenticated="config.authenticated" :urls="urls" />

        <main class="flex-1">
            <HeroSection :urls="urls" />
            <StatsBar />
            <SubjectsSection :urls="urls" />
            <HowItWorksSection />
            <FeaturesSection />
            <TeachersSection :urls="urls" />
            <TestimonialsSection />
            <FaqSection :urls="urls" />
            <CtaSection :urls="urls" />
        </main>

        <SiteFooter :app-name="config.appName" :urls="urls" :support="support" />

        <transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-2 opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="translate-y-2 opacity-0"
        >
            <a
                v-show="showBackToTop"
                href="#top"
                class="fixed bottom-6 right-6 z-40 flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200/80 bg-white/90 text-slate-600 shadow-lg backdrop-blur transition-colors hover:text-primary dark:border-slate-800/80 dark:bg-slate-900/90 dark:text-slate-300"
                aria-label="Back to top"
            >
                <AppIcon name="arrow-up" class="h-5 w-5" />
            </a>
        </transition>
    </div>
</template>

<style>
/* Landing-page only. The class names are prefixed so a global style block in a
   single-file component can never collide with the rest of the app. */
.lp-reveal {
    opacity: 0;
    transform: translateY(18px);
    transition: opacity 0.6s ease, transform 0.6s ease;
}

.lp-reveal--visible {
    opacity: 1;
    transform: translateY(0);
}

html {
    scroll-behavior: smooth;
    scroll-padding-top: 5rem;
}

@media (prefers-reduced-motion: reduce) {
    html {
        scroll-behavior: auto;
    }

    .lp-reveal {
        opacity: 1;
        transform: none;
        transition: none;
    }
}
</style>
