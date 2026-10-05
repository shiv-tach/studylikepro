<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Enums\ResponseStatus;
use App\Models\RequestResponse;
use App\Models\TeacherProfile;
use App\Models\TutoringRequest;
use App\Notifications\RequestResponseAccepted;
use App\Notifications\RequestResponseDeclined;
use App\Support\BookingDraft;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Teacher answers to a tutoring request. Accepting a request creates the
 * pending-payment hold that reserves the slot while the student pays.
 */
class RequestResponseService
{
    public function __construct(
        private readonly RequestMatcher $matcher,
        private readonly BookingService $bookings,
    ) {}

    public function accept(
        TutoringRequest $request,
        TeacherProfile $teacher,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?string $message = null,
    ): RequestResponse {
        $this->guardCanRespond($request, $teacher);

        if (! $this->matcher->isSlotOpen($teacher, $startsAt, $endsAt)) {
            throw ValidationException::withMessages([
                'starts_at' => __('That slot is no longer available — pick another one from your availability.'),
            ]);
        }

        $response = DB::transaction(function () use ($request, $teacher, $startsAt, $endsAt, $message) {
            $request->update(['status' => RequestStatus::Matched]);

            $request->responses()
                ->where('teacher_profile_id', '!=', $teacher->id)
                ->where('status', ResponseStatus::Pending->value)
                ->update(['status' => ResponseStatus::Expired->value, 'updated_at' => now()]);

            $price = $this->priceFor($request, $teacher, $startsAt, $endsAt);

            $this->bookings->reserve(new BookingDraft(
                student: $request->student,
                teacher: $teacher,
                startsAt: $startsAt,
                endsAt: $endsAt,
                subject: $request->subject,
                topic: $request->topic,
                tutoringRequest: $request,
                learnerName: $request->student->name,
                learnerGrade: $request->student->studentProfile?->grade_level,
                priceMinor: $price,
            ));

            return $request->responses()->updateOrCreate(
                ['teacher_profile_id' => $teacher->id],
                [
                    'status' => ResponseStatus::Accepted,
                    'message' => $message,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'price_minor' => $price,
                    'responded_at' => now(),
                ],
            );
        });

        $request->student->notify(new RequestResponseAccepted($response));

        return $response;
    }

    public function decline(TutoringRequest $request, TeacherProfile $teacher, ?string $message = null): RequestResponse
    {
        $this->guardCanRespond($request, $teacher);

        $response = $request->responses()->updateOrCreate(
            ['teacher_profile_id' => $teacher->id],
            [
                'status' => ResponseStatus::Declined,
                'message' => $message,
                'responded_at' => now(),
            ],
        );

        $request->student->notify(new RequestResponseDeclined($response));

        return $response;
    }

    /**
     * The lesson price for the slot the teacher is proposing.
     */
    public function priceFor(
        TutoringRequest $request,
        TeacherProfile $teacher,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
    ): int {
        return $this->bookings->priceMinor(
            $teacher,
            $request->subject,
            (int) $startsAt->diffInMinutes($endsAt),
        );
    }

    private function guardCanRespond(TutoringRequest $request, TeacherProfile $teacher): void
    {
        if (! $request->isMatchable()) {
            throw ValidationException::withMessages([
                'status' => __('This request is no longer open.'),
            ]);
        }

        $teachesTopic = $teacher->topics()->where('topics.id', $request->topic_id)->exists();

        if (! $teacher->isApproved() || ! $teachesTopic) {
            throw ValidationException::withMessages([
                'status' => __('You can only respond to requests in topics you teach.'),
            ]);
        }
    }
}
