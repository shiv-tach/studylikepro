<script setup>
/**
 * One theme preset tile: a miniature sidebar + content preview, the preset's
 * accent bar, its name and a check dot when it is the one being previewed.
 */
import { computed } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';
import { presetVisuals } from '../presets';

const props = defineProps({
    preset: { type: Object, required: true },
    selected: { type: Boolean, default: false },
});

defineEmits(['select']);

const visuals = computed(() => presetVisuals(props.preset.id));
</script>

<template>
    <button
        type="button"
        :aria-pressed="selected"
        class="group relative flex flex-col overflow-hidden rounded-2xl border-2 text-left transition-all duration-200"
        :class="selected
            ? 'border-primary shadow-lg shadow-primary/10 ring-2 ring-primary/20 dark:border-primary dark:ring-primary/30'
            : 'border-slate-200 hover:border-slate-300 hover:shadow-md dark:border-slate-800 dark:hover:border-slate-700'"
        @click="$emit('select', preset.id)"
    >
        <div class="relative h-28 w-full overflow-hidden" :class="visuals.body">
            <!-- Mini sidebar strip -->
            <div class="absolute left-0 top-0 h-full w-12 border-r border-slate-200/50 dark:border-slate-800/50" :class="visuals.sidebar">
                <div class="absolute left-3 top-3 h-2 w-2 rounded-full" :class="visuals.dot"></div>
                <div class="absolute left-3 top-8 space-y-1.5">
                    <div class="h-1 w-5 rounded-full bg-slate-300/60 dark:bg-slate-600/60"></div>
                    <div class="h-1 w-4 rounded-full bg-slate-300/60 dark:bg-slate-600/60"></div>
                    <div class="h-1 w-3 rounded-full bg-slate-300/60 dark:bg-slate-600/60"></div>
                </div>
            </div>

            <!-- Mini content area -->
            <div class="absolute left-16 right-3 top-3 space-y-1.5">
                <div class="h-1.5 w-16 rounded-full bg-slate-300/50 dark:bg-slate-600/50"></div>
                <div class="h-1 w-24 rounded-full bg-slate-200/50 dark:bg-slate-700/50"></div>
                <div class="mt-2 grid grid-cols-2 gap-1">
                    <div class="h-7 rounded-lg bg-white/80 shadow-sm dark:bg-slate-800/80"></div>
                    <div class="h-7 rounded-lg bg-white/80 shadow-sm dark:bg-slate-800/80"></div>
                </div>
            </div>

            <!-- Accent bar -->
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r" :class="visuals.accentBar"></div>
        </div>

        <div class="flex items-center justify-between gap-2 border-t border-slate-100 px-4 py-3 dark:border-slate-800">
            <div>
                <h4 class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ preset.name }}</h4>
                <p class="text-[11px] text-slate-400 dark:text-slate-500">{{ preset.description }}</p>
            </div>

            <div
                class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border-2 transition-all"
                :class="selected ? 'border-primary bg-primary text-white' : 'border-slate-300 dark:border-slate-600'"
            >
                <span v-if="selected" class="h-3.5 w-3.5"><AppIcon name="check" /></span>
            </div>
        </div>
    </button>
</template>
