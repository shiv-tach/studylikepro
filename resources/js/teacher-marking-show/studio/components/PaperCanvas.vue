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
import { drawStrokes } from '../renderAnnotations';

const props = defineProps({
    page: { type: Object, required: true },
    paper: { type: Object, required: true },
    strokes: { type: Array, default: () => [] },
    items: { type: Array, default: () => [] },
    scale: { type: Number, default: 1 },
    tool: { type: String, default: 'select' },
    brush: { type: Object, default: () => ({ color: '#e11d48', width: 0.003 }) },
    minDistance: { type: Number, default: 0.0015 },
    copy: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['stroke', 'place', 'remove-item', 'locate-item']);

const base = ref(null);
const ink = ref(null);
const dimensions = ref({ width: 0, height: 0 });
const rendering = ref(false);
const renderError = ref('');
const live = ref(null);

let drawing = false;
let renderToken = 0;
let cancelRender = null;

const drawingTool = computed(() => ['pen', 'highlighter', 'line', 'ellipse'].includes(props.tool));
const placingTool = computed(() => ['comment', 'correction', 'stamp'].includes(props.tool));
const cursor = computed(() => (drawingTool.value || placingTool.value ? 'crosshair' : 'default'));

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
    const rect = ink.value.getBoundingClientRect();

    return {
        x: Math.min(1, Math.max(0, (event.clientX - rect.left) / rect.width)),
        y: Math.min(1, Math.max(0, (event.clientY - rect.top) / rect.height)),
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
    <div class="relative inline-block">
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
                class="pointer-events-auto absolute"
                :style="{ left: `${item.x * 100}%`, top: `${item.y * 100}%` }"
            >
                <button
                    v-if="item.type === 'stamp'"
                    type="button"
                    class="group relative -translate-x-1/2 -translate-y-1/2 rounded-full px-2.5 py-1 text-[11px] font-bold text-white shadow-lg ring-1 ring-white/60 transition-transform hover:scale-105 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                    :style="{ backgroundColor: item.color || '#4f46e5' }"
                    :title="item.text"
                    @click="emit('locate-item', item)"
                >
                    {{ item.text || item.stamp }}
                    <span
                        class="absolute -right-1.5 -top-1.5 hidden h-4 w-4 items-center justify-center rounded-full bg-slate-900 text-[9px] font-bold text-white group-hover:flex"
                        role="button"
                        tabindex="0"
                        :aria-label="copy.remove ?? 'Remove'"
                        @click.stop="emit('remove-item', item.id)"
                        @keydown.enter.prevent.stop="emit('remove-item', item.id)"
                    >✕</span>
                </button>

                <button
                    v-else-if="item.type === 'comment'"
                    type="button"
                    class="group relative -translate-x-1/2 -translate-y-1/2 rounded-full bg-indigo-600 p-1.5 text-indigo-950 shadow-lg ring-2 ring-white focus:outline-none focus-visible:ring-4 focus-visible:ring-indigo-300"
                    :aria-label="`${copy.comment ?? 'Comment'}: ${item.text}`"
                    @click="emit('locate-item', item)"
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
                        @click.stop="emit('remove-item', item.id)"
                        @keydown.enter.prevent.stop="emit('remove-item', item.id)"
                    >✕</span>
                </button>

                <button
                    v-else
                    type="button"
                    class="group relative block max-w-[18rem] -translate-y-1/2 rounded-lg border-2 border-emerald-400 bg-emerald-950/95 px-2.5 py-2 text-left shadow-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300"
                    :aria-label="`${copy.correction ?? 'Correction'}: ${item.text}`"
                    @click="emit('locate-item', item)"
                >
                    <span class="block text-[9px] font-bold uppercase tracking-wider text-emerald-300">✨ {{ copy.correction ?? 'Correction' }}</span>
                    <span class="mt-0.5 block whitespace-pre-line text-[11px] font-medium leading-snug text-emerald-50">{{ item.text }}</span>
                    <span
                        class="absolute -right-2 -top-2 hidden h-5 w-5 items-center justify-center rounded-full bg-slate-900 text-[10px] font-bold text-white group-hover:flex"
                        role="button"
                        tabindex="0"
                        :aria-label="copy.remove ?? 'Remove'"
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
