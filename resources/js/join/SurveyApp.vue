<script setup>
/**
 * One screen of the invite survey: the question header, the error summary, the
 * real form (the step's component inside it), the recap beside it and the
 * phone submit bar.
 *
 * Blade still owns the answers, the options, the recap and every route — this
 * app renders them and keeps the small interactions (preset -> days, claims ->
 * upload, one submit per tap) in the browser.
 */
import { computed, provide } from 'vue';
import { SURVEY_KEY } from './context';
import { createSurveyForm } from './composables/useSurveyForm';
import QuestionHeader from './components/QuestionHeader.vue';
import ErrorSummary from './components/ErrorSummary.vue';
import SummaryPanel from './components/SummaryPanel.vue';
import MobileActionBar from './components/MobileActionBar.vue';
import SubmitButton from './components/SubmitButton.vue';
import LevelQuestion from './components/questions/LevelQuestion.vue';
import SubjectsQuestion from './components/questions/SubjectsQuestion.vue';
import LanguagesQuestion from './components/questions/LanguagesQuestion.vue';
import ServicesQuestion from './components/questions/ServicesQuestion.vue';
import ExperienceQuestion from './components/questions/ExperienceQuestion.vue';
import EducationQuestion from './components/questions/EducationQuestion.vue';
import RatesQuestion from './components/questions/RatesQuestion.vue';
import AvailabilityQuestion from './components/questions/AvailabilityQuestion.vue';
import ProofQuestion from './components/questions/ProofQuestion.vue';
import { CARD, GHOST_BUTTON } from './styles';

const props = defineProps({
    config: { type: Object, required: true },
});

const QUESTIONS = {
    level: LevelQuestion,
    subjects: SubjectsQuestion,
    languages: LanguagesQuestion,
    services: ServicesQuestion,
    experience: ExperienceQuestion,
    education: EducationQuestion,
    rates: RatesQuestion,
    availability: AvailabilityQuestion,
    proof: ProofQuestion,
};

const form = createSurveyForm(props.config);

provide(SURVEY_KEY, { config: props.config, form });

// Plain aliases so the template reads like a normal component: refs nested in
// the provided context object are not unwrapped automatically.
const hasErrors = computed(() => form.hasErrors.value);
const errorList = computed(() => form.errorList.value);
const busy = computed(() => form.busy.value);

const question = computed(() => QUESTIONS[props.config.step] ?? LevelQuestion);
const formId = 'join-question-form';
const submitLabel = computed(() => (props.config.isProof
    ? props.config.copy.continueToAccount
    : props.config.copy.continue));
</script>

<template>
    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start">
        <div class="space-y-5">
            <QuestionHeader
                :index="config.index"
                :total="config.total"
                :answered="config.answered"
                :percent="config.percent"
                :title="config.title"
                :body="config.body"
                :copy="config.copy"
            />

            <ErrorSummary v-if="hasErrors" :title="config.copy.errorTitle" :messages="errorList" />

            <form
                :id="formId"
                method="POST"
                :action="config.urls.submit"
                :enctype="config.isProof ? 'multipart/form-data' : null"
                :class="[CARD, 'space-y-5']"
                @submit="form.guard"
            >
                <input type="hidden" name="_token" :value="config.csrfToken" />
                <input v-if="!config.isProof" type="hidden" name="_method" value="PUT" />

                <component :is="question" />

                <div class="hidden items-center justify-between gap-3 border-t border-slate-200/80 pt-5 dark:border-slate-800/80 lg:flex">
                    <a v-if="config.urls.back" :href="config.urls.back" v-show="!busy" :class="GHOST_BUTTON">
                        {{ config.copy.back }}
                    </a>

                    <SubmitButton :label="submitLabel" class="ms-auto" />
                </div>
            </form>
        </div>

        <SummaryPanel
            :groups="config.summary"
            :answered="config.answered"
            :questions="config.total"
            :copy="config.copy"
        />
    </div>

    <MobileActionBar :form-id="formId" :back-url="config.urls.back" :submit-label="submitLabel" />
</template>
