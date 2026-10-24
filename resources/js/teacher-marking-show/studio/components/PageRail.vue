<script setup>
/**
 * Page navigation. A chip per page, grouped by the uploaded file, with a dot
 * when that page already carries markup. Thumbnails are deliberately not
 * rasterised here: a 10-file upload would cost more memory than the studio
 * itself.
 */
import { computed } from 'vue';

const props = defineProps({
    pages: { type: Array, default: () => [] },
    currentKey: { type: String, default: '' },
    marks: { type: Object, default: () => ({}) },
    copy: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['select']);

const groups = computed(() => {
    const map = new Map();

    props.pages.forEach((page) => {
        if (!map.has(page.fileIndex)) {
            map.set(page.fileIndex, { fileIndex: page.fileIndex, name: page.fileName, pages: [] });
        }

        map.get(page.fileIndex).pages.push(page);
    });

    return Array.from(map.values());
});

function markCount(page) {
    return props.marks[page.key] ?? 0;
}
</script>

<template>
    <nav aria-label="Paper pages" class="rounded-2xl border border-slate-200/80 bg-white/90 p-2.5 shadow-sm dark:border-slate-800 dark:bg-slate-900/90">
        <div class="flex items-center justify-between gap-2">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ copy.title }}</p>
            <p class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">{{ (copy.label ?? '').replace(':current', pages.findIndex((page) => page.key === currentKey) + 1).replace(':total', pages.length) }}</p>
        </div>

        <div class="mt-2 flex gap-3 overflow-x-auto pb-1">
            <div v-for="group in groups" :key="group.fileIndex" class="flex shrink-0 items-center gap-1">
                <span class="max-w-[8rem] truncate text-[10px] font-semibold uppercase tracking-wider text-slate-400" :title="group.name">
                    {{ group.name }}
                </span>

                <button
                    v-for="page in group.pages"
                    :key="page.key"
                    type="button"
                    class="relative flex h-8 min-w-[2rem] items-center justify-center rounded-lg border px-2 text-[11px] font-bold transition-colors"
                    :class="page.key === currentKey
                        ? 'border-primary bg-primary text-white shadow-sm'
                        : 'border-slate-200 bg-white text-slate-600 hover:border-primary/40 hover:text-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300'"
                    :aria-current="page.key === currentKey ? 'page' : undefined"
                    @click="emit('select', page)"
                >
                    {{ page.pageIndex + 1 }}
                    <span
                        v-if="markCount(page) > 0"
                        class="absolute -right-1 -top-1 flex h-3.5 min-w-[0.875rem] items-center justify-center rounded-full bg-amber-400 px-0.5 text-[8px] font-bold text-amber-950"
                        :title="`${markCount(page)}`"
                    >{{ markCount(page) > 9 ? '9+' : markCount(page) }}</span>
                </button>
            </div>
        </div>
    </nav>
</template>
