<script setup>
/**
 * The composer. It owns the DOM-only parts — the file input and the picked photo
 * — and hands the text and the file to the thread composable, which posts them
 * and clears the draft only once the message actually went out.
 *
 * Enter sends and Shift + Enter keeps the newline, so a long message can still be
 * written without the form swallowing it.
 */
import { ref } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';
import { useCopy, useThread } from '../composables/usePageContext';

const copy = useCopy();
const { body, sending, errors, send } = useThread();

const imageInput = ref(null);
const attachmentName = ref('');
const inputId = `messages-attachment-${Math.random().toString(36).slice(2, 8)}`;

function pick(event) {
    attachmentName.value = event.target.files?.[0]?.name ?? '';
}

function clearAttachment() {
    if (imageInput.value) {
        imageInput.value.value = '';
    }

    attachmentName.value = '';
}

async function submit() {
    const file = imageInput.value?.files?.[0] ?? null;

    if (await send(file)) {
        clearAttachment();
    }
}

function onKeydown(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        submit();
    }
}
</script>

<template>
    <form class="mt-5 border-t border-slate-200 pt-4 dark:border-slate-800" @submit.prevent="submit">
        <div class="flex items-end gap-3">
            <label class="sr-only" :for="inputId">{{ copy.attach }}</label>

            <textarea
                v-model="body"
                rows="2"
                maxlength="2000"
                :placeholder="copy.composerPlaceholder"
                :aria-label="copy.composerPlaceholder"
                class="block w-full resize-none rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                @keydown="onKeydown"
            ></textarea>

            <label
                :for="inputId"
                class="cursor-pointer rounded-xl border border-slate-300 p-2.5 text-slate-500 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-800"
                :title="copy.attach"
            >
                <span class="block h-5 w-5"><AppIcon name="camera" /></span>
            </label>
            <input :id="inputId" ref="imageInput" type="file" accept="image/*" class="sr-only" @change="pick" />

            <button
                type="submit"
                :disabled="sending"
                class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-bold text-white shadow-sm transition-colors hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-60"
            >
                {{ sending ? copy.sending : copy.send }}
            </button>
        </div>

        <p v-if="attachmentName" class="mt-2 flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
            <span class="h-3.5 w-3.5"><AppIcon name="camera" /></span>
            <span class="truncate">{{ attachmentName }}</span>
            <button type="button" class="shrink-0 font-semibold text-primary hover:underline" @click="clearAttachment">
                {{ copy.removeAttachment }}
            </button>
        </p>

        <p v-if="errors" class="mt-2 text-xs text-rose-600 dark:text-rose-400" role="alert">{{ errors }}</p>

        <p class="mt-2 text-[11px] text-slate-400">{{ copy.immutable }}</p>
        <p class="mt-1 hidden text-[11px] text-slate-400 sm:block">{{ copy.sendHint }}</p>
    </form>
</template>
