<script setup>
/**
 * The catalog half of "Add subjects": level tabs, a search box and selectable
 * tiles across levels. Selections survive switching level; the sticky bar
 * submits them all as `subject_ids[]` for admin approval.
 */
import { computed } from 'vue';
import { usePanel } from '../../shared/composables/usePanel';
import AppIcon from '../../shared/components/AppIcon.vue';
import UiAlert from '../../shared/components/UiAlert.vue';
import UiSectionHeading from '../../shared/components/UiSectionHeading.vue';
import {
    format,
    plural,
    useCopy,
    useCsrfToken,
    usePage,
    useUrls,
    useWorkspace,
} from '../composables/usePageContext';
import CatalogSubjectTile from './CatalogSubjectTile.vue';
import { GHOST_BUTTON, PRIMARY_BUTTON } from '../styles';

const workspace = useWorkspace();
const copy = useCopy();
const page = usePage();
const urls = useUrls();
const csrfToken = useCsrfToken();
const panel = usePanel();

const {
    catalog,
    activeLevel,
    activeLevelKey,
    catalogQuery,
    visibleCatalogSubjects,
    selectedSubjectIds,
    selectedCatalogCount,
    submittingAdd,
    toggleCatalogSubject,
    selectVisibleCatalogSubjects,
    clearVisibleCatalogSubjects,
} = workspace;

const hasLevels = computed(() => (catalog.levels?.length ?? 0) > 0);

const submitLabel = computed(() => (selectedCatalogCount.value > 0
    ? format(
        plural(selectedCatalogCount.value, copy.add?.requestSelectedOne, copy.add?.requestSelected),
        { count: selectedCatalogCount.value },
    )
    : copy.add?.requestCta));
</script>

<template>
    <section :class="panel">
        <UiSectionHeading
            :title="copy.add?.title"
            :description="copy.add?.hint"
            icon="book"
            level="h2"
        />

        <UiAlert v-if="page.subjectIdErrors?.length" tone="rose" icon="alert" role="alert" class="mt-4">
            <ul class="space-y-0.5">
                <li v-for="(message, index) in page.subjectIdErrors" :key="index">{{ message }}</li>
            </ul>
        </UiAlert>

        <p
            v-if="!hasLevels"
            class="mt-5 rounded-xl border border-dashed border-slate-300 p-4 text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400"
        >
            {{ copy.add?.allOwned }}
        </p>

        <form
            v-else
            method="POST"
            :action="urls.add"
            class="mt-5 space-y-4"
            @submit="submittingAdd = true"
        >
            <input type="hidden" name="_token" :value="csrfToken">

            <div class="flex flex-wrap gap-2" role="tablist" :aria-label="copy.add?.title">
                <button
                    v-for="level in catalog.levels"
                    :key="level.key"
                    type="button"
                    role="tab"
                    :aria-selected="activeLevelKey === level.key ? 'true' : 'false'"
                    class="inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-bold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
                    :class="activeLevelKey === level.key
                        ? 'bg-primary text-white shadow-sm shadow-primary/30'
                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'"
                    @click="activeLevelKey = level.key"
                >
                    <span aria-hidden="true">{{ level.icon }}</span>
                    {{ level.name }}
                    <span
                        class="rounded-full px-1.5 py-0.5 text-[10px]"
                        :class="activeLevelKey === level.key ? 'bg-white/20' : 'bg-white dark:bg-slate-900'"
                    >
                        {{ level.subjects.length }}
                    </span>
                </button>
            </div>

            <label class="relative block">
                <span class="sr-only">{{ copy.add?.search }}</span>
                <span class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400">
                    <AppIcon name="search" />
                </span>
                <input
                    v-model="catalogQuery"
                    type="search"
                    :placeholder="copy.add?.search"
                    class="block w-full rounded-xl border-slate-300 bg-white py-2.5 pl-9 pr-10 text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                >
                <button
                    v-if="catalogQuery"
                    type="button"
                    class="absolute right-2 top-1/2 -translate-y-1/2 rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                    :aria-label="copy.add?.clear"
                    @click="catalogQuery = ''"
                >
                    <span class="block h-3.5 w-3.5"><AppIcon name="close" /></span>
                </button>
            </label>

            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">
                    {{ format(copy.add?.levelCount, { count: activeLevel?.subjects.length ?? 0 }) }}
                </p>

                <div class="flex items-center gap-1">
                    <button type="button" :class="GHOST_BUTTON" @click="selectVisibleCatalogSubjects()">
                        {{ copy.add?.selectAll }}
                    </button>
                    <button type="button" :class="GHOST_BUTTON" @click="clearVisibleCatalogSubjects()">
                        {{ copy.add?.clear }}
                    </button>
                </div>
            </div>

            <p
                v-if="!visibleCatalogSubjects.length"
                class="rounded-xl border border-dashed border-slate-300 p-4 text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400"
            >
                {{ copy.add?.noMatches }}
            </p>

            <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <CatalogSubjectTile
                    v-for="subject in visibleCatalogSubjects"
                    :key="subject.id"
                    :subject="subject"
                    :selected="selectedSubjectIds.includes(subject.id)"
                    @change="toggleCatalogSubject(subject.id)"
                />
            </div>

            <div class="sticky bottom-4 z-10 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200/80 bg-white/95 p-3 shadow-sm backdrop-blur dark:border-slate-700/80 dark:bg-slate-900/95">
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200" role="status" aria-live="polite">
                    {{ format(plural(selectedCatalogCount, copy.add?.selectedOne, copy.add?.selected), { count: selectedCatalogCount }) }}
                </p>

                <button type="submit" :class="PRIMARY_BUTTON" :disabled="selectedCatalogCount === 0 || submittingAdd">
                    {{ submitLabel }}
                </button>
            </div>
        </form>
    </section>
</template>
