<?php

namespace App\Events;

use App\Models\Review;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A review was written, changed or hidden — the teacher's aggregates may now be
 * stale.
 */
class ReviewChanged
{
    use Dispatchable;

    public function __construct(public readonly Review $review) {}
}
