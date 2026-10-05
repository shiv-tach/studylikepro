<?php

namespace App\Services\Payments;

use App\Enums\EarningStatus;
use App\Enums\PayoutStatus;
use App\Events\PayoutPaid;
use App\Models\Payout;
use App\Models\TeacherEarning;
use App\Models\TeacherProfile;
use Illuminate\Support\Facades\DB;

/**
 * Manual payout batches: an admin sweeps a teacher's available earnings into one
 * transfer, then marks it paid once the money leaves the bank.
 */
class PayoutService
{
    /**
     * Queue every available, unbatch'd earning for this teacher.
     */
    public function createFor(TeacherProfile $teacher, ?string $notes = null): ?Payout
    {
        $earnings = app(EarningsService::class)->unbatchedFor($teacher);

        if ($earnings->isEmpty()) {
            return null;
        }

        return DB::transaction(function () use ($teacher, $earnings, $notes) {
            $amount = (int) $earnings->sum(fn (TeacherEarning $earning) => $earning->netMinor());

            $payout = Payout::query()->create([
                'teacher_profile_id' => $teacher->id,
                'reference' => $this->nextReference(),
                'amount_minor' => $amount,
                'lessons_count' => $earnings->count(),
                'currency' => $earnings->first()->currency,
                'status' => PayoutStatus::Pending,
                'notes' => $notes,
            ]);

            TeacherEarning::query()
                ->whereIn('id', $earnings->pluck('id'))
                ->update([
                    'payout_id' => $payout->id,
                    'status' => EarningStatus::Paid->value,
                    'paid_at' => now(),
                    'updated_at' => now(),
                ]);

            return $payout;
        });
    }

    public function markPaid(Payout $payout, ?string $reference = null, ?string $notes = null): Payout
    {
        $payout->forceFill([
            'status' => PayoutStatus::Paid,
            'paid_at' => now(),
            'reference' => $reference ?: $payout->reference,
            'notes' => $notes ?: $payout->notes,
        ])->save();

        PayoutPaid::dispatch($payout);

        return $payout;
    }

    private function nextReference(): string
    {
        $sequence = Payout::query()->whereYear('created_at', now()->year)->count() + 1;

        return sprintf('PAY-%s-%04d', now()->format('Ym'), $sequence);
    }
}
