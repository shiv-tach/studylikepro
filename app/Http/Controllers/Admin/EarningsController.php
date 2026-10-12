<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PayoutStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\TeacherEarning;
use App\Services\AdminCsvExporter;
use App\Services\Payments\PlatformEarningsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The platform's own money: every collection, the commission and booking fees it
 * keeps, refunds and the teacher payouts set aside — split by revenue source and
 * by month, over any date range.
 */
class EarningsController extends Controller
{
    public function __invoke(Request $request, PlatformEarningsService $earnings): View
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        [$from, $to] = AdminCsvExporter::range($filters);

        $entries = $earnings->settled($from, $to)
            ->orderBy('captured_at')
            ->get()
            ->map(fn (Payment $payment) => [
                'captured_at' => $payment->captured_at ?? $payment->created_at,
                ...$earnings->breakdown($payment),
            ]);

        return view('admin.earnings.index', [
            'from' => $from,
            'to' => $to,
            'filters' => $filters,
            'totals' => $this->totals($entries),
            'sources' => $this->sources($entries),
            'ledger' => $this->ledger($entries),
            'collections' => $earnings->settled($from, $to)
                ->latest('captured_at')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (Payment $payment) => [
                    'payment' => $payment,
                    ...$earnings->breakdown($payment),
                ]),
            'payoutsDue' => $this->payoutsDue(),
            'payoutsQueued' => $this->payoutsByStatus(PayoutStatus::Pending),
            'payoutsReleased' => $this->payoutsByStatus(PayoutStatus::Paid),
            'timezone' => config('studylikepro.default_display_timezone'),
        ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $entries
     * @return array<string, int>
     */
    private function totals(Collection $entries): array
    {
        return [
            'payments' => $entries->count(),
            'collected' => (int) $entries->sum('collected'),
            'commission' => (int) $entries->sum('commission'),
            'booking_fees' => (int) $entries->sum('booking_fee'),
            'refunded' => (int) $entries->sum('refunded'),
            'teacher' => (int) $entries->sum('teacher'),
            'net' => (int) $entries->sum('net'),
        ];
    }

    /**
     * Where the collections came from: lessons versus service purchases.
     *
     * @param  Collection<int, array<string, mixed>>  $entries
     * @return list<array<string, mixed>>
     */
    private function sources(Collection $entries): array
    {
        $labels = ['lessons' => __('Lesson payments'), 'services' => __('Service payments')];

        return collect($labels)
            ->map(function (string $label, string $key) use ($entries) {
                $group = $entries->where('source', $key);

                return [
                    'label' => $label,
                    'payments' => $group->count(),
                    'collected' => (int) $group->sum('collected'),
                    'commission' => (int) $group->sum('commission'),
                    'booking_fees' => (int) $group->sum('booking_fee'),
                    'refunded' => (int) $group->sum('refunded'),
                    'teacher' => (int) $group->sum('teacher'),
                    'net' => (int) $group->sum('net'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Month-by-month rollup across the selected range.
     *
     * @param  Collection<int, array<string, mixed>>  $entries
     * @return list<array<string, mixed>>
     */
    private function ledger(Collection $entries): array
    {
        return $entries
            ->groupBy(fn (array $entry) => $entry['captured_at']->format('Y-m'))
            ->sortKeysDesc()
            ->map(fn (Collection $group, string $month) => [
                'month' => Carbon::createFromFormat('Y-m', $month)->format('F Y'),
                'payments' => $group->count(),
                'collected' => (int) $group->sum('collected'),
                'commission' => (int) $group->sum('commission'),
                'booking_fees' => (int) $group->sum('booking_fee'),
                'refunded' => (int) $group->sum('refunded'),
                'teacher' => (int) $group->sum('teacher'),
                'net' => (int) $group->sum('net'),
            ])
            ->values()
            ->all();
    }

    private function payoutsDue(): int
    {
        return (int) TeacherEarning::query()
            ->eligible()
            ->whereNull('payout_id')
            ->sum(DB::raw('amount_minor - reversed_minor'));
    }

    private function payoutsByStatus(PayoutStatus $status): int
    {
        return (int) Payout::query()->where('status', $status->value)->sum('amount_minor');
    }
}
