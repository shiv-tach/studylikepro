<script setup>
import { computed } from 'vue';

/**
 * The teacher's own words and the three facts a parent checks before booking.
 * Everything here was formatted by Blade, so this panel never guesses a label.
 */
const props = defineProps({
    teacher: { type: Object, required: true },
    copy: { type: Object, required: true },
});

const facts = computed(() => (props.teacher.facts ?? []).filter((fact) => fact.value));
const hasBody = computed(() => Boolean(props.teacher.bio) || facts.value.length > 0);
</script>

<template>
    <section v-if="hasBody" class="hairline rounded-2xl bg-white p-6">
        <h2 class="font-display text-xl font-semibold">
            {{ copy.aboutTitle }} {{ teacher.name }}
        </h2>

        <p v-if="teacher.bio" class="mt-3 whitespace-pre-line text-sm leading-relaxed text-ink-700">{{ teacher.bio }}</p>

        <dl v-if="facts.length" class="mt-5 grid gap-4 border-t border-line pt-5 text-sm sm:grid-cols-3">
            <div v-for="fact in facts" :key="fact.label">
                <dt class="text-[10px] font-semibold tracking-widest text-ink-400 uppercase">{{ fact.label }}</dt>
                <dd class="mt-1 text-ink-700">{{ fact.value }}</dd>
            </div>
        </dl>
    </section>
</template>
