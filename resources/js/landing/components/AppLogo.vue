<script setup>
// Mirrors resources/views/components/application-logo.blade.php so the tab-mark
// looks identical whether it is drawn by Blade or by the Vue landing app.
defineProps({
    src: { type: String, required: true },
    alt: { type: String, default: '' },
});
</script>

<template>
    <img :src="src" :alt="alt" decoding="async" class="object-contain" />
</template>
