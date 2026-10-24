<script setup>
/**
 * One money tile. The figure eases up from zero on mount (static when the
 * visitor prefers reduced motion), but the final value is always exposed to a
 * screen reader — the animation is decoration, the number is the content.
 */
import { computed } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';
import { useCountUp } from '../../shared/composables/useCountUp';
import { usePanel } from '../../shared/composables/usePanel';
import { formatMinor } from '../composables/money';
import { usePage } from '../composables/usePageContext';
import { tileTone } from '../tones';

const props = defineProps({
    tile: { type: Object, required: true },
});

const page = usePage();
const panel = usePanel();
const tones = computed(() => tileTone(props.tile.tone));

// Count up in minor units so the animation never loses the two decimals.
const animated = useCountUp(computed(() => Number(props.tile.minor) || 0));

const valueLabel = computed(() =>
    formatMinor(page.money?.prefix ?? '', animated.value, page.money?.decimals ?? 2),
);
</script>

<template>
    <div :class="[panel, 'flex h-full flex-col justify-between']">
        <div class="flex items-start justify-between gap-3">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">
                {{ tile.label }}
            </p>
            <span
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl p-2"
                :class="tones.icon"
                aria-hidden="true"
            >
                <AppIcon :name="tile.icon" />
            </span>
        </div>

        <p class="mt-3 text-2xl font-extrabold tracking-tight tabular-nums" :class="tones.value">
            <span aria-hidden="true">{{ valueLabel }}</span>
            <span class="sr-only">{{ tile.valueLabel }}</span>
        </p>

        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ tile.caption }}</p>
    </div>
</template>
