<script setup>
/**
 * The heading row every catalog section shares: an icon tile, a title, an
 * optional description and a `<slot name="action">` for the trailing link.
 */
import { computed } from 'vue';
import { HEADING_TEXT, MUTED_TEXT } from '../styles';
import AppIcon from './AppIcon.vue';

defineProps({
    title: { type: String, required: true },
    description: { type: String, default: null },
    icon: { type: String, default: null },
    iconTone: { type: String, default: 'bg-primary/10 text-primary' },
    level: { type: String, default: 'h2' },
});
const titleClasses = computed(() => ['text-lg font-bold', HEADING_TEXT]);
const descriptionClasses = computed(() => ['mt-1 text-sm', MUTED_TEXT]);
</script>

<template>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="flex items-start gap-3">
            <span
                v-if="icon"
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl p-2"
                :class="iconTone"
            >
                <AppIcon :name="icon" />
            </span>

            <div>
                <component :is="level" :class="titleClasses">{{ title }}</component>
                <p v-if="description" :class="descriptionClasses">{{ description }}</p>
            </div>
        </div>

        <slot name="action" />
    </div>
</template>
