<script setup>
/**
 * The teacher preview under the service catalog: a few verified cards plus a
 * link into the full directory, which carries the same subject filter.
 */
import { useCopy } from '../composables/usePageContext';
import UiEmptyState from '../../shared/components/UiEmptyState.vue';
import UiSectionHeading from '../../shared/components/UiSectionHeading.vue';
import TeacherCard from './TeacherCard.vue';

defineProps({
    teachers: { type: Object, required: true },
});

const copy = useCopy();
</script>

<template>
    <section>
        <UiSectionHeading
            :title="copy.teachersTitle"
            :description="teachers.total > 0 ? copy.teachersAvailable : null"
            icon="users"
        >
            <template #action>
                <a
                    v-if="teachers.total > teachers.items.length"
                    :href="teachers.directoryUrl"
                    class="text-sm font-semibold text-primary transition-colors hover:text-primary/80"
                >
                    {{ copy.seeAllTeachers }}
                </a>
            </template>
        </UiSectionHeading>

        <UiEmptyState
            v-if="teachers.items.length === 0"
            class="mt-4"
            icon="users"
            :message="copy.teachersEmpty"
            :link-label="copy.browseAllTeachers"
            :link-href="teachers.directoryRootUrl"
        />

        <div v-else class="mt-4 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <TeacherCard v-for="teacher in teachers.items" :key="teacher.id" :teacher="teacher" />
        </div>
    </section>
</template>
