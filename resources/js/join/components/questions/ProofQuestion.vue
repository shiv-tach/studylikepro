<script setup>
/**
 * Question 9 — proof of a government teaching post (optional). "Yes" reveals
 * the upload; "No" is a complete answer and the teacher can add proof later
 * from the verification page.
 *
 * The panel is hidden with `v-show`, not `v-if`, so the file input keeps its
 * place in the form the way the Alpine version did.
 */
import { useSurveyContext } from '../../composables/useSurveyContext';
import OptionCard from '../OptionCard.vue';
import FieldMessages from '../FieldMessages.vue';
import { format } from '../../format';
import { LABEL, MUTED } from '../../styles';

const { config, form } = useSurveyContext();

const ACCEPT = 'application/pdf,image/jpeg,image/png,image/webp';
</script>

<template>
    <div class="space-y-5">
        <fieldset>
            <legend class="sr-only">{{ config.title }}</legend>

            <div class="grid gap-3 sm:grid-cols-2">
                <OptionCard
                    v-model="form.values.claims"
                    name="claims"
                    value="1"
                    :title="config.questionCopy.claimsYes"
                    :hint="config.questionCopy.claimsYesHint"
                    required
                />

                <OptionCard
                    v-model="form.values.claims"
                    name="claims"
                    value="0"
                    :title="config.questionCopy.claimsNo"
                    :hint="config.questionCopy.claimsNoHint"
                    required
                />
            </div>

            <FieldMessages :messages="form.errorsFor('claims')" />
        </fieldset>

        <div v-show="form.values.claims === '1'" class="rounded-xl border border-dashed border-slate-300 p-4 dark:border-slate-700">
            <label for="proof" :class="LABEL">{{ config.questionCopy.proofLabel }}</label>
            <p :class="MUTED" class="mt-1">{{ config.questionCopy.proofHint }}</p>

            <p v-if="config.proofName" class="mt-2 break-words text-xs font-semibold text-emerald-700 dark:text-emerald-400">
                {{ format(config.questionCopy.proofUploaded, { name: config.proofName }) }}
            </p>

            <input
                id="proof"
                name="document"
                type="file"
                :accept="ACCEPT"
                class="mt-3 block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-primary/10 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-primary dark:text-slate-300"
            />

            <FieldMessages :messages="form.errorsFor('document')" />
        </div>
    </div>
</template>
