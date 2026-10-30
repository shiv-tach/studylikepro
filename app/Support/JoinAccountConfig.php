<?php

namespace App\Support;

use Illuminate\Support\ViewErrorBag;

/**
 * The JSON island for the last invite screen, `/join/account`.
 *
 * One URL, two states: identity + password before the SMS code is sent, and the
 * code itself afterwards. Both forms are real POSTs — the Vue layer only decides
 * which one is visible, counts the resend cooldown and repeats the recap of the
 * answers the account is about to keep.
 */
final class JoinAccountConfig
{
    /**
     * @param  array<string, mixed>|null  $pending  the stored pending registration, if a code was sent
     * @param  array<string, mixed>  $answers
     */
    public static function build(
        ?array $pending,
        ?string $mobile,
        int $length,
        int $cooldown,
        bool $otpEnabled,
        array $answers,
        ViewErrorBag $errors,
        ?string $status,
    ): array {
        [$plainErrors, $fieldErrors] = JoinSurveyConfig::splitErrors($errors);

        $hasPending = $pending !== null;
        $answered = TeacherSurvey::answeredCount($answers);
        $questions = count(TeacherSurvey::ANSWER_STEPS);

        return [
            'pending' => $hasPending,
            'mobile' => $mobile,
            'length' => $length,
            'cooldown' => $cooldown,
            'otpEnabled' => $otpEnabled,
            'answered' => $answered,
            'questions' => $questions,
            'percent' => $questions > 0 ? (int) round(($answered / $questions) * 100) : 0,
            'currentGroup' => TeacherSurvey::GROUP_ACCOUNT,
            'rail' => TeacherSurvey::steps($answers),
            'summary' => TeacherSurvey::summary($answers),
            'formId' => $hasPending ? 'join-verify-form' : 'join-account-form',
            'values' => [
                'first_name' => old('first_name'),
                'last_name' => old('last_name'),
                'email' => old('email'),
                'mobile' => old('mobile'),
                'agree' => old('agree') !== null,
            ],
            'errors' => $plainErrors,
            'fieldErrors' => $fieldErrors,
            'errorList' => $errors->any() ? array_values($errors->all()) : [],
            'status' => [
                'resent' => $status === 'otp-resent',
                'sendFailed' => $status === 'otp-send-failed',
            ],
            'csrfToken' => csrf_token(),
            'urls' => [
                'store' => route('join.account.store'),
                'verify' => route('join.account.verify'),
                'resend' => route('join.account.resend'),
                'edit' => route('join.account.edit'),
                'back' => route('join.proof'),
                'terms' => route('legal.terms'),
                'privacy' => route('legal.privacy'),
            ],
            'copy' => [
                ...JoinSurveyConfig::copy(),
                'almostDone' => __('Almost done'),
                'confirmTitle' => __('Confirm your mobile number'),
                'createTitle' => __('Create your account'),
                'codeBody' => __('Enter the :length-digit code we texted to :mobile. Your account is created as soon as it matches.'),
                'otpOnBody' => __('We text a code to your mobile number. Your account — with everything you just filled in — is created once you confirm it.'),
                'otpOffBody' => __('Your account is created right away, with everything you just filled in.'),
                'accountErrorTitle' => __('Please fix the following:'),
                'otpResent' => __('A new code is on its way.'),
                'otpSendFailed' => __('We could not send the text just now. Please try again in a moment.'),
                'codeLabel' => __('Verification code'),
                'codeHint' => __('Codes expire after a few minutes. If it does not arrive, send a new one below.'),
                'verify' => __('Verify and create my account'),
                'verifyShort' => __('Verify code'),
                'checking' => __('Checking…'),
                'resend' => __('Send a new code'),
                'resendIn' => __('Resend in'),
                'changeDetails' => __('Change my details'),
                'resendNoScript' => __('The resend button works without JavaScript; it can be used once the cooldown has passed.'),
                'identityTitle' => __('How students reach you'),
                'firstName' => __('First name'),
                'lastName' => __('Last name'),
                'email' => __('Email'),
                'mobileLabel' => __('Mobile number'),
                'mobileHint' => __('Sri Lankan mobile, e.g. 07X XXX XXX X. You can sign in with it later.'),
                'password' => __('Password'),
                'passwordConfirmation' => __('Confirm password'),
                'agreeTitle' => __('Terms and policies accepted'),
                'agreePrefix' => __('I have read and accept the'),
                'teacherTerms' => __('teacher terms'),
                'agreeAnd' => __('and the'),
                'privacy' => __('privacy policy'),
                'agreeSuffix' => __(', including the commission and cancellation rules.'),
                'officialNote' => __('It’s official only after this: the account keeps the answers you just gave. Right after signing in, two short steps finish your profile — headline and bio, then your photo and the exact hours you teach.'),
                'sendMyCode' => __('Send my code'),
                'createMyAccount' => __('Create my account'),
                'sendCode' => __('Send code'),
                'createAccount' => __('Create account'),
            ],
        ];
    }
}
