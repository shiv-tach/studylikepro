<script setup>
/**
 * The workspace breadcrumb rail: a hierarchy trail (Dashboard → My subjects →
 * Subject) so a student can step back up the tree they came from without
 * hunting for the sidebar. Renders a semantic nav/ol; on narrow screens the
 * trail scrolls sideways instead of wrapping into a confusing multi-line blob.
 */
import AppIcon from './AppIcon.vue';

defineProps({
    /**
     * The trail, outermost first. The last item is the current page and renders
     * as plain text with `aria-current="page"`; give every other item a `url`.
     *
     * @type {{ label: string, url: string|null }[]}
     */
    items: { type: Array, default: () => [] },
});
</script>

<template>
    <nav v-if="items.length" aria-label="Breadcrumb" class="min-w-0">
        <ol
            class="flex items-center gap-0.5 overflow-x-auto whitespace-nowrap py-0.5 text-sm [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
        >
            <li
                v-for="(item, index) in items"
                :key="item.label"
                class="flex min-w-0 shrink-0 items-center gap-0.5"
            >
                <span
                    v-if="index > 0"
                    class="h-3.5 w-3.5 shrink-0 text-slate-300 dark:text-slate-600"
                    aria-hidden="true"
                >
                    <AppIcon name="chevron-right" />
                </span>

                <a
                    v-if="item.url"
                    :href="item.url"
                    class="rounded-md px-2 py-1.5 font-semibold text-slate-500 transition-colors hover:bg-slate-100 hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 dark:text-slate-400 dark:hover:bg-slate-800"
                >
                    {{ item.label }}
                </a>
                <span
                    v-else
                    aria-current="page"
                    class="max-w-[55vw] truncate px-2 py-1.5 font-semibold text-slate-800 dark:text-slate-100 sm:max-w-xs"
                >
                    {{ item.label }}
                </span>
            </li>
        </ol>
    </nav>
</template>
