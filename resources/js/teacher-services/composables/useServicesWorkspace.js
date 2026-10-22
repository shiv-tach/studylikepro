import { computed, reactive, ref, watch } from 'vue';
import { format, plural } from './usePageContext';

/**
 * The whole page's client state: which tab, filters and catalog layout are
 * active, which service the composer is editing, the cascade between grade /
 * subject / lesson, the hours + minutes split, and the live preview. Everything
 * that can be decided on the server (labels, routes, limits) arrives in the
 * island; this module only mirrors it.
 */
export function createServicesWorkspace(config) {
    const copy = config.copy ?? {};
    const types = config.types ?? [];
    const options = config.options ?? {};
    const limits = config.limits ?? {};
    const offerings = config.offerings ?? [];
    const VIEW_STORAGE_KEY = 'teacherServicesView';
    const VIEWS = ['list', 'card'];

    const activeTab = ref('all');
    const search = ref('');
    const status = ref('all');
    const view = ref(readStoredView());

    /** The catalog opens as a list; a teacher's pick survives the next visit. */
    function readStoredView() {
        try {
            const stored = window.localStorage.getItem(VIEW_STORAGE_KEY);

            return VIEWS.includes(stored) ? stored : 'list';
        } catch (error) {
            return 'list';
        }
    }

    watch(view, (next) => {
        try {
            window.localStorage.setItem(VIEW_STORAGE_KEY, next);
        } catch (error) {
            // Private mode or blocked storage: the toggle still works per page.
        }
    });

    const countsByType = computed(() => {
        const counts = {};

        offerings.forEach((offering) => {
            counts[offering.type] = (counts[offering.type] ?? 0) + 1;
        });

        return counts;
    });

    const tabs = computed(() => [
        {
            key: 'all',
            label: copy.tabs?.all ?? 'All services',
            icon: 'tag',
            count: offerings.length,
        },
        ...types.map((type) => ({
            key: type.value,
            label: type.pluralLabel,
            icon: type.icon,
            count: countsByType.value[type.value] ?? 0,
        })),
    ]);

    const filteredOfferings = computed(() => {
        const term = search.value.trim().toLowerCase();

        return offerings.filter((offering) => {
            if (activeTab.value !== 'all' && offering.type !== activeTab.value) {
                return false;
            }

            if (status.value === 'active' && !offering.isActive) {
                return false;
            }

            if (status.value === 'paused' && offering.isActive) {
                return false;
            }

            if (term === '') {
                return true;
            }

            return [offering.title, offering.typeLabel, offering.subjectName, offering.lessonName, offering.description]
                .filter(Boolean)
                .some((value) => String(value).toLowerCase().includes(term));
        });
    });

    const hasFilters = computed(() => status.value !== 'all' || search.value.trim() !== '');

    function clearFilters() {
        search.value = '';
        status.value = 'all';
    }

    const resultLabel = computed(() => format(
        plural(filteredOfferings.value.length, copy.toolbar?.resultsOne, copy.toolbar?.results),
        { count: filteredOfferings.value.length },
    ));

    const activeTabType = computed(() => types.find((type) => type.value === activeTab.value) ?? null);

    /* ---------------------------------------------------------------- composer */

    const composer = ref(null);
    const errors = ref({});
    const submitting = ref(false);
    const discardOpen = ref(false);
    const values = reactive(blankValues(''));

    const isEdit = computed(() => composer.value?.mode === 'edit');
    const isOpen = computed(() => composer.value !== null);

    const currentType = computed(() => types.find((type) => type.value === composer.value?.type) ?? null);

    const formAction = computed(() => (
        isEdit.value ? composer.value?.updateUrl ?? '#' : config.urls?.store ?? '#'
    ));

    const baseline = ref('');

    function toId(value) {
        return value === '' || value === null || value === undefined ? null : Number(value);
    }

    function blankValues(type) {
        return {
            title: '',
            description: '',
            price: '',
            gradeId: null,
            subjectId: null,
            lessonId: null,
            allocationMinutes: type === 'lesson' ? 60 : null,
            defaultSessionMinutes: null,
            maxSessions: null,
            maxPages: null,
            turnaroundHours: null,
        };
    }

    function assign(source = {}) {
        const base = blankValues(composer.value?.type ?? '');
        const pick = (key) => (source[key] === undefined ? base[key] : source[key]);

        Object.assign(values, {
            title: source.title ?? '',
            description: source.description ?? '',
            price: source.price === null || source.price === undefined ? '' : String(source.price),
            gradeId: toId(pick('gradeId')),
            subjectId: toId(pick('subjectId')),
            lessonId: toId(pick('lessonId')),
            allocationMinutes: toId(pick('allocationMinutes')),
            defaultSessionMinutes: toId(pick('defaultSessionMinutes')),
            maxSessions: toId(pick('maxSessions')),
            maxPages: toId(pick('maxPages')),
            turnaroundHours: toId(pick('turnaroundHours')),
        });
    }

    /** A stable snapshot so a field being re-typed the same way is not "dirty". */
    function snapshot() {
        return JSON.stringify({
            title: String(values.title ?? '').trim(),
            description: String(values.description ?? '').trim(),
            price: String(values.price ?? '').trim(),
            gradeId: toId(values.gradeId),
            subjectId: toId(values.subjectId),
            lessonId: toId(values.lessonId),
            allocationMinutes: toId(values.allocationMinutes),
            defaultSessionMinutes: toId(values.defaultSessionMinutes),
            maxSessions: toId(values.maxSessions),
            maxPages: toId(values.maxPages),
            turnaroundHours: toId(values.turnaroundHours),
        });
    }

    const dirty = computed(() => isOpen.value && snapshot() !== baseline.value);

    function resetBaseline() {
        baseline.value = snapshot();
    }

    function openCreate(type = null) {
        errors.value = {};
        discardOpen.value = false;
        submitting.value = false;
        composer.value = { mode: 'create', type: type || null, offeringId: null, updateUrl: null };
        assign(blankValues(type || ''));
        resetBaseline();
    }

    function openEdit(offering) {
        errors.value = {};
        discardOpen.value = false;
        submitting.value = false;
        composer.value = {
            mode: 'edit',
            type: offering.type,
            offeringId: offering.id,
            updateUrl: offering.updateUrl,
        };
        assign(offering.values ?? {});
        resetBaseline();
    }

    function closeComposer(force = false) {
        if (!composer.value) {
            return;
        }

        if (!force && dirty.value) {
            discardOpen.value = true;

            return;
        }

        composer.value = null;
        discardOpen.value = false;
        errors.value = {};
        submitting.value = false;
    }

    function confirmDiscard() {
        closeComposer(true);
    }

    function cancelDiscard() {
        discardOpen.value = false;
    }

    function selectType(type) {
        if (!composer.value || composer.value.mode === 'edit' || composer.value.type === type) {
            return;
        }

        composer.value = { ...composer.value, type };

        // Keep the shared fields, reset only what belongs to the old type.
        values.allocationMinutes = type === 'lesson' ? 60 : null;
        values.defaultSessionMinutes = null;
        values.maxSessions = null;
        values.maxPages = null;
        values.turnaroundHours = null;

        if (!['lesson', 'qna'].includes(type)) {
            values.lessonId = null;
        }

        resetBaseline();
    }

    function markSubmitting() {
        submitting.value = true;
    }

    /* ------------------------------------------------------- cascade + preview */

    const gradeChoices = computed(() => options.grades ?? []);

    const subjectChoices = computed(() => {
        const gradeId = toId(values.gradeId);

        return (options.subjects ?? []).filter((subject) => {
            if (!gradeId) {
                return true;
            }

            const grades = subject.grades ?? [];

            return grades.length === 0 || grades.includes(gradeId);
        });
    });

    const lessonChoices = computed(() => {
        const subjectId = toId(values.subjectId);
        const gradeId = toId(values.gradeId);

        return (options.lessons ?? []).filter((lesson) => (
            (!subjectId || lesson.subject_id === subjectId)
            && (!gradeId || lesson.grade_id === gradeId)
        ));
    });

    watch(() => values.gradeId, () => {
        if (values.subjectId && !subjectChoices.value.some((subject) => subject.id === toId(values.subjectId))) {
            values.subjectId = null;
        }

        if (values.lessonId && !lessonChoices.value.some((lesson) => lesson.id === toId(values.lessonId))) {
            values.lessonId = null;
        }
    });

    watch(() => values.subjectId, () => {
        if (values.lessonId && !lessonChoices.value.some((lesson) => lesson.id === toId(values.lessonId))) {
            values.lessonId = null;
        }
    });

    const hoursMax = computed(() => limits.hoursMax ?? 99);

    const allocationHours = computed({
        get() {
            const total = Number(values.allocationMinutes);

            if (!Number.isFinite(total) || total <= 0) {
                return 1;
            }

            return Math.min(hoursMax.value, Math.max(1, Math.floor(total / 60)));
        },
        set(hours) {
            const next = Math.min(hoursMax.value, Math.max(1, Number(hours) || 1));

            // 100 hours is the validated ceiling, so there is no room for the
            // extra 30 minutes at that point.
            values.allocationMinutes = next >= hoursMax.value ? hoursMax.value * 60 : next * 60 + allocationMinutesPart.value;
        },
    });

    const allocationMinutesPart = computed({
        get() {
            const total = Number(values.allocationMinutes);

            if (!Number.isFinite(total) || total <= 0) {
                return 0;
            }

            return total % 60 === 30 ? 30 : 0;
        },
        set(part) {
            const hours = allocationHours.value;
            values.allocationMinutes = hours >= hoursMax.value
                ? hoursMax.value * 60
                : hours * 60 + (Number(part) === 30 ? 30 : 0);
        },
    });

    function humanDuration(totalMinutes) {
        const total = Number(totalMinutes) || 0;
        const hours = Math.floor(total / 60);
        const rest = total % 60;

        if (hours > 0 && rest > 0) {
            return `${hours}h ${rest}m`;
        }

        if (hours > 0) {
            return `${hours}h`;
        }

        return `${rest}m`;
    }

    const preview = computed(() => {
        const type = currentType.value;
        const detail = copy.preview?.detail ?? {};
        const grade = gradeChoices.value.find((row) => row.id === toId(values.gradeId));
        const subject = (options.subjects ?? []).find((row) => row.id === toId(values.subjectId));
        const lesson = (options.lessons ?? []).find((row) => row.id === toId(values.lessonId));
        const amount = Number(values.price);
        const chips = [];

        if (type?.value === 'lesson' && values.allocationMinutes) {
            chips.push(format(detail.total, { value: humanDuration(values.allocationMinutes) }));
        }

        if (type?.isLiveSession && values.defaultSessionMinutes) {
            chips.push(format(detail.session, { value: values.defaultSessionMinutes }));
        }

        if (type?.value === 'paper_marking') {
            chips.push(values.maxPages ? format(detail.pages, { value: values.maxPages }) : detail.anyPages);
            chips.push(format(detail.turnaround, {
                value: values.turnaroundHours ?? limits.turnaroundMin ?? 72,
            }));
        }

        return {
            typeLabel: type?.label ?? null,
            icon: type?.icon ?? 'tag',
            title: String(values.title ?? '').trim() || copy.preview?.untitled,
            description: String(values.description ?? '').trim() || copy.preview?.noDescription,
            hasDescription: String(values.description ?? '').trim() !== '',
            priceLabel: Number.isFinite(amount) && amount > 0
                ? `${config.currency?.symbol ?? ''} ${amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`.trim()
                : null,
            priceSuffix: type?.priceSuffix ?? null,
            gradeLabel: grade?.label ?? null,
            subjectName: subject?.name ?? null,
            subjectIcon: subject?.icon ?? null,
            lessonName: lesson?.name ?? null,
            chips,
            hasScope: Boolean(grade || subject || lesson),
        };
    });

    /* ------------------------------------------------------------ initial open */

    const initial = config.openComposer;

    if (initial?.mode === 'edit') {
        const offering = offerings.find((row) => row.id === initial.offeringId);

        if (offering) {
            openEdit(offering);
            errors.value = { ...(config.errors ?? {}) };
            resetBaseline();
        }
    } else if (initial?.mode === 'create') {
        openCreate(initial.type);
        assign(config.form?.values ?? {});
        errors.value = { ...(config.errors ?? {}) };
        resetBaseline();
    }

    return {
        // list
        activeTab,
        activeTabType,
        search,
        status,
        view,
        tabs,
        filteredOfferings,
        hasFilters,
        clearFilters,
        resultLabel,
        // composer
        composer,
        isOpen,
        isEdit,
        currentType,
        values,
        errors,
        submitting,
        discardOpen,
        dirty,
        formAction,
        openCreate,
        openEdit,
        closeComposer,
        confirmDiscard,
        cancelDiscard,
        selectType,
        markSubmitting,
        // cascade + preview
        gradeChoices,
        subjectChoices,
        lessonChoices,
        hoursMax,
        allocationHours,
        allocationMinutesPart,
        preview,
    };
}

export default createServicesWorkspace;
