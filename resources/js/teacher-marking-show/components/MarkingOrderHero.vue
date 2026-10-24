<script setup>
/**
 * The band above the order: what state it is in, what the teacher has to do
 * next and the facts that decide it. Blade sends the headline and guidance per
 * status, so the page never re-derives the workflow in JavaScript.
 */
import { computed } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';
import UiChip from '../../shared/components/UiChip.vue';
import { useCopy, usePage } from '../composables/usePageContext';

const copy = useCopy();
const page = usePage();

const order = computed(() => page.order ?? {});

const meta = computed(() => [
    { key: 'student', label: copy.hero?.student, value: order.value.studentName },
    { key: 'pages', label: copy.hero?.pages, value: order.value.pagesLabel },
    { key: 'submitted', label: copy.hero?.submitted, value: order.value.submittedLabel },
    { key: 'deadline', label: copy.hero?.deadline, value: order.value.deadlineLabel },
    { key: 'price', label: copy.hero?.price, value: order.value.priceLabel },
    { key: 'paid', label: copy.hero?.paid, value: order.value.paidLabel },
].filter((item) => Boolean(item.value)));
</script>

<template>
    <section
        aria-labelledby="teacher-marking-order-hero"
        class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-primary via-primary/80 to-purple-600 p-6 text-white shadow-lg shadow-primary/10 dark:shadow-primary/5 sm:p-8"
    >
        <div class="relative z-10">
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold backdrop-blur-sm">
                    <span aria-hidden="true">{{ order.subjectIcon }}</span>
                    {{ order.subjectName }}
                </span>

                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold backdrop-blur-sm">
                    <span class="h-3.5 w-3.5 shrink-0" aria-hidden="true"><AppIcon :name="order.status?.icon ?? 'clipboard'" /></span>
                    {{ order.status?.label }}
                </span>

                <span
                    v-if="order.overdue"
                    class="inline-flex items-center gap-1.5 rounded-full bg-rose-500/90 px-3 py-1 text-xs font-semibold"
                >
                    <span class="h-3.5 w-3.5 shrink-0" aria-hidden="true"><AppIcon name="alert" /></span>
                    {{ copy.hero?.overdue }}
                </span>

                <span class="rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-white/80">
                    {{ order.reference }}
                </span>
            </div>

            <p class="mt-4 text-xs font-semibold uppercase tracking-[0.2em] text-white/70">
                {{ copy.hero?.eyebrow }}
            </p>
            <h1 id="teacher-marking-order-hero" class="mt-1 text-2xl font-extrabold tracking-tight sm:text-3xl">
                {{ order.headline }}
            </h1>
            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-white/90">
                {{ order.guidance }}
            </p>

            <dl v-if="meta.length" class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3" :aria-label="copy.hero?.metaLabel">
                <div
                    v-for="item in meta"
                    :key="item.key"
                    class="rounded-xl border border-white/20 bg-white/10 px-3 py-2 backdrop-blur-sm"
                >
                    <dt class="text-[10px] font-semibold uppercase tracking-wider text-white/60">{{ item.label }}</dt>
                    <dd class="mt-0.5 truncate text-sm font-semibold">{{ item.value }}</dd>
                </div>
            </dl>

            <div v-if="order.orderUrl" class="mt-5">
                <a
                    :href="order.orderUrl"
                    class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2 text-sm font-bold text-primary shadow-sm shadow-black/10 transition-colors hover:bg-white/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/70"
                >
                    {{ copy.hero?.openOrder }}
                    <span class="h-4 w-4" aria-hidden="true"><AppIcon name="arrow-right" /></span>
                </a>
            </div>
        </div>

        <div class="pointer-events-none absolute -right-6 -top-6 h-36 w-36 rounded-full bg-white/10 blur-xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-10 right-24 h-28 w-28 rounded-full bg-white/10 blur-lg" aria-hidden="true"></div>
    </section>
</template>
