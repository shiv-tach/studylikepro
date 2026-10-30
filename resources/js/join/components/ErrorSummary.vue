<script setup>
/**
 * One banner listing every message of a rejected save, so a teacher who
 * submitted from the bottom of a long card still sees what went wrong at the
 * top of the page.
 */
import UiAlert from '../../shared/components/UiAlert.vue';

defineProps({
    title: { type: String, default: '' },
    messages: { type: Array, default: () => [] },
});
</script>

<template>
    <div class="survey-enter">
        <UiAlert tone="rose" icon="alert" role="alert">
            <p class="font-semibold">{{ title }}</p>
            <ul class="mt-1 list-inside list-disc space-y-0.5">
                <li v-for="(message, index) in messages" :key="index">{{ message }}</li>
            </ul>
        </UiAlert>
    </div>
</template>
