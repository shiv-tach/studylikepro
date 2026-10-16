<script setup>
/**
 * The accent-colour swatches. Each is a radio: the swatch shows the colour, the
 * ring marks the live choice and the dot underneath repeats it for contrast.
 */
import { accentVisuals } from '../presets';
import PickerSection from './PickerSection.vue';

const props = defineProps({
    accents: { type: Array, default: () => [] },
    modelValue: { type: String, default: null },
    title: { type: String, required: true },
    hint: { type: String, default: '' },
});

defineEmits(['update:modelValue']);

function visuals(id) {
    return accentVisuals(id);
}
</script>

<template>
    <PickerSection :title="title" :hint="hint">
        <div class="flex flex-wrap items-center gap-4" role="radiogroup" :aria-label="title">
            <button
                v-for="accent in accents"
                :key="accent.id"
                type="button"
                role="radio"
                :aria-checked="modelValue === accent.id"
                :aria-label="accent.label ?? accent.id"
                class="group relative flex flex-col items-center gap-2 transition-transform hover:scale-110"
                @click="$emit('update:modelValue', accent.id)"
            >
                <span
                    class="relative flex h-12 w-12 items-center justify-center rounded-2xl transition-all duration-200"
                    :class="modelValue === accent.id
                        ? `ring-2 ring-offset-2 dark:ring-offset-slate-900 ${visuals(accent.id).ring}`
                        : `hover:ring-2 hover:ring-offset-2 hover:ring-slate-300 dark:hover:ring-offset-slate-900`"
                >
                    <span class="h-8 w-8 rounded-xl shadow-md" :class="visuals(accent.id).swatch"></span>
                </span>

                <span class="text-[11px] font-medium capitalize text-slate-500 dark:text-slate-400">{{ accent.label ?? accent.id }}</span>

                <span
                    class="absolute -bottom-1 h-1.5 w-1.5 rounded-full bg-primary transition-opacity"
                    :class="modelValue === accent.id ? 'opacity-100' : 'opacity-0'"
                ></span>
            </button>
        </div>
    </PickerSection>
</template>
