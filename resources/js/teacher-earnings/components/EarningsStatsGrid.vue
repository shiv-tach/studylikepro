<script setup>
/**
 * Where the money sits, in four tiles. The order is deliberate: available
 * first (the only one a teacher acts on), then what is still clearing, then
 * what has already been transferred, and lifetime earned last as context.
 */
import { computed } from 'vue';
import { useCopy, usePage } from '../composables/usePageContext';
import EarningStatTile from './EarningStatTile.vue';

const copy = useCopy();
const page = usePage();

const tiles = computed(() => page.stats?.tiles ?? []);
</script>

<template>
    <ul :aria-label="copy.tiles?.label" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <li v-for="tile in tiles" :key="tile.key" class="h-full">
            <EarningStatTile :tile="tile" />
        </li>
    </ul>
</template>
