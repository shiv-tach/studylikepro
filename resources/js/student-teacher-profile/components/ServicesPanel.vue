<script setup>
/**
 * The services this teacher sells, grouped by service type the way the teacher's
 * own console lists them. The action on each row is whatever Blade decided the
 * student may do with it: buy it, or start a marking order for it.
 */
import { usePanel } from '../../shared/composables/usePanel';
import UiChip from '../../shared/components/UiChip.vue';
import UiSectionHeading from '../../shared/components/UiSectionHeading.vue';
import { useCopy, useCsrfToken } from '../composables/usePageContext';

defineProps({
    groups: { type: Array, default: () => [] },
});

const copy = useCopy();
const csrfToken = useCsrfToken();
const panel = usePanel();

const primaryClass =
    'flex w-full items-center justify-center rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-md shadow-primary/20 transition hover:bg-primary/90';

const outlineClass =
    'flex w-full items-center justify-center rounded-xl border border-slate-200/80 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:border-primary/40 hover:text-primary dark:border-slate-800 dark:text-slate-300';

const actionClass = (action) => (action.variant === 'outline' ? outlineClass : primaryClass);
</script>

<template>
    <section :class="panel">
        <UiSectionHeading :title="copy.servicesTitle" icon="tag">
            <template #action>
                <UiChip>{{ copy.servicesTagline }}</UiChip>
            </template>
        </UiSectionHeading>

        <div class="mt-5 space-y-6">
            <div
                v-for="(group, index) in groups"
                :key="group.key"
                :class="index > 0 ? 'border-t border-slate-200/80 pt-5 dark:border-slate-800' : ''"
            >
                <div class="flex flex-wrap items-center gap-2">
                    <span
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-base"
                        aria-hidden="true"
                    >
                        {{ group.icon }}
                    </span>
                    <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ group.label }}</h3>
                    <span
                        class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400"
                    >
                        {{ group.items.length }}
                    </span>
                </div>

                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <article
                        v-for="offering in group.items"
                        :key="offering.id"
                        class="flex flex-col rounded-xl border border-slate-200/80 p-4 transition-all duration-300 hover:border-primary/40 hover:shadow-md dark:border-slate-800"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-x-3 gap-y-1">
                            <p class="min-w-0 break-words text-sm font-bold text-slate-800 dark:text-slate-100">
                                {{ offering.title }}
                            </p>
                            <p class="ml-auto shrink-0 whitespace-nowrap text-sm font-extrabold text-slate-800 dark:text-slate-100">
                                {{ offering.price }}
                            </p>
                        </div>

                        <p v-if="offering.metaLabel" class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                            {{ offering.metaLabel }}
                        </p>

                        <div class="mt-auto pt-3">
                            <form
                                v-if="offering.action.kind === 'form'"
                                method="POST"
                                :action="offering.action.url"
                            >
                                <input type="hidden" name="_token" :value="csrfToken">
                                <button type="submit" :class="actionClass(offering.action)">
                                    {{ offering.action.label }}
                                </button>
                            </form>

                            <a v-else :href="offering.action.url" :class="actionClass(offering.action)">
                                {{ offering.action.label }}
                            </a>
                        </div>
                    </article>
                </div>
            </div>
        </div>
    </section>
</template>
