<script setup>
import { computed, reactive, ref } from 'vue';
import AppIcon from '../landing/components/AppIcon.vue';
import SiteFooter from '../landing/components/SiteFooter.vue';
import SiteHeader from '../landing/components/SiteHeader.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

const copy = computed(() => props.config.copy ?? {});
const urls = computed(() => props.config.urls ?? {});
const filters = props.config.filters ?? {};

// Filters stay collapsed on a fresh visit and open on their own when the page
// was reached with a narrowed or re-sorted result set.
const filterKeys = ['level', 'subject', 'lesson', 'grade', 'language', 'min_rate', 'max_rate', 'weekday', 'time_from', 'time_to'];
const activeFilterCount = computed(() => filterKeys.filter((key) => String(filters[key] ?? '').trim() !== '').length);

const formElement = ref(null);
const filtersOpen = ref(activeFilterCount.value > 0 || (filters.sort ?? 'rating') !== 'rating');
const form = reactive({
    level: filters.level ?? '',
    subject: filters.subject ?? '',
    lesson: filters.lesson ?? '',
    grade: filters.grade ?? '',
    language: filters.language ?? '',
    min_rate: filters.min_rate ?? '',
    max_rate: filters.max_rate ?? '',
    weekday: filters.weekday ?? '',
    time_from: filters.time_from ?? '',
    time_to: filters.time_to ?? '',
    sort: filters.sort ?? 'rating',
});

// The pickers cascade server-side, so a level or subject change reloads the
// page with the dependent pickers rebuilt.
function onLevelChange() {
    form.subject = '';
    form.lesson = '';
    form.grade = '';
    formElement.value?.submit();
}

function onSubjectChange() {
    form.lesson = '';
    formElement.value?.submit();
}

const fieldClass = 'mt-1 w-full rounded-xl border border-line bg-white px-3 py-2.5 text-sm text-ink shadow-xs transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20';
const labelClass = 'text-xs font-semibold tracking-widest text-ink-400 uppercase';
</script>

<template>
    <div class="lp-theme flex min-h-screen flex-col bg-white text-ink">
        <a
            href="#top"
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
            active-section="teachers"
        />

        <main class="flex-1">
            <section id="top" class="relative overflow-hidden border-b border-line bg-white">
                <div class="lp-aurora lp-aurora-soft" aria-hidden="true"><span></span><span></span><span></span></div>
                <div class="lp-grid-lines pointer-events-none absolute inset-0" aria-hidden="true"></div>

                <div class="relative mx-auto w-full max-w-6xl px-5 pb-14 pt-12 lg:pb-20 lg:pt-20">
                    <nav class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-ink-500" aria-label="Breadcrumb">
                        <a :href="urls.home" class="transition hover:text-ink">Home</a>
                        <span aria-hidden="true">/</span>
                        <span class="font-medium text-ink-700">{{ copy.navTeachers }}</span>
                    </nav>

                    <div class="mt-8 flex flex-wrap items-center gap-2">
                        <p class="text-sm font-medium text-brand-600">{{ copy.eyebrow }}</p>
                    </div>
                    <h1 class="mt-3 max-w-3xl text-display-hero font-semibold">{{ copy.title }}</h1>
                    <p class="mt-5 max-w-2xl text-lg leading-relaxed text-ink-700">{{ copy.intro }}</p>
                </div>
            </section>

            <section class="border-b border-line bg-white py-10 lg:py-14">
                <div class="mx-auto w-full max-w-6xl px-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <h2 class="font-display text-2xl font-semibold text-ink">{{ copy.resultsTitle }}</h2>
                            <p class="mt-1 text-sm text-ink-500">{{ copy.found }}</p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <a
                                v-if="activeFilterCount"
                                :href="urls.teachers"
                                class="rounded-full px-2 py-2 text-sm font-medium text-ink-500 transition hover:text-ink"
                            >
                                {{ copy.clear }}
                            </a>
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-full border border-line bg-white px-4 py-2 text-sm font-medium text-ink-700 transition hover:bg-ink-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30"
                                :aria-expanded="filtersOpen"
                                aria-controls="teacher-filters"
                                @click="filtersOpen = !filtersOpen"
                            >
                                <AppIcon name="filter" class="h-4 w-4" />
                                {{ filtersOpen ? copy.filtersHide : copy.filtersToggle }}
                                <span
                                    v-if="activeFilterCount"
                                    class="inline-flex min-w-5 items-center justify-center rounded-full bg-brand-500 px-1.5 text-[11px] font-semibold text-white"
                                >{{ activeFilterCount }}</span>
                                <AppIcon name="chevron-down" class="h-4 w-4 transition-transform" :class="filtersOpen ? 'rotate-180' : ''" />
                            </button>
                        </div>
                    </div>

                    <p v-if="config.error" class="mt-3 text-sm text-rose-600" role="alert">{{ config.error }}</p>

                    <Transition
                        enter-active-class="transition duration-200 ease-out"
                        enter-from-class="opacity-0 -translate-y-1"
                        enter-to-class="opacity-100 translate-y-0"
                        leave-active-class="transition duration-150 ease-in"
                        leave-from-class="opacity-100 translate-y-0"
                        leave-to-class="opacity-0 -translate-y-1"
                    >
                        <form
                            v-if="filtersOpen"
                            id="teacher-filters"
                            ref="formElement"
                            method="GET"
                            :action="urls.teachers"
                            :aria-label="copy.filtersTitle"
                            class="hairline mt-5 rounded-2xl bg-ink-50 p-4 sm:p-5"
                        >
                            <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                                <div>
                                    <label for="filter-level" :class="labelClass">{{ copy.level }}</label>
                                    <select id="filter-level" v-model="form.level" name="level" :class="fieldClass" @change="onLevelChange">
                                        <option value="">{{ copy.anyLevel }}</option>
                                        <option v-for="option in config.levelOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                    </select>
                                </div>

                                <div>
                                    <label for="filter-subject" :class="labelClass">{{ copy.subject }}</label>
                                    <select id="filter-subject" v-model="form.subject" name="subject" :class="fieldClass" @change="onSubjectChange">
                                        <option value="">{{ copy.anySubject }}</option>
                                        <option v-for="option in config.subjectOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                    </select>
                                </div>

                                <div class="col-span-2 lg:col-span-1">
                                    <label for="filter-lesson" :class="labelClass">{{ copy.lesson }}</label>
                                    <select id="filter-lesson" v-model="form.lesson" name="lesson" :class="fieldClass">
                                        <option value="">{{ copy.anyLesson }}</option>
                                        <optgroup v-for="group in config.lessonGroups" :key="group.label" :label="group.label">
                                            <option v-for="option in group.options" :key="option.value" :value="option.value">{{ option.label }}</option>
                                        </optgroup>
                                    </select>
                                </div>

                                <div>
                                    <label for="filter-grade" :class="labelClass">{{ copy.grade }}</label>
                                    <select id="filter-grade" v-model="form.grade" name="grade" :class="fieldClass">
                                        <option value="">{{ copy.anyGrade }}</option>
                                        <optgroup v-for="group in config.gradeGroups" :key="group.label" :label="group.label">
                                            <option v-for="option in group.options" :key="option.value" :value="option.value">{{ option.label }}</option>
                                        </optgroup>
                                    </select>
                                </div>

                                <div>
                                    <label for="filter-language" :class="labelClass">{{ copy.language }}</label>
                                    <select id="filter-language" v-model="form.language" name="language" :class="fieldClass">
                                        <option value="">{{ copy.anyLanguage }}</option>
                                        <option v-for="language in config.languageOptions" :key="language" :value="language">{{ language }}</option>
                                    </select>
                                </div>

                                <div>
                                    <label for="filter-weekday" :class="labelClass">{{ copy.weekday }}</label>
                                    <select id="filter-weekday" v-model="form.weekday" name="weekday" :class="fieldClass">
                                        <option value="">{{ copy.anyDay }}</option>
                                        <option v-for="option in config.weekdayOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                    </select>
                                </div>

                                <div>
                                    <label for="filter-sort" :class="labelClass">{{ copy.sort }}</label>
                                    <select id="filter-sort" v-model="form.sort" name="sort" :class="fieldClass">
                                        <option v-for="option in config.sortOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                    </select>
                                </div>

                                <div class="col-span-2 grid grid-cols-2 gap-3">
                                    <div>
                                        <label for="filter-min-rate" :class="labelClass">{{ copy.minRate }}</label>
                                        <input id="filter-min-rate" v-model="form.min_rate" name="min_rate" type="number" min="0" step="50" :class="fieldClass" />
                                    </div>
                                    <div>
                                        <label for="filter-max-rate" :class="labelClass">{{ copy.maxRate }}</label>
                                        <input id="filter-max-rate" v-model="form.max_rate" name="max_rate" type="number" min="0" step="50" :class="fieldClass" />
                                    </div>
                                </div>

                                <div class="col-span-2 grid grid-cols-2 gap-3">
                                    <div>
                                        <label for="filter-time-from" :class="labelClass">{{ copy.from }}</label>
                                        <input id="filter-time-from" v-model="form.time_from" name="time_from" type="time" :class="fieldClass" />
                                    </div>
                                    <div>
                                        <label for="filter-time-to" :class="labelClass">{{ copy.to }}</label>
                                        <input id="filter-time-to" v-model="form.time_to" name="time_to" type="time" :class="fieldClass" />
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 border-t border-line pt-4">
                                <button
                                    type="submit"
                                    class="w-full rounded-full bg-brand-500 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600 sm:w-auto"
                                    style="box-shadow: var(--shadow-brand)"
                                >
                                    {{ copy.apply }}
                                </button>
                            </div>
                        </form>
                    </Transition>

                    <p v-if="!config.teachers.length" class="hairline mt-6 rounded-2xl bg-white p-10 text-center text-sm text-ink-500">
                        {{ copy.noResults }}
                    </p>

                    <div v-else class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        <a
                            v-for="teacher in config.teachers"
                            :key="teacher.id"
                            :href="teacher.url"
                            class="lift hairline group flex flex-col overflow-hidden rounded-2xl bg-white"
                        >
                            <div class="relative aspect-[4/3] w-full overflow-hidden bg-brand-50">
                                <img
                                    v-if="teacher.avatarUrl"
                                    :src="teacher.avatarUrl"
                                    :alt="teacher.name"
                                    class="h-full w-full object-cover object-top"
                                    loading="lazy"
                                    decoding="async"
                                />
                                <span
                                    v-else
                                    class="flex h-full w-full items-center justify-center font-display text-4xl font-semibold text-brand-600"
                                    aria-hidden="true"
                                >{{ teacher.initials }}</span>
                            </div>

                            <div class="flex min-w-0 flex-col px-6 pt-5">
                                <h3 class="truncate font-display text-lg font-semibold text-ink">{{ teacher.name }}</h3>
                                <p v-if="teacher.headline" class="mt-0.5 line-clamp-2 text-sm text-ink-500">{{ teacher.headline }}</p>
                            </div>

                            <div class="mt-4 flex flex-wrap items-center gap-2 px-6 text-xs font-semibold">
                                <span v-if="teacher.rating" class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2.5 py-0.5 text-brand-700">
                                    ★ {{ teacher.rating }}
                                    <span class="font-medium text-brand-500">({{ teacher.ratingCount }})</span>
                                </span>
                                <span v-else class="rounded-full bg-ink-50 px-2.5 py-0.5 text-ink-500">{{ copy.newTeacher }}</span>
                                <span class="inline-flex items-center gap-1 rounded-full bg-go/10 px-2.5 py-0.5 text-go-ink">
                                    <AppIcon name="shield-check" class="h-3.5 w-3.5" />
                                    {{ copy.verified }}
                                </span>
                            </div>

                            <div v-if="teacher.subjects.length" class="mt-4 flex flex-wrap gap-1.5 px-6">
                                <span
                                    v-for="subject in teacher.subjects"
                                    :key="subject.label"
                                    class="rounded-full bg-ink-50 px-2.5 py-0.5 text-[11px] font-semibold text-ink-700"
                                >{{ subject.label }}</span>
                                <span v-if="teacher.extraSubjects" class="rounded-full bg-ink-50 px-2.5 py-0.5 text-[11px] font-semibold text-ink-500">
                                    +{{ teacher.extraSubjects }}
                                </span>
                            </div>

                            <p v-if="teacher.metaLabel" class="mt-3 px-6 text-xs font-medium text-ink-500">{{ teacher.metaLabel }}</p>

                            <div class="mt-auto flex items-end justify-between gap-3 px-6 pb-6 pt-5">
                                <div>
                                    <p class="font-display text-xl font-semibold text-ink">
                                        {{ teacher.rate }}<span class="text-sm font-normal text-ink-400">{{ copy.perHour }}</span>
                                    </p>
                                    <p class="mt-0.5 text-xs text-ink-500">
                                        <template v-if="teacher.nextSlot">{{ copy.nextLabel }} {{ teacher.nextSlot }}</template>
                                        <template v-else>{{ copy.noSlots }}</template>
                                    </p>
                                </div>
                                <span class="inline-flex items-center gap-1 text-sm font-medium text-brand-600">
                                    {{ copy.view }}
                                    <AppIcon name="arrow-right" class="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                                </span>
                            </div>
                        </a>
                    </div>

                    <nav v-if="config.pagination.length > 3" class="mt-10 flex flex-wrap items-center justify-center gap-1.5" aria-label="Pagination">
                        <template v-for="(link, index) in config.pagination" :key="`${link.label}-${index}`">
                            <a
                                v-if="link.url"
                                :href="link.url"
                                class="min-w-9 rounded-full px-3 py-1.5 text-center text-sm font-medium transition"
                                :class="link.active ? 'bg-ink text-white' : 'hairline bg-white text-ink-700 hover:border-ink-500'"
                                :aria-current="link.active ? 'page' : undefined"
                            >{{ link.label }}</a>
                            <span v-else class="px-2 py-1.5 text-sm text-ink-400">{{ link.label }}</span>
                        </template>
                    </nav>
                </div>
            </section>

            <section v-if="!config.authenticated" class="mx-auto w-full max-w-6xl px-5 py-16 lg:py-20">
                <div class="relative overflow-hidden rounded-3xl px-6 py-14 text-center text-white sm:px-12" style="background: var(--color-ink)">
                    <div class="lp-aurora lp-aurora-dark" aria-hidden="true"><span></span><span></span><span></span></div>
                    <div class="lp-grid-lines lp-grid-lines-dark pointer-events-none absolute inset-0" aria-hidden="true"></div>

                    <h2 class="relative text-display-lg font-semibold">{{ copy.signInTitle }}</h2>
                    <p class="relative mx-auto mt-5 max-w-xl text-lg leading-relaxed text-white/70">{{ copy.signInBody }}</p>

                    <div class="relative mt-8 flex flex-col justify-center gap-3 sm:flex-row sm:items-center">
                        <a
                            :href="urls.registerStudent"
                            class="inline-flex shrink-0 items-center justify-center gap-2 rounded-full bg-brand-500 px-7 py-3.5 font-medium whitespace-nowrap text-white transition hover:bg-brand-600"
                            style="box-shadow: var(--shadow-brand)"
                        >
                            {{ copy.getStarted }}
                            <AppIcon name="arrow-right" class="h-4 w-4" />
                        </a>
                        <a
                            :href="urls.login"
                            class="shrink-0 rounded-full border border-white/20 bg-white/10 px-7 py-3.5 font-medium whitespace-nowrap text-white backdrop-blur transition hover:bg-white/20"
                        >
                            {{ copy.logIn }}
                        </a>
                    </div>
                </div>
            </section>
        </main>

        <SiteFooter
            :app-name="config.appName"
            :urls="urls"
            :support="config.support ?? {}"
            :logo="config.images.logo"
            :landing-url="urls.home"
        />
    </div>
</template>
