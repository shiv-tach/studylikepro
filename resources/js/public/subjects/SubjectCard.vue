<script setup>
import { isPlainClick } from '../client-navigation';
import AppIcon from '../../landing/components/AppIcon.vue';

const props = defineProps({
    subject: { type: Object, required: true },
    copy: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['open']);

function open(event) {
    if (!isPlainClick(event)) {
        return;
    }

    event.preventDefault();
    emit('open', { event, url: props.subject.url });
}
</script>

<template>
    <a
        :href="subject.url"
        class="lift hairline group flex flex-col rounded-2xl bg-white p-6"
        @click="open"
    >
        <div class="flex items-start justify-between gap-3">
            <span
                class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-2xl transition-colors group-hover:bg-brand-500 group-hover:text-white"
                aria-hidden="true"
            >{{ subject.icon }}</span>
            <span class="rounded-full bg-ink-50 px-2.5 py-0.5 text-[11px] font-semibold text-ink-500">
                {{ subject.lessonLabel }}
            </span>
        </div>

        <h3 class="mt-4 font-display text-lg font-semibold text-ink">{{ subject.name }}</h3>
        <p v-if="subject.description" class="mt-1.5 line-clamp-2 text-sm leading-relaxed text-ink-700">
            {{ subject.description }}
        </p>

        <span class="mt-auto inline-flex items-center gap-1.5 pt-5 text-sm font-medium text-brand-600">
            {{ copy.viewLessons }}
            <AppIcon name="arrow-right" class="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
        </span>
    </a>
</template>
