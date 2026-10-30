<script setup>
/**
 * Question 2 — the subjects of the level(s) answered first.
 *
 * The server has already narrowed the catalog to those levels and annotated
 * every row with its level plus any same-named subject in the other level. This
 * component adds the browsing layer: level tabs (O/L · A/L when the teacher
 * picked both), a search box that spans every picked level, select-all/clear on
 * the visible rows, and a selected summary that makes a forgotten A/L subject
 * obvious before the teacher continues.
 */
import { computed, ref } from 'vue';
import { useSurveyContext } from '../../composables/useSurveyContext';
import AppIcon from '../../../shared/components/AppIcon.vue';
import FieldMessages from '../FieldMessages.vue';
import SubjectTile from './SubjectTile.vue';
import { GHOST_BUTTON } from '../../styles';

const { config, form } = useSurveyContext();

const copy = computed(() => config.questionCopy ?? {});
const groups = computed(() => config.options.subjectGroups ?? []);
const multiLevel = computed(() => groups.value.length > 1);
const selectedIds = computed(() => form.values.subjects ?? []);

const activeKey = ref(groups.value[0]?.key ?? null);
const query = ref('');

const activeGroup = computed(
    () => groups.value.find((group) => group.key === activeKey.value) ?? groups.value[0] ?? null,
);

const allSubjects = computed(() => groups.value.flatMap((group) => group.subjects.map((subject) => ({
    ...subject,
    levelKey: group.key,
    levelName: subject.levelName ?? group.shortName ?? group.name,
}))));

const needle = computed(() => query.value.trim().toLowerCase());

// While a search is running the results span every level, so no tab is the
// active one — otherwise the highlighted tab would claim results it does not own.
const tabActiveKey = computed(() => (needle.value === '' ? activeKey.value : null));

const results = computed(() => {
    if (needle.value !== '') {
        return allSubjects.value.filter((subject) => subject.name.toLowerCase().includes(needle.value));
    }

    const group = activeGroup.value;

    return group === null ? [] : group.subjects.map((subject) => ({
        ...subject,
        levelKey: group.key,
        levelName: subject.levelName ?? group.shortName ?? group.name,
    }));
});

const selectedRows = computed(() => allSubjects.value.filter((subject) => selectedIds.value.includes(subject.id)));
const selectedCount = computed(() => selectedRows.value.length);

// A soft nudge only: every level has to be considered, but a teacher who picked
// "both" may legitimately teach just one of them today.
const missingLevels = computed(() => (multiLevel.value && selectedCount.value > 0
    ? groups.value.filter((group) => countFor(group) === 0)
    : []));

function format(text, values = {}) {
    return Object.entries(values).reduce(
        (result, [key, value]) => result.split(`:${key}`).join(String(value)),
        text ?? '',
    );
}

function countFor(group) {
    return group.subjects.filter((subject) => selectedIds.value.includes(subject.id)).length;
}

function toggle(id) {
    form.values.subjects = selectedIds.value.includes(id)
        ? selectedIds.value.filter((value) => value !== id)
        : [...selectedIds.value, id];
}

function add(id) {
    if (! selectedIds.value.includes(id)) {
        form.values.subjects = [...selectedIds.value, id];
    }
}

function selectTab(key) {
    activeKey.value = key;
    query.value = '';
}

function selectVisible() {
    const ids = results.value
        .map((subject) => subject.id)
        .filter((id) => ! selectedIds.value.includes(id));

    if (ids.length) {
        form.values.subjects = [...selectedIds.value, ...ids];
    }
}

function clearVisible() {
    const visible = new Set(results.value.map((subject) => subject.id));

    form.values.subjects = selectedIds.value.filter((id) => ! visible.has(id));
}

function suggestionsFor(subject) {
    return (subject.twins ?? []).filter((twin) => ! selectedIds.value.includes(twin.id));
}
</script>

<template>
    <fieldset>
        <legend class="sr-only">{{ config.title }}</legend>

        <div class="space-y-4">
            <p v-if="!multiLevel" class="text-sm text-slate-500 dark:text-slate-400">
                {{ copy.subjectBrowseHint }}
            </p>

            <div v-if="multiLevel" class="flex flex-wrap gap-2" :aria-label="copy.subjectTabsLabel" role="group">
                <button
                    v-for="group in groups"
                    :key="group.key"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-bold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
                    :class="tabActiveKey === group.key
                        ? 'bg-primary text-white shadow-sm shadow-primary/30'
                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'"
                    :aria-pressed="tabActiveKey === group.key ? 'true' : 'false'"
                    @click="selectTab(group.key)"
                >
                    <span aria-hidden="true">{{ group.icon }}</span>
                    {{ group.shortName ?? group.name }}
                    <span
                        class="rounded-full px-1.5 py-0.5 text-[10px]"
                        :class="tabActiveKey === group.key ? 'bg-white/20' : 'bg-white dark:bg-slate-900'"
                    >
                        {{ countFor(group) }}/{{ group.subjects.length }}
                    </span>
                </button>
            </div>

            <label class="relative block">
                <span class="sr-only">{{ copy.subjectSearch }}</span>
                <span class="pointer-events-none absolute left-3 top-1/2 z-10 h-4 w-4 -translate-y-1/2 text-slate-400">
                    <AppIcon name="search" />
                </span>
                <input
                    v-model="query"
                    type="search"
                    :placeholder="copy.subjectSearch"
                    class="block w-full rounded-xl border-slate-300 bg-white py-2.5 pl-9 pr-10 text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 [&::-webkit-search-cancel-button]:appearance-none"
                >
                <button
                    v-if="query"
                    type="button"
                    class="absolute right-2 top-1/2 -translate-y-1/2 rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                    :aria-label="copy.subjectSearchClear"
                    @click="query = ''"
                >
                    <span class="block h-3.5 w-3.5"><AppIcon name="close" /></span>
                </button>
            </label>

            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400" aria-live="polite">
                    <template v-if="needle">{{ copy.subjectSearchResults }} · </template>
                    {{ results.length === 1 ? copy.subjectCountOne : format(copy.subjectCount, { count: results.length }) }}
                </p>

                <div class="flex items-center gap-1">
                    <button type="button" :class="GHOST_BUTTON" @click="selectVisible">
                        {{ copy.subjectSelectAll }}
                    </button>
                    <button type="button" :class="GHOST_BUTTON" @click="clearVisible">
                        {{ copy.subjectClear }}
                    </button>
                </div>
            </div>

            <p
                v-if="!results.length"
                class="rounded-xl border border-dashed border-slate-300 p-4 text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400"
            >
                {{ format(copy.subjectNoMatches, { query: query.trim() }) }}
            </p>

            <div v-else class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                <SubjectTile
                    v-for="subject in results"
                    :key="subject.id"
                    :subject="subject"
                    :checked="selectedIds.includes(subject.id)"
                    :show-level="multiLevel"
                    :suggestions="suggestionsFor(subject)"
                    :copy="copy"
                    @toggle="toggle"
                    @add="add"
                />
            </div>

            <div
                v-if="missingLevels.length"
                class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs font-medium text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300"
            >
                <p v-for="group in missingLevels" :key="group.key">
                    {{ format(copy.subjectMissingLevel, { level: group.shortName ?? group.name }) }}
                </p>
            </div>

            <div class="rounded-xl border border-slate-200/80 bg-slate-50/70 p-3 dark:border-slate-800/80 dark:bg-slate-900/60">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        {{ copy.subjectSelectedTitle }}
                    </p>
                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">
                        {{ selectedCount === 1 ? copy.subjectSelectedOne : format(copy.subjectSelected, { count: selectedCount }) }}
                    </span>
                </div>

                <p v-if="!selectedRows.length" class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    {{ copy.subjectSelectionsEmpty }}
                </p>

                <ul v-else class="mt-2 flex max-h-40 flex-wrap gap-1.5 overflow-y-auto">
                    <li v-for="subject in selectedRows" :key="subject.id">
                        <span class="inline-flex items-center gap-1 rounded-full bg-white px-2 py-1 text-[11px] font-semibold text-slate-700 shadow-sm dark:bg-slate-800 dark:text-slate-200">
                            <span
                                v-if="multiLevel"
                                class="rounded-full bg-primary/10 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-primary"
                            >
                                {{ subject.levelName }}
                            </span>
                            {{ subject.name }}
                            <button
                                type="button"
                                class="rounded-full p-0.5 text-slate-400 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/40"
                                :aria-label="format(copy.subjectRemove, { name: subject.name })"
                                @click="toggle(subject.id)"
                            >
                                <span class="block h-3 w-3"><AppIcon name="close" /></span>
                            </button>
                        </span>
                    </li>
                </ul>
            </div>
        </div>

        <FieldMessages :messages="form.errorsFor('subjects')" />
        <FieldMessages :messages="form.errorsUnder('subjects.')" class="mt-1" />
    </fieldset>
</template>
