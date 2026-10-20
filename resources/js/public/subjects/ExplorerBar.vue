<script setup>
import { nextTick, ref, watch } from 'vue';
import { isPlainClick } from '../client-navigation';
import { useRovingFocus } from './composables/useRovingFocus';

const props = defineProps({
    levels: { type: Array, default: () => [] },
    level: { type: Object, default: null },
    grades: { type: Array, default: () => [] },
    grade: { type: Object, default: null },
    copy: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['select-level', 'select-grade', 'clear-grade', 'prefetch-level', 'prefetch-grade']);

const { container, onKeydown } = useRovingFocus();
const gradeList = ref(null);

// A sticky bar means the selected chip can sit off-screen after a deep link or
// a history move; pull it back into view without moving the page itself.
watch(
    () => props.grade?.id,
    async () => {
        await nextTick();
        gradeList.value?.querySelector('[aria-pressed="true"]')?.scrollIntoView({ inline: 'center', block: 'nearest' });
    },
    { immediate: true },
);

function selectLevel(event, option) {
    if (!isPlainClick(event)) {
        return;
    }

    event.preventDefault();
    emit('select-level', option);
}

function selectGrade(event, option) {
    if (!isPlainClick(event)) {
        return;
    }

    event.preventDefault();
    emit('select-grade', option);
}

function clearGrade(event) {
    if (!isPlainClick(event)) {
        return;
    }

    event.preventDefault();
    emit('clear-grade');
}
</script>

<template>
    <div class="sticky top-16 z-30 border-b border-line bg-white/95 backdrop-blur">
        <div class="mx-auto w-full max-w-6xl px-5">
            <div class="py-3">
                <p class="hidden text-[11px] font-semibold tracking-widest text-ink-400 uppercase sm:block">{{ copy.explorerLabel }}</p>

                <div
                    ref="container"
                    class="lp-tab-rail -mx-1 flex items-center gap-1 overflow-x-auto pb-1 sm:mt-2"
                    role="group"
                    :aria-label="copy.levelStep"
                    @keydown="onKeydown"
                >
                    <a
                        v-for="option in levels"
                        :key="option.key"
                        :href="option.url"
                        data-focusable
                        :aria-pressed="option.key === level?.key"
                        class="flex shrink-0 items-center gap-2 rounded-full px-3.5 py-2 text-sm font-medium whitespace-nowrap transition"
                        :class="option.key === level?.key
                            ? 'bg-ink text-white'
                            : 'text-ink-700 hover:bg-ink-50'"
                        @click="selectLevel($event, option)"
                        @pointerenter="emit('prefetch-level', option.key)"
                        @focus="emit('prefetch-level', option.key)"
                    >
                        <span aria-hidden="true">{{ option.icon }}</span>
                        {{ option.name }}
                        <span
                            v-if="option.gradeCount"
                            class="rounded-full px-1.5 text-[11px] font-semibold"
                            :class="option.key === level?.key ? 'bg-white/20 text-white' : 'bg-ink-50 text-ink-500'"
                        >{{ option.gradeCount }}</span>
                    </a>
                </div>
            </div>

            <Transition name="explorer-swap">
                <div v-if="level" class="flex flex-col gap-1.5 border-t border-line/70 py-2 sm:flex-row sm:items-center sm:gap-2.5">
                    <span class="shrink-0 text-[11px] font-semibold tracking-widest text-ink-400 uppercase">
                        {{ copy.gradeStep }}
                    </span>

                    <div
                        ref="gradeList"
                        class="lp-tab-rail -mx-1 flex min-w-0 flex-1 items-center gap-1.5 overflow-x-auto px-1 py-1"
                        role="group"
                        :aria-label="copy.gradeStep"
                    >
                        <a
                            v-for="option in grades"
                            :key="option.id"
                            :href="option.url"
                            data-focusable
                            :aria-pressed="option.id === grade?.id"
                            class="shrink-0 rounded-full border px-3.5 py-1.5 text-sm font-medium whitespace-nowrap transition"
                            :class="option.id === grade?.id
                                ? 'border-transparent bg-ink text-white'
                                : 'border-line bg-white text-ink-700 hover:border-ink-500'"
                            @click="selectGrade($event, option)"
                            @pointerenter="emit('prefetch-grade', option)"
                            @focus="emit('prefetch-grade', option)"
                        >{{ option.label }}</a>

                        <a
                            v-if="grade"
                            :href="level.url"
                            class="shrink-0 rounded-full px-3.5 py-1.5 text-sm font-medium whitespace-nowrap text-brand-600 underline-offset-4 transition hover:underline"
                            @click="clearGrade"
                        >{{ copy.allGrades }}</a>
                    </div>
                </div>
            </Transition>
        </div>
    </div>
</template>
