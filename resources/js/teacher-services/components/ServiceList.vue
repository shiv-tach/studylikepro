<script setup>
/**
 * The catalog for the selected tab — one compact row per service by default,
 * the card grid when the teacher picks it in the toolbar — plus the empty
 * states that actually help: subjects awaiting approval, no subjects yet,
 * nothing of this type yet, and nothing matching the filters.
 */
import { computed } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';
import RevealOnScroll from '../../shared/components/RevealOnScroll.vue';
import { usePanel } from '../../shared/composables/usePanel';
import { format, useCopy, useUrls } from '../composables/usePageContext';
import { EMPTY_RING, PRIMARY_BUTTON, SECONDARY_BUTTON } from '../styles';
import ServiceCard from './ServiceCard.vue';

const props = defineProps({
    offerings: { type: Array, default: () => [] },
    hasSubjects: { type: Boolean, default: true },
    subjectsAwaitingApproval: { type: Boolean, default: false },
    tab: { type: String, default: 'all' },
    tabType: { type: Object, default: null },
    view: { type: String, default: 'list' },
    hasFilters: { type: Boolean, default: false },
});

const emit = defineEmits(['create', 'edit', 'delete', 'clear']);

const copy = useCopy();
const urls = useUrls();
const panel = usePanel();

const empty = computed(() => {
    if (props.hasFilters) {
        return {
            icon: 'search',
            title: copy.list?.emptyFilteredTitle,
            body: copy.list?.emptyFilteredBody,
            cta: copy.list?.clearFilters,
            action: 'clear',
        };
    }

    if (props.tab === 'all' || !props.tabType) {
        return {
            icon: 'tag',
            title: copy.list?.emptyAllTitle,
            body: copy.list?.emptyAllBody,
            cta: copy.list?.emptyCta,
            action: 'create',
        };
    }

    return {
        icon: props.tabType.icon,
        title: format(copy.list?.emptyTypeTitle, { type: props.tabType.label }),
        body: format(copy.list?.emptyTypeBody, { type: props.tabType.label }),
        cta: format(copy.list?.emptyTypeCta, { type: props.tabType.label }),
        action: 'create',
    };
});

function onEmptyAction() {
    if (empty.value.action === 'clear') {
        emit('clear');

        return;
    }

    emit('create', props.tabType?.value ?? null);
}

function onDelete(offering) {
    if (offering.canDelete) {
        emit('delete', offering);
    }
}
</script>

<template>
    <div
        :id="`teacher-services-panel-${tab}`"
        role="tabpanel"
        :aria-labelledby="`teacher-services-tab-${tab}`"
    >
        <div v-if="subjectsAwaitingApproval" :class="[panel, EMPTY_RING, 'flex flex-col items-center gap-3 text-center']">
            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-100 p-3 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                <AppIcon name="clock" />
            </span>
            <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ copy.list?.noApprovedSubjectsTitle }}</h2>
            <p class="max-w-md text-sm text-slate-500 dark:text-slate-400">{{ copy.list?.noApprovedSubjectsBody }}</p>
            <a :href="urls.subjects" :class="[SECONDARY_BUTTON, 'mt-1']">
                {{ copy.list?.noApprovedSubjectsCta }}
                <span class="h-4 w-4" aria-hidden="true"><AppIcon name="arrow-right" /></span>
            </a>
        </div>

        <div v-else-if="!hasSubjects" :class="[panel, EMPTY_RING, 'flex flex-col items-center gap-3 text-center']">
            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 p-3 text-slate-400 dark:bg-slate-800 dark:text-slate-500">
                <AppIcon name="book" />
            </span>
            <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ copy.list?.noSubjectsTitle }}</h2>
            <p class="max-w-md text-sm text-slate-500 dark:text-slate-400">{{ copy.list?.noSubjectsBody }}</p>
            <a :href="urls.subjects" :class="[SECONDARY_BUTTON, 'mt-1']">
                {{ copy.list?.noSubjectsCta }}
                <span class="h-4 w-4" aria-hidden="true"><AppIcon name="arrow-right" /></span>
            </a>
        </div>

        <div v-else-if="!offerings.length" :class="[panel, EMPTY_RING, 'flex flex-col items-center gap-3 text-center']">
            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 p-3 text-primary">
                <AppIcon :name="empty.icon" />
            </span>
            <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ empty.title }}</h2>
            <p class="max-w-md text-sm text-slate-500 dark:text-slate-400">{{ empty.body }}</p>
            <button type="button" :class="[PRIMARY_BUTTON, 'mt-1']" @click="onEmptyAction">
                <span class="h-4 w-4" aria-hidden="true">
                    <AppIcon :name="empty.action === 'clear' ? 'close' : 'plus'" />
                </span>
                {{ empty.cta }}
            </button>
        </div>

        <div
            v-else
            :class="view === 'card' ? 'grid gap-4 md:grid-cols-2 xl:grid-cols-3' : 'flex flex-col gap-3'"
        >
            <RevealOnScroll
                v-for="(offering, index) in offerings"
                :key="offering.id"
                :delay="index * 20"
            >
                <ServiceCard
                    :offering="offering"
                    :view="view"
                    @edit="emit('edit', $event)"
                    @delete="onDelete"
                />
            </RevealOnScroll>
        </div>
    </div>
</template>
