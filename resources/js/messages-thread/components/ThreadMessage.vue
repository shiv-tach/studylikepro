<script setup>
/**
 * One message. A platform notice is a centred pill; a chat message is a bubble
 * aligned to its side, labelled with who sent it and when, with an optional photo
 * underneath. Everything is already formatted — the sender name, the time and the
 * attachment URL all arrive from the server payload.
 */
import { computed } from 'vue';
import { useCopy } from '../composables/usePageContext';

const props = defineProps({
    message: { type: Object, required: true },
});

const copy = useCopy();

const isSystem = computed(() => props.message.type === 'system');
const mine = computed(() => Boolean(props.message.mine));
const senderLabel = computed(() => (mine.value ? copy.you : props.message.sender || copy.someone));
const initials = computed(() => (props.message.sender || '?').substring(0, 2));
const bubbleClass = computed(() => (mine.value
    ? 'rounded-br-md bg-primary text-white'
    : 'rounded-bl-md bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200'));
</script>

<template>
    <p
        v-if="isSystem"
        class="mx-auto max-w-md rounded-full bg-slate-100 px-4 py-1.5 text-center text-xs text-slate-500 dark:bg-slate-800/70 dark:text-slate-400"
    >
        {{ message.body }}
    </p>

    <div v-else class="flex gap-3" :class="mine ? 'justify-end' : 'justify-start'">
        <div
            v-if="!mine"
            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-200 text-xs font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-200"
            aria-hidden="true"
        >
            {{ initials }}
        </div>

        <div class="max-w-[80%]">
            <p class="text-xs text-slate-400" :class="mine ? 'text-right' : ''">
                {{ senderLabel }} · {{ message.at }}
            </p>

            <div
                v-if="message.body"
                class="mt-1 whitespace-pre-line rounded-2xl px-4 py-2 text-sm"
                :class="bubbleClass"
            >
                {{ message.body }}
            </div>

            <a
                v-if="message.attachment_url"
                :href="message.attachment_url"
                target="_blank"
                rel="noopener noreferrer"
                class="mt-2 block"
            >
                <img
                    :src="message.attachment_url"
                    :alt="copy.sharedPhoto"
                    class="max-h-64 rounded-xl border border-slate-200 object-cover dark:border-slate-700"
                    :class="mine ? 'ml-auto' : ''"
                />
            </a>
        </div>
    </div>
</template>
