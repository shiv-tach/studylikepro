<?php

use App\Models\Booking;
use App\Models\StudentProfile;
use Illuminate\Support\Facades\Schema;

/**
 * Phase F removed the coarse Sri Lankan-external buckets. A grade id is the
 * only grade source on profiles and bookings now.
 */
it('dropped the legacy grade bucket columns', function () {
    expect(Schema::hasColumn('student_profiles', 'grade_level'))->toBeFalse()
        ->and(Schema::hasColumn('bookings', 'learner_grade'))->toBeFalse()
        ->and(Schema::hasColumn('student_profiles', 'grade_id'))->toBeTrue()
        ->and(Schema::hasColumn('bookings', 'learner_grade_id'))->toBeTrue();
});

it('no longer accepts the legacy grade bucket attributes', function () {
    expect((new StudentProfile)->getFillable())->not->toContain('grade_level')
        ->and((new Booking)->getFillable())->not->toContain('learner_grade');
});
