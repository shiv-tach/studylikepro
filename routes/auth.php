<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\InviteOnboardingController;
use App\Http\Controllers\Auth\MobileVerificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\TeacherSurveyController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store'])
        ->middleware(['throttle:register', 'throttle:otp']);

    // The invite survey. A teacher onboarding link opens the questions as a
    // guest: nothing is written to `users` until the last step collects the
    // password and proves the mobile number with an SMS code.
    Route::prefix('join')->name('join.')->middleware('invite.signup')->group(function () {
        // No intro screen: the link goes straight to question 1.
        Route::get('/', [TeacherSurveyController::class, 'start'])->name('start');

        // Question 1: are you tutoring for O/L, A/L or both?
        Route::get('/level', [TeacherSurveyController::class, 'editLevel'])->name('level');
        Route::put('/level', [TeacherSurveyController::class, 'updateLevel'])->name('level.update');

        // Question 2: the subjects that belong to that level.
        Route::get('/subjects', [TeacherSurveyController::class, 'editSubjects'])->name('subjects');
        Route::put('/subjects', [TeacherSurveyController::class, 'updateSubjects'])->name('subjects.update');

        // Question 3: the languages you teach in.
        Route::get('/languages', [TeacherSurveyController::class, 'editLanguages'])->name('languages');
        Route::put('/languages', [TeacherSurveyController::class, 'updateLanguages'])->name('languages.update');

        // Question 4: the services you would like to offer.
        Route::get('/services', [TeacherSurveyController::class, 'editServices'])->name('services');
        Route::put('/services', [TeacherSurveyController::class, 'updateServices'])->name('services.update');

        // Question 5: how many years you have been teaching.
        Route::get('/experience', [TeacherSurveyController::class, 'editExperience'])->name('experience');
        Route::put('/experience', [TeacherSurveyController::class, 'updateExperience'])->name('experience.update');

        // Question 6: the kind of institute you studied at.
        Route::get('/education', [TeacherSurveyController::class, 'editEducation'])->name('education');
        Route::put('/education', [TeacherSurveyController::class, 'updateEducation'])->name('education.update');

        // Question 7: the hourly fee plus a price for each service picked in
        // question 4.
        Route::get('/rates', [TeacherSurveyController::class, 'editRates'])->name('rates');
        Route::put('/rates', [TeacherSurveyController::class, 'updateRates'])->name('rates.update');

        // Question 8: the days of the week you can teach.
        Route::get('/availability', [TeacherSurveyController::class, 'editAvailability'])->name('availability');
        Route::put('/availability', [TeacherSurveyController::class, 'updateAvailability'])->name('availability.update');

        // Question 9: prove you are a government teacher (optional).
        Route::get('/proof', [TeacherSurveyController::class, 'editProof'])->name('proof');
        Route::post('/proof', [TeacherSurveyController::class, 'updateProof'])->name('proof.update');

        // The final step: identity + password, then the SMS code on the same URL.
        Route::get('/account', [InviteOnboardingController::class, 'editAccount'])->name('account');
        Route::post('/account', [InviteOnboardingController::class, 'storeAccount'])
            ->middleware(['throttle:register', 'throttle:otp'])
            ->name('account.store');
        Route::post('/account/verify', [InviteOnboardingController::class, 'verify'])
            ->middleware('throttle:otp-verify')
            ->name('account.verify');
        Route::post('/account/resend', [InviteOnboardingController::class, 'resend'])
            ->middleware('throttle:otp')
            ->name('account.resend');
        Route::post('/account/edit', [InviteOnboardingController::class, 'editDetails'])->name('account.edit');
    });

    // Step two: the mobile number is proved with the code sent by SMSLenz and
    // only then is the account created. Resending and restarting the form are
    // separate actions so each gets its own rate limit.
    Route::get('register/verify', [MobileVerificationController::class, 'show'])
        ->name('register.verify.show');

    Route::post('register/verify', [MobileVerificationController::class, 'verify'])
        ->middleware('throttle:otp-verify')
        ->name('register.verify.store');

    Route::post('register/verify/resend', [MobileVerificationController::class, 'resend'])
        ->middleware('throttle:otp')
        ->name('register.verify.resend');

    Route::post('register/cancel', [MobileVerificationController::class, 'cancel'])
        ->name('register.cancel');

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
