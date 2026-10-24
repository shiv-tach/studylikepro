import { jsPDF } from 'jspdf';
import {
    buildCommentNumbers,
    collectNotes,
    drawItems,
    drawStrokes,
    pageKey,
} from './renderAnnotations';

const EXPORT_WIDTH = 1300;
const A4 = { width: 595.28, height: 841.89 };
const MARGIN = 48;

function appendNotes(pdf, notes) {
    pdf.addPage([A4.width, A4.height], 'portrait');

    let y = MARGIN;

    pdf.setFont('helvetica', 'bold');
    pdf.setFontSize(16);
    pdf.text('Marking notes', MARGIN, y);
    y += 24;

    pdf.setFont('helvetica', 'normal');
    pdf.setFontSize(10);
    pdf.setTextColor(90, 90, 90);
    pdf.text('Comments and corrections placed on the marked pages.', MARGIN, y);
    y += 20;

    notes.forEach((note, index) => {
        const label = `${note.kind === 'correction' ? 'Correction' : 'Comment'} ${index + 1} — paper page ${note.page}`;

        pdf.setFont('helvetica', 'bold');
        pdf.setFontSize(11);
        pdf.setTextColor(20, 20, 20);

        if (y > A4.height - MARGIN - 40) {
            pdf.addPage([A4.width, A4.height], 'portrait');
            y = MARGIN;
        }

        pdf.text(label, MARGIN, y);
        y += 15;

        pdf.setFont('helvetica', 'normal');
        pdf.setFontSize(11);
        pdf.setTextColor(55, 55, 55);

        pdf.splitTextToSize(note.text, A4.width - MARGIN * 2).forEach((line) => {
            if (y > A4.height - MARGIN) {
                pdf.addPage([A4.width, A4.height], 'portrait');
                y = MARGIN;
            }

            pdf.text(line, MARGIN, y);
            y += 15;
        });

        y += 9;
    });
}

/**
 * Flatten the paper and its markup into one PDF the student can download.
 *
 * The pages are rasterised at `EXPORT_WIDTH`, which keeps handwriting legible
 * while staying well inside the upload limit; the annotation vectors are drawn
 * at that same resolution, so nothing turns blurry.
 *
 * @returns {Promise<{blob: Blob, size: number}>}
 */
export async function exportMarkedPdf({ paper, annotations, title }) {
    const pages = paper?.pages ?? [];

    if (pages.length === 0) {
        throw new Error('There are no rendered pages to export.');
    }

    const markup = new Map(
        (annotations?.pages ?? []).map((page) => [pageKey(page.file, page.page), page]),
    );
    const numbers = buildCommentNumbers(annotations);
    const notes = [];

    const canvas = document.createElement('canvas');
    let pdf = null;

    for (let index = 0; index < pages.length; index += 1) {
        const page = pages[index];
        const dimensions = await paper.render(page, canvas, EXPORT_WIDTH);
        const context = canvas.getContext('2d');
        const pageMarkup = markup.get(pageKey(page.fileIndex, page.pageIndex));

        if (pageMarkup) {
            drawStrokes(context, pageMarkup.strokes, dimensions.pixelWidth, dimensions.pixelHeight);
            drawItems(context, pageMarkup.items, {
                width: dimensions.pixelWidth,
                height: dimensions.pixelHeight,
                numbers,
                key: pageKey(page.fileIndex, page.pageIndex),
            });
            collectNotes(notes, pageMarkup.items, index + 1);
        }

        const data = canvas.toDataURL('image/jpeg', 0.85);
        const pageWidth = dimensions.width * 0.75;
        const pageHeight = dimensions.height * 0.75;
        const orientation = pageWidth > pageHeight ? 'landscape' : 'portrait';

        if (pdf === null) {
            pdf = new jsPDF({
                unit: 'pt',
                format: [pageWidth, pageHeight],
                orientation,
                compress: true,
            });
        } else {
            pdf.addPage([pageWidth, pageHeight], orientation);
        }

        pdf.addImage(data, 'JPEG', 0, 0, pageWidth, pageHeight);
    }

    if (notes.length > 0) {
        appendNotes(pdf, notes);
    }

    if (title) {
        pdf.setProperties({ title, subject: 'Marked paper' });
    }

    const blob = pdf.output('blob');

    return { blob, size: blob.size };
}

export function formatBytes(bytes) {
    if (!bytes) {
        return '0 KB';
    }

    if (bytes >= 1048576) {
        return `${(bytes / 1048576).toFixed(1)} MB`;
    }

    return `${Math.max(1, Math.round(bytes / 1024))} KB`;
}
