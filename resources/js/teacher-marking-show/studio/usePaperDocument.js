import * as pdfjsLib from 'pdfjs-dist';
import workerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';

/**
 * The uploaded paper as the studio sees it: one flat list of pages across
 * every uploaded file. PDFs are rendered with pdf.js, images are drawn
 * straight into the same canvas, so the annotation layer never cares which
 * kind of file a page came from.
 *
 * Rendering is lazy per file: the first page of a file is enough to learn the
 * page size and the page count, and a page is only rasterised when it is
 * displayed (or exported).
 */
pdfjsLib.GlobalWorkerOptions.workerSrc = workerUrl;

// Scanned pages are decoded by pdf.js's wasm module, which it fetches from this
// directory (copied out of pdfjs-dist by vite.config.js). Without it an image-only
// page renders blank, leaving just the text layer — e.g. a paper's watermark.
const wasmUrl = `${import.meta.env.BASE_URL}vendor/pdfjs/wasm/`;

function loadImage(url) {
    return new Promise((resolve, reject) => {
        const image = new Image();
        image.decoding = 'async';
        image.onload = () => resolve(image);
        image.onerror = () => reject(new Error(`Could not load ${url}`));
        image.src = url;
    });
}

export function createPaperDocument(files = []) {
    const slots = files.map((file, index) => ({
        index,
        name: file.name ?? `Paper ${index + 1}`,
        url: file.previewUrl ?? file.url ?? '',
        isPdf: Boolean(file.isPdf),
        doc: null,
        image: null,
        loading: null,
        pages: [],
    }));

    let pages = [];

    function loadSlot(slot) {
        if (slot.loading) {
            return slot.loading;
        }

        slot.loading = (async () => {
            if (!slot.url) {
                throw new Error('This file has no preview URL.');
            }

            if (slot.isPdf) {
                const doc = await pdfjsLib.getDocument({ url: slot.url, wasmUrl, withCredentials: true }).promise;
                slot.doc = doc;
                const first = await doc.getPage(1);
                const viewport = first.getViewport({ scale: 1 });

                for (let pageIndex = 0; pageIndex < doc.numPages; pageIndex += 1) {
                    slot.pages.push({
                        key: `${slot.index}:${pageIndex}`,
                        fileIndex: slot.index,
                        pageIndex,
                        fileName: slot.name,
                        width: viewport.width,
                        height: viewport.height,
                        isPdf: true,
                    });
                }

                return;
            }

            const image = await loadImage(slot.url);
            slot.image = image;
            slot.pages.push({
                key: `${slot.index}:0`,
                fileIndex: slot.index,
                pageIndex: 0,
                fileName: slot.name,
                width: image.naturalWidth || 900,
                height: image.naturalHeight || 1200,
                isPdf: false,
            });
        })();

        return slot.loading;
    }

    /**
     * Load every file in order. A file that fails to render is reported back
     * (the studio still shows the others and offers its download link).
     */
    async function load() {
        pages = [];
        const errors = [];

        for (const slot of slots) {
            try {
                await loadSlot(slot);
                pages.push(...slot.pages);
            } catch (error) {
                errors.push({
                    fileIndex: slot.index,
                    name: slot.name,
                    message: error?.message ? String(error.message) : 'Could not render this file.',
                });
            }
        }

        return { pages, errors };
    }

    /**
     * Draw one page into a canvas at `targetWidth` CSS pixels (the backing
     * store is multiplied by the device pixel ratio, clipped to 2.5 so a
     * retina laptop does not allocate enormous canvases).
     *
     * pdf.js refuses to share one canvas between two live render tasks, so the
     * caller receives a cancel handle and must call it before rendering again.
     *
     * @returns {Promise<{width: number, height: number, pixelWidth: number, pixelHeight: number}>}
     */
    async function render(page, canvas, targetWidth, registerCancel = null) {
        const slot = slots[page.fileIndex];

        if (!slot) {
            throw new Error('That page belongs to a file that could not be opened.');
        }

        const ratio = Math.min(2.5, Math.max(1, window.devicePixelRatio || 1));
        const width = Math.max(240, Math.round(targetWidth));
        const height = Math.round((page.height / page.width) * width);

        canvas.width = Math.round(width * ratio);
        canvas.height = Math.round(height * ratio);
        canvas.style.width = `${width}px`;
        canvas.style.height = `${height}px`;

        const context = canvas.getContext('2d');
        context.clearRect(0, 0, canvas.width, canvas.height);

        if (slot.isPdf) {
            const pdfPage = await slot.doc.getPage(page.pageIndex + 1);
            const viewport = pdfPage.getViewport({ scale: (width * ratio) / page.width });
            const task = pdfPage.render({ canvas, viewport });

            registerCancel?.(() => {
                try {
                    task.cancel();
                } catch (error) {
                    // The task already finished.
                }
            });

            try {
                await task.promise;
            } finally {
                registerCancel?.(null);
            }
        } else {
            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, canvas.width, canvas.height);
            context.drawImage(slot.image, 0, 0, canvas.width, canvas.height);
        }

        return {
            width,
            height,
            pixelWidth: canvas.width,
            pixelHeight: canvas.height,
        };
    }

    function dispose() {
        slots.forEach((slot) => {
            try {
                slot.doc?.destroy?.();
            } catch (error) {
                // Nothing useful to do: the document is being torn down.
            }
        });
    }

    return {
        load,
        render,
        dispose,
        get pages() {
            return pages;
        },
        slots,
    };
}
