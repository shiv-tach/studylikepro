<script setup>
import DropdownPanel from './DropdownPanel.vue';
import AppIcon from './AppIcon.vue';

const props = defineProps({
    notifications: { type: Object, default: () => ({ unreadCount: 0, items: [] }) },
    urls: { type: Object, required: true },
    csrfToken: { type: String, required: true },
});

const openUrl = (id) => props.urls.notificationOpen.replace('NOTIFICATION_ID', String(id));
</script>

<template>
    <DropdownPanel align="right" panel-class="w-80 sm:w-96">
        <template #trigger="{ open }">
            <button
                type="button"
                class="relative rounded-xl p-2 text-slate-500 transition-colors hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"
                :aria-expanded="open"
                aria-haspopup="true"
                aria-label="Notifications"
            >
                <span class="block h-5 w-5"><AppIcon name="bell" /></span>
                <span
                    v-if="notifications.unreadCount > 0"
                    class="absolute -right-0.5 -top-0.5 inline-flex min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 py-0.5 text-[10px] font-bold text-white"
                >
                    {{ notifications.unreadCount > 9 ? '9+' : notifications.unreadCount }}
                </span>
            </button>
        </template>

        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-slate-800">
            <p class="text-sm font-bold text-slate-800 dark:text-slate-100">Notifications</p>
            <form v-if="notifications.unreadCount > 0" method="POST" :action="urls.readAll">
                <input type="hidden" name="_token" :value="csrfToken" />
                <button type="submit" class="text-xs font-semibold text-primary hover:underline">Mark all read</button>
            </form>
        </div>

        <div class="max-h-80 overflow-y-auto">
            <template v-if="notifications.items.length">
                <form v-for="item in notifications.items" :key="item.id" method="POST" :action="openUrl(item.id)">
                    <input type="hidden" name="_token" :value="csrfToken" />
                    <button
                        type="submit"
                        class="flex w-full items-start gap-3 px-4 py-3 text-left transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/60"
                        :class="item.unread ? 'bg-primary/5' : ''"
                    >
                        <span
                            class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
                            :class="item.unread ? 'bg-primary' : 'bg-slate-300 dark:bg-slate-600'"
                        ></span>
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-semibold text-slate-700 dark:text-slate-200">{{ item.title }}</span>
                            <span v-if="item.body" class="mt-0.5 block text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ item.body }}</span>
                            <span class="mt-1 block text-[10px] uppercase tracking-wide text-slate-400">{{ item.time }}</span>
                        </span>
                    </button>
                </form>
            </template>
            <p v-else class="px-4 py-6 text-center text-sm text-slate-500 dark:text-slate-400">
                Nothing yet — lesson updates show up here.
            </p>
        </div>

        <a
            :href="urls.notifications"
            class="block border-t border-slate-200 px-4 py-3 text-center text-sm font-semibold text-primary hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/60"
        >
            View all notifications
        </a>
    </DropdownPanel>
</template>
