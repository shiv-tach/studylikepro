<script setup>
/**
 * The danger zone: a muted card and the button that opens the confirmation
 * dialog. The dialog is reopened automatically when a rejected delete comes back
 * with the userDeletion bag.
 */
import { computed, ref, watch } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';
import { useCopy } from '../composables/usePageContext';
import AccountCard from './AccountCard.vue';
import ConfirmDeleteDialog from './ConfirmDeleteDialog.vue';

const props = defineProps({
    errors: { type: Array, default: () => [] },
    forceOpen: { type: Boolean, default: false },
});

const copy = useCopy();
const open = ref(props.forceOpen);

// A rejected delete redirects back with the errors already in hand, so the
// dialog has to reopen on its own.
watch(
    () => props.forceOpen,
    (value) => {
        if (value) {
            open.value = true;
        }
    },
);

const hasErrors = computed(() => props.errors.length > 0);
</script>

<template>
    <AccountCard :title="copy.deleteTitle" :description="copy.deleteHint">
        <button
            type="button"
            class="inline-flex items-center gap-2 rounded-xl bg-rose-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2"
            :aria-expanded="open"
            @click="open = true"
        >
            <span class="h-4 w-4"><AppIcon name="trash" /></span>
            {{ copy.delete }}
        </button>

        <p v-if="hasErrors && !open" class="mt-2 text-sm font-medium text-rose-600 dark:text-rose-400">
            {{ copy.deleteErrorHint }}
        </p>

        <ConfirmDeleteDialog :open="open" :errors="errors" @close="open = false" />
    </AccountCard>
</template>
