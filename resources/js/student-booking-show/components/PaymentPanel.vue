<script setup>
/**
 * What the lesson costs, what has been paid for it and the one action worth
 * taking next. Blade decides which actions the student may take — join, cancel,
 * reschedule or chat — and pre-formats the total, the fee split and the payment
 * record, so this panel never recomputes money or re-checks a rule.
 */
import { usePanel } from '../../shared/composables/usePanel';
import { CHIP } from '../../shared/styles';
import { useCopy, useCsrfToken } from '../composables/usePageContext';

defineProps({
    money: { type: Object, required: true },
    actions: { type: Array, default: () => [] },
    cancelNotice: { type: String, default: null },
});

const emit = defineEmits(['cancel']);

const copy = useCopy();
const csrfToken = useCsrfToken();
const panel = usePanel();

// Payment statuses carry their own Tailwind classes in the PHP enum, which sits
// outside Tailwind's scanned paths, so the panel keeps its own copy here.
const paymentTones = {
    amber: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    sky: 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400',
    emerald: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    rose: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
    slate: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
};

const primaryClass =
    'inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-bold text-white shadow-sm transition-colors hover:bg-primary/90';
const outlineClass =
    'inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800';
</script>

<template>
    <section :class="panel">
        <div class="flex items-center justify-between gap-3">
            <span class="text-sm font-semibold text-slate-600 dark:text-slate-300">{{ money.totalLabel }}</span>
            <span class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-slate-100">{{ money.total }}</span>
        </div>

        <p v-if="money.purchaseNotice" class="mt-2 text-xs text-slate-500 dark:text-slate-400">
            {{ money.purchaseNotice.text }}
            <a :href="money.purchaseNotice.url" class="font-semibold text-primary hover:underline">
                {{ money.purchaseNotice.linkLabel }}
            </a>
        </p>

        <dl v-if="money.breakdown" class="mt-3 space-y-1 border-t border-slate-200 pt-3 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
            <div v-for="line in money.breakdown" :key="line.label" class="flex items-center justify-between gap-3">
                <dt>
                    {{ line.label }}
                    <span v-if="line.tag" class="text-emerald-600 dark:text-emerald-400">· {{ line.tag }}</span>
                </dt>
                <dd :class="line.positive ? 'text-emerald-600 dark:text-emerald-400' : ''">
                    {{ line.value }}
                    <span v-if="line.strike" class="ml-1 text-slate-400 line-through">{{ line.strike }}</span>
                </dd>
            </div>
        </dl>

        <dl v-if="money.payment" class="mt-4 space-y-2 border-t border-slate-200 pt-4 text-sm dark:border-slate-800">
            <div class="flex items-center justify-between gap-3">
                <dt class="text-slate-500 dark:text-slate-400">{{ copy.paymentLabel }}</dt>
                <dd class="flex items-center gap-2">
                    <span :class="[CHIP, paymentTones[money.payment.statusTone] ?? paymentTones.slate]">{{ money.payment.statusLabel }}</span>
                    <span class="text-xs text-slate-400">{{ money.payment.gatewayId }}</span>
                </dd>
            </div>
            <div v-if="money.payment.refunded" class="flex items-center justify-between gap-3">
                <dt class="text-slate-500 dark:text-slate-400">{{ copy.refundedLabel }}</dt>
                <dd class="font-semibold text-emerald-600 dark:text-emerald-400">−{{ money.payment.refunded }}</dd>
            </div>
            <div class="flex items-center justify-between gap-3">
                <dt class="text-slate-500 dark:text-slate-400">{{ copy.paidOnLabel }}</dt>
                <dd class="text-slate-600 dark:text-slate-300">{{ money.payment.paidOn }}</dd>
            </div>
        </dl>

        <a v-if="money.payment && money.receiptUrl" :href="money.receiptUrl" :class="[outlineClass, 'mt-4']">
            {{ copy.receiptLabel }}
        </a>

        <div v-if="actions.length" class="mt-5 flex flex-wrap gap-3 border-t border-slate-200 pt-5 dark:border-slate-800">
            <template v-for="action in actions" :key="action.label">
                <a v-if="action.kind === 'link'" :href="action.url" :class="action.variant === 'primary' ? primaryClass : outlineClass">
                    {{ action.label }}
                </a>

                <form v-else-if="action.kind === 'form'" method="POST" :action="action.url">
                    <input type="hidden" name="_token" :value="csrfToken">
                    <button type="submit" :class="outlineClass">{{ action.label }}</button>
                </form>

                <button v-else type="button" :class="outlineClass" @click="emit('cancel')">{{ action.label }}</button>
            </template>
        </div>

        <p v-if="cancelNotice" class="mt-3 text-xs text-slate-500 dark:text-slate-400">{{ cancelNotice }}</p>
    </section>
</template>
