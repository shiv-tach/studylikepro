<?php

namespace App\Http\Controllers\Student;

use App\Enums\ServiceType;
use App\Http\Controllers\Controller;
use App\Models\ServicePurchase;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The student side of the lesson-summary service: the delivered write-up.
 */
class SummaryController extends Controller
{
    public function show(ServicePurchase $purchase): View
    {
        Gate::authorize('view', $purchase);

        abort_unless($purchase->type() === ServiceType::Summary, 404);

        $purchase->loadMissing([
            'summary',
            'teacherProfile.user',
            'lesson',
            'subject',
        ]);

        return view('student.summaries.show', [
            'purchase' => $purchase,
        ]);
    }
}
