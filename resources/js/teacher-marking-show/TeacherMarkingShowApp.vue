<script setup>
/**
 * The paper-marking studio at /teacher/marking/{order}.
 *
 * One native multipart form wraps the whole workspace: the annotated PDF is
 * injected into a hidden file input right before submitting, so the delivery
 * still travels through `teacher.marking.mark` exactly like the old upload
 * form did (no fetch replacement, no lost validation state).
 *
 * Everything the teacher does is autosaved as vectors through the partial
 * draft endpoint; the flattened PDF is only built when they choose to deliver.
 */
import { computed, nextTick, onBeforeUnmount, onMounted, provide, ref, shallowRef, watch } from 'vue';
import { THEME_PRESET_KEY, useThemePreset } from '../shared/composables/useThemePreset';
import { useNotices } from '../shared/composables/useNotices';
import UiToastStack from '../shared/components/UiToastStack.vue';
import { COPY_KEY, CSRF_KEY, PAGE_KEY, URLS_KEY } from './composables/usePageContext';
import MarkingOrderHero from './components/MarkingOrderHero.vue';
import PaperFilesCard from './components/PaperFilesCard.vue';
import DeliveryCard from './components/DeliveryCard.vue';
import DisputeNotice from './components/DisputeNotice.vue';
import StudioTopBar from './studio/components/StudioTopBar.vue';
import StudioToolRail from './studio/components/StudioToolRail.vue';
import PaperCanvas from './studio/components/PaperCanvas.vue';
import PageRail from './studio/components/PageRail.vue';
import RubricPanel from './studio/components/RubricPanel.vue';
import AnnotationFeed from './studio/components/AnnotationFeed.vue';
import FeedbackPanel from './studio/components/FeedbackPanel.vue';
import AnnotationComposer from './studio/components/AnnotationComposer.vue';
import DeliverDialog from './studio/components/DeliverDialog.vue';
import { createAnnotations, TOOL_SHORTCUTS } from './studio/useAnnotations';
import { createRubric } from './studio/useRubric';
import { createPaperDocument } from './studio/usePaperDocument';
import { createAutosave } from './studio/useAutosave';
import { exportMarkedPdf, formatBytes } from './studio/exportMarkedPdf';

const props = defineProps({
    config: { type: Object, required: true },
});

provide(THEME_PRESET_KEY, useThemePreset(props.config.theme?.preset ?? 'classic'));
provide(COPY_KEY, props.config.copy ?? {});
provide(URLS_KEY, props.config.urls ?? {});
provide(CSRF_KEY, props.config.csrfToken ?? '');
provide(PAGE_KEY, props.config);

const copy = computed(() => props.config.copy ?? {});
const { toasts, dismiss } = useNotices(props.config.notices ?? []);

const isStudio = computed(() => Boolean(props.config.canMark && props.config.form));

/* ------------------------------------------------------------------ state */

const annotations = createAnnotations(props.config.annotations ?? null);
const rubric = createRubric(props.config.rubric ?? {});
const feedback = ref(String(props.config.form?.feedback ?? ''));
const marks = ref(
    (props.config.form?.marks?.length ? props.config.form.marks : [{ label: '', value: '' }])
        .map((row) => ({ ...row })),
);

const paper = shallowRef(null);
const pages = ref([]);const paperErrors = ref([]);
const paperLoading = ref(false);
const currentIndex = ref(0);
const zoom = ref(1);
const viewport = ref(null);
const viewportWidth = ref(900);
const formEl = ref(null);
const annotationsInput = ref(null);
const generatedInput = ref(null);
const extraInput = ref(null);

const currentPage = computed(() => pages.value[currentIndex.value] ?? null);

const fitFactor = computed(() => {
    if (!currentPage.value || viewportWidth.value <= 0) {
        return 1;
    }

    return Math.min(1.5, Math.max(0.25, (viewportWidth.value - 56) / currentPage.value.width));
});

const scale = computed(() => fitFactor.value * zoom.value);

const currentMarkup = computed(() => {
    if (!currentPage.value) {
        return { strokes: [], items: [] };
    }

    return annotations.snapshotPage(currentPage.value.fileIndex, currentPage.value.pageIndex);
});

const marksByPage = computed(() => {
    const counts = {};

    annotations.pages.forEach((entry) => {
        const count = entry.strokes.length + entry.items.length;

        if (count > 0) {
            counts[`${entry.file}:${entry.page}`] = count;
        }
    });

    return counts;
});

const currentKey = computed(() => (currentPage.value ? currentPage.value.key : ''));

const formErrors = computed(() => {
    const errors = props.config.form?.errors ?? {};

    return [
        ...(errors.marked_files ?? []),
        ...(errors.feedback ?? []),
        ...(errors.marks ?? []),
        ...(errors.rubric_scores ?? []),
        ...(errors.annotations ?? []),
        errors.order ?? null,
    ].filter(Boolean);
});

/* --------------------------------------------------------------- autosave */

const autosave = createAutosave({
    url: props.config.urls?.draft ?? null,
    token: props.config.csrfToken ?? '',
    snapshot: () => ({
        annotations: annotations.snapshot(),
        rubric_scores: rubric.payload(),
        feedback: feedback.value,
    }),
    onSaved: (data) => {
        if (data?.rubricScores) {
            // The server clamps scores; mirror the accepted values.
            data.rubricScores.forEach((row) => {
                const criterion = rubric.criteria.find((entry) => entry.key === row.key);

                if (criterion && typeof row.score === 'number') {
                    criterion.score = row.score;
                }
            });
        }
    },
});

let hydrating = true;

watch(() => annotations.revision.value, () => {
    if (!hydrating && isStudio.value) {
        autosave.schedule();
    }
});

watch(() => rubric.revision.value, () => {
    if (!hydrating && isStudio.value) {
        autosave.schedule();
    }
});

watch(feedback, () => {
    if (!hydrating && isStudio.value) {
        autosave.schedule();
    }
});

/* ------------------------------------------------------------- paper load */

let resizeObserver = null;

async function loadPaper() {
    if (!isStudio.value) {
        return;
    }

    paperLoading.value = true;
    paper.value = createPaperDocument(props.config.files ?? []);

    try {
        const result = await paper.value.load();

        pages.value = result.pages;
        paperErrors.value = result.errors;
        currentIndex.value = 0;
    } catch (error) {
        paperErrors.value = [{ name: '', message: String(error?.message ?? 'The paper could not be opened.') }];
    } finally {
        paperLoading.value = false;
        hydrating = false;
    }
}

/* -------------------------------------------------------------- placement */

const pending = ref(null);

function onPlace(place) {
    if (!currentPage.value) {
        return;
    }

    if (place.type === 'stamp') {
        const stamp = annotations.activeStamp.value;

        if (!stamp) {
            return;
        }

        annotations.addItem(currentPage.value.fileIndex, currentPage.value.pageIndex, {
            type: 'stamp',
            x: place.x,
            y: place.y,
            text: stamp.label,
            stamp: stamp.key,
            color: stamp.color,
            points: stamp.points,
            icon: stamp.icon,
        });

        return;
    }

    pending.value = { x: place.x, y: place.y, type: place.type };
}

function saveAnnotation(text) {
    if (!pending.value || !currentPage.value) {
        return;
    }

    annotations.addItem(currentPage.value.fileIndex, currentPage.value.pageIndex, {
        type: pending.value.type,
        x: pending.value.x,
        y: pending.value.y,
        text,
        color: pending.value.type === 'correction' ? '#34d399' : '#4f46e5',
    });

    pending.value = null;
}

function onStroke(stroke) {
    if (!currentPage.value) {
        return;
    }

    annotations.addStroke(currentPage.value.fileIndex, currentPage.value.pageIndex, stroke.points, {
        tool: stroke.tool,
        color: stroke.color,
        width: stroke.width,
    });
}

function onRemoveItem(id) {
    if (!currentPage.value) {
        return;
    }

    annotations.removeItem(currentPage.value.fileIndex, currentPage.value.pageIndex, id);
}

function clearCurrentPage() {
    if (!currentPage.value) {
        return;
    }

    if (window.confirm(copy.value.studio?.tools?.clearConfirm ?? 'Clear this page?') === false) {
        return;
    }

    annotations.clearPage(currentPage.value.fileIndex, currentPage.value.pageIndex);
}

function chooseStamp(stamp) {
    annotations.tool.value = 'stamp';
    annotations.activeStamp.value = stamp;
    annotations.brush.color = stamp.color;
}

function locate(row) {
    const index = pages.value.findIndex((page) => page.fileIndex === row.file && page.pageIndex === row.page);

    if (index === -1) {
        return;
    }

    currentIndex.value = index;
    nextTick(() => {
        if (viewport.value) {
            viewport.value.scrollTop = 0;
        }
    });
}

function removeFeedRow(row) {
    annotations.removeItem(row.file, row.page, row.id);
}

/* ----------------------------------------------------------------- marks */

function addMarkRow() {
    if (marks.value.length < 50) {
        marks.value.push({ label: '', value: '' });
    }
}

function removeMarkRow(index) {
    marks.value.splice(index, 1);

    if (marks.value.length === 0) {
        addMarkRow();
    }
}

/* -------------------------------------------------------------- delivery */

const deliverOpen = ref(false);
const deliverPhase = ref('review');
const deliverError = ref('');
const generatedLabel = ref(null);
const extraCount = ref(0);

function openDeliver() {
    deliverPhase.value = 'review';
    deliverError.value = '';
    generatedLabel.value = null;
    deliverOpen.value = true;
}

async function buildPdf() {
    if (!paper.value || pages.value.length === 0) {
        deliverPhase.value = 'error';
        deliverError.value = 'The paper has no rendered pages yet.';

        return;
    }

    deliverPhase.value = 'exporting';
    deliverError.value = '';

    try {
        // Make sure the server has the latest markup before we bake it in.
        await autosave.flush();

        const { blob, size } = await exportMarkedPdf({
            paper: paper.value,
            annotations: annotations.snapshot(),
            title: `Marked paper ${props.config.order?.reference ?? ''}`.trim(),
        });

        const filename = `marked-${String(props.config.order?.reference ?? 'paper').replace(/[^a-z0-9-]/gi, '') || 'paper'}.pdf`;
        const file = new File([blob], filename, { type: 'application/pdf' });

        if (generatedInput.value) {
            const transfer = new DataTransfer();
            transfer.items.add(file);
            generatedInput.value.files = transfer.files;
        }

        generatedLabel.value = formatBytes(size);
        deliverPhase.value = 'ready';
    } catch (error) {
        deliverPhase.value = 'error';
        deliverError.value = String(error?.message ?? 'The PDF could not be generated.');
    }
}

function onExtraFilesChanged() {
    extraCount.value = extraInput.value?.files?.length ?? 0;
}

async function submitDelivery() {
    if (!formEl.value) {
        return;
    }

    // Keep the autosave and the delivery in sync even if the teacher edited
    // something during the export.
    await autosave.flush();

    if (annotationsInput.value) {
        annotationsInput.value.value = JSON.stringify(annotations.snapshot() ?? { version: 1, pages: [] });
    }

    formEl.value.submit();
}

/* --------------------------------------------------------------- keyboard */

function isTypingTarget(target) {
    return target instanceof HTMLElement
        && ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName);
}

function onKeydown(event) {
    if (!isStudio.value || isTypingTarget(event.target)) {
        return;
    }

    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'z') {
        event.preventDefault();

        if (event.shiftKey) {
            annotations.redo();
        } else {
            annotations.undo();
        }

        return;
    }

    if (event.key === 'Escape') {
        pending.value = null;
        deliverOpen.value = false;

        return;
    }

    const tool = TOOL_SHORTCUTS[event.key.toLowerCase()];

    if (tool) {
        event.preventDefault();
        annotations.setTool(tool);
    }
}

/* ------------------------------------------------------------ unsaved guard */

function onBeforeUnload(event) {
    if (!isStudio.value) {
        return;
    }

    if (autosave.status.value === 'saving' || autosave.status.value === 'error') {
        event.preventDefault();
        event.returnValue = '';
    }
}

/* -------------------------------------------------------------- lifecycle */

onMounted(() => {
    loadPaper();

    if (viewport.value && typeof ResizeObserver !== 'undefined') {
        resizeObserver = new ResizeObserver((entries) => {
            viewportWidth.value = entries[0]?.contentRect?.width ?? viewportWidth.value;
        });
        resizeObserver.observe(viewport.value);
    }

    window.addEventListener('keydown', onKeydown);
    window.addEventListener('beforeunload', onBeforeUnload);
});

onBeforeUnmount(() => {
    resizeObserver?.disconnect();
    paper.value?.dispose?.();
    window.removeEventListener('keydown', onKeydown);
    window.removeEventListener('beforeunload', onBeforeUnload);
});
</script>

<template>
    <div class="space-y-4">
        <UiToastStack
            :toasts="toasts"
            :label="copy.toast?.label ?? 'Notifications'"
            :dismiss-label="copy.toast?.dismiss ?? 'Dismiss'"
            @dismiss="dismiss"
        />

        <template v-if="isStudio">
            <form
                ref="formEl"
                :action="config.form.action"
                method="POST"
                enctype="multipart/form-data"
                class="space-y-4"
                @submit.prevent
            >
                <input type="hidden" name="_token" :value="config.csrfToken">
                <input ref="annotationsInput" type="hidden" name="annotations" value="">
                <input ref="generatedInput" type="file" name="marked_files[]" multiple class="hidden" tabindex="-1" aria-hidden="true">
                <input
                    id="studio-extra-files"
                    ref="extraInput"
                    type="file"
                    name="marked_files[]"
                    multiple
                    accept=".jpg,.jpeg,.png,.webp,.pdf"
                    class="hidden"
                    tabindex="-1"
                    aria-hidden="true"
                    @change="onExtraFilesChanged"
                >

                <template v-if="rubric.hasCriteria">
                    <template v-for="(criterion, index) in rubric.criteria" :key="`rubric-hidden-${criterion.key}`">
                        <input v-if="criterion.score !== null" type="hidden" :name="`rubric_scores[${index}][key]`" :value="criterion.key">
                        <input v-if="criterion.score !== null" type="hidden" :name="`rubric_scores[${index}][score]`" :value="criterion.score">
                    </template>
                </template>
                <template v-else>
                    <template v-for="(row, index) in marks" :key="`mark-hidden-${index}`">
                        <input type="hidden" :name="`marks[${index}][label]`" :value="row.label">
                        <input type="hidden" :name="`marks[${index}][value]`" :value="row.value">
                    </template>
                </template>

                <StudioTopBar
                    :order="config.order"
                    :autosave-status="autosave.status.value"
                    :saved-at-label="autosave.savedAt.value"
                    :can-undo="annotations.canUndo.value"
                    :can-redo="annotations.canRedo.value"
                    :next-url="config.urls.next"
                    :urls="config.urls"
                    @undo="annotations.undo()"
                    @redo="annotations.redo()"
                    @deliver="openDeliver"
                />

                <div v-if="formErrors.length" class="rounded-2xl border border-rose-200 bg-rose-50 p-3 text-xs font-semibold text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300">
                    <p v-for="(message, index) in formErrors" :key="index">{{ message }}</p>
                </div>

                <div class="grid items-start gap-4 xl:grid-cols-[16rem_minmax(0,1fr)_20rem]">
                    <StudioToolRail
                        :tool="annotations.tool.value"
                        :stamps="config.stamps ?? []"
                        :brush="annotations.brush"
                        :active-stamp="annotations.activeStamp.value"
                        @set-tool="annotations.setTool"
                        @set-stamp="chooseStamp"
                        @set-color="(color) => { annotations.brush.color = color; }"
                        @clear-page="clearCurrentPage"
                    />

                    <div class="min-w-0 space-y-3">
                        <div class="flex items-center justify-between gap-2 rounded-2xl border border-slate-200/80 bg-white/90 px-3 py-2 shadow-sm dark:border-slate-800 dark:bg-slate-900/90">
                            <p class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                                {{ currentPage ? (copy.studio?.pages?.label ?? 'Page :current of :total').replace(':current', pages.findIndex((page) => page.key === currentKey) + 1).replace(':total', pages.length) : '' }}
                            </p>

                            <div class="flex items-center gap-1">
                                <button type="button" class="flex h-7 w-7 items-center justify-center rounded-lg text-sm font-bold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800" :aria-label="copy.studio?.tools?.zoomOut" @click="zoom = Math.max(0.4, Math.round((zoom - 0.1) * 10) / 10)">−</button>
                                <span class="w-12 text-center text-[11px] font-bold text-slate-600 dark:text-slate-300">{{ Math.round(scale * 100) }}%</span>
                                <button type="button" class="flex h-7 w-7 items-center justify-center rounded-lg text-sm font-bold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800" :aria-label="copy.studio?.tools?.zoomIn" @click="zoom = Math.min(2.5, Math.round((zoom + 0.1) * 10) / 10)">+</button>
                                <button type="button" class="ml-1 rounded-lg border border-slate-200 px-2 py-1 text-[10px] font-bold text-slate-500 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800" @click="zoom = 1">{{ copy.studio?.tools?.zoomReset }}</button>
                            </div>
                        </div>

                        <div
                            ref="viewport"
                            class="flex max-h-[calc(100vh-18rem)] min-h-[26rem] justify-center overflow-auto rounded-2xl bg-slate-200/70 p-6 dark:bg-slate-950/40"
                        >
                            <div v-if="paperLoading" class="flex items-center gap-2 self-center text-xs font-semibold text-slate-500 dark:text-slate-300">
                                <span class="h-4 w-4 animate-spin rounded-full border-2 border-primary border-t-transparent" aria-hidden="true"></span>
                                {{ copy.studio?.pages?.loading }}
                            </div>

                            <PaperCanvas
                                v-else-if="currentPage"
                                :page="currentPage"
                                :paper="paper"
                                :strokes="currentMarkup.strokes"
                                :items="currentMarkup.items"
                                :scale="scale"
                                :tool="annotations.tool.value"
                                :brush="annotations.brush"
                                :min-distance="annotations.MIN_POINT_DISTANCE"
                                :copy="copy.studio?.pages"
                                @stroke="onStroke"
                                @place="onPlace"
                                @remove-item="onRemoveItem"
                            />

                            <p v-else class="self-center text-xs text-slate-500 dark:text-slate-300">
                                {{ copy.studio?.pages?.error }}
                            </p>
                        </div>

                        <PageRail
                            :pages="pages"
                            :current-key="currentKey"
                            :marks="marksByPage"
                            :copy="copy.studio?.pages"
                            @select="(page) => { currentIndex = pages.findIndex((entry) => entry.key === page.key); }"
                        />

                        <div v-if="paperErrors.length" class="rounded-2xl border border-rose-200 bg-rose-50 p-3 text-[11px] font-semibold text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300">
                            <p v-for="problem in paperErrors" :key="problem.name">{{ problem.name }}: {{ problem.message }}</p>
                        </div>
                    </div>

                    <aside class="space-y-3 xl:sticky xl:top-20">
                        <RubricPanel
                            :rubric="rubric"
                            :marks="marks"
                            :max-marks="config.form.maxMarks ?? 50"
                            :copy="copy.studio?.rubric"
                            @set-score="rubric.setScore"
                            @add-mark="addMarkRow"
                            @remove-mark="removeMarkRow"
                        />

                        <FeedbackPanel v-model="feedback" :copy="copy.studio?.feedback" :max="config.form.maxFeedback ?? 5000" />

                        <AnnotationFeed
                            :rows="annotations.feed.value"
                            :current-key="currentKey"
                            :copy="copy.studio?.feed"
                            @locate="locate"
                            @remove="removeFeedRow"
                        />
                    </aside>
                </div>
            </form>

            <AnnotationComposer
                :open="pending !== null"
                :kind="pending?.type ?? 'comment'"
                :copy="copy.studio?.editor"
                @save="saveAnnotation"
                @cancel="pending = null"
            />

            <DeliverDialog
                :open="deliverOpen"
                :phase="deliverPhase"
                :copy="copy.studio?.deliver"
                :rubric="rubric"
                :error="deliverError"
                :generated-label="generatedLabel"
                :extra-count="extraCount"
                :total-label="copy.studio?.rubric?.total"
                @close="deliverOpen = false"
                @export="buildPdf"
                @submit="submitDelivery"
            />
        </template>

        <template v-else>
            <MarkingOrderHero />
            <PaperFilesCard :files="config.files" />
            <DeliveryCard v-if="config.delivery" :delivery="config.delivery" />
            <DisputeNotice v-if="config.dispute" :dispute="config.dispute" />
        </template>
    </div>
</template>
