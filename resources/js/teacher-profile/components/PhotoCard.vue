<script setup>
/**
 * The avatar: a live preview of whatever the teacher picks, plus the file input
 * Laravel actually receives. The input stays the real control (sr-only) and the
 * styled button is a `<label>` for it, so the picker is keyboard reachable. The
 * same preview feeds the public card beside the form.
 */
import { ref, watch } from 'vue';
import { usePanel } from '../../shared/composables/usePanel';
import AppIcon from '../../shared/components/AppIcon.vue';
import UiSectionHeading from '../../shared/components/UiSectionHeading.vue';
import { useCopy } from '../composables/usePageContext';
import { useProfileForm } from '../composables/useProfileForm';
import { GHOST_BUTTON } from '../styles';

const copy = useCopy();
const panel = usePanel();

const { teacher, avatarFile, avatarPreview, errors, pickAvatar, resetToken } = useProfileForm();

const input = ref(null);

// Undo has to clear the input itself — a file selection is not reactive state.
watch(resetToken, clearInput);

function clearInput() {
    if (input.value) {
        input.value.value = '';
    }
}

function onPick(event) {
    pickAvatar(event.target.files?.[0] ?? null);
}

function discard() {
    clearInput();
    pickAvatar(null);
}
</script>

<template>
    <section :class="panel">
        <UiSectionHeading :title="copy.photoTitle" :description="copy.photoHint" icon="camera" level="h3" />

        <div class="mt-5 flex flex-wrap items-center gap-4">
            <span class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-primary text-2xl font-bold text-white shadow-md shadow-primary/20">
                <img v-if="avatarPreview" :src="avatarPreview" :alt="teacher.name" class="h-full w-full object-cover">
                <span v-else aria-hidden="true">{{ teacher.initials }}</span>
            </span>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <input
                        id="avatar"
                        ref="input"
                        type="file"
                        name="avatar"
                        accept="image/jpeg,image/png,image/webp"
                        class="peer sr-only"
                        @change="onPick"
                    >

                    <label
                        for="avatar"
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 transition-colors hover:border-primary/40 hover:text-primary peer-focus-visible:ring-2 peer-focus-visible:ring-primary/40 dark:border-slate-700 dark:text-slate-300"
                    >
                        <span class="h-4 w-4 shrink-0"><AppIcon name="camera" /></span>
                        {{ avatarFile ? copy.photoReplace : copy.photoChoose }}
                    </label>

                    <button
                        v-if="avatarFile"
                        type="button"
                        :class="[GHOST_BUTTON, 'px-3 py-2 text-xs']"
                        @click="discard"
                    >
                        {{ copy.photoClear }}
                    </button>
                </div>

                <p v-if="avatarFile" class="mt-2 truncate text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                    {{ avatarFile.name }}
                </p>

                <ul v-if="errors.avatar?.length" class="mt-2 space-y-1" role="alert">
                    <li
                        v-for="(message, index) in errors.avatar"
                        :key="index"
                        class="text-sm font-medium text-rose-600 dark:text-rose-400"
                    >
                        {{ message }}
                    </li>
                </ul>
            </div>
        </div>
    </section>
</template>
