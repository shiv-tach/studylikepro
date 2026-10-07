<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Payments\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ReceiptController extends Controller
{
    /**
     * Printable receipt / invoice for a paid lesson.
     */
    public function show(Request $request, Booking $booking, PaymentService $payments): View
    {
        Gate::authorize('view', $booking);

        $payment = $payments->capturedFor($booking);

        abort_if($payment === null, 404);

        $payment->load(['refunds', 'booking.teacherProfile.user', 'booking.subject', 'booking.topic', 'booking.bookingFeePromotion']);

        return view('student.bookings.receipt', [
            'booking' => $booking,
            'payment' => $payment,
            'refunds' => $payment->refunds()->latest()->get(),
        ]);
    }
}
