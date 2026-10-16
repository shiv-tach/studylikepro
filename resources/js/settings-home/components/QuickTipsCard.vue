<script setup>
/**
 * The closing "Quick tips" panel. Each tip is one sentence with a single
 * emphasised phrase, which Blade splits into `prefix` / `strong` / `suffix` so
 * the wording (and its translation) never has to be assembled in JavaScript.
 */
import AppIcon from '../../shared/components/AppIcon.vue';

defineProps({
    title: { type: String, required: true },
    icon: { type: String, default: 'sparkles' },
    tips: { type: Array, default: () => [] },
});
</script>

<template>
    <div class="rounded-2xl border border-slate-200 bg-gradient-to-br from-primary/5 to-transparent p-6 dark:border-slate-800 dark:from-primary/10 dark:to-transparent">
        <div class="flex items-start gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/20 p-2.5 text-primary">
                <AppIcon :name="icon" />
            </div>
            <div>
                <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ title }}</h4>
                <ul class="mt-2 space-y-1.5 text-sm text-slate-500 dark:text-slate-400">
                    <li v-for="(tip, index) in tips" :key="index" class="flex items-start gap-2">
                        <span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-primary" aria-hidden="true"></span>
                        <span>
                            {{ tip.prefix }}<strong class="text-slate-700 dark:text-slate-300">{{ tip.strong }}</strong>{{ tip.suffix }}
                        </span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
