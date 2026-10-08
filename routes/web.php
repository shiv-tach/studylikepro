<?php

use App\Http\Controllers\Admin\ActivityController as AdminActivityController;
use App\Http\Controllers\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Admin\BookingFeePromotionController as AdminBookingFeePromotionController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DisputeController as AdminDisputeController;
use App\Http\Controllers\Admin\EducationLevelController as AdminEducationLevelController;
use App\Http\Controllers\Admin\LessonController as AdminLessonController;
use App\Http\Controllers\Admin\ModerationController as AdminModerationController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\PayoutController as AdminPayoutController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\SubjectBasketController as AdminSubjectBasketController;
use App\Http\Controllers\Admin\SubjectController as AdminSubjectController;
use App\Http\Controllers\Admin\TeacherInviteController as AdminTeacherInviteController;
use App\Http\Controllers\Admin\TeacherVerificationController as AdminTeacherVerificationController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RequestAttachmentController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\Student\BookingController as StudentBookingController;
use App\Http\Controllers\Student\DashboardController as StudentDashboardController;
use App\Http\Controllers\Student\InterestsController as StudentInterestsController;
use App\Http\Controllers\Student\PaymentController as StudentPaymentController;
use App\Http\Controllers\Student\ProfileController as StudentProfileController;
use App\Http\Controllers\Student\ReceiptController;
use App\Http\Controllers\Student\ReviewController as StudentReviewController;
use App\Http\Controllers\Student\TeacherFinderController;
use App\Http\Controllers\Student\TeacherProfileController as StudentTeacherProfileController;
use App\Http\Controllers\Student\TutoringRequestController;
use App\Http\Controllers\Teacher\AvailabilityController as TeacherAvailabilityController;
use App\Http\Controllers\Teacher\BookingController as TeacherBookingController;
use App\Http\Controllers\Teacher\DashboardController as TeacherDashboardController;
use App\Http\Controllers\Teacher\EarningsController as TeacherEarningsController;
use App\Http\Controllers\Teacher\ProfileController as TeacherProfileController;
use App\Http\Controllers\Teacher\RequestInboxController as TeacherRequestInboxController;
use App\Http\Controllers\Teacher\ReviewController as TeacherReviewController;
use App\Http\Controllers\Teacher\SubjectsController as TeacherSubjectsController;
use App\Http\Controllers\Teacher\VerificationController as TeacherVerificationController;
use App\Http\Controllers\TeacherDirectoryController;
use App\Http\Controllers\VerificationDocumentController;
use App\Http\Middleware\LogAdminActivity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/subjects', [CatalogController::class, 'index'])->name('catalog.subjects.index');
Route::get('/subjects/{subject}', [CatalogController::class, 'show'])->name('catalog.subjects.show');

Route::get('/teachers', [TeacherDirectoryController::class, 'index'])->name('teachers.index');
Route::get('/teachers/{teacherProfile}', [TeacherDirectoryController::class, 'show'])->name('teachers.show');

// Policies and support: public pages, linked from the footer and the sign-up forms.
Route::get('/privacy', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('/terms', [LegalController::class, 'terms'])->name('legal.terms');
Route::get('/refund-policy', [LegalController::class, 'refunds'])->name('legal.refunds');
Route::get('/contact', [LegalController::class, 'contact'])->name('legal.contact');
Route::post('/contact', [LegalController::class, 'submitContact'])->middleware('throttle:contact')->name('legal.contact.send');

Route::get('/dashboard', function (Request $request) {
    return redirect()->route($request->user()->dashboardRoute());
})->middleware(['auth', 'verified'])->name('dashboard');

// Live classrooms are shared: the policy decides who may enter and in what role.
Route::middleware(['auth', 'verified', 'not-suspended'])->group(function () {
    Route::get('/classroom/{booking}', [ClassroomController::class, 'show'])->name('classroom.show');
    Route::post('/classroom/{booking}/retry', [ClassroomController::class, 'retry'])->name('classroom.retry');

    // Lesson chat: only the two parties have an inbox at all, and the policy
    // decides who may read or write a given thread. Admins read the same
    // messages as evidence from the booking console instead.
    Route::middleware('role:'.User::ROLE_STUDENT.'|'.User::ROLE_TEACHER)->group(function () {
        Route::get('/messages', [ConversationController::class, 'index'])->name('messages.index');
        Route::get('/messages/{conversation}', [ConversationController::class, 'show'])->name('messages.show');
        Route::post('/messages/{conversation}', [ConversationController::class, 'store'])->middleware('throttle:messages')->name('messages.store');
        Route::get('/messages/{conversation}/poll', [ConversationController::class, 'poll'])->name('messages.poll');
        Route::post('/messages/{conversation}/report', [ConversationController::class, 'report'])->middleware('throttle:messages')->name('messages.report');
    });

    // In-app notification centre.
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'open'])->name('notifications.open');
});

Route::middleware(['auth', 'verified', 'role:'.User::ROLE_STUDENT, 'not-suspended'])
    ->prefix('student')
    ->name('student.')
    ->group(function () {
        Route::get('/dashboard', StudentDashboardController::class)->middleware('onboarded')->name('dashboard');

        Route::get('/profile', [StudentProfileController::class, 'edit'])->name('profile');
        Route::put('/profile', [StudentProfileController::class, 'update'])->middleware('throttle:uploads')->name('profile.update');

        Route::get('/interests', [StudentInterestsController::class, 'edit'])->middleware('onboarded')->name('interests.edit');
        Route::put('/interests', [StudentInterestsController::class, 'update'])->middleware('onboarded')->name('interests.update');

        // Teacher discovery matched to the student's profile grade, with the
        // student workspace around it. The public directory stays at /teachers.
        Route::get('/find-a-teacher', TeacherFinderController::class)->middleware('onboarded')->name('teachers.index');
        Route::get('/teachers/{teacherProfile}', StudentTeacherProfileController::class)->middleware('onboarded')->name('teachers.show');

        Route::get('/requests', [TutoringRequestController::class, 'index'])->middleware('onboarded')->name('requests.index');
        Route::get('/requests/create', [TutoringRequestController::class, 'create'])->middleware('onboarded')->name('requests.create');
        Route::post('/requests', [TutoringRequestController::class, 'store'])->middleware(['onboarded', 'throttle:requests'])->name('requests.store');
        Route::get('/requests/{tutoringRequest}', [TutoringRequestController::class, 'show'])->middleware('onboarded')->name('requests.show');
        Route::put('/requests/{tutoringRequest}/lesson', [TutoringRequestController::class, 'updateLesson'])->middleware('onboarded')->name('requests.lesson.update');
        Route::post('/requests/{tutoringRequest}/cancel', [TutoringRequestController::class, 'cancel'])->middleware('onboarded')->name('requests.cancel');

        Route::get('/lessons', [StudentBookingController::class, 'index'])->middleware('onboarded')->name('bookings.index');
        Route::get('/lessons/book/{teacherProfile}', [StudentBookingController::class, 'create'])->middleware('onboarded')->name('bookings.create');
        Route::post('/lessons/book/{teacherProfile}', [StudentBookingController::class, 'store'])->middleware('onboarded')->name('bookings.store');
        Route::get('/lessons/{booking}', [StudentBookingController::class, 'show'])->middleware('onboarded')->name('bookings.show');
        Route::post('/lessons/{booking}/cancel', [StudentBookingController::class, 'cancel'])->middleware('onboarded')->name('bookings.cancel');
        Route::post('/lessons/{booking}/reschedule', [StudentBookingController::class, 'reschedule'])->middleware('onboarded')->name('bookings.reschedule');

        Route::get('/lessons/{booking}/review', [StudentReviewController::class, 'show'])->middleware('onboarded')->name('reviews.show');
        Route::post('/lessons/{booking}/review', [StudentReviewController::class, 'store'])->middleware('onboarded')->name('reviews.store');
        Route::put('/lessons/{booking}/review', [StudentReviewController::class, 'update'])->middleware('onboarded')->name('reviews.update');

        Route::post('/lessons/{booking}/checkout', [StudentPaymentController::class, 'checkout'])->middleware(['onboarded', 'throttle:checkout'])->name('bookings.checkout');
        Route::get('/payments/{payment}', [StudentPaymentController::class, 'show'])->middleware('onboarded')->name('payments.checkout');
    });

Route::middleware(['auth', 'verified', 'role:'.User::ROLE_TEACHER, 'not-suspended'])
    ->prefix('teacher')
    ->name('teacher.')
    ->group(function () {
        Route::get('/dashboard', TeacherDashboardController::class)->middleware('onboarded')->name('dashboard');

        Route::get('/profile', [TeacherProfileController::class, 'edit'])->name('profile');
        Route::put('/profile', [TeacherProfileController::class, 'update'])->middleware('throttle:uploads')->name('profile.update');

        Route::get('/verification', [TeacherVerificationController::class, 'index'])->name('verification');
        Route::post('/verification/documents', [TeacherVerificationController::class, 'store'])->middleware('throttle:uploads')->name('verification.documents.store');
        Route::delete('/verification/documents/{document}', [TeacherVerificationController::class, 'destroy'])->name('verification.documents.destroy');
        Route::post('/verification/submit', [TeacherVerificationController::class, 'submit'])->name('verification.submit');

        Route::get('/subjects', [TeacherSubjectsController::class, 'index'])->middleware('onboarded')->name('subjects.index');
        Route::post('/subjects', [TeacherSubjectsController::class, 'store'])->middleware('onboarded')->name('subjects.store');
        Route::post('/subjects/{subject}', [TeacherSubjectsController::class, 'update'])->middleware('onboarded')->name('subjects.update');

        Route::get('/availability', [TeacherAvailabilityController::class, 'index'])->middleware('onboarded')->name('availability.index');
        Route::put('/availability/settings', [TeacherAvailabilityController::class, 'updateSettings'])->middleware('onboarded')->name('availability.settings.update');
        Route::post('/availability/slots', [TeacherAvailabilityController::class, 'storeSlot'])->middleware('onboarded')->name('availability.slots.store');
        Route::delete('/availability/slots/{slot}', [TeacherAvailabilityController::class, 'destroySlot'])->middleware('onboarded')->name('availability.slots.destroy');
        Route::post('/availability/time-off', [TeacherAvailabilityController::class, 'storeTimeOff'])->middleware('onboarded')->name('availability.time-off.store');
        Route::delete('/availability/time-off/{timeOff}', [TeacherAvailabilityController::class, 'destroyTimeOff'])->middleware('onboarded')->name('availability.time-off.destroy');

        Route::get('/requests', [TeacherRequestInboxController::class, 'index'])->middleware('onboarded')->name('requests.index');
        Route::post('/requests/{tutoringRequest}/respond', [TeacherRequestInboxController::class, 'respond'])->middleware('onboarded')->name('requests.respond');

        Route::get('/schedule', [TeacherBookingController::class, 'index'])->middleware('onboarded')->name('schedule.index');
        Route::get('/schedule/{booking}', [TeacherBookingController::class, 'show'])->middleware('onboarded')->name('bookings.show');
        Route::post('/schedule/{booking}/start', [TeacherBookingController::class, 'start'])->middleware('onboarded')->name('bookings.start');
        Route::post('/schedule/{booking}/complete', [TeacherBookingController::class, 'complete'])->middleware('onboarded')->name('bookings.complete');
        Route::post('/schedule/{booking}/no-show', [TeacherBookingController::class, 'markNoShow'])->middleware('onboarded')->name('bookings.no-show');
        Route::post('/schedule/{booking}/cancel', [TeacherBookingController::class, 'cancel'])->middleware('onboarded')->name('bookings.cancel');

        Route::get('/earnings', [TeacherEarningsController::class, 'index'])->middleware('onboarded')->name('earnings.index');
        Route::get('/reviews', [TeacherReviewController::class, 'index'])->middleware('onboarded')->name('reviews.index');
        Route::post('/reviews/{review}/flag', [TeacherReviewController::class, 'flag'])->middleware('onboarded')->name('reviews.flag');
    });

Route::middleware(['auth', 'verified', 'role:'.User::ROLE_ADMIN, 'not-suspended', LogAdminActivity::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');

        Route::get('/reports', [AdminReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export/{type}', [AdminReportController::class, 'export'])->name('reports.export');

        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
        Route::post('/users/{user}/suspend', [AdminUserController::class, 'suspend'])->name('users.suspend');
        Route::post('/users/{user}/reactivate', [AdminUserController::class, 'reactivate'])->name('users.reactivate');
        Route::post('/users/{user}/reverify', [AdminUserController::class, 'reverify'])->name('users.reverify');
        Route::post('/users/{user}/resend-notification', [AdminUserController::class, 'resendNotification'])->name('users.resend-notification');

        Route::get('/verifications', [AdminTeacherVerificationController::class, 'index'])->name('verifications.index');
        Route::get('/verifications/{teacherProfile}', [AdminTeacherVerificationController::class, 'show'])->name('verifications.show');
        Route::post('/verifications/{teacherProfile}/approve', [AdminTeacherVerificationController::class, 'approve'])->name('verifications.approve');
        Route::post('/verifications/{teacherProfile}/reject', [AdminTeacherVerificationController::class, 'reject'])->name('verifications.reject');

        Route::get('/invites', [AdminTeacherInviteController::class, 'index'])->name('invites.index');
        Route::post('/invites', [AdminTeacherInviteController::class, 'store'])->name('invites.store');
        Route::delete('/invites/{teacherInvite}', [AdminTeacherInviteController::class, 'revoke'])->name('invites.revoke');

        Route::get('/moderation', [AdminModerationController::class, 'index'])->name('moderation.index');
        Route::post('/moderation/reviews/{review}/hide', [AdminModerationController::class, 'hideReview'])->name('moderation.reviews.hide');
        Route::post('/moderation/reviews/{review}/dismiss', [AdminModerationController::class, 'dismissReview'])->name('moderation.reviews.dismiss');

        Route::get('/disputes', [AdminDisputeController::class, 'index'])->name('disputes.index');
        Route::get('/disputes/{dispute}', [AdminDisputeController::class, 'show'])->name('disputes.show');
        Route::post('/disputes/{dispute}/review', [AdminDisputeController::class, 'review'])->name('disputes.review');
        Route::post('/disputes/{dispute}/resolve', [AdminDisputeController::class, 'resolve'])->name('disputes.resolve');

        Route::get('/curriculum', [AdminEducationLevelController::class, 'index'])->name('curriculum.index');
        Route::put('/curriculum/levels/{level}', [AdminEducationLevelController::class, 'update'])->name('curriculum.levels.update');
        Route::put('/curriculum/baskets/{basket}', [AdminSubjectBasketController::class, 'update'])->name('curriculum.baskets.update');

        Route::get('/subjects', [AdminSubjectController::class, 'index'])->name('subjects.index');
        Route::post('/subjects', [AdminSubjectController::class, 'store'])->name('subjects.store');
        Route::get('/subjects/{subject}/edit', [AdminSubjectController::class, 'edit'])->name('subjects.edit');
        Route::put('/subjects/{subject}', [AdminSubjectController::class, 'update'])->name('subjects.update');
        Route::delete('/subjects/{subject}', [AdminSubjectController::class, 'destroy'])->name('subjects.destroy');

        Route::post('/subjects/{subject}/lessons', [AdminLessonController::class, 'store'])->name('subjects.lessons.store');
        Route::post('/subjects/{subject}/lessons/copy', [AdminLessonController::class, 'copy'])->name('subjects.lessons.copy');
        Route::post('/subjects/{subject}/lessons/bulk-active', [AdminLessonController::class, 'bulkActive'])->name('subjects.lessons.bulk-active');
        Route::post('/subjects/{subject}/lessons/{lesson}/move', [AdminLessonController::class, 'move'])->scopeBindings()->name('subjects.lessons.move');
        Route::put('/subjects/{subject}/lessons/{lesson}', [AdminLessonController::class, 'update'])->scopeBindings()->name('subjects.lessons.update');
        Route::delete('/subjects/{subject}/lessons/{lesson}', [AdminLessonController::class, 'destroy'])->scopeBindings()->name('subjects.lessons.destroy');

        Route::get('/offers', [AdminBookingFeePromotionController::class, 'index'])->name('offers.index');
        Route::post('/offers', [AdminBookingFeePromotionController::class, 'store'])->name('offers.store');
        Route::get('/offers/{offer}/edit', [AdminBookingFeePromotionController::class, 'edit'])->name('offers.edit');
        Route::put('/offers/{offer}', [AdminBookingFeePromotionController::class, 'update'])->name('offers.update');
        Route::delete('/offers/{offer}', [AdminBookingFeePromotionController::class, 'destroy'])->name('offers.destroy');

        Route::get('/bookings', [AdminBookingController::class, 'index'])->name('bookings.index');
        Route::get('/bookings/{booking}', [AdminBookingController::class, 'show'])->name('bookings.show');
        Route::post('/bookings/{booking}/cancel', [AdminBookingController::class, 'cancel'])->name('bookings.cancel');
        Route::post('/bookings/{booking}/force-complete', [AdminBookingController::class, 'forceComplete'])->name('bookings.force-complete');

        Route::get('/payments', [AdminPaymentController::class, 'index'])->name('payments.index');
        Route::get('/payments/export', [AdminPaymentController::class, 'export'])->name('payments.export');
        Route::get('/payments/{payment}', [AdminPaymentController::class, 'show'])->name('payments.show');
        Route::post('/payments/{payment}/refund', [AdminPaymentController::class, 'refund'])->name('payments.refund');

        Route::get('/payouts', [AdminPayoutController::class, 'index'])->name('payouts.index');
        Route::post('/payouts', [AdminPayoutController::class, 'store'])->name('payouts.store');
        Route::post('/payouts/{payout}/mark-paid', [AdminPayoutController::class, 'markPaid'])->name('payouts.mark-paid');

        Route::get('/activity', [AdminActivityController::class, 'index'])->name('activity.index');

        Route::get('/settings', [AdminSettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [AdminSettingsController::class, 'update'])->name('settings.update');
    });

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->middleware('throttle:uploads')->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::get('/settings/theme', [SettingsController::class, 'edit'])->name('settings.theme');
    Route::patch('/settings/theme', [SettingsController::class, 'update'])->name('settings.theme.update');
    Route::post('/settings/notifications', [SettingsController::class, 'updateNotifications'])->name('settings.notifications.update');

    Route::get('/verification-documents/{document}', [VerificationDocumentController::class, 'show'])->name('verification-documents.show');
    Route::get('/request-attachments/{attachment}', [RequestAttachmentController::class, 'show'])->name('request-attachments.show');

    Route::get('/receipts/{booking}', [ReceiptController::class, 'show'])->name('receipts.show');
    Route::match(['get', 'post'], '/payments/{payment}/return', [StudentPaymentController::class, 'return'])->name('payments.return');
});

// Provider callbacks: no session, no CSRF, signature-verified and idempotent.
Route::post('/webhooks/payments/{gateway}', PaymentWebhookController::class)
    ->middleware('throttle:webhooks')
    ->name('payments.webhook');

require __DIR__.'/auth.php';
