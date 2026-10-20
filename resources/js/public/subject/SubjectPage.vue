<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import LessonsPanel from './components/LessonsPanel.vue';
import SectionNav from './components/SectionNav.vue';
import ServicesPanel from './components/ServicesPanel.vue';
import SubjectCta from './components/SubjectCta.vue';
import SubjectHero from './components/SubjectHero.vue';
import TeachersPanel from './components/TeachersPanel.vue';

const props = defineProps({
    config: { type: Object, required: true },
    clientNavigation: { type: Boolean, default: false },
});

const emit = defineEmits(['navigate']);

const copy = computed(() => props.config.copy ?? {});

const counts = computed(() => ({
    services: (props.config.serviceGroups ?? []).reduce((total, group) => total + group.items.length, 0),
    teachers: props.config.teachers?.total ?? 0,
    lessons: (props.config.lessonGroups ?? []).reduce((total, group) => total + group.items.length, 0),
}));

const tabs = computed(() => [
    { key: 'services', label: copy.value.servicesTab, icon: 'layers', count: counts.value.services },
    { key: 'teachers', label: copy.value.teachersTab, icon: 'users', count: counts.value.teachers },
    { key: 'lessons', label: copy.value.lessonsTab, icon: 'book', count: counts.value.lessons },
]);

const active = ref('services');

// The sections are the page's navigation: a tab swaps the panel in place and
// the URL hash keeps the choice linkable and reloadable.
function preferredTab() {
    const hash = window.location.hash.replace('#', '');

    if (tabs.value.some((tab) => tab.key === hash)) {
        return hash;
    }

    return tabs.value.find((tab) => tab.count > 0)?.key ?? 'services';
}

function select(key) {
    active.value = key;
    window.history.replaceState(window.history.state, '', `#${key}`);
}

function onHashChange() {
    const key = window.location.hash.replace('#', '');

    if (tabs.value.some((tab) => tab.key === key)) {
        active.value = key;
    }
}

onMounted(() => {
    active.value = preferredTab();

    if (!window.location.hash) {
        window.history.replaceState(window.history.state, '', `#${active.value}`);
    }

    window.addEventListener('hashchange', onHashChange);
});

onBeforeUnmount(() => window.removeEventListener('hashchange', onHashChange));

watch(() => props.config, () => {
    active.value = preferredTab();
});
</script>

<template>
    <main class="flex-1">
        <SubjectHero :config="config" :client-navigation="clientNavigation" @navigate="emit('navigate', $event)" />

        <SectionNav :tabs="tabs" :active="active" :label="copy.sectionsLabel" @select="select" />

        <div class="bg-ink-50">
            <ServicesPanel v-if="active === 'services'" :config="config" />
            <TeachersPanel v-else-if="active === 'teachers'" :config="config" />
            <LessonsPanel v-else :config="config" />
        </div>

        <SubjectCta :config="config" />
    </main>
</template>
