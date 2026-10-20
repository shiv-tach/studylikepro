<script setup>
import { computed } from 'vue';
import AppIcon from '../../../landing/components/AppIcon.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

const copy = computed(() => props.config.copy ?? {});
const groups = computed(() => props.config.serviceGroups ?? []);
const directoryUrl = computed(() => props.config.teachers?.directoryUrl ?? props.config.urls?.teachers);

const buttonClass = (action) => (action.variant === 'outline'
    ? 'hairline bg-white text-ink-700 hover:border-ink-400 hover:text-ink'
    : 'bg-brand-500 text-white hover:bg-brand-600');
</script>

<template>
    <section
        id="subject-panel-services"
        role="tabpanel"
        aria-labelledby="subject-tab-services"
        tabindex="0"
        class="mx-auto w-full max-w-6xl px-5 py-12 focus:outline-none lg:py-16"
    >
        <h2 class="font-display text-2xl font-semibold text-ink">{{ copy.servicesTitle }}</h2>
        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-ink-500">{{ copy.servicesIntro }}</p>

        <div v-if="!groups.length" class="hairline mt-6 rounded-2xl bg-white p-10 text-center">
            <p class="text-sm text-ink-500">{{ copy.servicesEmpty }}</p>
            <a
                :href="directoryUrl"
                class="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-brand-600 transition hover:text-brand-700"
            >
                {{ copy.browseTeachers }}
                <AppIcon name="arrow-right" class="h-4 w-4" />
            </a>
        </div>

        <div v-else class="mt-8 space-y-10">
            <div v-for="group in groups" :key="group.key">
                <div class="flex flex-wrap items-center gap-2.5">
                    <span class="flex size-9 items-center justify-center rounded-xl bg-brand-50 text-base" aria-hidden="true">{{ group.icon }}</span>
                    <h3 class="font-display text-lg font-semibold text-ink">{{ group.label }}</h3>
                    <span class="rounded-full bg-ink-50 px-2.5 py-0.5 text-[11px] font-semibold text-ink-500">{{ group.items.length }}</span>
                </div>

                <div class="mt-4 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <article
                        v-for="item in group.items"
                        :key="item.id"
                        class="hairline lift flex flex-col rounded-2xl bg-white p-5"
                    >
                        <div class="flex items-center gap-3">
                            <img
                                v-if="item.teacher.avatarUrl"
                                :src="item.teacher.avatarUrl"
                                :alt="item.teacher.name"
                                class="size-11 shrink-0 rounded-xl object-cover"
                            />
                            <span
                                v-else
                                class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-sm font-semibold text-brand-600"
                                aria-hidden="true"
                            >{{ item.teacher.initials }}</span>

                            <a :href="item.teacher.url" class="truncate text-sm font-semibold text-brand-600 transition hover:text-brand-700">
                                {{ item.teacher.name }}
                            </a>
                        </div>

                        <div class="mt-4 flex items-start justify-between gap-3">
                            <h4 class="text-sm font-semibold text-ink">{{ item.title }}</h4>
                            <p class="shrink-0 text-sm font-semibold whitespace-nowrap text-ink">{{ item.price }}</p>
                        </div>

                        <ul v-if="item.chips.length" class="mt-3 flex flex-wrap gap-1.5">
                            <li
                                v-for="chip in item.chips"
                                :key="chip"
                                class="rounded-full bg-ink-50 px-2.5 py-0.5 text-[11px] font-medium text-ink-500"
                            >{{ chip }}</li>
                        </ul>

                        <div class="mt-auto pt-5">
                            <form v-if="item.action.kind === 'form'" method="POST" :action="item.action.url">
                                <input type="hidden" name="_token" :value="config.csrfToken" />
                                <button
                                    type="submit"
                                    class="w-full rounded-full px-4 py-2.5 text-sm font-medium transition"
                                    :class="buttonClass(item.action)"
                                >{{ item.action.label }}</button>
                            </form>
                            <a
                                v-else
                                :href="item.action.url"
                                class="flex w-full items-center justify-center rounded-full px-4 py-2.5 text-sm font-medium transition"
                                :class="buttonClass(item.action)"
                            >{{ item.action.label }}</a>
                        </div>
                    </article>
                </div>
            </div>
        </div>
    </section>
</template>
