import { computed, reactive, ref, watch } from 'vue';

/**
 * The state the subject workspace edits: one reactive copy of every approved
 * subject's grade/rate/lesson scope, the open card, the active tab, the catalog
 * selection and the propose form. Nothing here re-implements a server rule —
 * `SubjectSetupRequest` still trims lessons to the selected grades and drops
 * rate rows outside them; this composable only keeps the controls in step.
 */
export function createSubjectsWorkspace(config) {
    const subjects = reactive((config.subjects ?? []).map((subject) => ({
        ...subject,
        defaultRate: numberOrNull(subject.defaultRate),
        grades: (subject.grades ?? []).map((grade) => ({
            ...grade,
            rate: numberOrNull(grade.rate),
            lessons: (grade.lessons ?? []).map((lesson) => ({ ...lesson })),
        })),
    })));

    // What the teacher saw when the page loaded — the only thing `Reset` and
    // the dirty markers compare against.
    const snapshots = new Map(subjects.map((subject) => [subject.id, snapshot(subject)]));

    const activeTab = ref(normalizeTab(config.initialTab));
    const openSubjectId = ref(
        Number(config.openSubjectId ?? config.editingSubjectId ?? 0) || null,
    );
    const addSection = ref(config.addSection === 'propose' ? 'propose' : 'catalog');
    const submittingSubjectId = ref(null);
    const submittingAdd = ref(false);
    const submittingPropose = ref(false);

    const catalog = config.catalog ?? { levels: [], hasSubjects: false };
    const catalogLevels = computed(() => catalog.levels ?? []);
    const activeLevelKey = ref(catalog.defaultLevelKey ?? catalogLevels.value[0]?.key ?? null);
    const catalogQuery = ref('');
    const selectedSubjectIds = ref([]);

    const propose = config.propose ?? {};
    const proposeValues = reactive({
        name: '',
        levelId: null,
        note: '',
        ...(propose.values ?? {}),
    });
    const proposeErrors = reactive({
        name: [],
        levelId: [],
        note: [],
        ...(propose.errors ?? {}),
    });

    const pending = config.pending ?? [];
    const rejected = config.rejected ?? [];
    const pageErrors = config.subjectIdErrors ?? [];

    const activeLevel = computed(
        () => catalogLevels.value.find((level) => level.key === activeLevelKey.value) ?? null,
    );

    const visibleCatalogSubjects = computed(() => {
        const needle = catalogQuery.value.trim().toLowerCase();
        const subjectsForLevel = activeLevel.value?.subjects ?? [];

        return needle
            ? subjectsForLevel.filter((subject) => subject.name.toLowerCase().includes(needle))
            : subjectsForLevel;
    });

    const selectedCatalogCount = computed(() => selectedSubjectIds.value.length);

    const dirtySubjectIds = computed(
        () => subjects.filter((subject) => isDirty(subject)).map((subject) => subject.id),
    );

    const dirtyCount = computed(() => dirtySubjectIds.value.length);

    const failedSubject = computed(
        () => subjects.find((subject) => hasErrors(subject)) ?? null,
    );

    function isDirty(subject) {
        return snapshot(subject) !== snapshots.get(subject.id);
    }

    function resetSubject(subject) {
        const original = JSON.parse(snapshots.get(subject.id));

        subject.defaultRate = original.defaultRate;
        subject.grades.forEach((grade, index) => {
            const source = original.grades[index];

            if (!source) {
                return;
            }

            grade.selected = source.selected;
            grade.rate = source.rate;
            grade.lessons.forEach((lesson, lessonIndex) => {
                lesson.selected = source.lessons[lessonIndex] ?? lesson.selected;
            });
        });

        // A reset cancels the pending complaint as well as the edits.
        Object.keys(subject.errors ?? {}).forEach((key) => {
            subject.errors[key] = [];
        });
    }

    function toggleSubject(subject) {
        openSubjectId.value = openSubjectId.value === subject.id ? null : subject.id;
    }

    function isOpen(subject) {
        return openSubjectId.value === subject.id;
    }

    function toggleGrade(grade) {
        grade.selected = !grade.selected;
    }

    function setGradeLessons(grade, selected) {
        grade.lessons.forEach((lesson) => {
            lesson.selected = selected;
        });
    }

    function selectAllLessons(subject) {
        subject.grades.filter((grade) => grade.selected).forEach((grade) => setGradeLessons(grade, true));
    }

    function clearLessons(subject) {
        subject.grades.forEach((grade) => setGradeLessons(grade, false));
    }

    function countsFor(subject) {
        const selectedGrades = subject.grades.filter((grade) => grade.selected);

        return {
            grades: selectedGrades.length,
            totalGrades: subject.grades.length,
            lessons: selectedGrades.reduce(
                (total, grade) => total + grade.lessons.filter((lesson) => lesson.selected).length,
                0,
            ),
            totalLessons: subject.grades.reduce((total, grade) => total + grade.lessons.length, 0),
        };
    }

    function hasErrors(subject) {
        const errors = subject.errors ?? {};

        return ['grades', 'lessons', 'gradeRates', 'rate'].some(
            (key) => (errors[key] ?? []).length > 0,
        );
    }

    function errorMessages(subject) {
        const errors = subject.errors ?? {};

        return ['grades', 'lessons', 'gradeRates', 'rate'].flatMap((key) => errors[key] ?? []);
    }

    function markSubmitting(subject) {
        submittingSubjectId.value = subject.id;
    }

    function toggleCatalogSubject(id) {
        selectedSubjectIds.value = selectedSubjectIds.value.includes(id)
            ? selectedSubjectIds.value.filter((subjectId) => subjectId !== id)
            : [...selectedSubjectIds.value, id];
    }

    function selectVisibleCatalogSubjects() {
        const ids = visibleCatalogSubjects.value.map((subject) => subject.id);
        selectedSubjectIds.value = [...new Set([...selectedSubjectIds.value, ...ids])];
    }

    function clearVisibleCatalogSubjects() {
        const ids = new Set(visibleCatalogSubjects.value.map((subject) => subject.id));
        selectedSubjectIds.value = selectedSubjectIds.value.filter((id) => !ids.has(id));
    }

    function goToAdd() {
        activeTab.value = 'add';
        addSection.value = 'catalog';
        scrollToTop();
    }

    /**
     * "Request again" on a rejected row: open the add flow with the subject
     * already searched for (and ticked, when it is a catalog subject).
     */
    function reapply(item) {
        activeTab.value = 'add';

        if (item.reapplySection === 'propose') {
            addSection.value = 'propose';
            proposeValues.name = item.reapplyQuery ?? '';
            proposeValues.levelId = item.reapplyLevelId ?? null;

            return;
        }

        addSection.value = 'catalog';
        const level = catalogLevels.value.find((entry) => entry.key === item.reapplyLevelKey)
            ?? catalogLevels.value.find((entry) => entry.subjects.some((subject) => subject.name === item.reapplyQuery))
            ?? null;

        if (level) {
            activeLevelKey.value = level.key;
        }

        catalogQuery.value = item.reapplyQuery ?? '';

        const match = (level?.subjects ?? []).find((subject) => subject.name === item.reapplyQuery);

        if (match && !selectedSubjectIds.value.includes(match.id)) {
            selectedSubjectIds.value = [...selectedSubjectIds.value, match.id];
        }

        scrollToTop();
    }

    function scrollToTop() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // Keep the tab addressable (#requests / #add) without a router.
    watch(activeTab, (tab) => {
        const { pathname, search } = window.location;
        const url = `${pathname}${search}${tab === 'my' ? '' : `#${tab}`}`;

        window.history.replaceState(null, '', url);
    });

    return {
        subjects,
        pending,
        rejected,
        pageErrors,
        catalog,
        catalogLevels,
        activeLevel,
        activeLevelKey,
        catalogQuery,
        visibleCatalogSubjects,
        selectedSubjectIds,
        selectedCatalogCount,
        propose,
        proposeValues,
        proposeErrors,
        activeTab,
        addSection,
        openSubjectId,
        submittingSubjectId,
        submittingAdd,
        submittingPropose,
        dirtySubjectIds,
        dirtyCount,
        failedSubject,
        isDirty,
        resetSubject,
        toggleSubject,
        isOpen,
        toggleGrade,
        setGradeLessons,
        selectAllLessons,
        clearLessons,
        countsFor,
        hasErrors,
        errorMessages,
        markSubmitting,
        toggleCatalogSubject,
        selectVisibleCatalogSubjects,
        clearVisibleCatalogSubjects,
        goToAdd,
        reapply,
    };
}

function snapshot(subject) {
    return JSON.stringify({
        defaultRate: subject.defaultRate,
        grades: subject.grades.map((grade) => ({
            id: grade.id,
            selected: grade.selected,
            rate: grade.rate,
            lessons: grade.lessons.map((lesson) => [lesson.id, lesson.selected]),
        })),
    });
}

function numberOrNull(value) {
    return value === null || value === undefined || value === '' ? null : Number(value);
}

function normalizeTab(value) {
    return ['my', 'requests', 'add'].includes(value) ? value : 'my';
}

export default createSubjectsWorkspace;
