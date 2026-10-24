<script setup>
/**
 * The delivery confirmation. It narrates the export → attach → send flow, so
 * the teacher always knows whether the generated PDF is ready before the form
 * is submitted. The actual submit stays a native multipart POST owned by the
 * studio shell.
 */
const props = defineProps({
    open: { type: Boolean, default: false },
    phase: { type: String, default: 'review' },
    copy: { type: Object, default: () => ({}) },
    rubric: { type: Object, required: true },
    error: { type: String, default: '' },
    generatedLabel: { type: String, default: null },
    extraCount: { type: Number, default: 0 },
    totalLabel: { type: String, default: 'Total' },
});

const emit = defineEmits(['close', 'export', 'submit']);
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="studio-deliver-title" @keydown.esc.prevent="phase === 'exporting' ? null : emit('close')">
            <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 id="studio-deliver-title" class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ copy.title }}</h2>
                        <p class="mt-1 text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">{{ copy.intro }}</p>
                    </div>
                    <button
                        type="button"
                        class="text-lg font-bold leading-none text-slate-400 hover:text-slate-600 disabled:opacity-40"
                        :aria-label="copy.cancel"
                        :disabled="phase === 'exporting'"
                        @click="emit('close')"
                    >✕</button>
                </div>

                <dl v-if="rubric.hasCriteria" class="mt-4 flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 dark:bg-slate-800/60">
                    <dt class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ totalLabel }}</dt>
                    <dd class="text-lg font-extrabold text-primary">
                        {{ rubric.totalLabel }}
                        <span class="text-[11px] font-semibold text-slate-400">/ {{ rubric.maxLabel }}</span>
                    </dd>
                </dl>

                <p v-if="rubric.hasCriteria && !rubric.complete" class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] font-semibold text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300">
                    {{ copy.missingRubric }}
                </p>

                <div v-if="phase === 'exporting'" class="mt-4 flex items-center gap-3 rounded-xl border border-slate-200 px-3 py-3 dark:border-slate-700">
                    <span class="h-4 w-4 animate-spin rounded-full border-2 border-primary border-t-transparent" aria-hidden="true"></span>
                    <p class="text-xs font-semibold text-slate-600 dark:text-slate-300">{{ copy.exporting }}</p>
                </div>

                <div v-else-if="phase === 'ready'" class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-3 dark:border-emerald-900/40 dark:bg-emerald-950/30">
                    <p class="text-xs font-bold text-emerald-800 dark:text-emerald-300">✓ {{ (copy.exported ?? '').replace(':size', generatedLabel ?? '') }}</p>
                    <p class="mt-0.5 text-[11px] text-emerald-700/80 dark:text-emerald-300/80">{{ copy.attached }}</p>
                </div>

                <div v-else-if="phase === 'error'" class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-3 py-3 dark:border-rose-900/40 dark:bg-rose-950/30">
                    <p class="text-xs font-bold text-rose-700 dark:text-rose-300">{{ copy.exportError }}</p>
                    <p v-if="error" class="mt-1 text-[11px] text-rose-600/80 dark:text-rose-300/80">{{ error }}</p>
                </div>

                <div class="mt-4 border-t border-slate-200 pt-3 dark:border-slate-800">
                    <label for="studio-extra-files" class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                        📎 {{ copy.extra }}
                    </label>
                    <p class="mt-1 text-[10px] text-slate-400">
                        {{ copy.extraHint }}
                        <span v-if="extraCount > 0" class="font-bold text-primary">({{ extraCount }})</span>
                    </p>
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-xl px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 disabled:opacity-40 dark:text-slate-300 dark:hover:bg-slate-800"
                        :disabled="phase === 'exporting'"
                        @click="emit('close')"
                    >
                        {{ copy.cancel }}
                    </button>

                    <button
                        v-if="phase === 'review' || phase === 'error'"
                        type="button"
                        class="rounded-xl bg-primary px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="rubric.hasCriteria && !rubric.complete"
                        @click="emit('export')"
                    >
                        {{ phase === 'error' ? (copy.retry ?? copy.confirm) : copy.confirm }}
                    </button>

                    <button
                        v-else-if="phase === 'ready'"
                        type="button"
                        class="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-emerald-500"
                        @click="emit('submit')"
                    >
                        {{ copy.sendNow ?? copy.confirm }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
