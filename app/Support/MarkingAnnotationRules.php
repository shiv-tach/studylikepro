<?php

namespace App\Support;

use Illuminate\Validation\Rule;

/**
 * One place for the shape of the marking studio's JSON payloads.
 *
 * Annotations are vectors rather than pixels: every coordinate is normalised
 * to the page (0–1) and every stroke width is a fraction of the page width,
 * so the same markup renders correctly in the studio at any zoom and in the
 * flattened PDF export at any resolution.
 */
final class MarkingAnnotationRules
{
    public const VERSION = 1;

    /** Pages a single order may hold (10 files × a few dozen pages). */
    public const MAX_PAGES = 300;

    public const MAX_STROKES_PER_PAGE = 500;

    public const MAX_POINTS_PER_STROKE = 1000;

    public const MAX_ITEMS_PER_PAGE = 200;

    public const MAX_TEXT = 1000;

    public const MAX_RUBRIC_CRITERIA = 20;

    /** @var list<string> */
    public const STROKE_TOOLS = ['pen', 'highlighter', 'line', 'ellipse'];

    /** @var list<string> */
    public const ITEM_TYPES = ['comment', 'stamp', 'correction'];

    public const COLOR_PATTERN = '/^#[0-9a-fA-F]{6}([0-9a-fA-F]{2})?$/';

    /**
     * @return array<string, mixed>
     */
    public static function rules(string $prefix = 'annotations'): array
    {
        return [
            $prefix => ['nullable', 'array'],
            $prefix.'.version' => ['nullable', 'integer', 'in:'.self::VERSION],
            $prefix.'.pages' => ['nullable', 'array', 'max:'.self::MAX_PAGES],
            $prefix.'.pages.*' => ['array'],
            $prefix.'.pages.*.file' => ['required', 'integer', 'min:0', 'max:49'],
            $prefix.'.pages.*.page' => ['required', 'integer', 'min:0', 'max:500'],
            $prefix.'.pages.*.strokes' => ['nullable', 'array', 'max:'.self::MAX_STROKES_PER_PAGE],
            $prefix.'.pages.*.strokes.*' => ['array'],
            $prefix.'.pages.*.strokes.*.tool' => ['required', 'string', Rule::in(self::STROKE_TOOLS)],
            $prefix.'.pages.*.strokes.*.color' => ['required', 'string', 'regex:'.self::COLOR_PATTERN],
            $prefix.'.pages.*.strokes.*.width' => ['required', 'numeric', 'min:0.0005', 'max:0.08'],
            $prefix.'.pages.*.strokes.*.points' => ['required', 'array', 'min:2', 'max:'.self::MAX_POINTS_PER_STROKE],
            $prefix.'.pages.*.strokes.*.points.*' => ['array', 'size:2'],
            $prefix.'.pages.*.strokes.*.points.*.*' => ['numeric', 'min:0', 'max:1'],
            $prefix.'.pages.*.items' => ['nullable', 'array', 'max:'.self::MAX_ITEMS_PER_PAGE],
            $prefix.'.pages.*.items.*' => ['array'],
            $prefix.'.pages.*.items.*.type' => ['required', 'string', Rule::in(self::ITEM_TYPES)],
            $prefix.'.pages.*.items.*.x' => ['required', 'numeric', 'min:0', 'max:1'],
            $prefix.'.pages.*.items.*.y' => ['required', 'numeric', 'min:0', 'max:1'],
            $prefix.'.pages.*.items.*.text' => ['nullable', 'string', 'max:'.self::MAX_TEXT],
            $prefix.'.pages.*.items.*.stamp' => ['nullable', 'string', 'max:40'],
            $prefix.'.pages.*.items.*.color' => ['nullable', 'string', 'regex:'.self::COLOR_PATTERN],
            $prefix.'.pages.*.items.*.points' => ['nullable', 'numeric', 'min:-100', 'max:100'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function rubricScoreRules(string $prefix = 'rubric_scores'): array
    {
        return [
            $prefix => ['nullable', 'array', 'max:'.self::MAX_RUBRIC_CRITERIA],
            $prefix.'.*' => ['array'],
            $prefix.'.*.key' => ['required', 'string', 'max:60'],
            $prefix.'.*.score' => ['required', 'numeric', 'min:0', 'max:1000'],
        ];
    }

    /**
     * Round, clamp and trim the payload so the stored JSON stays small and
     * predictable. Pages with no markup are dropped; an empty payload is null.
     *
     * @param  array<string, mixed>|null  $payload
     * @return array{version: int, pages: list<array{file: int, page: int, strokes: list<array{tool: string, color: string, width: float, points: list<array{0: float, 1: float}>}>, items: list<array<string, mixed>>}>}|null
     */
    public static function normalize(?array $payload): ?array
    {
        if (! is_array($payload)) {
            return null;
        }

        $rawPages = $payload['pages'] ?? null;

        if (! is_array($rawPages)) {
            return null;
        }

        $pages = [];

        foreach ($rawPages as $rawPage) {
            if (! is_array($rawPage)) {
                continue;
            }

            $strokes = self::normalizeStrokes($rawPage['strokes'] ?? null);
            $items = self::normalizeItems($rawPage['items'] ?? null);

            if ($strokes === [] && $items === []) {
                continue;
            }

            $pages[] = [
                'file' => max(0, (int) ($rawPage['file'] ?? 0)),
                'page' => max(0, (int) ($rawPage['page'] ?? 0)),
                'strokes' => $strokes,
                'items' => $items,
            ];
        }

        return $pages === [] ? null : ['version' => self::VERSION, 'pages' => $pages];
    }

    /**
     * @param  mixed  $raw
     * @return list<array{tool: string, color: string, width: float, points: list<array{0: float, 1: float}>}>
     */
    private static function normalizeStrokes(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $strokes = [];

        foreach ($raw as $stroke) {
            if (! is_array($stroke)) {
                continue;
            }

            $points = [];

            foreach (is_array($stroke['points'] ?? null) ? $stroke['points'] : [] as $point) {
                if (! is_array($point) || count($point) < 2) {
                    continue;
                }

                if (! is_numeric($point[0] ?? null) || ! is_numeric($point[1] ?? null)) {
                    continue;
                }

                $points[] = [self::coordinate($point[0]), self::coordinate($point[1])];
            }

            if (count($points) < 2) {
                continue;
            }

            $strokes[] = [
                'tool' => (string) ($stroke['tool'] ?? 'pen'),
                'color' => (string) ($stroke['color'] ?? '#e11d48'),
                'width' => self::width($stroke['width'] ?? 0.003),
                'points' => $points,
            ];
        }

        return $strokes;
    }

    /**
     * @param  mixed  $raw
     * @return list<array<string, mixed>>
     */
    private static function normalizeItems(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $items = [];

        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }

            $text = trim((string) ($item['text'] ?? ''));
            $type = (string) ($item['type'] ?? '');

            // A pin or card without text is not worth keeping.
            if ($type !== 'stamp' && $text === '') {
                continue;
            }

            $items[] = [
                'type' => $type,
                'x' => self::coordinate($item['x'] ?? 0),
                'y' => self::coordinate($item['y'] ?? 0),
                'text' => mb_substr($text, 0, self::MAX_TEXT),
                'stamp' => filled($item['stamp'] ?? null) ? mb_substr((string) $item['stamp'], 0, 40) : null,
                'color' => filled($item['color'] ?? null) ? (string) $item['color'] : null,
                'points' => is_numeric($item['points'] ?? null) ? (float) $item['points'] : null,
            ];
        }

        return $items;
    }

    private static function coordinate(mixed $value): float
    {
        return round(min(1, max(0, (float) $value)), 4);
    }

    private static function width(mixed $value): float
    {
        return round(min(0.08, max(0.0005, (float) $value)), 5);
    }
}
