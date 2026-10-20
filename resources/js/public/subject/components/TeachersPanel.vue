<script setup>
import { computed } from 'vue';
import AppIcon from '../../../landing/components/AppIcon.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

const copy = computed(() => props.config.copy ?? {});
const teachers = computed(() => props.config.teachers ?? {});
const items = computed(() => teachers.value.items ?? []);
const showAll = computed(() => (teachers.value.total ?? 0) > items.value.length);
</script>

<template>
    <section
        id="subject-panel-teachers"
        role="tabpanel"
        aria-labelledby="subject-tab-teachers"
        tabindex="0"
        class="mx-auto w-full max-w-6xl px-5 py-12 focus:outline-none lg:py-16"
    >
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="font-display text-2xl font-semibold text-ink">{{ copy.teachersTitle }}</h2>
                <p v-if="teachers.total" class="mt-2 text-sm text-ink-500">{{ copy.teachersAvailable }}</p>
            </div>
            <a
                v-if="showAll"
                :href="teachers.directoryUrl"
                class="inline-flex items-center gap-1.5 text-sm font-medium text-brand-600 transition hover:text-brand-700"
            >
                {{ copy.seeAllTeachers }}
                <AppIcon name="arrow-right" class="h-4 w-4" />
            </a>
        </div>

        <div v-if="!items.length" class="hairline mt-6 rounded-2xl bg-white p-10 text-center">
            <p class="text-sm text-ink-500">{{ copy.teachersEmpty }}</p>
            <a
                :href="teachers.directoryRootUrl"
                class="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-brand-600 transition hover:text-brand-700"
            >
                {{ copy.browseAllTeachers }}
                <AppIcon name="arrow-right" class="h-4 w-4" />
            </a>
        </div>

        <div v-else class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <article
                v-for="teacher in items"
                :key="teacher.id"
                class="hairline lift group relative flex flex-col rounded-2xl bg-white p-5"
            >
                <a :href="teacher.url" class="absolute inset-0 rounded-2xl focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40">
                    <span class="sr-only">{{ teacher.name }}</span>
                </a>

                <img
                    v-if="teacher.avatarUrl"
                    :src="teacher.avatarUrl"
                    :alt="teacher.name"
                    class="size-12 rounded-xl object-cover"
                />
                <span
                    v-else
                    class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-base font-semibold text-brand-600"
                    aria-hidden="true"
                >{{ teacher.initials }}</span>

                <h3 class="mt-3 truncate text-base font-semibold text-ink">{{ teacher.name }}</h3>

                <div class="mt-2 flex flex-wrap items-center gap-2 text-[11px] font-semibold">
                    <span v-if="teacher.rating" class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2.5 py-0.5 text-brand-600">
                        ★ {{ teacher.rating }}
                        <span class="font-medium text-brand-600/70">({{ teacher.ratingCount }})</span>
                    </span>
                    <span v-else class="rounded-full bg-ink-50 px-2.5 py-0.5 text-ink-500">{{ copy.newTeacher }}</span>
                    <span class="inline-flex items-center gap-1 rounded-full bg-go/10 px-2.5 py-0.5 text-go-ink">
                        <AppIcon name="check" class="h-3.5 w-3.5" />
                        {{ copy.verified }}
                    </span>
                </div>

                <p v-if="teacher.headline" class="mt-2 line-clamp-2 text-sm leading-relaxed text-ink-700">{{ teacher.headline }}</p>
                <p v-if="teacher.metaLabel" class="mt-3 text-xs font-medium text-ink-500">{{ teacher.metaLabel }}</p>

                <div class="mt-auto flex items-end justify-between gap-3 pt-5">
                    <div>
                        <p class="text-lg font-semibold text-ink">
                            <span v-if="teacher.rateFrom" class="text-xs font-medium text-ink-400">{{ copy.rateFrom }} </span>{{ teacher.rate }}<span class="text-sm font-medium text-ink-400">{{ copy.perHour }}</span>
                        </p>
                        <p class="mt-0.5 text-xs text-ink-500">
                            <template v-if="teacher.nextSlot">{{ copy.nextLabel }} {{ teacher.nextSlot }}</template>
                            <template v-else>{{ copy.noSlots }}</template>
                        </p>
                    </div>

                    <a
                        v-if="teacher.cta.url"
                        :href="teacher.cta.url"
                        class="relative z-10 inline-flex shrink-0 items-center gap-1 rounded-full bg-brand-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-600"
                    >{{ teacher.cta.label }}</a>
                    <span v-else class="relative z-10 inline-flex shrink-0 items-center gap-1 text-sm font-medium text-brand-600">
                        {{ teacher.cta.label }}
                        <AppIcon name="arrow-right" class="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                    </span>
                </div>
            </article>
        </div>
    </section>
</template>
