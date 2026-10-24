<?php

namespace App\Support;

use App\Enums\MarkingOrderStatus;
use App\Models\PaperMarkingOrder;
use App\Support\MarkingDisplay;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

/**
 * The JSON island behind /teacher/marking/{order}.
 *
 * Blade still owns every label, formatted date, amount, file size and download
 * route, so the Vue app never formats anything or builds a URL. The page answers
 * two questions — what am I marking, and what have I delivered — so the payload
 * carries the order's identity, the student's paper, the delivery form's state
 * (including a rejected submit) and, once delivered, the feedback and marks.
 *
 * The form's errors are collected by prefix, because Laravel stores a failed
 * upload as `marked_files.0` and a failed mark row as `marks.3.value`; selecting
 * the exact key would silently drop both.
 */
final class TeacherMarkingShowConfig
{
    /**
     * @param  int  $maxFiles  Mirrors `studylikepro.services.marking_max_files`.
     */
    public static function build(PaperMarkingOrder $order, int $maxFiles, string $preset): array
    {
        $canMark = in_array(
            $order->status,
            [MarkingOrderStatus::Submitted, MarkingOrderStatus::InProgress],
            true,
        );

        $studentName = $order->student?->name ?? $order->purchase?->student?->name ?? __('Student');

        return [
            'appName' => config('app.name', 'Studylikepro'),
            'csrfToken' => csrf_token(),
            'theme' => ['preset' => $preset],
            'notices' => self::notices(),
            'order' => self::order($order, $studentName),
            'files' => self::files($order, 'files', is_array($order->files) ? $order->files : []),
            'canMark' => $canMark,
            'form' => $canMark ? self::form($order, $maxFiles) : null,
            'delivery' => self::delivery($order),
            'dispute' => self::dispute($order),
            'copy' => self::copy($maxFiles),
            'urls' => [
                'index' => route('teacher.marking.index'),
                'orders' => route('teacher.orders.index'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function order(PaperMarkingOrder $order, string $studentName): array
    {
        $awaitingWork = in_array(
            $order->status,
            [MarkingOrderStatus::Submitted, MarkingOrderStatus::InProgress],
            true,
        );

        $deadline = $awaitingWork ? $order->deadlineAt() : null;
        $guidance = self::guidance($order->status);

        return [
            'id' => $order->id,
            'reference' => '#'.str_pad((string) $order->id, 5, '0', STR_PAD_LEFT),
            'subjectName' => $order->subject?->name ?? __('Paper marking'),
            'subjectIcon' => $order->subject?->icon ?: '📘',
            'status' => [
                'value' => $order->status->value,
                'label' => $order->status->label(),
                'tone' => MarkingDisplay::tone($order->status),
                'icon' => MarkingDisplay::icon($order->status),
            ],
            'overdue' => $order->isOverdue(),
            'headline' => $guidance['headline'],
            'guidance' => $guidance['body'],
            'studentName' => $studentName,
            'studentInitials' => MarkingDisplay::initials($studentName),
            'pagesLabel' => $order->page_count
                ? trans_choice(':count page|:count pages', $order->page_count, ['count' => $order->page_count])
                : null,
            'submittedLabel' => $order->submitted_at === null
                ? null
                : __('Submitted :when', ['when' => display_datetime($order->submitted_at)]),
            'deadlineLabel' => $deadline === null
                ? null
                : __('Due :time', ['time' => display_datetime($deadline)]),
            'priceLabel' => $order->purchase === null
                ? null
                : platform_settings()->formatMinor((int) $order->purchase->price_minor),
            'paidLabel' => $order->purchase?->paid_at === null
                ? null
                : display_datetime($order->purchase->paid_at),
            'orderUrl' => $order->purchase === null
                ? null
                : route('teacher.orders.show', $order->purchase),
        ];
    }

    /**
     * The delivery form's state, including the values a rejected submit replayed.
     *
     * @return array<string, mixed>
     */
    private static function form(PaperMarkingOrder $order, int $maxFiles): array
    {
        $messages = self::errorMessages();

        return [
            'action' => route('teacher.marking.mark', $order),
            'maxFiles' => $maxFiles,
            'maxFeedback' => 5000,
            'maxMarks' => 50,
            'accept' => '.jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf',
            'feedback' => (string) old('feedback', ''),
            'marks' => self::markRows(),
            'errors' => [
                'marked_files' => self::errorsWithPrefix($messages, 'marked_files'),
                'feedback' => $messages['feedback'] ?? [],
                'marks' => self::errorsWithPrefix($messages, 'marks'),
                'order' => isset($messages['order'][0]) ? (string) $messages['order'][0] : null,
            ],
        ];
    }

    /**
     * The marked copy the teacher already delivered — feedback, marks and files.
     *
     * @return array<string, mixed>|null
     */
    private static function delivery(PaperMarkingOrder $order): ?array
    {
        $markedFiles = is_array($order->marked_files) ? $order->marked_files : [];
        $feedback = trim((string) $order->feedback);

        if ($markedFiles === [] && $feedback === '' && $order->marks === null) {
            return null;
        }

        return [
            'feedback' => $feedback === '' ? null : $feedback,
            'marks' => collect(is_array($order->marks) ? $order->marks : [])
                ->filter(fn ($row) => is_array($row))
                ->map(fn (array $row) => [
                    'label' => (string) ($row['label'] ?? ''),
                    'value' => (string) ($row['value'] ?? ''),
                ])
                ->values()
                ->all(),
            'files' => self::files($order, 'marked_files', $markedFiles),
            'returnedLabel' => $order->returned_at === null
                ? null
                : __('Returned :when', ['when' => display_datetime($order->returned_at)]),
        ];
    }

    /**
     * @return array{title: string, reason: string}|null
     */
    private static function dispute(PaperMarkingOrder $order): ?array
    {
        if ($order->status !== MarkingOrderStatus::Disputed || blank($order->dispute_reason)) {
            return null;
        }

        return [
            'title' => __('The student disputed this delivery:'),
            'reason' => (string) $order->dispute_reason,
        ];
    }

    /**
     * @param  array<int, mixed>  $files
     * @return list<array{name: string, sizeLabel: string|null, url: string}>
     */
    private static function files(PaperMarkingOrder $order, string $kind, array $files): array
    {
        $rows = [];

        // The download route addresses a file by its position in the stored
        // array, so a dropped entry must not renumber the ones after it.
        foreach ($files as $index => $file) {
            if (! is_array($file) || blank($file['path'] ?? null)) {
                continue;
            }

            $rows[] = [
                'name' => (string) ($file['original_name'] ?? basename((string) $file['path'])),
                'sizeLabel' => self::sizeLabel((int) ($file['size'] ?? 0)),
                'url' => route('teacher.marking.download', [$order, $kind, $index]),
            ];
        }

        return $rows;
    }

    /**
     * A stored byte count as the teacher reads it ("1.2 MB").
     */
    private static function sizeLabel(int $bytes): ?string
    {
        if ($bytes <= 0) {
            return null;
        }

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1).' MB';
        }

        return max(1, (int) round($bytes / 1024)).' KB';
    }

    /**
     * The submitted mark rows, or one blank row so the first field is always
     * there. A rejected submit replays every row the teacher had typed.
     *
     * @return list<array{label: string, value: string}>
     */
    private static function markRows(): array
    {
        $old = old('marks');
        $rows = [];

        if (is_array($old)) {
            foreach ($old as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $rows[] = [
                    'label' => (string) ($row['label'] ?? ''),
                    'value' => (string) ($row['value'] ?? ''),
                ];
            }
        }

        return $rows === [] ? [['label' => '', 'value' => '']] : $rows;
    }

    /**
     * Every validation message keyed by field, flattened from the session bag.
     *
     * @return array<string, list<string>>
     */
    private static function errorMessages(): array
    {
        $bag = session('errors');

        if ($bag instanceof ViewErrorBag) {
            $bag = $bag->getBag('default');
        }

        return $bag instanceof MessageBag ? $bag->getMessages() : [];
    }

    /**
     * Messages stored under a field or any of its children (`marks.3.value`).
     *
     * @param  array<string, list<string>>  $messages
     * @return list<string>
     */
    private static function errorsWithPrefix(array $messages, string $prefix): array
    {
        return collect($messages)
            ->filter(fn (array $items, string $key) => $key === $prefix || str_starts_with($key, $prefix.'.'))
            ->flatten()
            ->values()
            ->all();
    }

    /**
     * What this status asks of the teacher, in one sentence plus the why.
     *
     * @return array{headline: string, body: string}
     */
    private static function guidance(MarkingOrderStatus $status): array
    {
        return match ($status) {
            MarkingOrderStatus::Submitted => [
                'headline' => __('This paper is waiting to be marked.'),
                'body' => __("Download the student's paper, mark it, then upload the marked copy with your feedback."),
            ],
            MarkingOrderStatus::InProgress => [
                'headline' => __('You have started marking this paper.'),
                'body' => __('Deliver the marked copy before the deadline to keep your turnaround promise.'),
            ],
            MarkingOrderStatus::Returned => [
                'headline' => __('Marked copy delivered.'),
                'body' => __('The student can accept the marking or raise a dispute while the delivery is fresh.'),
            ],
            MarkingOrderStatus::Accepted => [
                'headline' => __('The student accepted your marking.'),
                'body' => __('This job is closed — the delivery stays on the order record.'),
            ],
            MarkingOrderStatus::Disputed => [
                'headline' => __('The student disputed this delivery.'),
                'body' => __('Support reviews the delivery and the dispute; you do not need to upload anything else.'),
            ],
            MarkingOrderStatus::Resolved => [
                'headline' => __('Support resolved this order.'),
                'body' => __('The delivery stays on the order record for reference.'),
            ],
            MarkingOrderStatus::Cancelled => [
                'headline' => __('This order was cancelled.'),
                'body' => __('No marking is expected on this paper.'),
            ],
        };
    }

    /**
     * @return list<array<string, string>>
     */
    private static function notices(): array
    {
        $flash = MarkingDisplay::flash();

        return $flash === null ? [] : [$flash];
    }

    /**
     * @return array<string, mixed>
     */
    private static function copy(int $maxFiles): array
    {
        return [
            'hero' => [
                'eyebrow' => __('Paper marking'),
                'metaLabel' => __('Order details'),
                'student' => __('Student'),
                'pages' => __('Paper length'),
                'submitted' => __('Submitted'),
                'deadline' => __('Deadline'),
                'price' => __('Order price'),
                'paid' => __('Paid'),
                'overdue' => __('Overdue'),
                'openOrder' => __('Open the order'),
            ],
            'files' => [
                'title' => __('Uploaded paper'),
                'intro' => __('The file the student paid to have marked.'),
                'empty' => __('No files uploaded.'),
                'download' => __('Download'),
            ],
            'form' => [
                'title' => __('Deliver the marking'),
                'intro' => __('The student is notified the moment you deliver.'),
                'filesLabel' => __('Marked copy'),
                'filesHint' => __('Upload the marked scans or PDFs — up to :count files, 5 MB each.', ['count' => $maxFiles]),
                'fileChoose' => __('Choose marked files'),
                'fileDropHint' => __('or drag and drop them here'),
                'fileTypes' => __('JPG, PNG, WebP or PDF'),
                'fileRemove' => __('Remove'),
                'tooManyFiles' => __('Pick at most :count files.', ['count' => $maxFiles]),
                'feedbackLabel' => __('Feedback'),
                'feedbackHint' => __('What went well, what to fix — up to :count characters.', ['count' => 5000]),
                'feedbackPlaceholder' => __('Solid work on the first half — watch the steps you circled.'),
                'marksLabel' => __('Marks (optional)'),
                'marksHint' => __('One row per question or section. Blank rows are ignored.'),
                'addRow' => __('Add mark row'),
                'removeRow' => __('Remove'),
                'labelPlaceholder' => __('Question / section'),
                'valuePlaceholder' => __('Marks'),
                'submit' => __('Deliver marking'),
                'submitting' => __('Delivering…'),
            ],
            'delivery' => [
                'title' => __('Your delivery'),
                'feedback' => __('Feedback'),
                'marks' => __('Marks'),
                'files' => __('Marked copy'),
            ],
            'toast' => [
                'label' => __('Notifications'),
                'dismiss' => __('Dismiss'),
            ],
        ];
    }
}
