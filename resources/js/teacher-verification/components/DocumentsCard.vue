<script setup>
/**
 * All of the teacher's documents in one card, split into one upload slot per
 * document type.
 *
 * The page used to list the files in one card and ask for a type + file + button
 * in another, which left teachers unsure which control belonged to what. A slot
 * is now the whole story: its heading says what it is, its picker uploads on
 * choice, and the files land back under that heading.
 */
import { usePanel } from '../../shared/composables/usePanel';
import AppIcon from '../../shared/components/AppIcon.vue';
import UiChip from '../../shared/components/UiChip.vue';
import UiSectionHeading from '../../shared/components/UiSectionHeading.vue';
import { format, useCopy } from '../composables/usePageContext';
import { useVerification } from '../composables/useVerification';
import UploadSlot from './UploadSlot.vue';

const emit = defineEmits(['request-remove']);

const copy = useCopy();
const panel = usePanel();

const {
    documentTypes,
    documents,
    documentsByType,
    slotFor,
    canEdit,
    selectedType,
    highlightId,
    removingId,
} = useVerification();
</script>

<template>
    <section id="documents" :class="panel" class="scroll-mt-40">
        <UiSectionHeading :title="copy.documentsTitle" :description="copy.documentsHint" icon="folder" level="h3">
            <template #action>
                <UiChip tone="primary">{{ format(copy.documentsCount, { count: documents.length }) }}</UiChip>
            </template>
        </UiSectionHeading>

        <!-- The checklist's missing-ID link points here; the anchor stays on the
             upload block so it still lands on the pickers, not the card top. -->
        <div id="upload-documents" class="mt-5 scroll-mt-40">
            <h4 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ copy.uploadTitle }}</h4>
            <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ copy.uploadHint }}</p>

            <p
                v-if="canEdit"
                class="mt-2 flex items-start gap-1.5 rounded-xl bg-primary/5 px-3 py-2 text-xs font-semibold text-primary dark:bg-primary/10"
            >
                <span class="mt-px h-3.5 w-3.5 shrink-0"><AppIcon name="upload" /></span>
                {{ copy.uploadAutoNote }}
            </p>

            <p
                v-if="canEdit && !documents.length"
                class="mt-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400"
            >
                {{ copy.documentsEmptyHint }}
            </p>

            <p
                v-if="!canEdit"
                class="mt-2 flex items-start gap-1.5 rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-500 dark:bg-slate-800/40 dark:text-slate-400"
            >
                <span class="mt-px h-3.5 w-3.5 shrink-0"><AppIcon name="lock" /></span>
                {{ copy.checklistLocked }}
            </p>

            <div class="mt-4 space-y-3">
                <UploadSlot
                    v-for="type in documentTypes"
                    :key="type.value"
                    :type="type"
                    :documents="documentsByType[type.value] ?? []"
                    :state="slotFor(type.value)"
                    :can-edit="canEdit"
                    :start-hint="selectedType === type.value"
                    :highlight-id="highlightId"
                    :removing-id="removingId"
                    @request-remove="emit('request-remove', $event)"
                />
            </div>

            <p class="mt-4 border-t border-slate-200/80 pt-3 text-xs leading-relaxed text-slate-400 dark:border-slate-800/80">
                {{ copy.uploadDraftNote }}
            </p>
        </div>
    </section>
</template>
