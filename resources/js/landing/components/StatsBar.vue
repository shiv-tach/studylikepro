<script setup>
import { stats } from '../content';
</script>

<template>
    <section class="border-y border-slate-200/80 bg-white dark:border-slate-800/80 dark:bg-slate-900" aria-label="What you get">
        <div class="mx-auto grid w-full max-w-6xl gap-8 px-4 py-12 sm:grid-cols-2 sm:px-6 lg:grid-cols-4 lg:px-8">
            <div v-for="stat in stats" :key="stat.value" class="flex flex-col gap-1.5">
                <span class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">{{ stat.value }}</span>
                <span class="text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ stat.label }}</span>
            </div>
        </div>
    </section>
</template>
