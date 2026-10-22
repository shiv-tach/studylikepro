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

const hashHref = (anchor) => (props.landingUrl ? `${props.landingUrl}${anchor}` : anchor);

const columns = computed(() => [
    {
        title: 'Learn',
        links: [
            { label: 'Subjects & grades', href: hashHref('#subjects') },
            { label: 'How it works', href: hashHref('#how-it-works') },
            { label: 'Browse the catalog', href: props.urls.subjects },
            { label: 'Browse teachers', href: props.urls.teachers },
        ],
    },
    {
        title: 'Legal',
        links: [
            { label: 'Privacy', href: props.urls.privacy },
            { label: 'Terms', href: props.urls.terms },
            { label: 'Cancellation & refunds', href: props.urls.refunds },
            { label: 'Contact', href: props.urls.contact },
        ],
    },
]);
</script>

<template>
    <footer class="relative overflow-hidden text-white" style="background: var(--color-ink)">
        <div class="lp-aurora lp-aurora-dark" aria-hidden="true"><span></span><span></span><span></span></div>
        <div class="lp-grid-lines lp-grid-lines-dark pointer-events-none absolute inset-0" aria-hidden="true"></div>

        <div class="relative mx-auto w-full max-w-6xl px-5">
            <div class="py-16">
                <h2 class="text-display-lg font-semibold">
                    Learn 1-on-1 with
                    <span class="inline-flex h-14 items-center gap-2.5 rounded-2xl bg-white px-5 align-middle font-sans text-sm font-normal text-ink-500">
                        <AppLogo :src="logo" class="h-7 w-auto" />
                        {{ appName }}
                    </span>
                </h2>
                <p class="mt-6 max-w-3xl text-lg leading-relaxed text-white/70">
                    Live 1-on-1 tutoring on demand, mapped to the Sri Lankan syllabus from Grade 1 to A/L. Find the
                    lesson for your grade and book a verified teacher — paid securely in rupees.
                </p>

                <div class="mt-10 grid grid-cols-2 gap-8 text-sm sm:grid-cols-3">
                    <div>
                        <p class="font-display text-3xl font-semibold">Grades 1–13</p>
                        <p class="mt-2 text-sm leading-relaxed text-white/55">Primary, O/L and A/L, with a lesson list for every grade.</p>
                    </div>
                    <div>
                        <p class="font-display text-3xl font-semibold">Verified only</p>
                        <p class="mt-2 text-sm leading-relaxed text-white/55">Every tutor is document-checked and approved before their first lesson.</p>
                    </div>
                    <div>
                        <p class="font-display text-3xl font-semibold">LKR</p>
                        <p class="mt-2 text-sm leading-relaxed text-white/55">Clear rates, receipts and a published refund policy.</p>
                    </div>
                </div>

                <div v-if="support.email || support.phone || support.hours" class="mt-12">
                    <p class="text-xs font-semibold tracking-widest text-white/45 uppercase">Contact us</p>
                    <div class="mt-2 flex flex-wrap items-baseline gap-x-6 gap-y-1">
                        <a
                            v-if="support.phone"
                            :href="`tel:${support.phone}`"
                            class="font-display text-2xl font-semibold transition hover:text-brand-200"
                        >{{ support.phone }}</a>
                        <a
                            v-if="support.email"
                            :href="`mailto:${support.email}`"
                            class="text-sm text-white/65 transition hover:text-white"
                        >{{ support.email }}</a>
                        <span v-if="support.hours" class="text-sm text-white/55">{{ support.hours }}</span>
                    </div>
                </div>

                <div class="mt-12 grid grid-cols-2 gap-8 text-sm sm:grid-cols-3">
                    <nav v-for="column in columns" :key="column.title" :aria-label="column.title">
                        <h3 class="text-xs font-semibold tracking-widest text-white/45 uppercase">{{ column.title }}</h3>
                        <ul class="mt-3">
                            <li v-for="link in column.links" :key="link.label">
                                <a :href="link.href" class="inline-block py-1.5 text-white/65 transition hover:text-white">{{ link.label }}</a>
                            </li>
                        </ul>
                    </nav>
                    <div>
                        <h3 class="text-xs font-semibold tracking-widest text-white/45 uppercase">Support</h3>
                        <ul class="mt-3">
                            <li v-if="support.email">
                                <a :href="`mailto:${support.email}`" class="inline-block py-1.5 text-white/65 transition hover:text-white">{{ support.email }}</a>
                            </li>
                            <li v-if="support.hours" class="py-1.5 text-white/55">{{ support.hours }}</li>
                            <li v-if="!support.email && !support.hours" class="py-1.5 text-white/55">Reach us from the contact page.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-4 border-t border-white/10 py-6 md:flex-row md:items-center md:justify-between">
                <p class="text-xs text-white/50">© {{ year }} {{ appName }} · Live 1-on-1 tutoring, on demand.</p>
                <a href="#top" class="text-xs text-white/65 transition hover:text-white">Back to top</a>
            </div>
        </div>
    </footer>
</template>
