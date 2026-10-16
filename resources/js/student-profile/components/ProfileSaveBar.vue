<script setup>
/**
 * The one button that submits the form, kept beside the strength meter so it
 * stays on screen while the student works through the fields.
 */
import { usePanel } from '../../shared/composables/usePanel';
import { useCopy } from '../composables/usePageContext';
import { useProfileForm } from '../composables/useProfileForm';
import { GHOST_BUTTON, PRIMARY_BUTTON } from '../styles';

const copy = useCopy();
const panel = usePanel();

const { dirty, submitting, reset } = useProfileForm();
</script>

<template>
    <section :class="panel" class="space-y-3">
        <p
            class="text-xs font-semibold"
            :class="dirty ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400'"
            role="status"
        >
            {{ dirty ? copy.unsaved : copy.saved }}
        </p>

        <button type="submit" :class="[PRIMARY_BUTTON, 'w-full']" :disabled="submitting">
            {{ submitting ? copy.saving : copy.save }}
        </button>

        <button v-if="dirty" type="button" :class="[GHOST_BUTTON, 'w-full']" @click="reset">
            {{ copy.undo }}
        </button>
    </section>
</template>
