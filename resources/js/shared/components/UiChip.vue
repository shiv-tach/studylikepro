<script setup>
/**
 * A small pill used for counts, grades, ratings and "pick one" hints. The tone
 * is semantic so a chip keeps its meaning whatever preset the shell is on.
 */
import { computed } from 'vue';
import { CHIP, CHIP_TONES } from '../styles';
import AppIcon from './AppIcon.vue';

const props = defineProps({
    tone: { type: String, default: 'slate' },
    icon: { type: String, default: null },
});

const classes = computed(() => [CHIP, CHIP_TONES[props.tone] ?? CHIP_TONES.slate]);
</script>

<template>
    <span :class="classes">
        <span v-if="icon" class="h-3.5 w-3.5 shrink-0">
            <AppIcon :name="icon" />
        </span>
        <slot />
    </span>
</template>
