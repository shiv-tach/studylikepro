<script setup>
/**
 * A settings card that links somewhere else: the whole tile is one anchor, the
 * icon takes the accent on hover and the CTA arrow nudges right. Used for Theme
 * & Appearance and Profile Information.
 */
import { computed } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';
import { cardTone } from '../tones';

const props = defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    icon: { type: String, default: 'cog' },
    tone: { type: String, default: 'slate' },
    cta: { type: String, default: '' },
    href: { type: String, required: true },
});

const tile = computed(() => cardTone(props.tone));
</script>

<template>
    <a
        :href="href"
        class="group flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-6 transition-all duration-200 hover:border-primary/40 hover:shadow-lg hover:shadow-primary/5 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-primary/40"
    >
        <div class="mb-4 flex items-center gap-3">
            <div
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl p-2.5 transition-colors group-hover:bg-primary group-hover:text-white"
                :class="tile"
            >
                <AppIcon :name="icon" />
            </div>
            <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ title }}</h3>
        </div>

        <p class="text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ description }}</p>

        <div class="mt-4 flex items-center gap-1 text-sm font-medium text-primary">
            <span>{{ cta }}</span>
            <span class="h-4 w-4 transition-transform group-hover:translate-x-0.5">
                <AppIcon name="chevron-right" />
            </span>
        </div>
    </a>
</template>
