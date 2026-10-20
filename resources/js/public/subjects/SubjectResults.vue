<script setup>
import SubjectCard from './SubjectCard.vue';

defineProps({
    groups: { type: Array, default: () => [] },
    copy: { type: Object, default: () => ({}) },
});

defineEmits(['open']);
</script>

<template>
    <div class="space-y-10">
        <section v-for="group in groups" :key="group.key" :aria-label="group.title || undefined">
            <div v-if="group.title" class="flex flex-wrap items-center gap-2.5">
                <h2 class="font-display text-xl font-semibold text-ink">{{ group.title }}</h2>
                <span
                    class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold tracking-wide uppercase"
                    :class="group.pickOne
                        ? 'bg-brand-50 text-brand-600'
                        : 'bg-white text-ink-500'"
                >{{ group.badge }}</span>
            </div>
            <p v-if="group.description" class="mt-1 text-sm text-ink-500">{{ group.description }}</p>

            <div class="mt-4 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <SubjectCard
                    v-for="subject in group.subjects"
                    :key="subject.name"
                    :subject="subject"
                    :copy="copy"
                    @open="$emit('open', $event)"
                />
            </div>
        </section>
    </div>
</template>
