<script setup>
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const props = defineProps({
    appName: { type: String, required: true },
    urls: { type: Object, required: true },
    support: { type: Object, default: () => ({}) },
    logo: { type: String, required: true },
    // Public screens link the marketing sections back to the landing page.
    landingUrl: { type: String, default: null },
});

const year = new Date().getFullYear();

// On the landing page the brand mark returns to the hero anchor; the public
// screens pass their home URL instead.
const homeHref = computed(() => props.landingUrl ?? '#top');

const hashHref = (anchor) => (props.landingUrl ? `${props.landingUrl}${anchor}` : anchor);

const hasSupport = computed(() => Boolean(props.support.email || props.support.phone || props.support.hours));

const telHref = computed(() => `tel:${(props.support.phone ?? '').replace(/[^+\d]/g, '')}`);

const columns = computed(() => [
    {
        title: 'Learn',
        links: [
            { label: 'Subjects & grades', href: hashHref('#subjects') },
            { label: 'How it works', href: hashHref('#how-it-works') },
            { label: 'Why Studylikepro', href: hashHref('#features') },
            { label: 'FAQ', href: hashHref('#faq') },
        ],
    },
    {
        title: 'Explore',
        links: [
            { label: 'Browse the catalog', href: props.urls.subjects },
            { label: 'Browse teachers', href: props.urls.teachers },
            { label: 'Contact us', href: props.urls.contact },
        ],
    },
    {
        title: 'Legal',
        links: [
            { label: 'Privacy', href: props.urls.privacy },
            { label: 'Terms', href: props.urls.terms },
            { label: 'Cancellation & refunds', href: props.urls.refunds },
        ],
    },
]);
</script>

<template>
    <footer class="border-t border-line bg-ink-50">
        <div class="mx-auto w-full max-w-6xl px-5">
            <div
                class="grid grid-cols-2 gap-x-6 gap-y-10 py-14 sm:grid-cols-3 lg:grid-cols-[minmax(0,1.6fr)_repeat(3,minmax(0,1fr))] lg:gap-x-10 lg:py-16"
            >
                <div class="col-span-2 sm:col-span-3 lg:col-span-1">
                    <a :href="homeHref" class="inline-flex items-center gap-2.5">
                        <AppLogo :src="logo" class="h-9 w-auto" />
                        <span class="font-display text-xl font-semibold tracking-tight">{{ appName }}</span>
                    </a>
                    <p class="mt-4 max-w-md text-sm leading-relaxed text-ink-500">
                        Live 1-on-1 lessons for grades 6–13, mapped to the Sri Lankan syllabus — taught by verified
                        teachers and paid securely in rupees.
                    </p>
                    <ul v-if="hasSupport" class="mt-5 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm">
                        <li v-if="support.email">
                            <a :href="`mailto:${support.email}`" class="text-ink-700 transition hover:text-brand-600">
                                {{ support.email }}
                            </a>
                        </li>
                        <li v-if="support.phone">
                            <a :href="telHref" class="text-ink-700 transition hover:text-brand-600">
                                {{ support.phone }}
                            </a>
                        </li>
                        <li v-if="support.hours" class="text-ink-400">{{ support.hours }}</li>
                    </ul>
                </div>

                <nav v-for="column in columns" :key="column.title" :aria-label="column.title">
                    <h2 class="text-xs font-semibold tracking-widest text-ink-400 uppercase">{{ column.title }}</h2>
                    <ul class="mt-4 space-y-2.5 text-sm">
                        <li v-for="link in column.links" :key="link.label">
                            <a :href="link.href" class="text-ink-500 transition hover:text-ink">{{ link.label }}</a>
                        </li>
                    </ul>
                </nav>
            </div>

            <div class="border-t border-line py-6 text-xs text-ink-400">
                <p>© {{ year }} {{ appName }} · Live 1-on-1 tutoring, on demand.</p>
            </div>
        </div>
    </footer>
</template>
