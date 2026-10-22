<script setup>
/**
 * Pause/resume, edit and delete for one service. Shared by both catalog
 * layouts so a row and a card always offer the same three actions. Pause and
 * resume stay a real POST form; edit and delete open the page's dialogs.
 */
import AppIcon from '../../shared/components/AppIcon.vue';
import { useCopy, useCsrfToken } from '../composables/usePageContext';
import { DANGER_GHOST_BUTTON, GHOST_BUTTON } from '../styles';

defineProps({
    offering: { type: Object, required: true },
});

const emit = defineEmits(['edit', 'delete']);

const copy = useCopy();
const csrfToken = useCsrfToken();
</script>

<template>
    <div class="flex flex-wrap items-center gap-1">
        <form method="POST" :action="offering.toggleUrl">
            <input type="hidden" name="_token" :value="csrfToken">
            <button type="submit" :class="GHOST_BUTTON">
                <span class="h-4 w-4" aria-hidden="true">
                    <AppIcon :name="offering.isActive ? 'pause' : 'play'" />
                </span>
                {{ offering.isActive ? copy.card?.pause : copy.card?.resume }}
            </button>
        </form>

        <button type="button" :class="GHOST_BUTTON" @click="emit('edit', offering)">
            <span class="h-4 w-4" aria-hidden="true"><AppIcon name="pencil" /></span>
            {{ copy.card?.edit }}
        </button>

        <button
            type="button"
            :class="[DANGER_GHOST_BUTTON, 'ml-auto']"
            :disabled="!offering.canDelete"
            :title="offering.canDelete ? copy.card?.delete : copy.card?.deleteBlocked"
            @click="emit('delete', offering)"
        >
            <span class="h-4 w-4" aria-hidden="true"><AppIcon name="trash" /></span>
            {{ copy.card?.delete }}
        </button>
    </div>
</template>
