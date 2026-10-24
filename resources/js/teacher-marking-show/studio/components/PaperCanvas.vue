<script setup>
/**
 * One page of the paper with two stacked canvases: the rasterised page at the
 * bottom and a transparent ink layer on top. Splitting them means a pointer
 * move only repaints the (cheap) ink layer, never the pdf.js page.
 *
 * Pins, stamps and correction cards stay DOM elements so they are clickable,
 * focusable and readable by assistive technology; the export draws its own
 * graphics from the same data.
 */
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { drawStrokes, stampSizeOf } from '../renderAnnotations';

const props = defineProps({
    page: { type: Object, required: true },
    paper: { type: Object, required: true },
    strokes: { type: Array, default: () => [] },
    items: { type: Array, default: () => [] },
    stamps: { type: Array, default: () => [] },
    selectedId: { type: String, default: null },
    scale: { type: Number, default: 1 },
    tool: { type: String, default: 'select' },
    brush: { type: Object, default: () => ({ color: '#e11d48', width: 0.003 }) },
    minDistance: { type: Number, default: 0.0015 },
    copy: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['stroke', 'place', 'remove-item', 'locate-item', 'move-item', 'rendered']);

const DRAG_THRESHOLD = 3; // px a pointer must travel before it counts as a drag
const NUDGE_STEP = 0.005; // page fraction moved per arrow-key press

const base = ref(null);
const ink = ref(null);
const dimensions = ref({ width: 0, height: 0 });
const rendering = ref(false);
const renderError = ref('');
const live = ref(null);
const drag = ref(null);

let drawing = false;
let renderToken = 0;
let cancelRender = null;

const drawingTool = computed(() => ['pen', 'highlighter', 'line', 'ellipse'].includes(props.tool));
const placingTool = computed(() => ['comment', 'correction', 'stamp'].includes(props.tool));
const cursor = computed(() => (drawingTool.value || placingTool.value ? 'crosshair' : 'default'));

// A stamp is a mark, not a label: the rail owns the wording, the paper only
// shows the glyph (✓, ✕, ⚠ …) so a page full of ticks stays readable.
function stampStyle(item) {
    return props.stamps.find((stamp) => stamp.key === item.stamp) ?? null;
}

function stampGlyph(item) {
    return item.icon || stampStyle(item)?.icon || String(item.text ?? '').slice(0, 2) || '•';
}

function stampLabel(item) {
    return stampStyle(item)?.label || item.text || 'Stamp';
}

// The glyph is sized against the rendered page, so the same stamp is the same
// size on screen and in the exported PDF, whatever the zoom.
function stampFontSize(item) {
    return Math.max(10, Math.round(stampSizeOf(item) * dimensions.value.width));
}

// A small glyph still needs a comfortable hit area for dragging and removing.
function stampHitSize(item) {
    return Math.max(28, Math.round(stampFontSize(item) * 1.4));
}

/* ------------------------------------------------------------ moving marks */

function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
}

function clamp01(value) {
    return clamp(value, 0, 1);
}

// The mark a pointer event belongs to. The wrapper is the anchor point, and
// the rendered mark is what has to stay on the page; for a stamp that is the
// glyph, not its (larger, invisible) hit area.
function markNode(wrapper) {
    const mark = wrapper?.firstElementChild;

    return mark?.querySelector('[data-mark-glyph]') ?? mark ?? wrapper;
}

// How far the mark's anchor may travel before the glyph itself crosses the
// paper edge. The mark's own offsets from the anchor are measured, so a centred
// stamp, a left-aligned correction card and a pin all stop exactly at the edge
// instead of hanging over it.
function boundsFor(wrapper, x, y) {
    const box = (base.value ?? ink.value)?.getBoundingClientRect();
    const mark = markNode(wrapper)?.getBoundingClientRect?.();

    if (!box || !mark || box.width === 0 || box.height === 0) {
        return { minX: 0, maxX: 1, minY: 0, maxY: 1 };
    }

    const minX = x - (mark.left - box.left) / box.width;
    const maxX = x + 1 - (mark.right - box.left) / box.width;
    const minY = y - (mark.top - box.top) / box.height;
    const maxY = y + 1 - (mark.bottom - box.top) / box.height;

    // A mark wider or taller than the page has nowhere to go but the middle.
    if (maxX < minX || maxY < minY) {
        return { minX: 0, maxX: 1, minY: 0, maxY: 1 };
    }

    return {
        minX: clamp01(minX),
        maxX: clamp01(maxX),
        minY: clamp01(minY),
        maxY: clamp01(maxY),
    };
}

// A drag is previewed with a local offset so the pointer stays glued to the
// mark without rewriting the store on every move; the final spot is committed
// once on release, which keeps the gesture a single undo step.
function itemStyle(item) {
    const offset = drag.value?.id === item.id ? drag.value : null;

    return {
        left: `${clamp01(item.x + (offset?.dx ?? 0)) * 100}%`,
        top: `${clamp01(item.y + (offset?.dy ?? 0)) * 100}%`,
        zIndex: offset ? 30 : null,
    };
}

function onMarkPointerDown(event, item) {
    if (event.button !== 0 || event.isPrimary === false) {
        return;
    }

    const box = (base.value ?? ink.value)?.getBoundingClientRect();

    if (!box || box.width === 0 || box.height === 0) {
        return;
    }

    drag.value = {
        id: item.id,
        pointerId: event.pointerId,
        startX: event.clientX,
        startY: event.clientY,
        x: item.x,
        y: item.y,
        dx: 0,
        dy: 0,
        width: box.width,
        height: box.height,
        bounds: boundsFor(event.currentTarget, item.x, item.y),
        moved: false,
    };

    // Capture so the gesture survives the pointer leaving the (small) mark.
    event.currentTarget.setPointerCapture?.(event.pointerId);
}

function onMarkPointerMove(event) {
    const state = drag.value;

    if (!state || event.pointerId !== state.pointerId) {
        return;
    }

    const travelled = Math.hypot(event.clientX - state.startX, event.clientY - state.startY);

    if (!state.moved && travelled < DRAG_THRESHOLD) {
        return;
    }

    state.moved = true;

    const bounds = state.bounds;
    const nextX = clamp(state.x + (event.clientX - state.startX) / state.width, bounds.minX, bounds.maxX);
    const nextY = clamp(state.y + (event.clientY - state.startY) / state.height, bounds.minY, bounds.maxY);

    state.dx = nextX - state.x;
    state.dy = nextY - state.y;
    event.preventDefault();
}

function onMarkPointerUp(event) {
    const state = drag.value;

    if (!state || (event.pointerId !== undefined && event.pointerId !== state.pointerId)) {
        return;
    }

    drag.value = null;

    if (event.currentTarget.hasPointerCapture?.(state.pointerId)) {
        event.currentTarget.releasePointerCapture(state.pointerId);
    }

    const item = props.items.find((row) => row.id === state.id);

    if (!item) {
        return;
    }

    // A tap without travel is a selection: pointer capture makes the browser
    // dispatch the follow-up click to the wrapper, so the button never sees it.
    if (!state.moved) {
        emit('locate-item', item);

        return;
    }

    emit('move-item', {
        id: state.id,
        x: clamp01(state.x + state.dx),
        y: clamp01(state.y + state.dy),
    });
}

function onMarkPointerCancel() {
    drag.value = null;
}

// A pointer tap already reports itself on pointer-up, so the button's own click
// is only needed for keyboard and assistive activation — which reports no
// click count. Ignoring the mouse click here keeps one gesture from toggling
// the selection twice.
function onMarkClick(event, item) {
    if (event.detail === 0) {
        emit('locate-item', item);
    }
}

// Dragging is a mouse/touch affordance, so the same move is reachable from the
// keyboard once a mark has focus.
function onMarkKeydown(event, item) {
    const step = event.shiftKey ? NUDGE_STEP * 4 : NUDGE_STEP;
    const deltas = {
        ArrowLeft: [-step, 0],
        ArrowRight: [step, 0],
        ArrowUp: [0, -step],
        ArrowDown: [0, step],
    };
    const delta = deltas[event.key];

    if (!delta) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();

    const bounds = boundsFor(event.currentTarget, item.x, item.y);

    emit('move-item', {
        id: item.id,
        x: clamp(item.x + delta[0], bounds.minX, bounds.maxX),
        y: clamp(item.y + delta[1], bounds.minY, bounds.maxY),
    });
}

function ratio() {
    return Math.min(2.5, Math.max(1, window.devicePixelRatio || 1));
}

function drawInk() {
    const canvas = ink.value;

    if (!canvas || dimensions.value.width === 0) {
        return;
    }

    const context = canvas.getContext('2d');
    const deviceRatio = ratio();

    context.setTransform(deviceRatio, 0, 0, deviceRatio, 0, 0);
    context.clearRect(0, 0, dimensions.value.width, dimensions.value.height);
    drawStrokes(context, props.strokes, dimensions.value.width, dimensions.value.height);

    if (live.value) {
        drawStrokes(context, [live.value], dimensions.value.width, dimensions.value.height);
    }
}

async function renderBase() {
    if (!props.page || !base.value) {
        return;
    }

    // pdf.js cannot share a canvas between two live render tasks; abandon the
    // previous one before starting the next.
    cancelRender?.();
    cancelRender = null;

    const token = ++renderToken;
    rendering.value = true;
    renderError.value = '';

    try {
        const result = await props.paper.render(
            props.page,
            base.value,
            props.page.width * props.scale,
            (cancel) => {
                cancelRender = cancel;
            },
        );

        if (token !== renderToken) {
            return;
        }

        dimensions.value = { width: result.width, height: result.height };

        if (ink.value) {
            ink.value.width = result.pixelWidth;
            ink.value.height = result.pixelHeight;
            ink.value.style.width = `${result.width}px`;
            ink.value.style.height = `${result.height}px`;
        }

        drawInk();
        emit('rendered', dimensions.value);
    } catch (error) {
        const cancelled = error?.name === 'RenderingCancelledException'
            || String(error?.message ?? '').toLowerCase().includes('cancel');

        if (token === renderToken && !cancelled) {
            renderError.value = String(error?.message ?? props.copy?.error ?? 'This page could not be rendered.');
        }
    } finally {
        if (token === renderToken) {
            rendering.value = false;
        }
    }
}

function pointFromEvent(event) {
    // The page canvas sets the box every mark is positioned against; the ink
    // layer is resized only once a (re)render finishes, so measuring against
    // it could place a mark away from the pointer right after a zoom.
    const box = base.value?.getBoundingClientRect() ?? ink.value.getBoundingClientRect();

    return {
        x: Math.min(1, Math.max(0, (event.clientX - box.left) / box.width)),
        y: Math.min(1, Math.max(0, (event.clientY - box.top) / box.height)),
    };
}

function onPointerDown(event) {
    if (props.tool === 'select' || dimensions.value.width === 0) {
        return;
    }

    const point = pointFromEvent(event);

    if (placingTool.value) {
        emit('place', { ...point, type: props.tool });
        return;
    }

    drawing = true;
    live.value = {
        tool: props.tool,
        color: props.brush.color,
        width: props.brush.width,
        points: [[point.x, point.y]],
    };

    ink.value.setPointerCapture?.(event.pointerId);
    event.preventDefault();
    drawInk();
}

function onPointerMove(event) {
    if (!drawing || !live.value) {
        return;
    }

    const point = pointFromEvent(event);
    const last = live.value.points[live.value.points.length - 1];

    if (Math.hypot(point.x - last[0], point.y - last[1]) < props.minDistance) {
        return;
    }

    // The server caps a stroke at 1000 points; stop adding before that.
    if (live.value.points.length >= 1000) {
        return;
    }

    live.value.points.push([point.x, point.y]);
    drawInk();
    event.preventDefault();
}

function finishStroke() {
    if (!drawing) {
        return;
    }

    drawing = false;

    const stroke = live.value;
    live.value = null;

    if (stroke && stroke.points.length >= 2) {
        emit('stroke', {
            tool: stroke.tool,
            color: stroke.color,
            width: stroke.width,
            points: stroke.points,
        });
    }

    drawInk();
}

watch(() => [props.page, props.scale], renderBase, { immediate: true });
watch(() => props.strokes, drawInk, { deep: true });
watch(() => props.brush?.color, drawInk);

onMounted(renderBase);

onBeforeUnmount(() => {
    renderToken += 1;
    cancelRender?.();
    cancelRender = null;
});
</script>

<template>
    <div class="relative inline-block self-start">
        <canvas ref="base" class="block rounded-sm bg-white shadow-2xl ring-1 ring-slate-300/70 dark:ring-slate-700" />

        <canvas
            ref="ink"
            class="absolute inset-0 block touch-none"
            :style="{ cursor }"
            @pointerdown="onPointerDown"
            @pointermove="onPointerMove"
            @pointerup="finishStroke"
            @pointercancel="finishStroke"
            @pointerleave="finishStroke"
        ></canvas>

        <div class="pointer-events-none absolute inset-0">
            <div
                v-for="item in items"
                :key="item.id"
                class="pointer-events-auto absolute touch-none select-none"
                :class="drag?.id === item.id ? 'cursor-grabbing' : 'cursor-grab'"
                :style="itemStyle(item)"
                @pointerdown="onMarkPointerDown($event, item)"
                @pointermove="onMarkPointerMove"
                @pointerup="onMarkPointerUp"
                @pointercancel="onMarkPointerCancel"
                @keydown="onMarkKeydown($event, item)"
            >
                <button
                    v-if="item.type === 'stamp'"
                    type="button"
                    class="group relative flex -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full font-bold leading-none transition-transform hover:scale-110 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                    :class="selectedId === item.id ? 'scale-110 rounded-md ring-2 ring-primary' : ''"
                    :style="{
                        color: item.color || '#4f46e5',
                        fontSize: `${stampFontSize(item)}px`,
                        width: `${stampHitSize(item)}px`,
                        height: `${stampHitSize(item)}px`,
                    }"
                    :title="stampLabel(item)"
                    :aria-label="stampLabel(item)"
                    :aria-pressed="selectedId === item.id"
                    @click="onMarkClick($event, item)"
                >
                    <span data-mark-glyph aria-hidden="true">{{ stampGlyph(item) }}</span>
                    <span
                        class="absolute -right-1.5 -top-1.5 hidden h-4 w-4 items-center justify-center rounded-full bg-slate-900 text-[9px] font-bold text-white group-hover:flex"
                        role="button"
                        tabindex="0"
                        :aria-label="copy.remove ?? 'Remove'"
                        @pointerdown.stop
                        @click.stop="emit('remove-item', item.id)"
                        @keydown.enter.prevent.stop="emit('remove-item', item.id)"
                    >✕</span>
                </button>

                <button
                    v-else-if="item.type === 'comment'"
                    type="button"
                    class="group relative -translate-x-1/2 -translate-y-1/2 rounded-full bg-indigo-600 p-1.5 text-indigo-950 shadow-lg ring-2 ring-white focus:outline-none focus-visible:ring-4 focus-visible:ring-indigo-300"
                    :aria-label="`${copy.comment ?? 'Comment'}: ${item.text}`"
                    @click="onMarkClick($event, item)"
                >
                    <span class="block h-2 w-2 rounded-full bg-white" aria-hidden="true"></span>
                    <span
                        class="pointer-events-none absolute left-1/2 top-full z-10 mt-1 hidden w-56 -translate-x-1/2 rounded-lg bg-slate-900 px-2.5 py-2 text-left text-[11px] font-medium leading-snug text-white shadow-xl group-hover:block group-focus-visible:block"
                    >{{ item.text }}</span>
                    <span
                        class="absolute -right-1.5 -top-1.5 hidden h-4 w-4 items-center justify-center rounded-full bg-rose-600 text-[9px] font-bold text-white group-hover:flex"
                        role="button"
                        tabindex="0"
                        :aria-label="copy.remove ?? 'Remove'"
                        @pointerdown.stop
                        @click.stop="emit('remove-item', item.id)"
                        @keydown.enter.prevent.stop="emit('remove-item', item.id)"
                    >✕</span>
                </button>

                <button
                    v-else
                    type="button"
                    class="group relative block max-w-[18rem] -translate-y-1/2 rounded-lg border-2 border-emerald-400 bg-emerald-950/95 px-2.5 py-2 text-left shadow-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300"
                    :aria-label="`${copy.correction ?? 'Correction'}: ${item.text}`"
                    @click="onMarkClick($event, item)"
                >
                    <span class="block text-[9px] font-bold uppercase tracking-wider text-emerald-300">✨ {{ copy.correction ?? 'Correction' }}</span>
                    <span class="mt-0.5 block whitespace-pre-line text-[11px] font-medium leading-snug text-emerald-50">{{ item.text }}</span>
                    <span
                        class="absolute -right-2 -top-2 hidden h-5 w-5 items-center justify-center rounded-full bg-slate-900 text-[10px] font-bold text-white group-hover:flex"
                        role="button"
                        tabindex="0"
                        :aria-label="copy.remove ?? 'Remove'"
                        @pointerdown.stop
                        @click.stop="emit('remove-item', item.id)"
                        @keydown.enter.prevent.stop="emit('remove-item', item.id)"
                    >✕</span>
                </button>
            </div>
        </div>

        <div v-if="rendering || renderError" class="absolute inset-0 flex items-center justify-center rounded-sm bg-white/80 text-xs font-semibold text-slate-500 dark:bg-slate-900/80 dark:text-slate-300">
            <span v-if="rendering">{{ copy.loading ?? 'Rendering the paper…' }}</span>
            <span v-else class="max-w-xs px-4 text-center">{{ renderError }}</span>
        </div>
    </div>
</template>
