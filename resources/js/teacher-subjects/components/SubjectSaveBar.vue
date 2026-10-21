<script setup>
/**
 * The save bar inside one subject card: whether anything changed since the
 * page loaded, a reset back to the stored setup, and the one button that
 * submits this card's real form.
 */
import { computed } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';
import { format, useCopy, useWorkspace } from '../composables/usePageContext';
import { GHOST_BUTTON, PRIMARY_BUTTON } from '../styles';

const props = defineProps({
    subject: { type: Object, required: true },
});

const workspace = useWorkspace();
const copy = useCopy();

const { submittingSubjectId } = workspace;

const dirty = computed(() => workspace.isDirty(props.subject));
const submitting = computed(() => submittingSubjectId.value === props.subject.id);
const counts = computed(() => workspace.countsFor(props.subject));
const summary = computed(() => format(copy.save?.counts, {
    selected: counts.value.lessons,
    total: counts.value.totalLessons,
    grades: counts.value.grades,
    totalGrades: counts.value.totalGrades,
}));
</script>

<template>
    <div class="sticky bottom-4 z-10 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200/80 bg-white/95 p-3 shadow-sm backdrop-blur dark:border-slate-700/80 dark:bg-slate-900/95">
        <div class="min-w-0">
            <p
                class="text-xs font-semibold"
                :class="dirty ? 'text-amber-600 dark:text-amber-400' : 'text-slate-500 dark:text-slate-400'"
                role="status"
            >
                {{ dirty ? copy.save?.unsaved : copy.save?.saved }}
            </p>
            <p class="mt-0.5 text-[11px] text-slate-400 dark:text-slate-500">
                {{ summary }}
            </p>
        </div>

        <div class="flex items-center gap-2">
            <button
                v-if="dirty"
                type="button"
                :class="GHOST_BUTTON"
                @click="workspace.resetSubject(subject)"
            >
                {{ copy.save?.reset }}
            </button>

            <button type="submit" :class="PRIMARY_BUTTON" :disabled="submitting">
                <span class="h-4 w-4" aria-hidden="true"><AppIcon name="check" /></span>
                {{ submitting ? copy.save?.saving : copy.save?.save }}
            </button>
        </div>
    </div>
</template>
