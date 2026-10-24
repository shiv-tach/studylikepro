<script setup>
/**
 * The small dialog that collects a comment or correction before the pin is
 * placed. It is teleported so the page's overflow containers cannot clip it.
 */
import { nextTick, ref, watch } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    kind: { type: String, default: 'comment' },
    copy: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['save', 'cancel']);

const text = ref('');
const textarea = ref(null);

watch(
    () => props.open,
    async (open) => {
        if (!open) {
            return;
        }

        text.value = '';
        await nextTick();
        textarea.value?.focus();
    },
);

function submit() {
    const value = text.value.trim();

    if (value === '') {
        textarea.value?.focus();

        return;
    }

    emit('save', value);
}
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" @keydown.esc.prevent="emit('cancel')">
            <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-start justify-between gap-3">
                    <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100">
                        {{ kind === 'correction' ? copy.correctionTitle : copy.commentTitle }}
                    </h2>
                    <button type="button" class="text-lg font-bold leading-none text-slate-400 hover:text-slate-600" :aria-label="copy.cancel" @click="emit('cancel')">✕</button>
                </div>

                <label for="studio-annotation-text" class="mt-3 block text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                    {{ copy.textLabel }}
                </label>
                <textarea
                    id="studio-annotation-text"
                    ref="textarea"
                    v-model="text"
                    rows="4"
                    maxlength="1000"
                    :placeholder="copy.textPlaceholder"
                    class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-xs shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                    @keydown.ctrl.enter.prevent="submit"
                    @keydown.meta.enter.prevent="submit"
                ></textarea>

                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="rounded-xl px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800" @click="emit('cancel')">
                        {{ copy.cancel }}
                    </button>
                    <button type="button" class="rounded-xl bg-primary px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-primary/90" @click="submit">
                        {{ copy.add }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
