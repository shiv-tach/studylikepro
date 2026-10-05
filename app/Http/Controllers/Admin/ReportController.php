<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingStatus;
use App\Enums\RefundStatus;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Refund;
use App\Models\TeacherEarning;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Services\AdminCsvExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The numbers the business runs on: revenue, commission, demand and supply over
 * any date range, plus CSV exports of the underlying tables.
 */
class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        [$from, $to] = AdminCsvExporter::range($filters);

        return view('admin.reports.index', [
            'from' => $from,
            'to' => $to,
            'filters' => $filters,
            'kpis' => $this->kpis($from, $to),
            'trend' => $this->trend($from, $to),
            'bookingsByStatus' => $this->bookingsByStatus($from, $to),
            'teachers' => $this->teacherPerformance($from, $to),
            'exportTypes' => AdminCsvExporter::TYPES,
        ]);
    }

    public function export(Request $request, string $type, AdminCsvExporter $exporter): StreamedResponse
    {
        abort_unless(in_array($type, AdminCsvExporter::TYPES, true), 404);

        [$from, $to] = AdminCsvExporter::range($request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]));

        return $exporter->download($type, $from, $to);
    }

    /**
     * @return array<string, int|float>
     */
    private function kpis(Carbon $from, Carbon $to): array
    {
        // Collections count every settled payment — refunded money is subtracted
        // separately so gross and refunds stay comparable.
        $settled = Payment::query()
            ->settled()
            ->whereBetween('captured_at', [$from, $to]);

        $gross = (int) (clone $settled)->sum('amount_minor');
        $commission = (int) Booking::query()
            ->whereIn('id', (clone $settled)->select('booking_id'))
            ->sum('platform_fee_minor');

        $refunded = (int) Refund::query()
            ->where('status', RefundStatus::Processed->value)
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount_minor');

        $payouts = (int) Payout::query()
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount_minor');

        return [
            'gross' => $gross,
            'refunded' => $refunded,
            'net' => $gross - $refunded,
            'commission' => $commission,
            'payments' => (clone $settled)->count(),
            'bookings' => Booking::query()->whereBetween('starts_at', [$from, $to])->count(),
            'completed' => Booking::query()
                ->where('status', BookingStatus::Completed->value)
                ->whereBetween('starts_at', [$from, $to])
                ->count(),
            'new_students' => User::query()->role(User::ROLE_STUDENT)->whereBetween('created_at', [$from, $to])->count(),
            'new_teachers' => User::query()->role(User::ROLE_TEACHER)->whereBetween('created_at', [$from, $to])->count(),
            'active_teachers' => Booking::query()
                ->where('status', BookingStatus::Completed->value)
                ->whereBetween('starts_at', [$from, $to])
                ->distinct()
                ->count('teacher_profile_id'),
            'verified_teachers' => TeacherProfile::query()->where('verification_status', VerificationStatus::Approved->value)->count(),
            'payouts' => $payouts,
            'payouts_due' => (int) TeacherEarning::query()->eligible()->whereNull('payout_id')->sum(DB::raw('amount_minor - reversed_minor')),
        ];
    }

    /**
     * Revenue and lesson counts per day for the selected range.
     *
     * @return list<array{date: string, label: string, revenue: int, bookings: int}>
     */
    private function trend(Carbon $from, Carbon $to): array
    {
        $revenue = Payment::query()
            ->settled()
            ->whereBetween('captured_at', [$from, $to])
            ->selectRaw('DATE(captured_at) as day, SUM(amount_minor) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $bookings = Booking::query()
            ->whereBetween('starts_at', [$from, $to])
            ->selectRaw('DATE(starts_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $days = [];
        $cursor = $from->copy()->startOfDay();
        $max = (int) ($revenue->max() ?: 0);

        while ($cursor->lessThanOrEqualTo($to)) {
            $key = $cursor->toDateString();

            $days[] = [
                'date' => $key,
                'label' => $cursor->format('d M'),
                'revenue' => (int) ($revenue[$key] ?? 0),
                'bookings' => (int) ($bookings[$key] ?? 0),
            ];

            $cursor->addDay();
        }

        return $days;
    }

    /**
     * @return array<string, int>
     */
    private function bookingsByStatus(Carbon $from, Carbon $to): array
    {
        $counts = Booking::query()
            ->whereBetween('starts_at', [$from, $to])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $result = [];

        foreach (BookingStatus::cases() as $status) {
            $result[$status->label()] = (int) ($counts[$status->value] ?? 0);
        }

        return $result;
    }

    /**
     * @return Collection<int, array{teacher: TeacherProfile, lessons: int, gross: int, commission: int, net: int}>
     */
    private function teacherPerformance(Carbon $from, Carbon $to)
    {
        return Booking::query()
            ->where('status', BookingStatus::Completed->value)
            ->whereBetween('starts_at', [$from, $to])
            ->selectRaw('teacher_profile_id, COUNT(*) as lessons, SUM(price_minor) as gross, SUM(platform_fee_minor) as commission, SUM(teacher_payout_minor) as net')
            ->groupBy('teacher_profile_id')
            ->orderByDesc('gross')
            ->limit(20)
            ->get()
            ->map(function ($row) {
                $teacher = TeacherProfile::query()->with('user')->find($row->teacher_profile_id);

                return $teacher === null ? null : [
                    'teacher' => $teacher,
                    'lessons' => (int) $row->lessons,
                    'gross' => (int) $row->gross,
                    'commission' => (int) $row->commission,
                    'net' => (int) $row->net,
                ];
            })
            ->filter()
            ->values();
    }
}
