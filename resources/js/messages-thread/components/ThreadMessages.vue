<script setup>
/**
 * The scrolling thread. It is the element the poller scrolls to the end of after
 * every append, so it binds the composable's `threadEl` ref directly.
 */
import { useCopy, useThread } from '../composables/usePageContext';
import ThreadMessage from './ThreadMessage.vue';

const copy = useCopy();
const { messages, threadEl } = useThread();
</script>

<template>
    <div
        ref="threadEl"
        class="flex max-h-[60vh] min-h-[18rem] flex-col gap-4 overflow-y-auto pr-1"
        role="log"
        aria-live="polite"
    >
        <ThreadMessage v-for="message in messages" :key="message.id" :message="message" />

        <p
            v-if="!messages.length"
            class="mx-auto my-auto max-w-md text-center text-sm italic text-slate-400 dark:text-slate-500"
        >
            {{ copy.emptyHint }}
        </p>
    </div>
</template>
