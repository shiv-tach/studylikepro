<script setup>
/**
 * A settings card for a section that is not built yet: same surface as a link
 * card, but the CTA is muted and the header carries a "Coming soon" badge, so it
 * never looks clickable.
 */
import { computed } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';
import UiChip from '../../shared/components/UiChip.vue';
import { cardTone } from '../tones';

const props = defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    icon: { type: String, default: 'cog' },
    tone: { type: String, default: 'slate' },
    badge: { type: String, default: null },
    cta: { type: String, default: '' },
});

const tile = computed(() => cardTone(props.tone));
</script>

<template>
    <div
        class="group flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-6 transition-all duration-200 hover:border-primary/40 hover:shadow-lg hover:shadow-primary/5 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-primary/40"
    >
        <div class="mb-4 flex items-center gap-3">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl p-2.5" :class="tile">
                <AppIcon :name="icon" />
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ title }}</h3>
                <UiChip v-if="badge" tone="slate">{{ badge }}</UiChip>
            </div>
        </div>

        <p class="text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ description }}</p>

        <div class="mt-4 flex items-center gap-1 text-sm font-medium text-slate-400 dark:text-slate-500">
            <span>{{ cta }}</span>
            <span class="h-4 w-4"><AppIcon name="clock" /></span>
        </div>
    </div>
</template>
