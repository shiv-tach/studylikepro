<script setup>
/**
 * The server-rendered validation messages for one field. Laravel owns the
 * wording, so the component only decides how a message looks.
 */
defineProps({
    messages: { type: Array, default: () => [] },
});
</script>

<template>
    <ul v-if="messages.length" class="mt-2 space-y-1">
        <li
            v-for="(message, index) in messages"
            :key="index"
            class="text-sm font-medium text-rose-600 dark:text-rose-400"
        >
            {{ message }}
        </li>
    </ul>
</template>
