<script setup>
/**
 * The create/edit sheet. One real form posts to `teacher.services.store` or
 * `teacher.services.update`; the type tiles only decide which fields are shown.
 * A bounced save reopens the sheet with the server's messages, and closing a
 * changed form asks before throwing the edits away.
 */
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';
import UiAlert from '../../shared/components/UiAlert.vue';
import UiConfirmDialog from '../../shared/components/UiConfirmDialog.vue';
import { useCopy, useCsrfToken, usePage, useWorkspace } from '../composables/usePageContext';
import { PRIMARY_BUTTON, SECONDARY_BUTTON } from '../styles';
import ScopeSelector from './ScopeSelector.vue';
import ServiceBasicsFields from './ServiceBasicsFields.vue';
import ServicePreviewCard from './ServicePreviewCard.vue';
import ServiceTypePicker from './ServiceTypePicker.vue';
import TypeSpecificFields from './TypeSpecificFields.vue';
import RubricBuilder from './RubricBuilder.vue';

const copy = useCopy();
const page = usePage();
const csrfToken = useCsrfToken();
const workspace = useWorkspace();

const {
    composer,
    isEdit,
    currentType,
    values,
    errors,
    submitting,
    discardOpen,
    formAction,
    selectType,
    closeComposer,
    confirmDiscard,
    cancelDiscard,
    markSubmitting,
} = workspace;

const panel = ref(null);
let previousActive = null;

const selectedType = computed({
    get: () => composer.value?.type ?? null,
    set: (type) => selectType(type),
});

const formLevelErrors = computed(() => errors.value.service_type ?? []);

const hasErrors = computed(() => Object.values(errors.value ?? {}).some((messages) => messages?.length));

const hasDeliveryFields = computed(() => Boolean(currentType.value) && (
    currentType.value.value === 'lesson'
    || currentType.value.value === 'paper_marking'
    || currentType.value.isLiveSession
));

const submitLabel = computed(() => {
    if (submitting.value) {
        return copy.composer?.submitting;
    }

    return isEdit.value ? copy.composer?.submitSave : copy.composer?.submitCreate;
});

function focusables() {
    if (!panel.value) {
        return [];
    }

    return [...panel.value.querySelectorAll('a, button, input:not([type="hidden"]), select, textarea, [tabindex]:not([tabindex="-1"])')]
        .filter((element) => !element.hasAttribute('disabled'));
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        event.stopPropagation();
        closeComposer();

        return;
    }

    if (event.key !== 'Tab') {
        return;
    }

    const items = focusables();

    if (items.length === 0) {
        return;
    }

    const first = items[0];
    const last = items[items.length - 1];

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

watch(composer, async (value) => {
    if (value) {
        previousActive = document.activeElement;
        document.body.classList.add('overflow-y-hidden');
        await nextTick();
        panel.value?.focus();
    } else {
        document.body.classList.remove('overflow-y-hidden');
        previousActive?.focus?.();
    }
}, { immediate: true });

// The discard dialog shares the body lock; when it closes without discarding,
// the composer is still open and must keep the page from scrolling behind it.
watch(discardOpen, (open) => {
    if (!open && composer.value) {
        document.body.classList.add('overflow-y-hidden');
    }
});

onBeforeUnmount(() => document.body.classList.remove('overflow-y-hidden'));
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="composer"
                class="fixed inset-0 z-50"
                role="dialog"
                aria-modal="true"
                aria-labelledby="service-composer-title"
                @keydown="onKeydown"
            >
                <div class="fixed inset-0 bg-slate-500/75 dark:bg-slate-900/80" @click="closeComposer()"></div>

                <div
                    ref="panel"
                    tabindex="-1"
                    class="services-sheet absolute inset-y-0 right-0 flex w-full max-w-4xl flex-col border-l border-slate-200 bg-white shadow-2xl outline-none dark:border-slate-800 dark:bg-slate-900"
                >
                    <header class="flex items-start justify-between gap-3 border-b border-slate-200/80 p-5 dark:border-slate-800/80">
                        <div class="min-w-0">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">
                                {{ copy.hero?.eyebrow }}
                            </p>
                            <h2 id="service-composer-title" class="mt-1 text-lg font-bold text-slate-800 dark:text-slate-100">
                                {{ isEdit ? copy.composer?.editTitle : copy.composer?.createTitle }}
                                <span v-if="isEdit && currentType" class="text-sm font-semibold text-slate-400">· {{ currentType.label }}</span>
                            </h2>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                {{ isEdit ? copy.composer?.editSubtitle : copy.composer?.createSubtitle }}
                            </p>
                        </div>

                        <button
                            type="button"
                            class="-mr-1 -mt-1 shrink-0 rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                            :aria-label="copy.composer?.close"
                            @click="closeComposer()"
                        >
                            <span class="block h-4 w-4"><AppIcon name="close" /></span>
                        </button>
                    </header>

                    <div class="flex-1 overflow-y-auto p-5">
                        <form
                            id="service-composer-form"
                            method="POST"
                            :action="formAction"
                            class="space-y-6"
                            @submit="markSubmitting()"
                        >
                            <input type="hidden" name="_token" :value="csrfToken">
                            <input v-if="isEdit" type="hidden" name="_method" value="PUT">
                            <input v-if="isEdit && composer.offeringId" type="hidden" name="editing_offering" :value="composer.offeringId">
                            <input v-if="currentType" type="hidden" name="service_type" :value="currentType.value">

                            <UiAlert v-if="hasErrors" tone="rose" icon="alert" role="alert">
                                <span class="font-semibold">{{ copy.errors?.title }}</span>
                                <span class="ml-1">{{ copy.errors?.body }}</span>
                                <ul v-if="formLevelErrors.length" class="mt-1 space-y-0.5">
                                    <li v-for="(message, index) in formLevelErrors" :key="index">{{ message }}</li>
                                </ul>
                            </UiAlert>

                            <p v-if="isEdit" class="rounded-xl bg-slate-50 px-3 py-2 text-[11px] leading-relaxed text-slate-500 dark:bg-slate-950/40 dark:text-slate-400">
                                {{ copy.composer?.typeLocked }}
                            </p>

                            <ServiceTypePicker
                                v-if="!isEdit"
                                v-model="selectedType"
                                :types="page.types ?? []"
                            />

                            <div v-if="!currentType" class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                                {{ copy.composer?.pickTypeHint }}
                            </div>

                            <template v-else>
                                <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_17rem]">
                                    <div class="space-y-6">
                                        <section class="space-y-4">
                                            <div>
                                                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ copy.composer?.basicsTitle }}</h3>
                                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ copy.composer?.basicsHint }}</p>
                                            </div>
                                            <ServiceBasicsFields />
                                        </section>

                                        <hr class="border-slate-200/70 dark:border-slate-800/70">

                                        <section class="space-y-4">
                                            <div>
                                                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ copy.composer?.scopeTitle }}</h3>
                                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ copy.composer?.scopeHint }}</p>
                                            </div>
                                            <ScopeSelector />
                                        </section>

                                        <template v-if="hasDeliveryFields">
                                            <hr class="border-slate-200/70 dark:border-slate-800/70">

                                            <section class="space-y-4">
                                                <div>
                                                    <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ copy.composer?.settingsTitle }}</h3>
                                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ copy.composer?.settingsHint }}</p>
                                                </div>
                                                <TypeSpecificFields />
                                                <RubricBuilder v-if="currentType.value === 'paper_marking'" />
                                            </section>
                                        </template>

                                        <p class="text-[11px] text-slate-400">{{ copy.composer?.requiredHint }}</p>
                                    </div>

                                    <div class="lg:sticky lg:top-0">
                                        <ServicePreviewCard />
                                    </div>
                                </div>
                            </template>
                        </form>
                    </div>

                    <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200/80 p-4 dark:border-slate-800/80">
                        <div class="hidden text-right text-[11px] text-slate-400 lg:block">
                            <p v-if="values.title" class="truncate font-semibold text-slate-500 dark:text-slate-400">{{ values.title }}</p>
                        </div>

                        <div class="ml-auto flex items-center gap-2">
                            <button type="button" :class="SECONDARY_BUTTON" @click="closeComposer()">
                                {{ copy.composer?.cancel }}
                            </button>

                            <button
                                type="submit"
                                form="service-composer-form"
                                :class="PRIMARY_BUTTON"
                                :disabled="!currentType || submitting"
                            >
                                <span class="h-4 w-4" aria-hidden="true"><AppIcon name="check" /></span>
                                {{ submitLabel }}
                            </button>
                        </div>
                    </footer>
                </div>
            </div>
        </Transition>

        <UiConfirmDialog
            :open="discardOpen"
            :title="copy.composer?.discardTitle"
            :body="copy.composer?.discardBody"
            :confirm-label="copy.composer?.discard"
            :cancel-label="copy.composer?.keepEditing"
            :close-label="copy.composer?.close"
            tone="rose"
            @confirm="confirmDiscard"
            @close="cancelDiscard"
        />
    </Teleport>
</template>

<style scoped>
@keyframes services-sheet-in {
    from {
        transform: translateX(100%);
    }

    to {
        transform: translateX(0);
    }
}

.services-sheet {
    animation: services-sheet-in 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

@media (prefers-reduced-motion: reduce) {
    .services-sheet {
        animation: none;
    }
}
</style>
