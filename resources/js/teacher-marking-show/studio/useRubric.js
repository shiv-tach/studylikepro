import { computed, reactive, ref } from 'vue';

/**
 * The rubric the paper is scored against. Criteria arrive from Blade (a
 * snapshot of the offering's rubric at submission time), together with any
 * score already saved. Totals are always derived here, never trusted from
 * the client — the server clamps them again on save.
 */
export function createRubric(initial = {}) {
    const criteria = reactive(
        (initial.criteria ?? []).map((criterion) => ({
            key: String(criterion.key),
            title: String(criterion.title ?? ''),
            description: criterion.description ?? null,
            max: Number(criterion.max) || 0,
            maxLabel: String(criterion.maxLabel ?? criterion.max ?? ''),
            score: criterion.score === null || criterion.score === undefined ? null : Number(criterion.score),
        })),
    );

    const revision = ref(0);

    const hasCriteria = computed(() => criteria.length > 0);
    const total = computed(() => criteria.reduce((sum, criterion) => sum + (Number(criterion.score) || 0), 0));
    const max = computed(() => criteria.reduce((sum, criterion) => sum + (Number(criterion.max) || 0), 0));

    const scoredCount = computed(() => criteria.filter((criterion) => criterion.score !== null).length);
    const complete = computed(() => criteria.length === 0 || scoredCount.value === criteria.length);

    function format(value) {
        const rounded = Math.round(Number(value) * 100) / 100;

        return Number.isInteger(rounded) ? String(rounded) : rounded.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
    }

    const totalLabel = computed(() => format(total.value));
    const maxLabel = computed(() => format(max.value));

    function setScore(key, raw) {
        const criterion = criteria.find((row) => row.key === key);

        if (!criterion) {
            return;
        }

        if (raw === '' || raw === null || raw === undefined) {
            criterion.score = null;
            revision.value += 1;

            return;
        }

        const value = Number(raw);

        criterion.score = Number.isFinite(value)
            ? Math.min(criterion.max, Math.max(0, value))
            : null;
        revision.value += 1;
    }

    function clearScore(key) {
        setScore(key, null);
    }

    /**
     * Only scored criteria travel to the server; a blank one means "not graded
     * yet", not "zero".
     *
     * @returns {Array<{key: string, score: number}>}
     */
    function payload() {
        return criteria
            .filter((criterion) => criterion.score !== null && criterion.score !== '')
            .map((criterion) => ({ key: criterion.key, score: Number(criterion.score) }));
    }

    return {
        criteria,
        revision,
        setScore,
        clearScore,
        payload,
        format,
        // Plain objects passed as props do not auto-unwrap nested refs, so the
        // derived values are exposed as getters. Templates and the dialog can
        // read `rubric.totalLabel` directly, while `revision` stays a ref for
        // the autosave watcher.
        get hasCriteria() {
            return hasCriteria.value;
        },
        get total() {
            return total.value;
        },
        get max() {
            return max.value;
        },
        get totalLabel() {
            return totalLabel.value;
        },
        get maxLabel() {
            return maxLabel.value;
        },
        get complete() {
            return complete.value;
        },
        get scoredCount() {
            return scoredCount.value;
        },
    };
}
