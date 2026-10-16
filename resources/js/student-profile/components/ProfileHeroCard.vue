<script setup>
/**
 * Who the profile belongs to — the avatar, the name, the email and the two
 * answers that scope the whole workspace — with the strength meter that fills
 * up as the optional answers are added.
 */
import { usePanel } from '../../shared/composables/usePanel';
import AppIcon from '../../shared/components/AppIcon.vue';
import UiChip from '../../shared/components/UiChip.vue';
import { format, useCopy } from '../composables/usePageContext';
import { useProfileForm } from '../composables/useProfileForm';

const copy = useCopy();
const panel = usePanel();

const { student, values, avatarPreview, selectedGrade, strength } = useProfileForm();
</script>

<template>
    <section :class="panel" class="flex flex-wrap items-center gap-5">
        <span
            class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-primary text-xl font-bold text-white shadow-md shadow-primary/20"
        >
            <img v-if="avatarPreview" :src="avatarPreview" :alt="student.name" class="h-full w-full object-cover">
            <span v-else aria-hidden="true">{{ student.initials }}</span>
        </span>

        <div class="min-w-0 flex-1">
            <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ student.name }}</h2>

            <p v-if="student.email" class="mt-0.5 flex items-center gap-1.5 text-sm text-slate-500 dark:text-slate-400">
                <span class="h-4 w-4 shrink-0"><AppIcon name="mail" /></span>
                <span class="truncate">{{ student.email }}</span>
            </p>

            <div class="mt-3 flex flex-wrap items-center gap-2">
                <UiChip v-if="selectedGrade" tone="primary" icon="graduation">{{ selectedGrade.label }}</UiChip>
                <UiChip v-else tone="amber" icon="alert">{{ copy.gradePlaceholder }}</UiChip>

                <UiChip v-if="values.learningLanguage" tone="sky">{{ values.learningLanguage }}</UiChip>

                <UiChip v-if="student.memberSince">{{ copy.memberSinceLabel }} {{ student.memberSince }}</UiChip>
            </div>
        </div>

        <div class="w-full sm:w-56">
            <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-wider text-slate-400">
                <span>{{ copy.strengthTitle }}</span>
                <span>{{ format(copy.progressLabel, { percent: strength.percent }) }}</span>
            </div>

            <div
                class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                role="progressbar"
                :aria-label="copy.strengthTitle"
                :aria-valuenow="strength.percent"
                aria-valuemin="0"
                aria-valuemax="100"
            >
                <div
                    class="h-full rounded-full bg-primary transition-all duration-500 ease-out motion-reduce:transition-none"
                    :style="{ width: `${strength.percent}%` }"
                ></div>
            </div>
        </div>
    </section>
</template>
