<?php

namespace App\Support;

use App\Enums\MarkingOrderStatus;
use App\Models\PaperMarkingOrder;
use App\Support\MarkingDisplay;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The JSON island behind /teacher/marking.
 *
 * Blade still owns every label, formatted date, amount and route, so the Vue app
 * never formats a date, builds a plural rule or hardcodes a URL. The page answers
 * one question — what paper needs me next — so the payload carries the status
 * rail (with whole-inbox counts), the hero numbers and the current page of
 * orders.
 *
 * Orders are only ever listed when their purchase has been paid; the controller
 * filters that before pagination, so an unpaid submission never reaches here.
 */
final class TeacherMarkingConfig
{
    /**
     * @param  LengthAwarePaginator<int, PaperMarkingOrder>  $orders  The current (filtered) page of paid orders.
     * @param  array<string, int>  $statusCounts  Status value => row count, over the whole inbox.
     */
    public static function build(
        LengthAwarePaginator $orders,
        array $statusCounts,
        int $overdue,
        ?string $selectedStatus,
        string $preset,
    ): array {
        $counts = collect(MarkingOrderStatus::cases())
            ->mapWithKeys(fn (MarkingOrderStatus $status) => [
                $status->value => (int) ($statusCounts[$status->value] ?? 0),
            ])
            ->all();

        return [
            'appName' => config('app.name', 'Studylikepro'),
            'theme' => ['preset' => $preset],
            'notices' => self::notices(),
            'stats' => [
                'awaiting' => $counts[MarkingOrderStatus::Submitted->value] + $counts[MarkingOrderStatus::InProgress->value],
                'overdue' => $overdue,
                'returned' => $counts[MarkingOrderStatus::Returned->value],
            ],
            'statusTabs' => self::statusTabs($counts, $selectedStatus),
            'orders' => collect($orders->items())
                ->map(fn (PaperMarkingOrder $order) => self::card($order))
                ->values()
                ->all(),
            'pagination' => [
                'visible' => $orders->count(),
                'total' => $orders->total(),
            ],
            'copy' => self::copy(),
            'urls' => [
                'index' => route('teacher.marking.index'),
                'services' => route('teacher.services.index'),
                'orders' => route('teacher.orders.index'),
            ],
        ];
    }

    /**
     * Every status is offered, never only the ones already in use: an empty tab
     * is exactly what tells the teacher there is nothing at that stage yet.
     *
     * @param  array<string, int>  $counts
     * @return list<array<string, mixed>>
     */
    private static function statusTabs(array $counts, ?string $selectedStatus): array
    {
        $tabs = [[
            'key' => '',
            'label' => __('All papers'),
            'count' => array_sum($counts),
            'url' => route('teacher.marking.index'),
            'active' => $selectedStatus === null,
        ]];

        foreach (MarkingOrderStatus::cases() as $status) {
            $tabs[] = [
                'key' => $status->value,
                'label' => $status->label(),
                'count' => $counts[$status->value] ?? 0,
                'url' => route('teacher.marking.index', ['status' => $status->value]),
                'active' => $selectedStatus === $status->value,
            ];
        }

        return $tabs;
    }

    /**
     * One order in the inbox: what the paper is, who sent it, what it is worth
     * and how long the teacher has left.
     *
     * @return array<string, mixed>
     */
    private static function card(PaperMarkingOrder $order): array
    {
        $awaitingWork = in_array(
            $order->status,
            [MarkingOrderStatus::Submitted, MarkingOrderStatus::InProgress],
            true,
        );

        $deadline = $awaitingWork ? $order->deadlineAt() : null;
        $studentName = $order->purchase?->student?->name ?? __('Student');

        return [
            'id' => $order->id,
            'reference' => '#'.str_pad((string) $order->id, 5, '0', STR_PAD_LEFT),
            'url' => route('teacher.marking.show', $order),
            'subjectName' => $order->subject?->name ?? __('Paper marking'),
            'subjectIcon' => $order->subject?->icon ?: '📘',
            'status' => [
                'value' => $order->status->value,
                'label' => $order->status->label(),
                'tone' => MarkingDisplay::tone($order->status),
                'icon' => MarkingDisplay::icon($order->status),
            ],
            'overdue' => $order->isOverdue(),
            'submittedLabel' => $order->submitted_at?->diffForHumans(),
            'studentName' => $studentName,
            'studentInitials' => MarkingDisplay::initials($studentName),
            'pagesLabel' => $order->page_count
                ? trans_choice(':count page|:count pages', $order->page_count, ['count' => $order->page_count])
                : null,
            'priceLabel' => platform_settings()->formatMinor($order->purchase?->price_minor ?? 0),
            'deadlineLabel' => $deadline === null
                ? null
                : __('Due :time', ['time' => display_datetime($deadline, 'd M')]),
            'actionLabel' => MarkingDisplay::actionLabel($order->status),
        ];
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
    private static function copy(): array
    {
        return [
            'hero' => [
                'eyebrow' => __('Paper marking'),
                'title' => __('Marking inbox'),
                'intro' => __('Every paid paper that needs your pen, what it is worth and how long the student is waiting.'),
                'statsLabel' => __('Your marking queue at a glance'),
                'awaitingChip' => ':count paper to mark|:count papers to mark',
                'overdueChip' => ':count overdue|:count overdue',
                'returnedChip' => ':count returned|:count returned',
                'offersNote' => __('Only papers bought from your published marking offers land here.'),
                'offersCta' => __('Manage marking offers'),
            ],
            'rail' => [
                'label' => __('Filter by marking status'),
            ],
            'card' => [
                'overdue' => __('Overdue'),
                'due' => __('Deadline'),
                'student' => __('Student'),
                'marks' => __('Marks attached'),
            ],
            'empty' => [
                'title' => __('No paper-marking orders here yet.'),
                'body' => __('Once a student pays for your marking offer, their paper appears in this inbox.'),
                'cta' => __('See your marking offers'),
            ],
            'toast' => [
                'label' => __('Notifications'),
                'dismiss' => __('Dismiss'),
            ],
        ];
    }
}
