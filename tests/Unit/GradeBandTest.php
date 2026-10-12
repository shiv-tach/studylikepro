<?php

use App\Enums\GradeBand;

test('band labels follow the level they apply to', function () {
    // The O/L level starts at Grade 6, so its junior band reads Grades 6-9.
    expect(GradeBand::Junior->labelWithin(6, 11))->toBe('Grades 6-9')
        ->and(GradeBand::Senior->labelWithin(6, 11))->toBe('Grades 10-11')
        ->and(GradeBand::All->labelWithin(6, 11))->toBe('All grades');
});

test('band labels clamp to a narrower level', function () {
    expect(GradeBand::Junior->labelWithin(1, 5))->toBe('Grades 1-5')
        // A band that misses the level keeps its own label.
        ->and(GradeBand::Senior->labelWithin(1, 5))->toBe('Grades 10-11')
        ->and(GradeBand::All->labelWithin(1, 5))->toBe('All grades');
});

test('band labels fall back when a level has no grade range', function () {
    expect(GradeBand::Junior->labelWithin(null, null))->toBe('Grades 1-9')
        ->and(GradeBand::Senior->labelWithin(null, null))->toBe('Grades 10-11')
        ->and(GradeBand::All->labelWithin(null, null))->toBe('All grades');
});

test('a band covers the grades it is named after', function () {
    expect(GradeBand::Junior->includes(6))->toBeTrue()
        ->and(GradeBand::Junior->includes(9))->toBeTrue()
        ->and(GradeBand::Junior->includes(10))->toBeFalse()
        ->and(GradeBand::Senior->includes(10))->toBeTrue()
        ->and(GradeBand::Senior->includes(9))->toBeFalse()
        // The unnumbered "Other" grade only belongs to the All band.
        ->and(GradeBand::All->includes(null))->toBeTrue()
        ->and(GradeBand::Junior->includes(null))->toBeFalse();
});
