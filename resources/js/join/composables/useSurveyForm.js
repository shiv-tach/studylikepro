import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';

/**
 * The live state of one survey screen: the answers being edited, the server's
 * messages for the last rejected save, and the double-submit guard the Alpine
 * `submitOnce` helper used to provide.
 *
 * The server owns validation — this only replays what it said and keeps the
 * button honest while the browser navigates away.
 */
export function createSurveyForm(config) {
    // A deep copy: the island is a parsed JSON string, and the form has to be
    // free to mutate it without touching `config` (the rail/summary read it).
    const values = reactive(JSON.parse(JSON.stringify(config.values ?? {})));

    // "My own days" is the empty radio value, so a null preset has to become ''
    // or neither card would be checked on a fresh visit.
    if ('preset' in values && (values.preset === null || values.preset === undefined)) {
        values.preset = '';
    }

    const busy = ref(false);
    const errors = computed(() => config.errors ?? {});
    const fieldErrors = computed(() => config.fieldErrors ?? {});
    const errorList = computed(() => config.errorList ?? []);
    const hasErrors = computed(() => errorList.value.length > 0);

    const errorsFor = (name) => errors.value[name] ?? [];

    /** `subjects.0` style messages, flattened for the group that owns them. */
    const errorsUnder = (prefix) => Object.entries(fieldErrors.value)
        .filter(([key]) => key.startsWith(prefix))
        .flatMap(([, messages]) => messages);

    function guard(event) {
        if (busy.value) {
            event.preventDefault();

            return;
        }

        busy.value = true;
    }

    // Restored from the back/forward cache: the page was never submitted, so
    // the button has to be usable again (the Alpine `submitOnce` did the same).
    const unlock = () => {
        busy.value = false;
    };

    onMounted(() => window.addEventListener('pageshow', unlock));
    onBeforeUnmount(() => window.removeEventListener('pageshow', unlock));

    return {
        values,
        busy,
        errors,
        fieldErrors,
        errorList,
        hasErrors,
        errorsFor,
        errorsUnder,
        guard,
    };
}

export default createSurveyForm;
