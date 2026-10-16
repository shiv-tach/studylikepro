<script setup>
/**
 * The thread card: the scrolling messages and the composer pinned underneath it,
 * sharing one surface the way the Blade screen had them in one card.
 */
import { usePanel } from '../../shared/composables/usePanel';
import ThreadMessages from './ThreadMessages.vue';
import MessageComposer from './MessageComposer.vue';

const panel = usePanel();
</script>

<template>
    <div :class="[panel, 'flex flex-col']">
        <ThreadMessages />
        <MessageComposer />
    </div>
</template>
