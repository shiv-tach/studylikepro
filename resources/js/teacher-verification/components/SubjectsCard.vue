<script setup>
/**
 * The onboarding subject picker: the catalog grouped into level tabs (O/L,
 * A/L, ...), searchable, with per-tab select-all/clear. Picks post to
 * the same route as the no-JS form and save immediately; the admin approves
 * them together with the application.
 */
import { computed, ref, watch } from 'vue';
import { usePanel } from '../../shared/composables/usePanel';
import AppIcon from '../../shared/components/AppIcon.vue';
import UiChip from '../../shared/components/UiChip.vue';
import UiSectionHeading from '../../shared/components/UiSectionHeading.vue';
import { format, useCopy, useCsrfToken, useUrls } from '../composables/usePageContext';
import { useVerification } from '../composables/useVerification';
import { GHOST_BUTTON } from '../styles';

const copy = useCopy();
const urls = useUrls();
const csrfToken = useCsrfToken();
const panel = usePanel();

const { subjectGroups, subjectIds, syncingSubjects, canEdit, errors, syncSubjects } = useVerification();

// A level can hold several dozen subjects (O/L alone has 46), so the list opens
// with a short first page instead of a wall of checkboxes.
const VISIBLE_LIMIT = 10;

const form = ref(null);
const activeTab = ref(0);
const query = ref('');
const expanded = ref(false);

const activeGroup = computed(() => subjectGroups[activeTab.value] ?? null);

const filteredSubjects = computed(() => {
    if (!activeGroup.value) {
        return [];
    }

    const needle = query.value.trim().toLowerCase();

    if (!needle) {
        return activeGroup.value.subjects;
    }

    return activeGroup.value.subjects.filter((subject) => subject.name.toLowerCase().includes(needle));
});

const selectedCount = computed(() => subjectIds.value.length);

const isSearching = computed(() => query.value.trim().length > 0);

// Collapsed shows the first page plus anything already picked beyond it, so a
// choice can never hide behind the "show all" button. Searching drops the cap.
const visibleSubjects = computed(() => {
    const matches = filteredSubjects.value;

    if (expanded.value || isSearching.value) {
        return matches;
    }

    const head = matches.slice(0, VISIBLE_LIMIT);
    const shown = new Set(head.map((subject) => Number(subject.id)));

    return [...head, ...matches.filter((subject) => !shown.has(Number(subject.id)) && isSelected(subject.id))];
});

const hiddenCount = computed(() => filteredSubjects.value.length - visibleSubjects.value.length);

const canToggleList = computed(() => expanded.value || hiddenCount.value > 0);

// The chosen subjects across every level, for the read-only view after submit.
const selectedSubjects = computed(() => {
    const byId = new Map();

    subjectGroups.forEach((group) => group.subjects.forEach((subject) => byId.set(subject.id, subject)));

    return subjectIds.value.map((id) => byId.get(Number(id)) ?? { id: Number(id), name: `#${id}`, icon: '📘' });
});

function isSelected(id) {
    return subjectIds.value.some((subjectId) => Number(subjectId) === Number(id));
}

async function persist(ids) {
    const result = await syncSubjects(ids);

    if (result === 'fallback') {
        form.value?.submit();
    }
}

function toggle(id) {
    const ids = new Set(subjectIds.value.map(Number));

    if (ids.has(Number(id))) {
        ids.delete(Number(id));
    } else {
        ids.add(Number(id));
    }

    void persist([...ids]);
}

function selectAllInTab() {
    if (!activeGroup.value) {
        return;
    }

    const ids = new Set(subjectIds.value.map(Number));
    activeGroup.value.subjects.forEach((subject) => ids.add(Number(subject.id)));
    void persist([...ids]);
}

function clearTab() {
    if (!activeGroup.value) {
        return;
    }

    const removable = new Set(activeGroup.value.subjects.map((subject) => Number(subject.id)));
    void persist(subjectIds.value.filter((id) => !removable.has(Number(id))));
}

// Opening another level, or starting a new search, goes back to the short list.
watch([activeTab, query], () => {
    expanded.value = false;
});
</script>

<template>
    <section id="subjects" :class="panel" class="scroll-mt-40">
        <UiSectionHeading
            :title="copy.subjectsTitle"
            :description="canEdit ? copy.subjectsHint : copy.subjectsLockedHint"
            icon="book"
            level="h3"
        />

        <!-- The real form behind the picker: only used when fetch cannot run,
             and always carries the full selection, whatever tab is open. -->
        <form ref="form" method="POST" :action="urls.subjects" class="contents">
            <input type="hidden" name="_token" :value="csrfToken">
            <input v-for="id in subjectIds" :key="id" type="hidden" name="subjects[]" :value="id">
        </form>

        <template v-if="canEdit">
            <div class="mt-5 flex flex-wrap gap-2" role="tablist" :aria-label="copy.subjectsTitle">
                <button
                    v-for="(group, index) in subjectGroups"
                    :key="group.key"
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === index ? 'true' : 'false'"
                    :aria-controls="`subject-tab-${group.key}`"
                    class="min-h-9 rounded-full px-3.5 py-1.5 text-xs font-bold transition-colors"
                    :class="activeTab === index
                        ? 'bg-primary text-white shadow-sm shadow-primary/30'
                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'"
                    @click="activeTab = index"
                >
                    <span class="mr-1" aria-hidden="true">{{ group.icon }}</span>{{ group.name }}
                </button>
            </div>

            <div v-if="activeGroup" :id="`subject-tab-${activeGroup.key}`" role="tabpanel">
                <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:gap-3">
                    <label class="relative w-full sm:min-w-48 sm:flex-1">
                        <span class="sr-only">{{ copy.subjectsSearch }}</span>
                        <span class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400">
                            <AppIcon name="search" />
                        </span>
                        <input
                            v-model="query"
                            type="search"
                            :placeholder="copy.subjectsSearch"
                            class="min-h-11 w-full rounded-xl border border-slate-200/80 bg-white py-2 pl-9 pr-3 text-base shadow-sm focus:border-primary focus:ring-primary dark:border-slate-800 dark:bg-slate-900 dark:text-slate-100 sm:text-sm"
                        >
                    </label>
                    <div class="grid grid-cols-2 gap-2 sm:flex sm:gap-3">
                        <button type="button" :class="[GHOST_BUTTON, 'text-xs']" @click="selectAllInTab">
                            {{ copy.subjectsSelectAll }}
                        </button>
                        <button type="button" :class="[GHOST_BUTTON, 'text-xs']" @click="clearTab">
                            {{ copy.subjectsClear }}
                        </button>
                    </div>
                </div>

                <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    <label
                        v-for="subject in visibleSubjects"
                        :key="subject.id"
                        class="flex min-h-11 min-w-0 cursor-pointer items-center gap-2.5 rounded-xl border px-3.5 py-2.5 transition-all duration-200"
                        :class="isSelected(subject.id)
                            ? 'border-primary/50 bg-primary/5 ring-1 ring-primary/20'
                            : 'border-slate-200/80 hover:border-primary/40 dark:border-slate-800 dark:hover:border-primary/30'"
                    >
                        <input
                            type="checkbox"
                            :checked="isSelected(subject.id)"
                            :disabled="syncingSubjects"
                            class="h-4 w-4 shrink-0 rounded border-slate-300 text-primary shadow-sm focus:ring-primary dark:border-slate-600 dark:bg-slate-900"
                            @change="toggle(subject.id)"
                        >
                        <span class="text-base leading-none" aria-hidden="true">{{ subject.icon }}</span>
                        <span class="min-w-0 break-words text-sm font-semibold text-slate-700 dark:text-slate-200">{{ subject.name }}</span>
                    </label>
                </div>

                <p v-if="filteredSubjects.length === 0" class="mt-3 text-sm text-slate-400">{{ copy.subjectsEmpty }}</p>

                <button
                    v-else-if="!isSearching && canToggleList"
                    type="button"
                    :class="[GHOST_BUTTON, 'mt-3 w-full border border-slate-200/80 text-xs dark:border-slate-800']"
                    :aria-expanded="expanded ? 'true' : 'false'"
                    @click="expanded = !expanded"
                >
                    <span class="h-4 w-4 transition-transform motion-reduce:transition-none" :class="expanded ? 'rotate-180' : ''">
                        <AppIcon name="chevron-down" />
                    </span>
                    {{ expanded ? copy.subjectsShowLess : format(copy.subjectsShowAll, { count: filteredSubjects.length }) }}
                </button>

                <ul v-if="errors.subjects?.length" class="mt-3 space-y-1" role="alert">
                    <li v-for="(message, index) in errors.subjects" :key="index" class="text-sm font-medium text-rose-600 dark:text-rose-400">
                        {{ message }}
                    </li>
                </ul>
            </div>

            <p
                class="mt-4 flex items-center gap-2 border-t border-slate-200/80 pt-3 text-xs font-semibold dark:border-slate-800/80"
                :class="selectedCount ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'"
                role="status"
            >
                <span v-if="syncingSubjects" class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-slate-300 border-t-primary motion-reduce:animate-none"></span>
                <span v-else-if="selectedCount" class="h-3.5 w-3.5 text-emerald-500"><AppIcon name="check" /></span>
                {{ format(copy.subjectsSelected, { count: selectedCount }) }}
            </p>
        </template>

        <template v-else>
            <div class="mt-4 flex flex-wrap gap-2">
                <UiChip v-for="subject in selectedSubjects" :key="subject.id" tone="slate">
                    <span class="mr-1" aria-hidden="true">{{ subject.icon }}</span>{{ subject.name }}
                </UiChip>
            </div>
            <p v-if="selectedSubjects.length === 0" class="mt-2 text-sm text-slate-400">{{ copy.subjectsEmpty }}</p>
        </template>
    </section>
</template>
