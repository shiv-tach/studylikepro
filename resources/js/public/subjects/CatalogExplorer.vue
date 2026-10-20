<script setup>
import { computed, watch } from 'vue';
import { isPlainClick } from '../client-navigation';
import CatalogSkeleton from './CatalogSkeleton.vue';
import ExplorerBar from './ExplorerBar.vue';
import SubjectResults from './SubjectResults.vue';
import { createCatalogExplorer } from './composables/useCatalogExplorer';

const props = defineProps({
    config: { type: Object, required: true },
    urls: { type: Object, required: true },
});

const emit = defineEmits(['change', 'open-subject', 'state']);

const explorer = createCatalogExplorer(props.config, props.urls, emit);
const {
    levels, level, grade, grades, groups, basketNote, counts, pending, error, copy,
} = explorer;

const hasResults = computed(() => counts.value.subjects > 0);

defineExpose({ applyState: explorer.applyState });

// The hero breadcrumb follows the explorer without either owning the other.
watch([level, grade], ([nextLevel, nextGrade]) => {
    emit('state', { level: nextLevel, grade: nextGrade });
}, { immediate: true });

function selectLevel(event, option) {
    if (!isPlainClick(event)) {
        return;
    }

    event.preventDefault();
    explorer.selectLevel(option);
}

function prefetchLevel(key) {
    explorer.prefetch(key);
}

function prefetchGrade(option) {
    explorer.prefetch(option.levelKey ?? level.value?.key, option.id);
}
</script>

<template>
    <section id="catalog-explorer" class="border-b border-line bg-ink-50 pb-14 lg:pb-16" :aria-label="copy.explorerLabel">
        <ExplorerBar
            :levels="levels"
            :level="level"
            :grades="grades"
            :grade="grade"
            :copy="copy"
            @select-level="explorer.selectLevel"
            @select-grade="explorer.selectGrade"
            @clear-grade="explorer.clearGrade"
            @prefetch-level="prefetchLevel"
            @prefetch-grade="prefetchGrade"
        />

        <div class="mx-auto w-full max-w-6xl px-5">
            <div v-if="error" class="mt-8 flex flex-wrap items-center gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-700" role="alert">
                <span class="flex-1">{{ error }}</span>
                <button
                    type="button"
                    class="rounded-full bg-rose-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-rose-700"
                    @click="explorer.retry()"
                >{{ copy.retry }}</button>
            </div>

            <p v-if="pending" class="sr-only" role="status">{{ copy.loadingSubjects }}</p>
            <CatalogSkeleton v-if="pending" class="mt-8" />

            <template v-else>
                <!-- No level yet: the same four cards the page has always opened with. -->
                <div v-if="!level" class="pt-10">
                    <p class="text-xs font-semibold tracking-widest text-ink-400 uppercase">{{ copy.levelStep }}</p>

                    <div v-if="levels.length" class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <a
                            v-for="option in levels"
                            :key="option.key"
                            :href="option.url"
                            class="lift hairline group rounded-2xl bg-white p-5"
                            @click="selectLevel($event, option)"
                            @pointerenter="prefetchLevel(option.key)"
                            @focus="prefetchLevel(option.key)"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <span
                                    class="flex size-11 items-center justify-center rounded-xl bg-brand-50 text-xl"
                                    aria-hidden="true"
                                >{{ option.icon }}</span>
                            </div>
                            <h2 class="mt-4 font-display text-lg font-semibold text-ink">{{ option.name }}</h2>
                            <p v-if="option.gradeRange" class="mt-1 text-xs font-medium text-ink-500">{{ option.gradeRange }}</p>
                            <p class="mt-3 text-sm font-medium text-brand-600 underline-offset-4 group-hover:underline">
                                {{ copy.levelCta }}
                            </p>
                        </a>
                    </div>

                    <p v-else class="hairline mt-5 rounded-2xl bg-white p-8 text-center text-sm text-ink-500">
                        {{ copy.catalogEmpty }}
                    </p>
                </div>

                <!-- Level picked, grade not yet: the chips are right above. -->
                <div v-else-if="!grade" class="pt-10">
                    <p class="text-xs font-semibold tracking-widest text-ink-400 uppercase">{{ copy.gradeStep }}</p>
                    <p class="mt-2 max-w-xl text-sm leading-relaxed text-ink-500">
                        {{ grades.length ? copy.chooseGrade : copy.gradeEmpty }}
                    </p>
                </div>

                <div v-else>
                    <div class="flex flex-wrap items-baseline justify-between gap-3 pt-8">
                        <div class="flex flex-wrap items-center gap-2.5">
                            <h2 id="catalog-results-title" class="font-display text-2xl font-semibold text-ink">
                                {{ copy.resultsTitle }}
                            </h2>
                            <span class="hairline rounded-full bg-white px-3 py-1 text-xs font-semibold text-ink-700">
                                {{ level.name }} · {{ grade.label }}
                            </span>
                        </div>
                        <p class="text-sm text-ink-500" aria-live="polite">
                            {{ counts.subjects }} {{ copy.subjectsWord }} · {{ counts.lessons }} {{ copy.lessonsWord }}
                        </p>
                    </div>

                    <p v-if="basketNote" class="mt-4 max-w-3xl text-sm leading-relaxed text-ink-700">
                        {{ basketNote }}
                    </p>

                    <p v-if="!hasResults" class="hairline mt-6 rounded-2xl bg-white p-8 text-center text-sm text-ink-500">
                        {{ copy.subjectsEmpty }}
                    </p>

                    <div v-else class="mt-7">
                        <SubjectResults
                            :groups="groups"
                            :copy="copy"
                            @open="$emit('open-subject', $event)"
                        />
                    </div>
                </div>
            </template>
        </div>
    </section>
</template>
