<script setup>
import AppLogo from './AppLogo.vue';

const props = defineProps({
    appName: { type: String, required: true },
    urls: { type: Object, required: true },
    support: { type: Object, default: () => ({}) },
});

const year = new Date().getFullYear();

const columns = [
    {
        title: 'Learn',
        links: [
            { label: 'Subjects & grades', href: '#subjects' },
            { label: 'How it works', href: '#how-it-works' },
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
];
</script>

<template>
    <footer class="border-t border-slate-200/80 bg-white dark:border-slate-800/80 dark:bg-slate-900">
        <div class="mx-auto w-full max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
            <div class="grid gap-10 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.2fr)]">
                <div>
                    <a href="#top" class="flex items-center gap-2.5">
                        <AppLogo class="h-8 w-8 text-primary" />
                        <span class="text-lg font-bold tracking-tight text-slate-800 dark:text-slate-100">{{ appName }}</span>
                    </a>
                    <p class="mt-4 max-w-xs text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                        Live 1-on-1 tutoring on demand, mapped to the Sri Lankan syllabus from Grade 1 to A/L.
                    </p>
                </div>

                <nav v-for="column in columns" :key="column.title" :aria-label="column.title">
                    <h2 class="text-xs font-bold uppercase tracking-widest text-slate-400">{{ column.title }}</h2>
                    <ul class="mt-4 flex flex-col gap-2.5">
                        <li v-for="link in column.links" :key="link.label">
                            <a
                                :href="link.href"
                                class="text-sm text-slate-600 transition-colors hover:text-primary dark:text-slate-400"
                            >
                                {{ link.label }}
                            </a>
                        </li>
                    </ul>
                </nav>

                <div>
                    <h2 class="text-xs font-bold uppercase tracking-widest text-slate-400">Support</h2>
                    <ul class="mt-4 flex flex-col gap-2.5 text-sm text-slate-600 dark:text-slate-400">
                        <li v-if="support.email">
                            <a :href="`mailto:${support.email}`" class="transition-colors hover:text-primary">{{ support.email }}</a>
                        </li>
                        <li v-if="support.phone">{{ support.phone }}</li>
                        <li v-if="support.hours">{{ support.hours }}</li>
                    </ul>
                </div>
            </div>

            <div class="mt-10 flex flex-col items-start justify-between gap-3 border-t border-slate-200/80 pt-6 text-sm text-slate-400 dark:border-slate-800/80 dark:text-slate-500 sm:flex-row sm:items-center">
                <span>© {{ year }} {{ appName }} · Live 1-on-1 tutoring, on demand.</span>
                <a href="#top" class="transition-colors hover:text-primary">Back to top</a>
            </div>
        </div>
    </footer>
</template>
