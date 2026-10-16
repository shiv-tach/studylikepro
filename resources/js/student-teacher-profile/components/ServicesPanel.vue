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

const actionClass =
    'inline-block rounded-lg bg-primary/10 px-2.5 py-1 text-[11px] font-semibold text-primary transition-colors hover:bg-primary hover:text-white';
</script>

<template>
    <section :class="panel">
        <UiSectionHeading :title="copy.servicesTitle" icon="tag">
            <template #action>
                <UiChip>{{ copy.servicesTagline }}</UiChip>
            </template>
        </UiSectionHeading>

        <div class="mt-4 space-y-4">
            <div v-for="group in groups" :key="group.key">
                <p class="text-xs font-semibold uppercase tracking-wider text-primary">
                    {{ group.icon }} {{ group.label }}
                </p>

                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    <div
                        v-for="offering in group.items"
                        :key="offering.id"
                        class="flex items-center justify-between gap-3 rounded-xl border border-slate-200/80 p-3 dark:border-slate-800"
                    >
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-700 dark:text-slate-200">
                                {{ offering.title }}
                            </p>
                            <p v-if="offering.metaLabel" class="mt-0.5 text-xs text-slate-400">
                                {{ offering.metaLabel }}
                            </p>
                        </div>

                        <div class="shrink-0 text-right">
                            <p class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ offering.price }}</p>

                            <form
                                v-if="offering.action.kind === 'form'"
                                method="POST"
                                :action="offering.action.url"
                                class="mt-1"
                            >
                                <input type="hidden" name="_token" :value="csrfToken">
                                <button type="submit" :class="actionClass">{{ offering.action.label }}</button>
                            </form>

                            <a v-else :href="offering.action.url" :class="[actionClass, 'mt-1']">
                                {{ offering.action.label }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
