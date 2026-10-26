<script setup>
/**
 * Where the purchase stands and what it costs: the status, the window it is
 * valid for, the money and the one action worth taking next. Only a hold whose
 * window is still open offers a payment, and the countdown can run out while the
 * page stays open — the panel then reads as the expired purchase the scheduler
 * is about to write, drops the forms and offers a reload that picks the server
 * state back up.
 */
import { computed, ref } from 'vue';
import { usePanel } from '../../shared/composables/usePanel';
import AppIcon from '../../shared/components/AppIcon.vue';
import PaymentCountdown from '../../shared/components/PaymentCountdown.vue';
import UiChip from '../../shared/components/UiChip.vue';
import { useCopy, useCsrfToken } from '../composables/usePageContext';

const props = defineProps({
    status: { type: Object, required: true },
});

const copy = useCopy();
const csrfToken = useCsrfToken();
const panel = usePanel();

const primaryClass =
    'inline-flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-primary/20 transition-colors hover:bg-primary/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40';

const outlineClass =
    'inline-flex items-center gap-1.5 rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition-colors hover:border-primary/40 hover:text-primary dark:border-slate-700 dark:text-slate-300';

// The countdown reaching zero is the only thing that changes this panel by
// itself, so it is tracked apart from the status Blade rendered.
const lapsed = ref(false);

const label = computed(() => (lapsed.value ? props.status.expired.label : props.status.label));
const tone = computed(() => (lapsed.value ? 'slate' : props.status.tone));
const actions = computed(() => (lapsed.value ? [] : props.status.actions));

const reload = () => window.location.reload();
</script>

<template>
    <section :class="panel" aria-labelledby="purchase-status-heading">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 id="purchase-status-heading" class="text-xs font-semibold uppercase tracking-wider text-slate-400">
                    {{ copy.statusLabel }}
                </h2>

                <UiChip class="mt-2" :tone="tone">{{ label }}</UiChip>

                <p v-if="status.expiry && !lapsed" class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                    {{ status.expiry.label }} {{ status.expiry.value }}
                </p>
            </div>

            <div class="text-right">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ status.price.label }}</p>
                <p class="mt-1 text-2xl font-extrabold tracking-tight text-slate-900 dark:text-slate-100">
                    {{ status.price.value }}
                </p>
                <p v-if="status.price.fee" class="mt-1 text-xs text-slate-400">{{ status.price.fee }}</p>
            </div>
        </div>

        <div
            v-if="lapsed || actions.length"
            class="mt-5 flex flex-wrap items-center gap-3 border-t border-slate-200 pt-5 dark:border-slate-800"
        >
            <PaymentCountdown
                v-if="status.hold && !lapsed"
                :deadline="status.hold.deadline"
                :pay-within="copy.payWithin"
                :hold-expired="copy.holdExpired"
                @expired="lapsed = true"
            />

            <template v-if="lapsed">
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ copy.lapsed }}</p>

                <button type="button" :class="outlineClass" @click="reload">
                    {{ copy.refresh }}
                    <span class="h-4 w-4"><AppIcon name="arrow-right" /></span>
                </button>
            </template>

            <template v-else>
                <template v-for="action in actions" :key="action.url">
                    <a
                        v-if="action.kind === 'link'"
                        :href="action.url"
                        :class="action.variant === 'primary' ? primaryClass : outlineClass"
                    >
                        {{ action.label }}
                    </a>

                    <form v-else method="POST" :action="action.url">
                        <input type="hidden" name="_token" :value="csrfToken">
                        <button type="submit" :class="action.variant === 'primary' ? primaryClass : outlineClass">
                            {{ action.label }}<template v-if="action.amount"> — {{ action.amount }}</template>
                        </button>
                    </form>
                </template>
            </template>
        </div>

        <!-- A manual slip is with the admin team, or was turned down. -->
        <div
            v-if="status.manual"
            :class="['mt-4 rounded-xl border p-3 text-xs', status.manual.rejected
                ? 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/20 dark:text-rose-300'
                : 'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-900/40 dark:bg-sky-950/20 dark:text-sky-300']"
        >
            <p class="font-semibold">{{ status.manual.label }}</p>
            <p v-if="status.manual.reason" class="mt-1">{{ status.manual.reason }}</p>
            <p v-if="status.manual.submittedAt" class="mt-1">{{ status.manual.submittedAt }}</p>
            <div class="mt-2 flex flex-wrap gap-3">
                <a
                    v-if="status.manual.slipUrl"
                    :href="status.manual.slipUrl"
                    target="_blank"
                    rel="noopener"
                    class="font-semibold underline"
                >
                    {{ status.manual.slipLabel }}
                </a>
                <a :href="status.manual.checkoutUrl" class="font-semibold underline">
                    {{ status.manual.checkoutLabel }}
                </a>
            </div>
        </div>

        <p v-if="status.payment" class="mt-4 text-xs text-slate-400">{{ status.payment.label }}</p>
    </section>
</template>
