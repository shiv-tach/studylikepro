<script setup>
import { computed } from 'vue';
import { sidebarClass, sidebarDividerClass, sidebarFooterSurfaceClass } from '../theme';
import { useShell } from '../composables/useShell';
import AppIcon from './AppIcon.vue';
import AppLogo from './AppLogo.vue';
import SidebarNavItem from './SidebarNavItem.vue';

const props = defineProps({
    navItems: { type: Array, default: () => [] },
    user: { type: Object, required: true },
    dashboardUrl: { type: String, required: true },
    logoUrl: { type: String, required: true },
    logoDarkUrl: { type: String, required: true },
    appName: { type: String, default: 'Study Like Pro' },
});

const { preset, sidebarOpen, closeSidebar } = useShell();

const asideClass = computed(() => sidebarClass(preset.value));
const dividerClass = computed(() => sidebarDividerClass(preset.value));
const footerSurface = computed(() => sidebarFooterSurfaceClass(preset.value));
const nameClass = computed(() => (preset.value === 'midnight' ? 'text-slate-200' : 'text-slate-700 dark:text-slate-300'));
</script>

<template>
    <aside
        :class="[
            asideClass,
            'fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r transition-all duration-300 ease-in-out md:translate-x-0',
            sidebarOpen ? 'translate-x-0' : '-translate-x-full',
        ]"
    >
        <!-- Brand + mobile close -->
        <div :class="[dividerClass, 'flex h-16 items-center justify-between border-b px-6']">
            <a :href="dashboardUrl" class="flex items-center">
                <AppLogo v-if="preset === 'midnight'" :src="logoDarkUrl" :alt="appName" class="h-8 w-auto" />
                <template v-else>
                    <AppLogo :src="logoUrl" :alt="appName" class="h-8 w-auto dark:hidden" />
                    <AppLogo :src="logoDarkUrl" :alt="appName" class="hidden h-8 w-auto dark:block" />
                </template>
            </a>

            <button
                type="button"
                class="rounded-lg p-1 text-slate-500 transition-colors hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 md:hidden"
                aria-label="Close navigation"
                @click="closeSidebar"
            >
                <span class="block h-5 w-5"><AppIcon name="close" /></span>
            </button>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 space-y-1.5 overflow-y-auto px-4 py-6" aria-label="Primary" @click="closeSidebar">
            <SidebarNavItem v-for="item in navItems" :key="item.label" :item="item" />
        </nav>

        <!-- Signed-in user -->
        <div :class="[dividerClass, 'border-t p-4']">
            <div :class="[footerSurface, 'flex items-center gap-3 rounded-xl p-3']">
                <img
                    v-if="user.avatarUrl"
                    :src="user.avatarUrl"
                    :alt="user.name"
                    class="h-9 w-9 rounded-xl object-cover shadow-md"
                />
                <span
                    v-else
                    class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary text-sm font-bold text-white shadow-md shadow-primary/20"
                >
                    {{ user.initials }}
                </span>
                <div class="flex-1 overflow-hidden">
                    <h4 :class="[nameClass, 'truncate text-sm font-semibold']">{{ user.name }}</h4>
                    <span class="mt-0.5 inline-flex items-center rounded-full bg-primary/10 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-primary">
                        {{ user.roleLabel }}
                    </span>
                </div>
            </div>
        </div>
    </aside>
</template>
