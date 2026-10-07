<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\TeacherEarning;
use App\Services\ActivityLogger;
use App\Services\AdminCsvExporter;
use App\Services\Payments\RefundService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        $payments = $this->query($filters)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.payments.index', [
            'payments' => $payments,
            'statuses' => PaymentStatus::cases(),
            'filters' => $filters,
            'timezone' => config('studylikepro.default_display_timezone'),
            'stats' => $this->stats(),
        ]);
    }

    /**
     * One transaction with its refund history.
     */
    public function show(Payment $payment): View
    {
        $payment->load(['student', 'booking.teacherProfile.user', 'booking.subject', 'booking.lesson', 'refunds']);

        return view('admin.payments.show', [
            'payment' => $payment,
            'refunds' => $payment->refunds()->latest()->get(),
            'timezone' => config('studylikepro.default_display_timezone'),
        ]);
    }

    /**
     * Export payments for the selected date range.
     */
    public function export(Request $request, AdminCsvExporter $exporter): StreamedResponse
    {
        [$from, $to] = AdminCsvExporter::range($request->only('from', 'to'));

        return $exporter->download('payments', $from, $to);
    }

    public function refund(Request $request, Payment $payment, RefundService $refunds, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('refund', $payment);

        $validated = $request->validate([
            'percent' => ['required', 'integer', 'in:0,25,50,75,100'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $refund = $refunds->refund(
            $payment,
            (int) $validated['percent'],
            Refund::INITIATED_BY_ADMIN,
            $validated['reason'] ?? 'Refund issued from the admin console.',
        );

        $activity->describe(sprintf(
            'Refunded %s of payment #%d (%d%%)',
            platform_settings()->formatMinor((int) $refund?->amount_minor),
            $payment->id,
            (int) $validated['percent'],
        ));

        return redirect()
            ->route('admin.payments.show', $payment)
            ->with('status', $refund ? 'refund-issued' : 'refund-skipped');
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return $request->validate([
            'status' => ['nullable', 'string', 'in:'.implode(',', array_column(PaymentStatus::cases(), 'value'))],
            'gateway' => ['nullable', 'string', 'max:30'],
            'search' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Payment>
     */
    private function query(array $filters): Builder
    {
        return Payment::query()
            ->with(['student', 'booking.teacherProfile.user', 'refunds'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['gateway'] ?? null, fn (Builder $query, string $gateway) => $query->where('gateway', $gateway))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->where('created_at', '<=', Carbon::parse($to)->endOfDay()))
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('gateway_order_id', 'like', "%{$search}%")
                        ->orWhere('gateway_payment_id', 'like', "%{$search}%")
                        ->orWhereHas('student', fn (Builder $student) => $student
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"));
                });
            });
    }

    /**
     * @return array{collected: int, refunded: int, fees: int, payouts_due: int}
     */
    private function stats(): array
    {
        $paidBookingIds = Payment::query()->settled()->select('booking_id');

        return [
            'collected' => (int) Payment::query()->settled()->sum('amount_minor'),
            'refunded' => (int) Refund::query()->where('status', RefundStatus::Processed->value)->sum('amount_minor'),
            'fees' => (int) Booking::query()->whereIn('id', $paidBookingIds)->sum('platform_fee_minor'),
            'payouts_due' => (int) TeacherEarning::query()
                ->eligible()
                ->whereNull('payout_id')
                ->sum(DB::raw('amount_minor - reversed_minor')),
        ];
    }
}
