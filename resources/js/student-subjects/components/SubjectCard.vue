<script setup>
/**
 * A subject tile on the "My subjects" page. It is a display, not a picker: the
 * whole card is one link into the grade-scoped subject page.
 */
import { CARD, CARD_HOVER, HEADING_TEXT, MUTED_TEXT } from '../../shared/styles';
import AppIcon from '../../shared/components/AppIcon.vue';
import UiChip from '../../shared/components/UiChip.vue';
import { useCopy } from '../composables/usePageContext';

defineProps({
    subject: { type: Object, required: true },
});

const copy = useCopy();
</script>

<template>
    <a
        :href="subject.url"
        :class="[
            CARD,
            CARD_HOVER,
            'group relative flex flex-col overflow-hidden focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40',
        ]"
    >
        <span
            class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-primary via-primary/70 to-purple-500 opacity-0 transition-opacity duration-300 group-hover:opacity-100"
            aria-hidden="true"
        ></span>

        <div class="flex items-start justify-between gap-3 p-5 pb-4">
            <span
                class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-3xl transition-colors duration-300 group-hover:bg-primary group-hover:text-white"
            >
                {{ subject.icon }}
            </span>
            <UiChip>{{ subject.lessonLabel }}</UiChip>
        </div>

        <div class="flex-1 px-5 pb-5">
            <h3 :class="['text-base font-bold leading-snug', HEADING_TEXT]">{{ subject.name }}</h3>

            <p v-if="subject.description" :class="['mt-1.5 line-clamp-2 text-sm', MUTED_TEXT]">
                {{ subject.description }}
            </p>
        </div>

        <span
            class="mt-auto flex items-center justify-between gap-2 border-t border-slate-100 px-5 py-3.5 text-sm font-semibold text-primary dark:border-slate-800/80"
        >
            {{ copy.viewCta }}
            <span class="h-4 w-4 transition-transform duration-300 group-hover:translate-x-0.5">
                <AppIcon name="chevron-right" />
            </span>
        </span>
    </a>
</template>
