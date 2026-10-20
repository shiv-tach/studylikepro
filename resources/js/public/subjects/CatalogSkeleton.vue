<template>
    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3" aria-hidden="true">
        <div v-for="index in 6" :key="index" class="hairline animate-pulse rounded-2xl bg-white p-6">
            <div class="flex items-start justify-between">
                <div class="size-12 rounded-xl bg-ink-100"></div>
                <div class="h-4 w-16 rounded-full bg-ink-100"></div>
            </div>
            <div class="mt-5 h-4 w-2/3 rounded-full bg-ink-100"></div>
            <div class="mt-3 h-3 w-full rounded-full bg-ink-100"></div>
            <div class="mt-2 h-3 w-4/5 rounded-full bg-ink-100"></div>
            <div class="mt-8 h-3 w-24 rounded-full bg-ink-100"></div>
        </div>
    </div>
</template>
