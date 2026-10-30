/**
 * Every label of the survey carries a `:placeholder` the server fills, so the
 * island can stay one JSON blob and Vue only substitutes the values (never a
 * translated sentence).
 */
export function format(template, values = {}) {
    return Object.entries(values).reduce(
        (text, [key, value]) => text.split(`:${key}`).join(String(value ?? '')),
        String(template ?? ''),
    );
}

export default format;
