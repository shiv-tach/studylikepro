<script setup>
/**
 * The "Add subjects" tab: pick from the catalog, or propose something the
 * catalog does not have. Both paths are real POST forms; the switch only
 * decides which one is visible.
 */
import { useCopy, useWorkspace } from '../composables/usePageContext';
import CatalogPicker from './CatalogPicker.vue';
import ProposeSubjectForm from './ProposeSubjectForm.vue';

const { addSection } = useWorkspace();
const copy = useCopy();

const SECTION_CLASS = 'rounded-xl px-4 py-2 text-sm font-semibold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40';
const ACTIVE_CLASS = 'bg-primary text-white shadow-sm shadow-primary/30';
const IDLE_CLASS = 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800';
</script>

<template>
    <div class="space-y-5">
        <div class="inline-flex flex-wrap gap-1 rounded-2xl border border-slate-200/80 bg-white p-1.5 shadow-sm dark:border-slate-800/80 dark:bg-slate-900">
            <button
                type="button"
                :class="[SECTION_CLASS, addSection === 'catalog' ? ACTIVE_CLASS : IDLE_CLASS]"
                :aria-pressed="addSection === 'catalog'"
                @click="addSection = 'catalog'"
            >
                {{ copy.add?.catalogTab }}
            </button>
            <button
                type="button"
                :class="[SECTION_CLASS, addSection === 'propose' ? ACTIVE_CLASS : IDLE_CLASS]"
                :aria-pressed="addSection === 'propose'"
                @click="addSection = 'propose'"
            >
                {{ copy.add?.proposeTab }}
            </button>
        </div>

        <CatalogPicker v-show="addSection === 'catalog'" />
        <ProposeSubjectForm v-show="addSection === 'propose'" />
    </div>
</template>
