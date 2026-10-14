<script setup>
/**
 * One card of the question form: a heading, the reason the field exists, an
 * optional trailing control and the field itself. The surface follows the
 * shell's theme preset through `usePanel`.
 */
import { usePanel } from '../../shared/composables/usePanel';
import UiChip from '../../shared/components/UiChip.vue';

defineProps({
    title: { type: String, required: true },
    description: { type: String, default: null },
    badge: { type: String, default: null },
});

const panel = usePanel();
</script>

<template>
    <section :class="panel">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ title }}</h3>
                    <UiChip v-if="badge">{{ badge }}</UiChip>
                </div>
                <p v-if="description" class="mt-1 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                    {{ description }}
                </p>
            </div>

            <slot name="action" />
        </div>

        <slot />
    </section>
</template>
