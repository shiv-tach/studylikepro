<script setup>
/**
 * Laravel's messages for one field — the Vue twin of `x-input-error`, so a
 * rejected save reads the same on the survey as everywhere else.
 */
defineProps({
    messages: { type: Array, default: () => [] },
});
</script>

<template>
    <ul v-if="messages.length" class="mt-2 space-y-1" role="alert">
        <li v-for="(message, index) in messages" :key="index" class="text-sm font-medium text-rose-600 dark:text-rose-400">
            {{ message }}
        </li>
    </ul>
</template>
