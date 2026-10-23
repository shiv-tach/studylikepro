<script setup>
/**
 * The suggested weeks. A preset only fills the editor — nothing is written
 * until the teacher submits the real form — so applying one is safe, and it
 * asks first when the editor already holds unsaved changes.
 */
import { onBeforeUnmount, ref } from 'vue';
import { usePanel } from '../../shared/composables/usePanel';
import UiConfirmDialog from '../../shared/components/UiConfirmDialog.vue';
import UiSectionHeading from '../../shared/components/UiSectionHeading.vue';
import { format, useCopy } from '../composables/usePageContext';
import { useWeekEditor } from '../composables/useWeekEditor';
import { OPTION_IDLE, PRESET_CARD } from '../styles';

const copy = useCopy();
const panel = usePanel();

const { presets, dirty, applyPreset, clearWeek } = useWeekEditor();

const pendingPreset = ref(null);
const pendingClear = ref(false);
const applied = ref(false);

let appliedTimer = null;

function flashApplied() {
    applied.value = true;

    if (appliedTimer) {
        clearTimeout(appliedTimer);
    }

    appliedTimer = setTimeout(() => {
        applied.value = false;
    }, 4000);
}

function requestPreset(preset) {
    if (dirty.value) {
        pendingPreset.value = preset;

        return;
    }

    applyPreset(preset);
    flashApplied();
}

function confirmPreset() {
    if (pendingPreset.value) {
        applyPreset(pendingPreset.value);
        flashApplied();
    }

    pendingPreset.value = null;
}

function requestClear() {
    if (!dirty.value) {
        clearWeek();

        return;
    }

    pendingClear.value = true;
}

function confirmClear() {
    clearWeek();
    pendingClear.value = false;
}

function hoursOf(preset) {
    let minutes = 0;

    Object.values(preset.days ?? {}).forEach((ranges) => {
        ranges.forEach((range) => {
            const [startHours, startMinutes] = range.start.split(':').map(Number);
            const [endHours, endMinutes] = range.end.split(':').map(Number);

            minutes += Math.max(0, ((endHours * 60) + endMinutes) - ((startHours * 60) + startMinutes));
        });
    });

    const hours = minutes / 60;

    return Number.isInteger(hours) ? String(hours) : hours.toFixed(1);
}

onBeforeUnmount(() => {
    if (appliedTimer) {
        clearTimeout(appliedTimer);
    }
});
</script>

<template>
    <section :class="panel">
        <UiSectionHeading :title="copy.presetTitle" :description="copy.presetHint" icon="sparkles" level="h3" />

        <div class="mt-5 grid gap-3 sm:grid-cols-3">
            <button
                v-for="preset in presets"
                :key="preset.key"
                type="button"
                :class="[PRESET_CARD, preset.recommended ? 'border-primary/50 bg-primary/5 ring-1 ring-primary/20' : OPTION_IDLE]"
                @click="requestPreset(preset)"
            >
                <span class="flex items-center justify-between gap-2">
                    <span class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ preset.label }}</span>
                    <span
                        v-if="preset.recommended"
                        class="inline-flex items-center rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-primary"
                    >
                        {{ copy.presetRecommended }}
                    </span>
                </span>

                <span class="text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ preset.hint }}</span>
                <span class="text-[11px] font-semibold text-slate-400">
                    {{ format(copy.weeklyHours, { hours: hoursOf(preset) }) }}
                </span>
                <span class="mt-auto text-xs font-bold text-primary">{{ copy.presetApply }} →</span>
            </button>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs font-semibold text-emerald-600 dark:text-emerald-400" role="status" aria-live="polite">
                {{ applied ? copy.presetApplied : '' }}
            </p>

            <button
                type="button"
                class="rounded-xl px-3 py-1.5 text-xs font-semibold text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800"
                @click="requestClear"
            >
                {{ copy.clearWeek }}
            </button>
        </div>

        <UiConfirmDialog
            :open="Boolean(pendingPreset)"
            :title="copy.confirmPresetTitle"
            :body="copy.confirmPresetBody"
            :confirm-label="copy.confirmPresetConfirm"
            :cancel-label="copy.confirmCancel"
            :close-label="copy.confirmClose"
            @confirm="confirmPreset"
            @close="pendingPreset = null"
        />

        <UiConfirmDialog
            :open="pendingClear"
            :title="copy.confirmClearTitle"
            :body="copy.confirmClearBody"
            :confirm-label="copy.confirmClearConfirm"
            :cancel-label="copy.confirmCancel"
            :close-label="copy.confirmClose"
            tone="rose"
            @confirm="confirmClear"
            @close="pendingClear = false"
        />
    </section>
</template>
