<?php

namespace App\Support;

use App\Enums\EarningStatus;
use App\Enums\ServiceType;
use App\Models\Payout;
use App\Models\TeacherEarning;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * The JSON island behind /teacher/earnings.
 *
 * Blade (well, this class on Blade's behalf) owns every amount, date, status
 * label and route, so the Vue app never formats money, builds a plural rule or
 * hardcodes a URL. The page answers four questions — what can I be paid now,
 * what is still clearing, where did it come from, and what has been paid out —
 * so the payload carries the totals, the status tabs, the ledger page, the
 * monthly trend and the latest payouts.
 *
 * The ledger is still paginated by Laravel: its links stay Blade-rendered
 * below the mount, because they are plain URLs and must work without Vue.
 */
final class TeacherEarningsConfig
{
    /**
     * Service types carry a glyph in the shared icon set, never an emoji: the
     * ledger row and the order card must speak the same visual language.
     *
     * @var array<string, string>
     */
    private const TYPE_ICONS = [
        ServiceType::Lesson->value => 'graduation',
        ServiceType::PaperMarking->value => 'clipboard',
        ServiceType::PaperDiscussion->value => 'chat',
        ServiceType::Qna->value => 'sparkles',
    ];

    /**
     * @param  array{available: int, pending: int, lifetime: int, reversed: int, paid: int}  $totals
     * @param  LengthAwarePaginator<int, TeacherEarning>  $ledger  The current (filtered) ledger page.
     * @param  Collection<int, Payout>  $payouts  The most recent batches.
     * @param  array<string, int>  $counts  Status => row count, plus `all`.
     * @param  list<array{key: string, label: string, minor: int, count: int}>  $trend  Oldest month first.
     */
    public static function build(
        array $totals,
        LengthAwarePaginator $ledger,
        Collection $payouts,
        array $counts,
        ?string $status,
        array $trend,
        string $timezone,
        string $preset,
    ): array {
        $money = fn (int $minor): string => platform_settings()->formatMinor($minor);

        return [
            'appName' => config('app.name', 'Studylikepro'),
            'theme' => ['preset' => $preset],
            // Money is formatted by Blade; the count-up only animates `/100`
            // behind the same prefix and the same two decimals.
            'money' => [
                'prefix' => platform_settings()->currencySymbol(),
                'decimals' => 2,
            ],
            'commissionPercent' => platform_settings()->int('commission_percent'),
            'stats' => self::stats($totals, $money),
            'statusTabs' => self::statusTabs($counts, $status),
            'ledger' => self::ledger($ledger, $timezone, $money),
            'trend' => self::trend($trend, $money),
            'payouts' => self::payouts($payouts, $money),
            'pagination' => [
                'visible' => $ledger->count(),
                'total' => $ledger->total(),
                'page' => $ledger->currentPage(),
                'lastPage' => $ledger->lastPage(),
                'filtered' => $status !== null,
            ],
            'copy' => self::copy(),
            'urls' => [
                'index' => route('teacher.earnings.index'),
                'schedule' => route('teacher.schedule.index'),
                'orders' => route('teacher.orders.index'),
            ],
        ];
    }

    /**
     * The four money tiles. "Available for payout" is the one a teacher acts
     * on, so it leads; lifetime earned sits last because it is context, not an
     * action.
     *
     * @param  array{available: int, pending: int, lifetime: int, reversed: int, paid: int}  $totals
     * @return array<string, mixed>
     */
    private static function stats(array $totals, callable $money): array
    {
        return [
            'tiles' => [
                [
                    'key' => 'available',
                    'label' => __('Available for payout'),
                    'caption' => __('Ready to be batched into a transfer'),
                    'minor' => $totals['available'],
                    'valueLabel' => $money($totals['available']),
                    'tone' => 'emerald',
                    'icon' => 'wallet',
                ],
                [
                    'key' => 'pending',
                    'label' => __('Pending lessons'),
                    'caption' => __('Clears once the lesson or service is delivered'),
                    'minor' => $totals['pending'],
                    'valueLabel' => $money($totals['pending']),
                    'tone' => 'amber',
                    'icon' => 'clock',
                ],
                [
                    'key' => 'paid',
                    'label' => __('Paid out'),
                    'caption' => __('Transferred to you by the Studylikepro team'),
                    'minor' => $totals['paid'],
                    'valueLabel' => $money($totals['paid']),
                    'tone' => 'primary',
                    'icon' => 'check-circle',
                ],
                [
                    'key' => 'lifetime',
                    'label' => __('Lifetime earned'),
                    'caption' => __('Every paid lesson and service, before refunds'),
                    'minor' => $totals['lifetime'],
                    'valueLabel' => $money($totals['lifetime']),
                    'tone' => 'slate',
                    'icon' => 'chart',
                ],
            ],
            'reversed' => [
                'minor' => $totals['reversed'],
                'label' => $totals['reversed'] > 0
                    ? __('Reversed by refunds: :amount.', ['amount' => $money($totals['reversed'])])
                    : null,
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
    private static function statusTabs(array $counts, ?string $status): array
    {
        $tabs = [[
            'key' => '',
            'label' => __('All earnings'),
            'count' => (int) ($counts['all'] ?? 0),
            'url' => route('teacher.earnings.index'),
            'active' => $status === null,
        ]];

        foreach (EarningStatus::cases() as $case) {
            $tabs[] = [
                'key' => $case->value,
                'label' => $case->label(),
                'count' => (int) ($counts[$case->value] ?? 0),
                'url' => route('teacher.earnings.index', ['status' => $case->value]),
                'active' => $status === $case->value,
            ];
        }

        return $tabs;
    }

    /**
     * One page of the ledger, already resolved to the shape the row and the card
     * render: what was sold, to whom, when, where the money sits, and what it is
     * worth after any refund.
     *
     * @param  LengthAwarePaginator<int, TeacherEarning>  $ledger
     * @return list<array<string, mixed>>
     */
    private static function ledger(LengthAwarePaginator $ledger, string $timezone, callable $money): array
    {
        return collect($ledger->items())
            ->map(function (TeacherEarning $earning) use ($timezone, $money): array {
                $booking = $earning->booking;
                $purchase = $earning->servicePurchase;

                if ($booking !== null) {
                    $title = collect([$booking->subject?->name, $booking->lesson?->name])
                        ->filter()
                        ->implode(' · ');

                    $row = [
                        'title' => $title !== '' ? $title : __('Tutoring lesson'),
                        'typeLabel' => __('Lesson'),
                        'typeIcon' => 'graduation',
                        'url' => route('teacher.bookings.show', $booking),
                    ];
                } elseif ($purchase !== null) {
                    $row = [
                        'title' => $purchase->scopeLabel(),
                        'typeLabel' => $purchase->type()->label(),
                        'typeIcon' => self::TYPE_ICONS[$purchase->type()->value] ?? 'tag',
                        'url' => route('teacher.orders.show', $purchase),
                    ];
                } else {
                    $row = [
                        'title' => __('Earning'),
                        'typeLabel' => null,
                        'typeIcon' => 'wallet',
                        'url' => null,
                    ];
                }

                // The lesson date is the clearest "when" for a booking; a
                // service purchase has no lesson clock, so it falls back to
                // when the money becomes available and then to when it was
                // recorded.
                $when = $booking?->starts_at ?? $earning->available_at ?? $earning->created_at;

                return [
                    'id' => $earning->id,
                    ...$row,
                    'subtitle' => $booking?->learner_name
                        ?: ($booking?->student?->name ?? $purchase?->student?->name),
                    'dateLabel' => $when?->copy()->setTimezone($timezone)->format('d M Y'),
                    'statusLabel' => $earning->status->label(),
                    'statusTone' => EarningDisplay::tone($earning->status),
                    'statusIcon' => EarningDisplay::icon($earning->status),
                    'payoutReference' => $earning->payout?->reference,
                    'amountMinor' => $earning->amount_minor,
                    'amountLabel' => $money($earning->amount_minor),
                    'reversedMinor' => $earning->reversed_minor,
                    'reversedLabel' => $earning->reversed_minor > 0
                        ? '−'.$money($earning->reversed_minor)
                        : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Six months of net earnings, ready to draw: each bar carries its own
     * percentage so the component only sets a height.
     *
     * @param  list<array{key: string, label: string, minor: int, count: int}>  $trend
     * @return array<string, mixed>
     */
    private static function trend(array $trend, callable $money): array
    {
        $max = collect($trend)->max('minor') ?? 0;
        $total = (int) collect($trend)->sum('minor');

        return [
            'max' => $max,
            'totalLabel' => $money($total),
            'months' => collect($trend)
                ->map(fn (array $month): array => [
                    'key' => $month['key'],
                    'label' => $month['label'],
                    'minor' => $month['minor'],
                    'valueLabel' => $money($month['minor']),
                    'count' => $month['count'],
                    'percent' => $max > 0 ? (int) round($month['minor'] / $max * 100) : 0,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, Payout>  $payouts
     * @return list<array<string, mixed>>
     */
    private static function payouts(Collection $payouts, callable $money): array
    {
        return $payouts
            ->map(fn (Payout $payout): array => [
                'id' => $payout->id,
                'reference' => $payout->reference,
                'lessonsLabel' => trans_choice(':count lesson|:count lessons', $payout->lessons_count, ['count' => $payout->lessons_count]),
                'paidLabel' => $payout->paid_at?->format('d M Y') ?? __('Queued'),
                'methodLabel' => $payout->method ? ucfirst($payout->method) : null,
                'amountLabel' => $money($payout->amount_minor),
                'statusLabel' => $payout->status->label(),
                'statusTone' => $payout->status->value === 'paid' ? 'emerald' : 'amber',
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private static function copy(): array
    {
        return [
            'hero' => [
                'eyebrow' => __('Teacher earnings'),
                'title' => __('What you have earned'),
                'intro' => __('Every paid lesson and service, where the money sits, and what has already been transferred to you.'),
                'lessonsCta' => __('Back to my lessons'),
                'ordersCta' => __('See my orders'),
                'chipsLabel' => __('Your money at a glance'),
                'availableChip' => __(':amount ready'),
                'pendingChip' => __(':amount clearing'),
                'lifetimeLabel' => __('Lifetime earned'),
            ],
            'tiles' => [
                'label' => __('Where your money sits'),
            ],
            'trend' => [
                'title' => __('Last six months'),
                'subtitle' => __('Net of refunds, grouped by the month the earning was recorded.'),
                'totalLabel' => __('Six-month total'),
                'empty' => __('Nothing recorded yet — the trend fills in as lessons are delivered.'),
                'monthLabel' => __(':month: :amount from :count earning|:month: :amount from :count earnings'),
            ],
            'ledger' => [
                'title' => __('Ledger'),
                'subtitle' => __('A lesson or service becomes available once it is delivered; reversed amounts are shown next to the original earning.'),
                'filtersLabel' => __('Filter the ledger by status'),
                'headers' => [
                    'item' => __('Item'),
                    'when' => __('When'),
                    'status' => __('Status'),
                    'earned' => __('Earned'),
                    'reversed' => __('Reversed'),
                ],
                'empty' => __('No earnings yet — your first paid lesson or service will appear here.'),
                'filteredEmpty' => __('Nothing in this status right now.'),
                'clearFilter' => __('Show all earnings'),
                'showing' => __(':visible of :total shown'),
                'payout' => __('Payout'),
                'noReversal' => '—',
            ],
            'payouts' => [
                'title' => __('Payouts'),
                'subtitle' => __('Payouts are transferred manually by the Studylikepro team in batches.'),
                'empty' => __('No payouts yet.'),
            ],
        ];
    }
}
