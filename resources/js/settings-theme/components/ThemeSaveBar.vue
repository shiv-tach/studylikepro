<script setup>
/**
 * The sticky save bar. It mirrors whatever is being previewed and submits the
 * four chosen values to the server as one real PATCH form, so validation and the
 * redirect stay server-side.
 */
import { ref } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';
import { useCopy, useCsrfToken, useUrls } from '../composables/usePageContext';

defineProps({
    draft: { type: Object, required: true },
});

const copy = useCopy();
const urls = useUrls();
const csrfToken = useCsrfToken();
const submitting = ref(false);
</script>

<template>
    <div class="sticky bottom-4 z-10 flex flex-wrap items-center justify-end gap-4 rounded-2xl border border-slate-200 bg-white/90 px-6 py-4 backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/90">
        <p class="mr-auto text-xs text-slate-400 dark:text-slate-500">
            {{ copy.previewPrefix }}
            <span class="font-semibold capitalize text-slate-600 dark:text-slate-300">{{ draft.preset }}</span>
            &middot;
            <span class="capitalize">{{ draft.accent }}</span>
            &middot;
            <span class="capitalize">{{ draft.mode }}</span>
        </p>

        <form method="POST" :action="urls.update" @submit="submitting = true">
            <input type="hidden" name="_token" :value="csrfToken">
            <input type="hidden" name="_method" value="PATCH">
            <input type="hidden" name="theme_preset" :value="draft.preset">
            <input type="hidden" name="theme_accent" :value="draft.accent">
            <input type="hidden" name="theme_mode" :value="draft.mode">
            <input type="hidden" name="theme_sidebarStyle" :value="draft.sidebarStyle">

            <button
                type="submit"
                :disabled="submitting"
                class="inline-flex items-center gap-2 rounded-xl bg-primary px-6 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary/25 transition-all hover:shadow-xl hover:shadow-primary/30 hover:brightness-110 active:scale-95 disabled:opacity-60"
            >
                <span class="h-4 w-4"><AppIcon name="check" /></span>
                {{ submitting ? copy.saving : copy.save }}
            </button>
        </form>
    </div>
</template>
