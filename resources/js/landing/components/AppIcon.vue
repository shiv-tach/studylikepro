<script setup>
import { computed } from 'vue';
import { icons } from '../icons';

const props = defineProps({
    name: { type: String, required: true },
});

const icon = computed(() => icons[props.name] ?? { paths: [], circles: [] });
</script>

<template>
    <svg
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.7"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
        focusable="false"
    >
        <path v-for="d in icon.paths" :key="d" :d="d" />
        <circle
            v-for="(circle, index) in icon.circles"
            :key="`circle-${index}`"
            :cx="circle.cx"
            :cy="circle.cy"
            :r="circle.r"
        />
    </svg>
</template>
