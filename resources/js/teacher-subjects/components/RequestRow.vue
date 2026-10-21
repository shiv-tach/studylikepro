<script setup>
/**
 * One row in the request queue. Pending requests can be withdrawn (a real POST
 * after a two-step confirm); a rejected row shows the admin's note and offers
 * to start the request again.
 */
import { ref } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';
import UiChip from '../../shared/components/UiChip.vue';
import { format, useCopy, useCsrfToken } from '../composables/usePageContext';
import { DANGER_GHOST_BUTTON, GHOST_BUTTON, SECONDARY_BUTTON } from '../styles';

const props = defineProps({
    item: { type: Object, required: true },
});

const emit = defineEmits(['reapply']);

const copy = useCopy();
const csrfToken = useCsrfToken();

const confirming = ref(false);
const withdrawing = ref(false);
</script>

<template>
    <li class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200/80 px-4 py-3 dark:border-slate-800/80">
        <div class="flex min-w-0 flex-wrap items-center gap-2.5">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-base dark:bg-slate-800" aria-hidden="true">
                {{ item.icon }}
            </span>

            <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ item.name }}</span>

            <UiChip v-if="item.levelName" tone="slate">{{ item.levelName }}</UiChip>
            <UiChip v-if="item.isNew" tone="primary">{{ copy.requests?.newSubject }}</UiChip>
            <UiChip :tone="item.statusTone ?? 'amber'">{{ item.statusLabel }}</UiChip>

            <span v-if="item.requestedAt" class="text-[11px] text-slate-400">
                {{ format(copy.requests?.requestedAt, { when: item.requestedAt }) }}
            </span>
        </div>

        <div v-if="item.cancelUrl" class="flex items-center gap-1">
            <button
                v-if="!confirming"
                type="button"
                :class="DANGER_GHOST_BUTTON"
                @click="confirming = true"
            >
                {{ copy.requests?.withdraw }}
            </button>

            <template v-else>
                <form method="POST" :action="item.cancelUrl" @submit="withdrawing = true">
                    <input type="hidden" name="_token" :value="csrfToken">
                    <button type="submit" :class="DANGER_GHOST_BUTTON" :disabled="withdrawing">
                        {{ copy.requests?.confirmWithdraw }}
                    </button>
                </form>
                <button type="button" :class="GHOST_BUTTON" @click="confirming = false">
                    {{ copy.requests?.keep }}
                </button>
            </template>
        </div>

        <div v-else class="flex items-center gap-2">
            <button type="button" :class="SECONDARY_BUTTON" @click="emit('reapply', item)">
                <span class="h-4 w-4" aria-hidden="true"><AppIcon name="arrow-right" /></span>
                {{ copy.requests?.reapply }}
            </button>
        </div>

        <p v-if="item.note" class="w-full rounded-lg bg-rose-50/70 px-3 py-2 text-xs leading-relaxed text-rose-700 dark:bg-rose-950/30 dark:text-rose-300">
            <span class="font-semibold">{{ copy.requests?.adminNote }}:</span> {{ item.note }}
        </p>
    </li>
</template>
