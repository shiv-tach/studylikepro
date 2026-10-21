<script setup>
/**
 * One approved subject. The header summarises the live setup (grades, lessons,
 * default rate) and the body is the real form, kept mounted so collapsing a
 * card never loses an edit.
 */
import { computed } from 'vue';
import { usePanel } from '../../shared/composables/usePanel';
import AppIcon from '../../shared/components/AppIcon.vue';
import UiChip from '../../shared/components/UiChip.vue';
import { format, plural, useCopy, usePage, useWorkspace } from '../composables/usePageContext';
import SubjectSetupForm from './SubjectSetupForm.vue';
import { GHOST_BUTTON } from '../styles';

const props = defineProps({
    subject: { type: Object, required: true },
});

const workspace = useWorkspace();
const copy = useCopy();
const page = usePage();
const panel = usePanel();

const counts = computed(() => workspace.countsFor(props.subject));
const open = computed(() => workspace.isOpen(props.subject));
const dirty = computed(() => workspace.isDirty(props.subject));

const defaultRateLabel = computed(() => (props.subject.defaultRate
    ? format(copy.subject?.defaultRate, {
        rate: `${page.teacher?.currencySymbol ?? ''} ${props.subject.defaultRate}`.trim(),
    })
    : copy.subject?.noDefaultRate));
</script>

<template>
    <section
        :id="`subject-setup-${subject.id}`"
        :class="panel"
        class="scroll-mt-24"
    >
        <div class="flex flex-wrap items-start justify-between gap-3">
            <button
                type="button"
                class="group flex min-w-0 flex-1 items-start gap-3 rounded-xl text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
                :aria-expanded="open ? 'true' : 'false'"
                :aria-controls="`subject-body-${subject.id}`"
                @click="workspace.toggleSubject(subject)"
            >
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-xl" aria-hidden="true">
                    {{ subject.icon }}
                </span>

                <span class="min-w-0">
                    <span class="flex flex-wrap items-center gap-2">
                        <span class="text-base font-bold text-slate-800 dark:text-slate-100">{{ subject.name }}</span>
                        <UiChip v-if="subject.levelName" tone="slate">{{ subject.levelName }}</UiChip>
                        <UiChip v-if="dirty" tone="amber">{{ copy.save?.unsaved }}</UiChip>
                    </span>

                    <span class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                        <span>{{ format(plural(counts.grades, copy.subject?.gradesCountOne, copy.subject?.gradesCount), { selected: counts.grades, total: counts.totalGrades }) }}</span>
                        <span aria-hidden="true">·</span>
                        <span>{{ format(plural(counts.lessons, copy.subject?.lessonsCountOne, copy.subject?.lessonsCount), { selected: counts.lessons }) }}</span>
                        <span aria-hidden="true">·</span>
                        <span>{{ defaultRateLabel }}</span>
                    </span>
                </span>
            </button>

            <div class="flex flex-wrap items-center gap-2">
                <UiChip v-if="!counts.grades" tone="amber" icon="alert">{{ copy.subject?.needGrades }}</UiChip>

                <button
                    type="button"
                    :class="GHOST_BUTTON"
                    :aria-expanded="open ? 'true' : 'false'"
                    :aria-controls="`subject-body-${subject.id}`"
                    @click="workspace.toggleSubject(subject)"
                >
                    {{ open ? copy.subject?.hide : copy.subject?.edit }}
                    <span
                        class="h-4 w-4 transition-transform duration-200"
                        :class="open ? 'rotate-180' : ''"
                        aria-hidden="true"
                    >
                        <AppIcon name="chevron-down" />
                    </span>
                </button>
            </div>
        </div>

        <div v-show="open" :id="`subject-body-${subject.id}`">
            <SubjectSetupForm :subject="subject" />
        </div>
    </section>
</template>
