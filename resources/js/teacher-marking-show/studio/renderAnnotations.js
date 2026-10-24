/**
 * Canvas drawing shared by the live studio overlay and the PDF export, so the
 * delivered copy matches what the teacher saw. All coordinates arrive
 * normalised (0–1); `width`/`height` are the pixel dimensions being drawn
 * into, which makes the same code work at screen size and export resolution.
 */
const HIGHLIGHTER_ALPHA = 0.35;
const FONT_STACK = '"Plus Jakarta Sans", Inter, system-ui, sans-serif';

/** Stamp glyph height as a fraction of the page width. */
export const DEFAULT_STAMP_SIZE = 0.024;
export const MIN_STAMP_SIZE = 0.012;
export const MAX_STAMP_SIZE = 0.08;

/**
 * The stamp's glyph size, clamped to what the paper and the server accept.
 *
 * @returns {number} fraction of the page width
 */
export function stampSizeOf(item) {
    const size = Number(item?.size);

    if (!Number.isFinite(size) || size <= 0) {
        return DEFAULT_STAMP_SIZE;
    }

    return Math.min(MAX_STAMP_SIZE, Math.max(MIN_STAMP_SIZE, size));
}

export function pageKey(file, page) {
    return `${Number(file) || 0}:${Number(page) || 0}`;
}

function roundRect(ctx, x, y, width, height, radius) {
    const r = Math.min(radius, width / 2, height / 2);

    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + width, y, x + width, y + height, r);
    ctx.arcTo(x + width, y + height, x, y + height, r);
    ctx.arcTo(x, y + height, x, y, r);
    ctx.arcTo(x, y, x + width, y, r);
    ctx.closePath();
}

function wrapText(ctx, text, maxWidth) {
    const words = String(text).split(/\s+/).filter(Boolean);
    const lines = [];
    let line = '';

    words.forEach((word) => {
        const candidate = line === '' ? word : `${line} ${word}`;

        if (ctx.measureText(candidate).width <= maxWidth) {
            line = candidate;

            return;
        }

        if (line !== '') {
            lines.push(line);
        }

        // A single word wider than the box (a URL, say) is chunked.
        if (ctx.measureText(word).width > maxWidth) {
            let chunk = '';

            for (const character of word) {
                if (ctx.measureText(chunk + character).width > maxWidth && chunk !== '') {
                    lines.push(chunk);
                    chunk = character;
                } else {
                    chunk += character;
                }
            }

            line = chunk;
        } else {
            line = word;
        }
    });

    if (line !== '') {
        lines.push(line);
    }

    return lines.length === 0 ? [''] : lines;
}

export function drawStrokes(ctx, strokes, width, height) {
    if (!Array.isArray(strokes) || strokes.length === 0) {
        return;
    }

    strokes.forEach((stroke) => {
        const points = (stroke.points ?? []).filter((point) => Array.isArray(point) && point.length >= 2);

        if (points.length < 2) {
            return;
        }

        const lineWidth = Math.max(1, (Number(stroke.width) || 0.003) * width);

        ctx.save();
        ctx.lineWidth = lineWidth;
        ctx.strokeStyle = stroke.color || '#e11d48';
        ctx.lineJoin = 'round';
        ctx.lineCap = stroke.tool === 'highlighter' ? 'butt' : 'round';
        ctx.globalAlpha = stroke.tool === 'highlighter' ? HIGHLIGHTER_ALPHA : 1;
        ctx.beginPath();

        if (stroke.tool === 'ellipse') {
            const first = points[0];
            const last = points[points.length - 1];
            const cx = ((Number(first[0]) + Number(last[0])) / 2) * width;
            const cy = ((Number(first[1]) + Number(last[1])) / 2) * height;
            const rx = Math.max(Math.abs(Number(last[0]) - Number(first[0])) / 2 * width, lineWidth);
            const ry = Math.max(Math.abs(Number(last[1]) - Number(first[1])) / 2 * height, lineWidth);

            ctx.ellipse(cx, cy, rx, ry, 0, 0, Math.PI * 2);
        } else if (stroke.tool === 'line') {
            const first = points[0];
            const last = points[points.length - 1];

            ctx.moveTo(Number(first[0]) * width, Number(first[1]) * height);
            ctx.lineTo(Number(last[0]) * width, Number(last[1]) * height);
        } else {
            points.forEach((point, index) => {
                const x = Number(point[0]) * width;
                const y = Number(point[1]) * height;

                if (index === 0) {
                    ctx.moveTo(x, y);
                } else {
                    ctx.lineTo(x, y);
                }
            });
        }

        ctx.stroke();
        ctx.restore();
    });
}

function stampGlyph(item, stamps) {
    const configured = (stamps ?? []).find((stamp) => stamp.key === item.stamp);

    return item.icon || configured?.icon || String(item.text ?? '').trim() || '';
}

function drawStamp(ctx, item, width, height, stamps) {
    // A stamp is only its mark: the bare glyph in the stamp colour, exactly like
    // the live studio overlay. No badge behind it, no label, no points.
    const glyph = stampGlyph(item, stamps);

    if (glyph === '') {
        return;
    }

    const size = Math.max(12, stampSizeOf(item) * width);
    const x = item.x * width;
    const y = item.y * height;

    ctx.save();
    ctx.fillStyle = item.color || '#4f46e5';
    ctx.font = `700 ${Math.round(size)}px ${FONT_STACK}`;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(glyph, x, y);
    ctx.restore();
}

function drawCommentPin(ctx, item, width, height, number) {
    const radius = Math.max(8, width * 0.011);
    const x = item.x * width;
    const y = item.y * height;

    ctx.save();
    ctx.beginPath();
    ctx.arc(x, y, radius, 0, Math.PI * 2);
    ctx.fillStyle = '#4f46e5';
    ctx.fill();
    ctx.lineWidth = Math.max(1.5, radius * 0.22);
    ctx.strokeStyle = '#ffffff';
    ctx.stroke();

    ctx.fillStyle = '#ffffff';
    ctx.font = `700 ${Math.round(radius * 0.95)}px ${FONT_STACK}`;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(number === null ? '•' : String(number), x, y + radius * 0.04);
    ctx.restore();
}

function drawCorrection(ctx, item, width, height) {
    const text = String(item.text ?? '').trim();

    if (text === '') {
        return;
    }

    const boxWidth = Math.min(width * 0.5, width - 20);
    const padding = Math.max(6, width * 0.008);
    const fontSize = Math.max(9, width * 0.0125);
    const titleSize = Math.max(7, fontSize * 0.78);
    const lineHeight = fontSize * 1.35;

    ctx.save();
    ctx.font = `500 ${fontSize}px ${FONT_STACK}`;
    const lines = wrapText(ctx, text, boxWidth - padding * 2);
    const boxHeight = padding * 2 + titleSize + 6 + lines.length * lineHeight;
    const x = Math.min(Math.max(item.x * width, 6), Math.max(6, width - boxWidth - 6));
    const y = Math.min(Math.max(item.y * height - boxHeight / 2, 6), Math.max(6, height - boxHeight - 6));

    roundRect(ctx, x, y, boxWidth, boxHeight, 7);
    ctx.fillStyle = 'rgba(4, 47, 46, 0.95)';
    ctx.fill();
    ctx.lineWidth = Math.max(1.2, fontSize * 0.12);
    ctx.strokeStyle = '#34d399';
    ctx.stroke();

    ctx.textAlign = 'left';
    ctx.textBaseline = 'top';
    ctx.fillStyle = '#6ee7b7';
    ctx.font = `700 ${titleSize}px ${FONT_STACK}`;
    ctx.fillText('CORRECTION', x + padding, y + padding);

    ctx.fillStyle = '#ecfdf5';
    ctx.font = `500 ${fontSize}px ${FONT_STACK}`;
    lines.forEach((line, index) => {
        ctx.fillText(line, x + padding, y + padding + titleSize + 6 + index * lineHeight);
    });
    ctx.restore();
}

/**
 * @param {CanvasRenderingContext2D} ctx
 * @param {Array<object>} items
 * @param {{width: number, height: number, numbers?: Map<string, number>, key?: string, stamps?: Array<object>}} options
 */
export function drawItems(ctx, items, options) {
    if (!Array.isArray(items) || items.length === 0) {
        return;
    }

    const { width, height, numbers = new Map(), key = '', stamps = [] } = options;

    items.forEach((item, index) => {
        if (item.type === 'stamp') {
            drawStamp(ctx, item, width, height, stamps);
        } else if (item.type === 'comment') {
            drawCommentPin(ctx, item, width, height, numbers.get(`${key}:${index}`) ?? null);
        } else if (item.type === 'correction') {
            drawCorrection(ctx, item, width, height);
        }
    });
}

/**
 * Comment pins and correction cards are numbered in reading order across the
 * whole paper, so the notes appendix can refer to "note 3" unambiguously.
 *
 * @returns {Map<string, number>}
 */
export function buildCommentNumbers(annotations) {
    const numbers = new Map();
    let next = 0;

    (annotations?.pages ?? []).forEach((page) => {
        const key = pageKey(page.file, page.page);

        (page.items ?? []).forEach((item, index) => {
            if (item.type === 'comment' || item.type === 'correction') {
                next += 1;
                numbers.set(`${key}:${index}`, next);
            }
        });
    });

    return numbers;
}

/**
 * The text appendix appended after the marked pages.
 *
 * @param {Array<{page: number, kind: string, text: string}>} target
 * @param {Array<object>} items
 * @param {number} pageNumber 1-based index in the exported PDF
 */
export function collectNotes(target, items, pageNumber) {
    (items ?? []).forEach((item) => {
        if ((item.type === 'comment' || item.type === 'correction') && String(item.text ?? '').trim() !== '') {
            target.push({
                page: pageNumber,
                kind: item.type,
                text: String(item.text).trim(),
            });
        }
    });
}
