/**
 * A multipart POST that reports how much of the file has actually been sent.
 *
 * `fetch` cannot observe upload progress, and a 5 MB phone scan can take long
 * enough that a teacher needs to see it moving (and to be able to cancel it), so
 * the verification upload slots use XMLHttpRequest for this one request.
 *
 * `uploadWithProgress()` returns immediately with `{ promise, abort }`:
 * - `promise` resolves `{ status, payload }`, where `status` is the HTTP status
 *   (or `'aborted'`) and `payload` is the decoded JSON body when there is one.
 * - it rejects only when the request never completed (offline, DNS, timeout), so
 *   the caller can fall back to a plain form post.
 */
export function uploadWithProgress({ url, formData, onProgress }) {
    if (typeof XMLHttpRequest === 'undefined') {
        throw new Error('XMLHttpRequest is not available');
    }

    const xhr = new XMLHttpRequest();

    const promise = new Promise((resolve, reject) => {
        xhr.open('POST', url, true);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.upload.addEventListener('progress', (event) => {
            if (!event.lengthComputable || event.total === 0) {
                return;
            }

            // Keep the last percent for the server: the file is sent at 100, but
            // the row only exists once the response comes back.
            onProgress?.(Math.min(99, Math.round((event.loaded / event.total) * 100)));
        });

        xhr.addEventListener('load', () => {
            let payload = null;

            try {
                payload = JSON.parse(xhr.responseText);
            } catch (error) {
                payload = null;
            }

            if (xhr.status === 0) {
                reject(new Error('The upload could not reach the server.'));

                return;
            }

            resolve({ status: xhr.status, payload });
        });

        xhr.addEventListener('error', () => reject(new Error('The upload could not reach the server.')));
        xhr.addEventListener('timeout', () => reject(new Error('The upload timed out.')));
        xhr.addEventListener('abort', () => resolve({ status: 'aborted', payload: null }));

        xhr.send(formData);
    });

    return {
        promise,
        abort: () => xhr.abort(),
    };
}

export default uploadWithProgress;
