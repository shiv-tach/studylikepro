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
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store'])
        ->middleware(['throttle:register', 'throttle:otp']);

    // The invite wizard. A teacher onboarding link opens the onboarding steps as
    // a guest: nothing is written to `users` until the last step collects the
    // password and proves the mobile number with an SMS code.
    Route::prefix('join')->name('join.')->middleware('invite.signup')->group(function () {
        Route::get('/', [InviteOnboardingController::class, 'welcome'])->name('start');

        Route::get('/profile', [InviteOnboardingController::class, 'editAbout'])->name('profile');
        Route::put('/profile', [InviteOnboardingController::class, 'updateAbout'])
            ->middleware('throttle:uploads')
            ->name('profile.update');

        Route::get('/preferences', [InviteOnboardingController::class, 'editPreferences'])->name('preferences');
        Route::put('/preferences', [InviteOnboardingController::class, 'updatePreferences'])
            ->middleware('throttle:uploads')
            ->name('preferences.update');

        Route::get('/availability', [InviteOnboardingController::class, 'editAvailability'])->name('availability');
        Route::put('/availability', [InviteOnboardingController::class, 'updateAvailability'])->name('availability.update');

        Route::patch('/draft', [InviteOnboardingController::class, 'saveDraft'])
            ->middleware('throttle:onboarding-draft')
            ->name('draft');

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
