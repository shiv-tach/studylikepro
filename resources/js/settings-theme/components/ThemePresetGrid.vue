<script setup>
/**
 * The preset picker grid. It is a radio group in spirit, so the tiles carry
 * `aria-pressed` and the section heading is wired to it with the section title.
 */
import PickerSection from './PickerSection.vue';
import ThemePresetCard from './ThemePresetCard.vue';

defineProps({
    presets: { type: Array, default: () => [] },
    modelValue: { type: String, default: null },
    title: { type: String, required: true },
    hint: { type: String, default: '' },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <PickerSection :title="title" :hint="hint">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <ThemePresetCard
                v-for="preset in presets"
                :key="preset.id"
                :preset="preset"
                :selected="modelValue === preset.id"
                @select="$emit('update:modelValue', $event)"
            />
        </div>
    </PickerSection>
</template>
