<?php

namespace App\Support;

use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Models\Topic;
use App\Models\TutoringRequest;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Everything needed to turn a chosen slot into a booking hold.
 */
final readonly class BookingDraft
{
    public function __construct(
        public User $student,
        public TeacherProfile $teacher,
        public CarbonInterface $startsAt,
        public CarbonInterface $endsAt,
        public ?Subject $subject = null,
        public ?Topic $topic = null,
        public ?TutoringRequest $tutoringRequest = null,
        public ?string $learnerName = null,
        public ?string $learnerGrade = null,
        public ?int $priceMinor = null,
    ) {}

    public function durationMinutes(): int
    {
        return (int) $this->startsAt->diffInMinutes($this->endsAt);
    }
}
