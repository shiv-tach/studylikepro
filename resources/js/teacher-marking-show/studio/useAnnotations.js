import { computed, reactive, ref } from 'vue';
import { DEFAULT_STAMP_SIZE, MAX_STAMP_SIZE, MIN_STAMP_SIZE } from './renderAnnotations';

/**
 * The markup store. Coordinates are normalised to the page and widths are a
 * fraction of the page width, so zoom and export resolution never change what
 * the teacher drew.
 *
 * History is kept as JSON snapshots because a stroke is the smallest useful
 * undo step; snapshots are cheap once the points are rounded by the server
 * normaliser and the studio's own `round` helper.
 */
const MAX_HISTORY = 40;
const MIN_POINT_DISTANCE = 0.0015;

function clampStampSize(value) {
    const size = Number(value);

    if (!Number.isFinite(size) || size <= 0) {
        return DEFAULT_STAMP_SIZE;
    }

    return Math.round(Math.min(MAX_STAMP_SIZE, Math.max(MIN_STAMP_SIZE, size)) * 100000) / 100000;
}

export const TOOL_SHORTCUTS = {
    v: 'select',
    p: 'pen',
    h: 'highlighter',
    l: 'line',
    e: 'ellipse',
    c: 'comment',
    k: 'correction',
};

function createId() {
    if (typeof crypto !== 'undefined' && crypto.randomUUID) {
        return crypto.randomUUID();
    }

    return `a-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
}

function strokeTop(strokes) {
    let top = 1;

    strokes.forEach((stroke) => {
        (stroke.points ?? []).forEach((point) => {
            top = Math.min(top, Number(point[1]) || 0);
        });
    });

    return top;
}

function normalizeItem(item) {
    return {
        id: item.id ?? createId(),
        type: item.type ?? 'comment',
        x: Math.min(1, Math.max(0, Number(item.x) || 0)),
        y: Math.min(1, Math.max(0, Number(item.y) || 0)),
        text: String(item.text ?? ''),
        stamp: item.stamp ?? null,
        color: item.color ?? null,
        points: item.points ?? null,
        icon: item.icon ?? null,
        size: item.size == null ? null : clampStampSize(item.size),
    };
}

export function createAnnotations(initial = null) {
    const pages = reactive([]);
    const tool = ref('select');
    const brush = reactive({ color: '#e11d48', width: 0.003 });
    const activeStamp = ref(null);
    // The glyph size every new stamp is placed at; the rail's slider owns it.
    const stampSize = ref(DEFAULT_STAMP_SIZE);
    const revision = ref(0);
    const history = [];
    const future = [];

    function hydrate(payload) {
        pages.splice(0, pages.length);

        (payload?.pages ?? []).forEach((page) => {
            pages.push({
                file: Number(page.file) || 0,
                page: Number(page.page) || 0,
                strokes: (page.strokes ?? []).map((stroke) => ({
                    tool: stroke.tool ?? 'pen',
                    color: stroke.color ?? '#e11d48',
                    width: Number(stroke.width) || 0.003,
                    points: (stroke.points ?? []).map((point) => [Number(point[0]) || 0, Number(point[1]) || 0]),
                })),
                items: (page.items ?? []).map(normalizeItem),
            });
        });

        history.length = 0;
        future.length = 0;
        revision.value += 1;
    }

    function pageFor(file, page, create = true) {
        let entry = pages.find((row) => row.file === file && row.page === page);

        if (!entry && create) {
            entry = { file, page, strokes: [], items: [] };
            pages.push(entry);
        }

        return entry ?? null;
    }

    function snapshotPage(file, page) {
        return pages.find((row) => row.file === file && row.page === page) ?? { file, page, strokes: [], items: [] };
    }

    function snapshot() {
        const rows = pages
            .filter((entry) => entry.strokes.length > 0 || entry.items.length > 0)
            .map((entry) => ({
                file: entry.file,
                page: entry.page,
                strokes: entry.strokes.map((stroke) => ({
                    tool: stroke.tool,
                    color: stroke.color,
                    width: Math.round(stroke.width * 100000) / 100000,
                    points: stroke.points.map((point) => [
                        Math.round(point[0] * 10000) / 10000,
                        Math.round(point[1] * 10000) / 10000,
                    ]),
                })),
                items: entry.items.map(({ id, ...item }) => item),
            }));

        return rows.length === 0 ? null : { version: 1, pages: rows };
    }

    function remember() {
        try {
            history.push(JSON.stringify(pages));
        } catch (error) {
            return;
        }

        if (history.length > MAX_HISTORY) {
            history.shift();
        }

        future.length = 0;
    }

    function restore(raw) {
        const parsed = JSON.parse(raw);
        pages.splice(0, pages.length, ...parsed);
        revision.value += 1;
    }

    function undo() {
        if (history.length === 0) {
            return false;
        }

        future.push(JSON.stringify(pages));
        restore(history.pop());

        return true;
    }

    function redo() {
        if (future.length === 0) {
            return false;
        }

        history.push(JSON.stringify(pages));
        restore(future.pop());

        return true;
    }

    function addStroke(file, page, points, options = {}) {
        if (!Array.isArray(points) || points.length < 2) {
            return null;
        }

        remember();

        const entry = pageFor(file, page);
        const stroke = {
            tool: options.tool ?? tool.value,
            color: options.color ?? brush.color,
            width: options.width ?? brush.width,
            points: points.map(([x, y]) => [Math.min(1, Math.max(0, x)), Math.min(1, Math.max(0, y))]),
        };

        // A straight line only needs its ends; keep the payload small.
        if (stroke.tool === 'line' || stroke.tool === 'ellipse') {
            stroke.points = [stroke.points[0], stroke.points[stroke.points.length - 1]];
        }

        entry.strokes.push(stroke);
        revision.value += 1;

        return stroke;
    }

    function addItem(file, page, item) {
        remember();

        const entry = pageFor(file, page);
        const stored = normalizeItem(item);
        entry.items.push(stored);
        revision.value += 1;

        return stored;
    }

    function removeItem(file, page, id) {
        const entry = pageFor(file, page, false);

        if (!entry) {
            return;
        }

        const index = entry.items.findIndex((item) => item.id === id);

        if (index === -1) {
            return;
        }

        remember();
        entry.items.splice(index, 1);
        revision.value += 1;
    }

    // Repositioning a placed mark (stamp, pin or card) is one undo step even
    // though the gesture streams many pointer events: the canvas only commits
    // the final spot on release.
    function moveItem(file, page, id, x, y) {
        const entry = pageFor(file, page, false);

        if (!entry) {
            return null;
        }

        const item = entry.items.find((row) => row.id === id);

        if (!item) {
            return null;
        }

        const nextX = Math.min(1, Math.max(0, Number(x) || 0));
        const nextY = Math.min(1, Math.max(0, Number(y) || 0));

        if (item.x === nextX && item.y === nextY) {
            return item;
        }

        remember();
        item.x = nextX;
        item.y = nextY;
        revision.value += 1;

        return item;
    }

    // Growing or shrinking a placed stamp is one undo step, like moving it:
    // `record` is false for the intermediate values of a slider drag.
    function resizeItem(file, page, id, size, { record = true } = {}) {
        const entry = pageFor(file, page, false);

        if (!entry) {
            return null;
        }

        const item = entry.items.find((row) => row.id === id);

        if (!item) {
            return null;
        }

        const next = clampStampSize(size);

        if (item.size === next) {
            return item;
        }

        if (record) {
            remember();
        }

        item.size = next;
        revision.value += 1;

        return item;
    }

    function clearPage(file, page) {
        const entry = pageFor(file, page, false);

        if (!entry || (entry.strokes.length === 0 && entry.items.length === 0)) {
            return;
        }

        remember();
        entry.strokes.splice(0, entry.strokes.length);
        entry.items.splice(0, entry.items.length);
        revision.value += 1;
    }

    function setStampSize(next) {
        stampSize.value = clampStampSize(next);
    }

    function setTool(next) {
        tool.value = next;
        activeStamp.value = null;

        if (next === 'highlighter') {
            brush.color = '#facc15';
            brush.width = 0.02;
        } else if (next === 'pen' || next === 'line' || next === 'ellipse') {
            if (brush.color === '#facc15') {
                brush.color = '#e11d48';
            }

            brush.width = next === 'pen' ? 0.003 : 0.002;
        }
    }

    const itemCount = computed(() =>
        pages.reduce((total, entry) => total + entry.items.length, 0),
    );

    const strokeCount = computed(() =>
        pages.reduce((total, entry) => total + entry.strokes.length, 0),
    );

    // The feed doubles as navigation, so every row carries the spot it refers
    // to: `y` is enough to scroll the paper to it.
    const feed = computed(() => {
        const rows = [];

        pages.forEach((entry) => {
            entry.items.forEach((item) => {
                rows.push({
                    id: item.id,
                    file: entry.file,
                    page: entry.page,
                    kind: item.type,
                    text: item.text,
                    stamp: item.stamp,
                    color: item.color,
                    y: item.y,
                });
            });

            if (entry.strokes.length > 0) {
                rows.push({
                    id: `strokes-${entry.file}-${entry.page}`,
                    file: entry.file,
                    page: entry.page,
                    kind: 'strokes',
                    count: entry.strokes.length,
                    y: strokeTop(entry.strokes),
                });
            }
        });

        return rows;
    });

    // Resume the teacher's previous session, if there is one.
    if (initial) {
        hydrate(initial);
    }

    return {
        pages,
        tool,
        brush,
        activeStamp,
        stampSize,
        revision,
        itemCount,
        strokeCount,
        feed,
        hydrate,
        pageFor,
        snapshotPage,
        snapshot,
        undo,
        redo,
        canUndo: computed(() => history.length > 0),
        canRedo: computed(() => future.length > 0),
        addStroke,
        addItem,
        removeItem,
        moveItem,
        resizeItem,
        clearPage,
        setTool,
        setStampSize,
        MIN_POINT_DISTANCE,
    };
}
