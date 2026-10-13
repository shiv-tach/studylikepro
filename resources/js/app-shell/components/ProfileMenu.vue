<script setup>
import DropdownPanel from './DropdownPanel.vue';
import AppIcon from './AppIcon.vue';

const props = defineProps({
    user: { type: Object, required: true },
    urls: { type: Object, required: true },
    csrfToken: { type: String, required: true },
});
</script>

<template>
    <DropdownPanel align="right" panel-class="w-48 p-1">
        <template #trigger="{ open }">
            <button
                type="button"
                class="flex items-center gap-2 rounded-xl p-1.5 transition-colors hover:bg-slate-100 dark:hover:bg-slate-800"
                :aria-expanded="open"
                aria-haspopup="true"
            >
                <img
                    v-if="user.avatarUrl"
                    :src="user.avatarUrl"
                    :alt="user.name"
                    class="h-8 w-8 rounded-xl object-cover shadow-md"
                />
                <span
                    v-else
                    class="flex h-8 w-8 items-center justify-center rounded-xl bg-gradient-to-tr from-primary to-purple-500 text-sm font-semibold text-white shadow-md shadow-primary/20"
                >
                    {{ user.initials }}
                </span>
                <span class="hidden text-sm font-medium text-slate-700 dark:text-slate-300 md:block">{{ user.name }}</span>
                <span class="hidden h-4 w-4 text-slate-400 md:block"><AppIcon name="chevron-down" /></span>
            </button>
        </template>

        <a
            :href="urls.profile"
            class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-700 transition-colors hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800"
        >
            <span class="h-4 w-4"><AppIcon name="user" /></span>
            Profile
        </a>
        <a
            :href="urls.settings"
            class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-700 transition-colors hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800"
        >
            <span class="h-4 w-4"><AppIcon name="cog" /></span>
            Settings
        </a>
        <hr class="my-1 border-slate-200 dark:border-slate-800" />
        <form method="POST" :action="urls.logout">
            <input type="hidden" name="_token" :value="csrfToken" />
            <button
                type="submit"
                class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-rose-600 transition-colors hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/30"
            >
                <span class="h-4 w-4"><AppIcon name="logout" /></span>
                Log Out
            </button>
        </form>
    </DropdownPanel>
</template>
