<script setup>
/**
 * Two different absences: a teacher who has never been paid, and a filter that
 * happens to match nothing. The second one offers the way back to the full
 * ledger, because the teacher's money has not disappeared — the view has.
 */
import { computed } from 'vue';
import UiEmptyState from '../../shared/components/UiEmptyState.vue';
import { useCopy, usePage, useUrls } from '../composables/usePageContext';

const copy = useCopy();
const page = usePage();
const urls = useUrls();

const filtered = computed(() => Boolean(page.pagination?.filtered));
</script>

<template>
    <UiEmptyState
        :icon="filtered ? 'search' : 'wallet'"
        :message="filtered ? copy.ledger?.filteredEmpty : copy.ledger?.empty"
        :link-label="filtered ? copy.ledger?.clearFilter : null"
        :link-href="filtered ? urls.index : null"
        class="mt-4"
    />
</template>
