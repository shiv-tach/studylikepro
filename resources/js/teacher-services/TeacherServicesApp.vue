<script setup>
/**
 * The teacher's services catalog at /teacher/services. Blade decides every
 * label, price, limit and URL; this app owns the tabs, the search and status
 * filters, the composer sheet and the delete dialog. Each real request is still
 * a standard form (CSRF + redirect + toast), so a browser without JavaScript
 * keeps the <noscript> twin on the same page.
 */
import { computed, provide, ref } from 'vue';
import { THEME_PRESET_KEY, useThemePreset } from '../shared/composables/useThemePreset';
import { useNotices } from '../shared/composables/useNotices';
import UiToastStack from '../shared/components/UiToastStack.vue';
import { COPY_KEY, CSRF_KEY, PAGE_KEY, URLS_KEY, WORKSPACE_KEY } from './composables/usePageContext';
import { createServicesWorkspace } from './composables/useServicesWorkspace';
import DeleteServiceDialog from './components/DeleteServiceDialog.vue';
import ServiceComposer from './components/ServiceComposer.vue';
import ServiceList from './components/ServiceList.vue';
import ServiceTabs from './components/ServiceTabs.vue';
import ServiceToolbar from './components/ServiceToolbar.vue';
import ServicesHero from './components/ServicesHero.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

provide(THEME_PRESET_KEY, useThemePreset(props.config.theme?.preset ?? 'classic'));
provide(COPY_KEY, props.config.copy ?? {});
provide(URLS_KEY, props.config.urls ?? {});
provide(CSRF_KEY, props.config.csrfToken ?? '');
provide(PAGE_KEY, props.config);

const workspace = createServicesWorkspace(props.config);
provide(WORKSPACE_KEY, workspace);

const copy = computed(() => props.config.copy ?? {});

const { toasts, dismiss } = useNotices(props.config.notices ?? []);

const {
    activeTab,
    activeTabType,
    search,
    status,
    tabs,
    filteredOfferings,
    hasFilters,
    clearFilters,
    resultLabel,
    openCreate,
    openEdit,
} = workspace;

const deleteTarget = ref(null);

function requestDelete(offering) {
    if (!offering?.canDelete) {
        return;
    }

    deleteTarget.value = offering;
}

function closeDelete() {
    deleteTarget.value = null;
}

function createFromActiveTab() {
    openCreate(activeTabType.value?.value ?? null);
}
</script>

<template>
    <div class="space-y-6">
        <UiToastStack
            :toasts="toasts"
            :label="copy.toastLabel ?? 'Notifications'"
            :dismiss-label="copy.toastDismiss ?? 'Dismiss'"
            @dismiss="dismiss"
        />

        <ServicesHero :can-create="config.hasSubjects" @create="openCreate()" />

        <ServiceTabs v-model="activeTab" :tabs="tabs" />

        <ServiceToolbar
            v-model:search="search"
            v-model:status="status"
            :result-label="resultLabel"
            :can-create="config.hasSubjects"
            @create="createFromActiveTab"
        />

        <ServiceList
            :offerings="filteredOfferings"
            :has-subjects="config.hasSubjects"
            :tab="activeTab"
            :tab-type="activeTabType"
            :has-filters="hasFilters"
            @create="openCreate($event)"
            @edit="openEdit"
            @delete="requestDelete"
            @clear="clearFilters"
        />

        <ServiceComposer />

        <DeleteServiceDialog :offering="deleteTarget" @close="closeDelete" />
    </div>
</template>
