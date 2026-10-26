<script setup>
/**
 * One purchase in the wallet: the service it is, where it stands, who teaches
 * it, how much of it is left and the one action worth taking next. Everything
 * shown here arrives formatted from Blade — the card never guesses a label, a
 * price or a URL — and the action is whatever Blade decided the student may do
 * with it: pay it, book a session against it, or open its delivery page. A
 * purchase still awaiting payment also carries a live countdown, and outliving
 * it turns the card into the expired purchase it has just become.
 */
import { computed, ref } from 'vue';
import { usePanel } from '../../shared/composables/usePanel';
import { CHIP } from '../../shared/styles';
import AppIcon from '../../shared/components/AppIcon.vue';
import PaymentCountdown from '../../shared/components/PaymentCountdown.vue';
import UiChip from '../../shared/components/UiChip.vue';
import { useCopy, useCsrfToken } from '../composables/usePageContext';

const props = defineProps({
    purchase: { type: Object, required: true },
});

// Purchase statuses carry their own Tailwind classes in the PHP enum, which sits
// outside Tailwind's scanned paths, so the card keeps its own copy here.
const statusTones = {
    pending_payment: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    active: 'bg-primary/10 text-primary',
    completed: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    cancelled: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
    expired: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
    disputed: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
    resolved: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
};

const buttonClass =
    'inline-flex items-center gap-1.5 rounded-xl bg-primary px-3.5 py-2 text-sm font-semibold text-white shadow-sm shadow-primary/20 transition-colors hover:bg-primary/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40';

const outlineClass =
    'inline-flex items-center gap-1.5 rounded-xl border border-slate-200/80 px-3.5 py-2 text-sm font-semibold text-slate-600 transition-colors hover:border-primary/40 hover:text-primary dark:border-slate-800 dark:text-slate-300';

const csrfToken = useCsrfToken();
const copy = useCopy();
const panel = usePanel(true);

// The countdown can run out while the page stays open, which turns the card
// into the expired purchase the scheduler is about to write: the status, the
// balance and the payment form all give way to a reload that picks the server
// state back up.
const holdExpired = ref(false);

const status = computed(() => (holdExpired.value && props.purchase.expiredStatus
    ? props.purchase.expiredStatus
    : { value: props.purchase.status, label: props.purchase.statusLabel }));

// A manual slip awaiting an admin reads as its own state rather than as the
// unpaid "Awaiting payment" chip.
const manualTone = 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400';
const statusTone = computed(() => (props.purchase.manualSubmitted && !holdExpired.value
    ? manualTone
    : statusTones[status.value.value] ?? statusTones.cancelled));
const actionClass = computed(() => (props.purchase.action?.variant === 'primary' ? buttonClass : outlineClass));

const reload = () => window.location.reload();
</script>

<template>
    <article :class="[panel, 'flex h-full flex-col']">
        <div class="flex items-start justify-between gap-3">
            <div class="flex min-w-0 items-center gap-2.5">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 p-2.5 text-primary">
                    <AppIcon :name="purchase.typeIcon" />
                </span>
                <div class="min-w-0">
                    <p class="truncate text-[11px] font-bold uppercase tracking-wider text-primary">
                        {{ purchase.typeLabel }}
                    </p>
                    <p class="truncate text-xs text-slate-400">{{ purchase.purchasedLabel }}</p>
                </div>
            </div>

            <span :class="[CHIP, statusTone]">{{ status.label }}</span>
        </div>

        <h3 class="mt-3 text-sm font-bold text-slate-800 dark:text-slate-100">
            <a :href="purchase.url" class="transition-colors hover:text-primary focus:outline-none focus-visible:underline">
                {{ purchase.title }}
            </a>
        </h3>

        <p class="mt-1 truncate text-sm text-slate-500 dark:text-slate-400">{{ purchase.teacherLabel }}</p>

        <div v-if="purchase.progress && !holdExpired" class="mt-4">
            <div class="flex items-center justify-between text-xs text-slate-400">
                <span>{{ purchase.progress.label }}</span>
                <span class="tabular-nums">{{ purchase.progress.percent }}%</span>
            </div>
            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                <div
                    class="h-full rounded-full transition-all duration-300"
                    :class="purchase.progress.exhausted ? 'bg-emerald-500' : 'bg-primary'"
                    :style="{ width: `${purchase.progress.percent}%` }"
                />
            </div>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-1.5">
            <PaymentCountdown
                v-if="purchase.payBy"
                :deadline="purchase.payBy.deadline"
                :pay-within="copy.payWithin"
                :hold-expired="copy.holdExpired"
                @expired="holdExpired = true"
            />

            <UiChip v-if="purchase.balance && !holdExpired" :tone="purchase.balance.tone" icon="clock">
                {{ purchase.balance.label }}
            </UiChip>

            <UiChip v-if="purchase.delivery" :tone="purchase.delivery.tone">
                {{ purchase.delivery.label }}
            </UiChip>

            <UiChip v-if="purchase.expiresLabel" tone="amber" icon="clock">{{ purchase.expiresLabel }}</UiChip>

            <UiChip v-if="purchase.scope" icon="tag">{{ purchase.scope }}</UiChip>
        </div>

        <div class="mt-auto flex items-center justify-between gap-3 border-t border-slate-200/80 pt-4 dark:border-slate-800/80">
            <p class="text-sm font-extrabold text-slate-800 dark:text-slate-100">{{ purchase.price }}</p>

            <button v-if="holdExpired" type="button" :class="outlineClass" @click="reload">
                {{ copy.refresh }}
                <span class="h-3.5 w-3.5"><AppIcon name="arrow-right" /></span>
            </button>

            <template v-else-if="purchase.action">
                <form v-if="purchase.action.kind === 'form'" method="POST" :action="purchase.action.url">
                    <input type="hidden" name="_token" :value="csrfToken">
                    <button type="submit" :class="actionClass">{{ purchase.action.label }}</button>
                </form>

                <a v-else :href="purchase.action.url" :class="actionClass">
                    {{ purchase.action.label }}
                    <span class="h-3.5 w-3.5"><AppIcon name="chevron-right" /></span>
                </a>
            </template>
        </div>
    </article>
</template>
