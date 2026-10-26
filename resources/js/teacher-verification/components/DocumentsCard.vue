<script setup>
/**
 * Everything the teacher has on file, in one list, with the count beside the
 * heading. This is the "what have I actually sent?" view: it stays visible while
 * the application is under review, when the upload card is gone.
 */
import { usePanel } from '../../shared/composables/usePanel';
import UiChip from '../../shared/components/UiChip.vue';
import UiSectionHeading from '../../shared/components/UiSectionHeading.vue';
import { format, useCopy } from '../composables/usePageContext';
import { useVerification } from '../composables/useVerification';
import DocumentRow from './DocumentRow.vue';

const emit = defineEmits(['request-remove']);

const copy = useCopy();
const panel = usePanel();

const { documents, canEdit, highlightId, removingId } = useVerification();
</script>

<template>
    <section id="documents" :class="panel" class="scroll-mt-40">
        <UiSectionHeading :title="copy.documentsTitle" :description="copy.documentsHint" icon="folder" level="h3">
            <template #action>
                <UiChip tone="primary">{{ format(copy.documentsCount, { count: documents.length }) }}</UiChip>
            </template>
        </UiSectionHeading>

        <div
            v-if="!documents.length"
            class="mt-5 rounded-xl border border-dashed border-slate-300 p-6 text-center dark:border-slate-700"
        >
            <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">{{ copy.documentsEmptyTitle }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ copy.documentsEmptyHint }}</p>
        </div>

        <ul v-else class="mt-5 space-y-2">
            <DocumentRow
                v-for="document in documents"
                :key="document.id"
                :document="document"
                :highlighted="document.id === highlightId"
                :can-edit="canEdit"
                :removing="document.id === removingId"
                @remove="emit('request-remove', $event)"
            />
        </ul>
    </section>
</template>
