<script setup>
/**
 * The teacher's marking inbox at /teacher/marking. Blade decides every count,
 * label, amount and URL; this app only lays them out. The status rail is real
 * links, so filtering works with JavaScript off, and the paginator stays
 * Blade-rendered below the mount.
 */
import { computed, provide } from 'vue';
import { THEME_PRESET_KEY, useThemePreset } from '../shared/composables/useThemePreset';
import { useNotices } from '../shared/composables/useNotices';
import UiToastStack from '../shared/components/UiToastStack.vue';
import { COPY_KEY, PAGE_KEY, URLS_KEY } from './composables/usePageContext';
import MarkingHero from './components/MarkingHero.vue';
import MarkingStatusRail from './components/MarkingStatusRail.vue';
import MarkingOrderList from './components/MarkingOrderList.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

// The shell owns the preset; sharing it here keeps every card in step.
provide(THEME_PRESET_KEY, useThemePreset(props.config.theme?.preset ?? 'classic'));
provide(COPY_KEY, props.config.copy ?? {});
provide(URLS_KEY, props.config.urls ?? {});
provide(PAGE_KEY, props.config);

const copy = computed(() => props.config.copy ?? {});

const { toasts, dismiss } = useNotices(props.config.notices ?? []);
</script>

<template>
    <div class="space-y-6">
        <UiToastStack
            :toasts="toasts"
            :label="copy.toast?.label ?? 'Notifications'"
            :dismiss-label="copy.toast?.dismiss ?? 'Dismiss'"
            @dismiss="dismiss"
        />

        <MarkingHero />

        <MarkingStatusRail />

        <MarkingOrderList :orders="config.orders" />
    </div>
</template>
