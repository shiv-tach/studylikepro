<script setup>
/**
 * One uploaded document: what it is, whether it is the one the review needs, the
 * file the teacher actually picked, and (while documents can still change) the
 * remove action. A row that was just added is ringed and labelled, so the
 * confirmation is on the row itself and not only in a floating notice.
 */
import { computed } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';
import UiChip from '../../shared/components/UiChip.vue';
import { format, useCopy } from '../composables/usePageContext';
import { DANGER_GHOST_BUTTON } from '../styles';

const props = defineProps({
    document: { type: Object, required: true },
    highlighted: { type: Boolean, default: false },
    canEdit: { type: Boolean, default: false },
    removing: { type: Boolean, default: false },
});

const emit = defineEmits(['remove']);

const copy = useCopy();

const TYPE_ICONS = {
    id_proof: 'shield',
    degree: 'graduation',
    certificate: 'check-circle',
    other: 'folder',
};

const icon = computed(() => TYPE_ICONS[props.document.type] ?? 'folder');

const meta = computed(() => [
    props.document.originalName,
    props.document.uploadedAt ? format(copy.uploadedOn, { date: props.document.uploadedAt }) : null,
].filter(Boolean).join(' · '));
</script>

<template>
    <li
        class="flex min-w-0 items-center gap-2.5 rounded-xl border p-2.5 transition-colors duration-300 sm:gap-3 sm:p-3"
        :class="highlighted
            ? 'border-primary/60 bg-primary/5 ring-1 ring-primary/20'
            : 'border-slate-200/80 dark:border-slate-800/80'"
    >
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 p-2 text-primary sm:h-10 sm:w-10 sm:p-2.5">
            <AppIcon :name="icon" />
        </span>

        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-1.5">
                <p class="max-w-full break-words text-sm font-semibold text-slate-800 dark:text-slate-100">{{ document.typeLabel }}</p>
                <UiChip :tone="document.isRequired ? 'emerald' : 'slate'">
                    {{ document.isRequired ? copy.requiredDocument : copy.optionalDocument }}
                </UiChip>
                <UiChip v-if="highlighted" tone="primary">{{ copy.justUploaded }}</UiChip>
            </div>

            <p class="mt-0.5 break-words text-xs leading-relaxed text-slate-400">{{ meta }}</p>
        </div>

        <button
            v-if="canEdit"
            type="button"
            :class="DANGER_GHOST_BUTTON"
            :disabled="removing"
            :aria-label="format(copy.removeDocument, { type: document.typeLabel })"
            @click="emit('remove', document)"
        >
            <span class="h-4 w-4" :class="removing ? 'animate-pulse' : ''"><AppIcon name="trash" /></span>
        </button>
    </li>
</template>
