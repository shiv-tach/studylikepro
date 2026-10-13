<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    delay: { type: Number, default: 0 },
});

const element = ref(null);
const revealed = ref(false);
let observer = null;

onMounted(() => {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (reducedMotion || typeof IntersectionObserver === 'undefined') {
        revealed.value = true;

        return;
    }

    observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }

                revealed.value = true;
                observer.disconnect();
            });
        },
        { rootMargin: '0px 0px -10% 0px', threshold: 0.05 },
    );

    observer.observe(element.value);
});

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <div
        ref="element"
        class="transition duration-500 ease-out motion-reduce:transition-none"
        :class="revealed ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0'"
        :style="{ transitionDelay: `${delay}ms` }"
    >
        <slot />
    </div>
</template>
