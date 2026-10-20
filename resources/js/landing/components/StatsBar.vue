<script setup>
import { boards } from '../content';
</script>

<template>
    <section class="border-b border-line bg-white py-8" aria-label="Grades and subjects covered on Studylikepro">
        <div class="lp-marquee relative overflow-hidden">
            <div class="lp-marquee-track flex w-max items-center">
                <ul class="flex shrink-0 items-center gap-6 pr-6 sm:gap-9 sm:pr-9">
                    <li
                        v-for="board in boards"
                        :key="board"
                        class="flex h-8 items-center gap-2 whitespace-nowrap text-sm font-semibold text-ink-500 sm:h-9"
                    >
                        <span class="size-1.5 rounded-full bg-brand-300" aria-hidden="true"></span>
                        {{ board }}
                    </li>
                </ul>
                <ul class="flex shrink-0 items-center gap-6 pr-6 sm:gap-9 sm:pr-9" aria-hidden="true">
                    <li
                        v-for="board in boards"
                        :key="`dup-${board}`"
                        class="flex h-8 items-center gap-2 whitespace-nowrap text-sm font-semibold text-ink-500 sm:h-9"
                    >
                        <span class="size-1.5 rounded-full bg-brand-300" aria-hidden="true"></span>
                        {{ board }}
                    </li>
                </ul>
            </div>
        </div>
    </section>
</template>
