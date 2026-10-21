<script setup>
/**
 * "What we still need" — the one place that answers, at a glance, whether the
 * application can be sent: a progress bar plus one line per requirement with its
 * live state and a jump link to whatever is missing. Everything on it is
 * computed from the page's own state, so a document uploaded a second ago has
 * already ticked its line.
 */
import { usePanel } from '../../shared/composables/usePanel';
import AppIcon from '../../shared/components/AppIcon.vue';
import UiChip from '../../shared/components/UiChip.vue';
import UiSectionHeading from '../../shared/components/UiSectionHeading.vue';
import { useCopy } from '../composables/usePageContext';
import { useVerification } from '../composables/useVerification';
import { DONE_RING, EMPTY_RING } from '../styles';

const copy = useCopy();
const panel = usePanel();

const { requirements, progress, canEdit } = useVerification();
</script>

<template>
    <section :class="panel">
        <UiSectionHeading :title="copy.checklistTitle" :description="copy.checklistHint" icon="clipboard" level="h3" />

        <div class="mt-5">
            <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-wider text-slate-400">
                <span>{{ copy.checklistProgress }}</span>
                <span class="tabular-nums">{{ progress.done }} / {{ progress.total }}</span>
            </div>

            <div
                class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                role="progressbar"
                :aria-label="copy.checklistTitle"
                :aria-valuenow="progress.percent"
                aria-valuemin="0"
                aria-valuemax="100"
            >
                <div
                    class="h-full rounded-full bg-primary transition-all duration-500 ease-out motion-reduce:transition-none"
                    :style="{ width: `${progress.percent}%` }"
                ></div>
            </div>
        </div>

        <ul class="mt-5 space-y-3">
            <li v-for="item in requirements" :key="item.key" class="flex items-start gap-2.5">
                <span
                    class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border"
                    :class="item.done ? DONE_RING : EMPTY_RING"
                >
                    <span v-if="item.done" class="h-3 w-3 text-emerald-600 dark:text-emerald-400">
                        <AppIcon name="check" />
                    </span>
                </span>

                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span class="text-sm font-semibold" :class="item.done ? 'text-slate-700 dark:text-slate-200' : 'text-slate-800 dark:text-slate-100'">
                            {{ item.label }}
                        </span>
                        <UiChip :tone="item.done ? 'emerald' : 'amber'">
                            {{ item.done ? copy.checklistDone : copy.checklistTodo }}
                        </UiChip>
                    </div>

                    <p class="mt-0.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ item.hint }}</p>

                    <a
                        v-if="item.action"
                        :href="item.action.href"
                        class="mt-1 inline-flex items-center gap-1 text-xs font-bold text-primary hover:underline"
                    >
                        {{ item.action.label }}
                    </a>
                </div>
            </li>
        </ul>

        <p v-if="canEdit" class="mt-5 border-t border-slate-200/80 pt-3 text-xs leading-relaxed text-slate-400 dark:border-slate-800/80">
            {{ copy.checklistTip }}
        </p>
        <p v-else class="mt-5 flex items-start gap-2 border-t border-slate-200/80 pt-3 text-xs leading-relaxed text-slate-400 dark:border-slate-800/80">
            <span class="mt-0.5 h-3.5 w-3.5 shrink-0"><AppIcon name="lock" /></span>
            {{ copy.checklistLocked }}
        </p>
    </section>
</template>
