<script setup>
import { computed, ref } from 'vue';
import AppIcon from './AppIcon.vue';
import AppLogo from './AppLogo.vue';

const props = defineProps({
    appName: { type: String, required: true },
    authenticated: { type: Boolean, default: false },
    urls: { type: Object, required: true },
    logo: { type: String, required: true },
    homeUrl: { type: String, default: '#top' },
    // The key of the nav item that is the current page, e.g. "subjects".
    activeSection: { type: String, default: null },
});

// The catalog, directory and contact screens keep the landing page's own
// navigation, so its section anchors resolve against the home page there.
const anchorHref = (anchor) => (props.homeUrl === '#top' ? anchor : `${props.homeUrl.replace(/\/+$/, '')}${anchor}`);

const sections = computed(() => [
    { key: 'how-it-works', href: anchorHref('#how-it-works'), label: 'How it works' },
    { key: 'subjects', href: props.urls.subjects, label: 'Subjects' },
    { key: 'teachers', href: props.urls.teachers, label: 'Teachers' },
    { key: 'features', href: anchorHref('#features'), label: 'Why Studylikepro' },
    { key: 'faq', href: anchorHref('#faq'), label: 'FAQ' },
    { key: 'contact', href: props.urls.contact, label: 'Contact' },
]);

const isActive = (link) => props.activeSection !== null && props.activeSection === (link.key ?? link.href);

// Native <dialog> side sheet (reference §4.2): the browser owns focus trapping,
// the backdrop scrim and Escape, so Vue only opens and closes it.
const sheet = ref(null);
const menuOpen = ref(false);

function openMenu() {
    menuOpen.value = true;
    sheet.value?.showModal();
}

function closeMenu() {
    menuOpen.value = false;
    sheet.value?.close();
}
</script>

<template>
    <header class="sticky top-0 z-40 border-b border-line/80 bg-white/85 backdrop-blur">
        <div class="mx-auto flex h-16 w-full max-w-6xl items-center justify-between gap-3 px-4 sm:px-5">
            <button
                type="button"
                class="-ml-1.5 flex items-center gap-1.5 rounded-lg px-1.5 py-1.5 text-sm font-medium text-ink-700 transition hover:bg-ink-50 hover:text-ink lg:hidden"
                :aria-expanded="menuOpen"
                aria-controls="lp-nav"
                aria-label="Open navigation"
                @click="openMenu"
            >
                <AppIcon name="menu" class="h-5 w-5" />
                <span class="sr-only sm:not-sr-only">Menu</span>
            </button>

            <a :href="homeUrl" class="flex shrink-0 items-center gap-2">
                <AppLogo :src="logo" class="h-8 w-auto" />
                <span class="font-display text-lg font-semibold tracking-tight">{{ appName }}</span>
            </a>

            <nav class="hidden items-center gap-6 text-sm text-ink-700 lg:flex" aria-label="Page sections">
                <a
                    v-for="section in sections"
                    :key="section.href"
                    :href="section.href"
                    class="transition hover:text-ink"
                    :class="{ 'font-semibold text-brand-600': isActive(section) }"
                >
                    {{ section.label }}
                </a>
            </nav>

            <div class="flex shrink-0 items-center gap-1 text-sm sm:gap-3">
                <a
                    v-if="authenticated"
                    :href="urls.dashboard"
                    class="rounded-full bg-brand-500 px-4 py-2 font-medium text-white transition hover:bg-brand-600"
                >
                    <span class="sm:hidden">Dashboard</span><span class="hidden sm:inline">Go to dashboard</span>
                </a>
                <template v-else>
                    <a
                        :href="urls.login"
                        class="hidden whitespace-nowrap px-2 py-2 text-ink-700 transition hover:text-ink sm:inline"
                    >
                        Log in
                    </a>
                    <a
                        :href="urls.registerStudent"
                        class="rounded-full bg-brand-500 px-4 py-2 font-medium text-white transition hover:bg-brand-600"
                    >
                        <span class="sm:hidden">Join</span><span class="hidden sm:inline">Get started</span>
                    </a>
                </template>
            </div>
        </div>

        <dialog
            id="lp-nav"
            ref="sheet"
            class="lp-sheet lg:hidden"
            aria-labelledby="lp-nav-title"
            @close="menuOpen = false"
        >
            <div class="flex h-full flex-col outline-none">
                <div class="flex h-16 shrink-0 items-center justify-between gap-3 border-b border-line px-5">
                    <span id="lp-nav-title" class="font-display text-lg font-semibold">Menu</span>
                    <button
                        type="button"
                        class="flex size-9 items-center justify-center rounded-lg text-ink-500 transition hover:bg-ink-50 hover:text-ink"
                        aria-label="Close navigation"
                        @click="closeMenu"
                    >
                        <AppIcon name="close" class="h-5 w-5" />
                    </button>
                </div>

                <nav class="flex min-h-0 flex-1 flex-col gap-1 overflow-y-auto px-3 py-5 text-ink-700" aria-label="Page sections">
                    <a
                        v-for="section in sections"
                        :key="section.href"
                        :href="section.href"
                        class="rounded-lg px-3 py-2.5 transition hover:bg-ink-50 hover:text-ink"
                        :class="{ 'bg-ink-50 font-semibold text-ink': isActive(section) }"
                        @click="closeMenu"
                    >
                        {{ section.label }}
                    </a>
                </nav>

                <div class="shrink-0 border-t border-line px-5 py-4">
                    <a
                        v-if="authenticated"
                        :href="urls.dashboard"
                        class="block rounded-full bg-brand-500 px-4 py-2.5 text-center font-medium text-white"
                        @click="closeMenu"
                    >
                        Go to dashboard
                    </a>
                    <template v-else>
                        <a
                            :href="urls.registerStudent"
                            class="block rounded-full bg-brand-500 px-4 py-2.5 text-center font-medium text-white"
                            @click="closeMenu"
                        >
                            Get started
                        </a>
                        <a
                            :href="urls.login"
                            class="hairline mt-2 block rounded-full px-4 py-2.5 text-center font-medium text-ink-700"
                            @click="closeMenu"
                        >
                            Log in
                        </a>
                    </template>
                </div>
            </div>
        </dialog>
    </header>
</template>
