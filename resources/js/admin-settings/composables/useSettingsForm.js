import { reactive, ref } from 'vue';

/**
 * The editable copy of every platform setting. The fields share one draft so the
 * save button can post them as a single form, and a rejected save can be
 * re-seeded from what Blade sent back (old() wins over the stored value).
 */
export const SETTINGS_FORM_KEY = Symbol('admin-settings.form');

export function createSettingsForm(config) {
    const values = reactive({});

    (config.groups ?? []).forEach((group) => {
        (group.items ?? []).forEach((item) => {
            values[item.key] = item.value ?? '';
        });
    });

    const submitting = ref(false);

    return { values, submitting };
}

export default createSettingsForm;
