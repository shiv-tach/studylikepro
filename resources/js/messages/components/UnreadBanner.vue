<script setup>
/**
 * The "you have unread messages" strip above the inbox. The count and its
 * translated sentence are folded into one string by Blade, so this component
 * only frames it.
 */
import AppIcon from '../../shared/components/AppIcon.vue';

defineProps({
    label: { type: String, required: true },
});
</script>

<template>
    <div
        class="flex items-center gap-2 rounded-2xl border border-primary/30 bg-primary/5 p-4 text-sm font-medium text-primary"
        role="status"
    >
        <span class="h-4 w-4 shrink-0"><AppIcon name="chat" /></span>
        {{ label }}
    </div>
</template>
