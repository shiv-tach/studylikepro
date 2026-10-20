<script setup>
// Mirrors the `x-application-logo` Blade component so the mark looks identical
// whether it is drawn by Blade or by the Vue shell.
defineProps({
    src: { type: String, required: true },
});
</script>

<template>
    <img :src="src" alt="" decoding="async" class="object-contain" />
</template>
