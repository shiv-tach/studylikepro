<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from './AppIcon.vue';
import AppLogo from './AppLogo.vue';
import ThemeToggle from './ThemeToggle.vue';

defineProps({
    appName: { type: String, required: true },
    authenticated: { type: Boolean, default: false },
    urls: { type: Object, required: true },
});

const sections = [
    { href: '#how-it-works', label: 'How it works' },
    { href: '#subjects', label: 'Subjects & grades' },
    { href: '#features', label: 'Why Studylikepro' },
    { href: '#teachers', label: 'For teachers' },
    { href: '#faq', label: 'FAQ' },
];

const menuOpen = ref(false);
const elevated = ref(false);

function onScroll() {
    elevated.value = window.scrollY > 8;
}

onMounted(() => {
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
});

onBeforeUnmount(() => window.removeEventListener('scroll', onScroll));
</script>

<template>
    <header
        class="sticky top-0 z-40 border-b bg-white/80 backdrop-blur-md transition-shadow dark:bg-slate-950/80"
        :class="elevated
            ? 'border-slate-200/80 shadow-sm shadow-slate-900/5 dark:border-slate-800/80'
            : 'border-transparent'"
    >
        <div class="mx-auto flex h-16 w-full max-w-6xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
            <a href="#top" class="flex items-center gap-2.5 rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40">
                <AppLogo class="h-8 w-8 text-primary" />
                <span class="text-lg font-bold tracking-tight text-slate-800 dark:text-slate-100">{{ appName }}</span>
            </a>

            <nav class="hidden items-center gap-1 lg:flex" aria-label="Page sections">
                <a
                    v-for="section in sections"
                    :key="section.href"
                    :href="section.href"
                    class="rounded-xl px-3 py-2 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white"
                >
                    {{ section.label }}
                </a>
            </nav>

            <div class="flex items-center gap-2">
                <ThemeToggle />

                <a
                    v-if="authenticated"
                    :href="urls.dashboard"
                    class="hidden rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-md shadow-primary/20 transition hover:bg-primary/90 sm:block"
                >
                    Go to dashboard
                </a>
                <template v-else>
                    <a
                        :href="urls.login"
                        class="hidden rounded-xl px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800 sm:block"
                    >
                        Log in
                    </a>
                    <a
                        :href="urls.registerStudent"
                        class="hidden rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-md shadow-primary/20 transition hover:bg-primary/90 sm:block"
                    >
                        Get started
                    </a>
                </template>

                <button
                    type="button"
                    class="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200/80 text-slate-600 transition hover:text-primary dark:border-slate-800/80 dark:text-slate-300 lg:hidden"
                    :aria-expanded="menuOpen"
                    aria-controls="landing-mobile-menu"
                    aria-label="Toggle navigation menu"
                    @click="menuOpen = !menuOpen"
                >
                    <AppIcon :name="menuOpen ? 'close' : 'menu'" class="h-5 w-5" />
                </button>
            </div>
        </div>

        <div
            v-show="menuOpen"
            id="landing-mobile-menu"
            class="border-t border-slate-200/80 bg-white/95 px-4 pb-5 pt-3 backdrop-blur-md dark:border-slate-800/80 dark:bg-slate-950/95 sm:px-6 lg:hidden"
        >
            <nav class="flex flex-col" aria-label="Page sections">
                <a
                    v-for="section in sections"
                    :key="section.href"
                    :href="section.href"
                    class="rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                    @click="menuOpen = false"
                >
                    {{ section.label }}
                </a>
            </nav>

            <div class="mt-4 flex flex-col gap-2">
                <a
                    v-if="authenticated"
                    :href="urls.dashboard"
                    class="rounded-xl bg-primary px-4 py-2.5 text-center text-sm font-semibold text-white shadow-md shadow-primary/20"
                >
                    Go to dashboard
                </a>
                <template v-else>
                    <a
                        :href="urls.registerStudent"
                        class="rounded-xl bg-primary px-4 py-2.5 text-center text-sm font-semibold text-white shadow-md shadow-primary/20"
                    >
                        Get started
                    </a>
                    <a
                        :href="urls.login"
                        class="rounded-xl border border-slate-200/80 px-4 py-2.5 text-center text-sm font-semibold text-slate-600 dark:border-slate-800/80 dark:text-slate-300"
                    >
                        Log in
                    </a>
                </template>
            </div>
        </div>
    </header>
</template>
