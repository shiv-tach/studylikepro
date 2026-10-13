<script setup>
import { computed } from 'vue';
import { navItemClass } from '../theme';
import { useShell } from '../composables/useShell';
import AppIcon from './AppIcon.vue';

const props = defineProps({
    item: { type: Object, required: true },
});

const { preset } = useShell();

const itemClass = computed(() => navItemClass(preset.value, props.item.active));
</script>

<template>
    <a
        :href="item.url"
        :class="[itemClass, 'flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition-all']"
        :aria-current="item.active ? 'page' : undefined"
    >
        <span class="h-5 w-5 shrink-0"><AppIcon :name="item.icon" /></span>
        <span>{{ item.label }}</span>
        <span
            v-if="item.badge"
            class="ml-auto inline-flex min-w-5 items-center justify-center rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-bold text-white"
        >
            {{ item.badge }}
        </span>
    </a>
</template>
