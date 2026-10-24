<script setup>
/**
 * One marking order at /teacher/marking/{order}. The delivery is still a real
 * multipart POST, so it works the same when the bundle is slow to load; a
 * rejected submit replays the feedback, every mark row and the field errors.
 */
import { computed, provide } from 'vue';
import { THEME_PRESET_KEY, useThemePreset } from '../shared/composables/useThemePreset';
import { useNotices } from '../shared/composables/useNotices';
import UiToastStack from '../shared/components/UiToastStack.vue';
import { COPY_KEY, CSRF_KEY, PAGE_KEY, URLS_KEY } from './composables/usePageContext';
import MarkingOrderHero from './components/MarkingOrderHero.vue';
import PaperFilesCard from './components/PaperFilesCard.vue';
import DeliveryFormCard from './components/DeliveryFormCard.vue';
import DeliveryCard from './components/DeliveryCard.vue';
import DisputeNotice from './components/DisputeNotice.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

// The shell owns the preset; sharing it here keeps every card in step.
provide(THEME_PRESET_KEY, useThemePreset(props.config.theme?.preset ?? 'classic'));
provide(COPY_KEY, props.config.copy ?? {});
provide(URLS_KEY, props.config.urls ?? {});
provide(CSRF_KEY, props.config.csrfToken ?? '');
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

        <MarkingOrderHero />

        <PaperFilesCard :files="config.files" />

        <DeliveryFormCard v-if="config.canMark && config.form" />

        <DeliveryCard v-if="config.delivery" :delivery="config.delivery" />

        <DisputeNotice v-if="config.dispute" :dispute="config.dispute" />
    </div>
</template>
