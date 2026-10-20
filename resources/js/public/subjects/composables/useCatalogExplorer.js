import { computed, ref, shallowRef } from 'vue';
import { loadCatalogState } from '../../client-navigation';

/**
 * The sticky explorer's state machine. The server stays the source of truth for
 * which subjects run in a grade (grade bands, baskets, active lessons), so a
 * pick fetches that grade's island instead of filtering a mirrored catalog in
 * JavaScript. Responses are cached per selection, so going back to a grade the
 * visitor already opened is instant, and hovering a level or grade warms the
 * next one before it is clicked.
 */
export function createCatalogExplorer(config, urls, emit) {
    const state = shallowRef(snapshot(config));
    const pending = ref(false);
    const error = ref(null);
    const copy = computed(() => config.copy ?? {});

    let controller = null;

    const levels = computed(() => state.value.levels);
    const level = computed(() => state.value.level);
    const grade = computed(() => state.value.grade);
    const grades = computed(() => state.value.grades);
    const groups = computed(() => state.value.subjectGroups);
    const groupedByBasket = computed(() => state.value.groupedByBasket);
    const basketNote = computed(() => state.value.basketNote ?? copy.value.basketNote ?? null);

    const counts = computed(() => {
        let subjects = 0;
        let lessons = 0;

        for (const group of groups.value) {
            for (const subject of group.subjects ?? []) {
                subjects++;
                lessons += Number(subject.lessonCount ?? 0);
            }
        }

        return { subjects, lessons };
    });

    async function load(levelKey, gradeId, { silent = false } = {}) {
        controller?.abort();
        controller = new AbortController();
        const signal = controller.signal;

        if (!silent) {
            pending.value = true;
        }

        error.value = null;

        try {
            const payload = await loadCatalogState(urls.subjects, { level: levelKey, grade: gradeId }, signal);

            if (payload) {
                state.value = snapshot(payload);
            }

            return state.value;
        } catch (failure) {
            if (failure.name !== 'AbortError') {
                error.value = copy.value.loadFailed ?? 'Could not load this grade.';
            }

            return null;
        } finally {
            // An older request finishing after a newer one started must not
            // clear the newer request's loading state.
            if (controller?.signal === signal) {
                pending.value = false;
            }
        }
    }

    async function selectLevel(option) {
        if (option.key === level.value?.key) {
            return;
        }

        emit('change', { level: option.key, grade: null, url: option.url });

        const loaded = await load(option.key, null);

        // A level with a single grade (e.g. "Other") has nothing to choose, so
        // the pick is promoted to the grade itself. It replaces the level entry
        // instead of stacking a second history step.
        if (loaded && loaded.grades.length === 1) {
            const only = loaded.grades[0];

            emit('change', { level: option.key, grade: only.id, url: only.url, replace: true });
            await load(option.key, only.id);
        }
    }

    async function selectGrade(option) {
        if (option.id === grade.value?.id) {
            return;
        }

        const levelKey = option.levelKey ?? level.value?.key;

        if (!levelKey) {
            return;
        }

        emit('change', { level: levelKey, grade: option.id, url: option.url });
        await load(levelKey, option.id);
    }

    async function clearGrade() {
        if (!level.value || !grade.value) {
            return;
        }

        emit('change', { level: level.value.key, grade: null, url: level.value.url });
        await load(level.value.key, null);
    }

    /** Restore a selection that came from history or a deep link. */
    async function applyState({ level: levelKey = null, grade: gradeId = null } = {}) {
        if ((level.value?.key ?? null) === levelKey && (grade.value?.id ?? null) === gradeId) {
            return;
        }

        await load(levelKey, gradeId);
    }

    /** Warm the cache without touching the current view. */
    function prefetch(levelKey, gradeId = null) {
        if (!levelKey) {
            return;
        }

        loadCatalogState(urls.subjects, { level: levelKey, grade: gradeId }).catch(() => {});
    }

    function retry() {
        return load(level.value?.key ?? null, grade.value?.id ?? null);
    }

    return {
        levels,
        level,
        grade,
        grades,
        groups,
        groupedByBasket,
        basketNote,
        counts,
        pending,
        error,
        copy,
        selectLevel,
        selectGrade,
        clearGrade,
        applyState,
        prefetch,
        retry,
    };
}

function snapshot(payload) {
    return {
        levels: payload.levels ?? [],
        level: payload.level ?? null,
        grade: payload.grade ?? null,
        grades: payload.grades ?? [],
        subjectGroups: payload.subjectGroups ?? [],
        groupedByBasket: payload.groupedByBasket === true,
        basketNote: payload.basketNote ?? payload.copy?.basketNote ?? null,
    };
}
