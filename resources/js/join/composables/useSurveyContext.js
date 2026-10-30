import { inject } from 'vue';
import { SURVEY_KEY } from '../context';

/**
 * The page context every survey component reads: the island `config` and the
 * live `form` (its values, the server's messages and the submit guard).
 */
export function useSurveyContext() {
    const context = inject(SURVEY_KEY, null);

    if (!context) {
        throw new Error('The invite survey context is missing — is this component outside SurveyApp/AccountApp?');
    }

    return context;
}

export default useSurveyContext;
