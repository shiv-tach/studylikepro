<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from '../landing/components/AppIcon.vue';
import SiteFooter from '../landing/components/SiteFooter.vue';
import SiteHeader from '../landing/components/SiteHeader.vue';
import { isPlainClick, loadPage } from './client-navigation';
import CatalogExplorer from './subjects/CatalogExplorer.vue';
import SubjectPage from './subject/SubjectPage.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

const copy = computed(() => props.config.copy ?? {});
const urls = computed(() => props.config.urls ?? {});

// The explorer owns the pickers; this shell owns history, the hero breadcrumb
// and the in-place subject view, so navigation has a single owner.
const explorerEl = ref(null);
const catalog = ref({ level: props.config.level ?? null, grade: props.config.grade ?? null });
const filters = ref({
    level: props.config.level?.key ?? null,
    grade: props.config.grade?.id ?? null,
});
const view = ref('catalog');
const subjectPage = ref(null);
const loading = ref(false);
const catalogTitle = document.title;

function prefersReducedMotion() {
    return window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
}

// A pick can happen from the sticky bar while the visitor is deep in a grid;
// bring the new results under the bar instead of leaving them off-screen.
function scrollToExplorer() {
    const target = document.getElementById('catalog-explorer');

    if (!target) {
        return;
    }

    window.scrollTo({
        top: target.getBoundingClientRect().top + window.scrollY - 64,
        behavior: prefersReducedMotion() ? 'auto' : 'smooth',
    });
}

function onExplorerState({ level, grade }) {
    catalog.value = { level, grade };
}

function onExplorerChange({ level, grade, url, replace = false }) {
    filters.value = { level: level ?? null, grade: grade ?? null };

    const entry = {
        view: 'catalog',
        ...filters.value,
        scrollY: replace ? (window.history.state?.scrollY ?? 0) : 0,
    };

    // A level with a single grade resolves in two steps; the second one
    // replaces the first instead of adding another Back stop.
    if (replace) {
        window.history.replaceState(entry, '', url);

        return;
    }

    const current = window.history.state;

    if (current?.view === 'catalog') {
        window.history.replaceState({ ...current, scrollY: window.scrollY }, '');
    }

    window.history.pushState(entry, '', url);
    scrollToExplorer();
}

async function restoreScroll(event) {
    const state = event.state ?? {};

    if (state.view === 'subject' && subjectPage.value) {
        view.value = 'subject';
        document.title = subjectPage.value.documentTitle;
        window.scrollTo(0, 0);

        return;
    }

    if (state.view === 'subject') {
        // The entry belongs to a subject rendered by an earlier document.
        window.location.reload();

        return;
    }

    view.value = 'catalog';
    document.title = catalogTitle;
    filters.value = { level: state.level ?? null, grade: state.grade ?? null };

    await explorerEl.value?.applyState({
        level: state.level ?? null,
        grade: state.grade ?? null,
    });

    requestAnimationFrame(() => window.scrollTo(0, state.scrollY ?? 0));
}

async function openSubject({ event, url } = {}) {
    if (event) {
        if (!isPlainClick(event)) {
            return;
        }

        event.preventDefault();
    }

    if (loading.value) {
        return;
    }

    loading.value = true;

    try {
        const page = await loadPage(url);

        if (!page) {
            return;
        }

        // Arriving from the grid: remember the explorer's filters and position,
        // then push the subject as its own history entry so Back returns to the
        // same grid. Switching subject-to-subject (grade switcher, "all grades")
        // leaves the previous subject entry alone, so Back walks the chain.
        if (view.value === 'catalog') {
            window.history.replaceState({ view: 'catalog', ...filters.value, scrollY: window.scrollY }, '');
        }

        subjectPage.value = page;
        view.value = 'subject';
        document.title = page.documentTitle;
        window.history.pushState({ view: 'subject', url: page.url }, '', page.url);
        window.scrollTo(0, 0);

        // The catalog stays mounted behind the subject, so move the reading
        // position (and keyboard focus) into the new page explicitly.
        await nextTick();
        document.getElementById('top')?.focus({ preventScroll: true });
    } catch (error) {
        window.location.assign(url);
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    window.history.replaceState({ view: 'catalog', ...filters.value, scrollY: window.scrollY }, '');
    window.addEventListener('popstate', restoreScroll);
});

onBeforeUnmount(() => window.removeEventListener('popstate', restoreScroll));
</script>

<template>
    <div class="lp-theme flex min-h-screen flex-col bg-white text-ink">
        <a
            :href="view === 'catalog' ? '#catalog-top' : '#top'"
            class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-full focus:bg-brand-500 focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-white"
        >
            Skip to content
        </a>

        <SiteHeader
            :app-name="config.appName"
            :authenticated="config.authenticated"
            :urls="urls"
            :logo="config.images.logo"
            :home-url="urls.home"
            active-section="subjects"
        />

        <main v-show="view === 'catalog'" class="flex-1">
            <section id="catalog-top" class="relative overflow-hidden border-b border-line bg-white">
                <div class="lp-aurora lp-aurora-soft" aria-hidden="true"><span></span><span></span><span></span></div>
                <div class="lp-grid-lines pointer-events-none absolute inset-0" aria-hidden="true"></div>

                <div class="relative mx-auto w-full max-w-6xl px-5 pb-10 pt-10 lg:pb-12 lg:pt-14">
                    <nav class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-ink-500" aria-label="Breadcrumb">
                        <a :href="urls.home" class="transition hover:text-ink">Home</a>
                        <span aria-hidden="true">/</span>
                        <a v-if="catalog.level" :href="urls.subjects" class="transition hover:text-ink">{{ copy.title }}</a>
                        <template v-if="catalog.level">
                            <span aria-hidden="true">/</span>
                            <a :href="catalog.level.url" class="transition hover:text-ink">{{ catalog.level.name }}</a>
                        </template>
                        <template v-if="catalog.grade">
                            <span aria-hidden="true">/</span>
                            <span class="font-medium text-ink-700">{{ catalog.grade.label }}</span>
                        </template>
                        <span v-if="!catalog.level" class="font-medium text-ink-700">{{ copy.title }}</span>
                    </nav>

                    <p class="mt-6 text-sm font-medium text-brand-600">{{ copy.eyebrow }}</p>
                    <h1 class="mt-3 max-w-3xl text-display-lg font-semibold lg:text-display-hero">{{ copy.title }}</h1>
                    <p class="mt-4 max-w-2xl text-lg leading-relaxed text-ink-700">{{ copy.intro }}</p>
                </div>
            </section>

            <CatalogExplorer
                ref="explorerEl"
                :config="config"
                :urls="urls"
                @change="onExplorerChange"
                @state="onExplorerState"
                @open-subject="openSubject"
            />

            <section v-if="!config.authenticated" class="mx-auto w-full max-w-6xl px-5 py-16 lg:py-20">
                <div class="relative overflow-hidden rounded-3xl px-6 py-14 text-center text-white sm:px-12" style="background: var(--color-ink)">
                    <div class="lp-aurora lp-aurora-dark" aria-hidden="true"><span></span><span></span><span></span></div>
                    <div class="lp-grid-lines lp-grid-lines-dark pointer-events-none absolute inset-0" aria-hidden="true"></div>

                    <h2 class="relative text-display-lg font-semibold">{{ copy.ctaTitle }}</h2>
                    <p class="relative mx-auto mt-5 max-w-xl text-lg leading-relaxed text-white/70">{{ copy.ctaBody }}</p>

                    <div class="relative mt-8 flex flex-col justify-center gap-3 sm:flex-row sm:items-center">
                        <a
                            :href="urls.registerStudent"
                            class="inline-flex shrink-0 items-center justify-center gap-2 rounded-full bg-brand-500 px-7 py-3.5 font-medium whitespace-nowrap text-white transition hover:bg-brand-600"
                            style="box-shadow: var(--shadow-brand)"
                        >
                            {{ copy.ctaStudent }}
                            <AppIcon name="arrow-right" class="h-4 w-4" />
                        </a>
                        <a
                            :href="urls.registerTeacher"
                            class="shrink-0 rounded-full border border-white/20 bg-white/10 px-7 py-3.5 font-medium whitespace-nowrap text-white backdrop-blur transition hover:bg-white/20"
                        >
                            {{ copy.ctaTeacher }}
                        </a>
                    </div>
                </div>
            </section>
        </main>

        <SubjectPage
            v-if="subjectPage"
            :config="subjectPage.config"
            client-navigation
            @navigate="(url) => openSubject({ url })"
        />

        <SiteFooter
            :app-name="config.appName"
            :urls="urls"
            :support="config.support ?? {}"
            :logo="config.images.logo"
            :landing-url="urls.home"
        />
    </div>
</template>
