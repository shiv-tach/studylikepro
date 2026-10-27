<?php

namespace App\Services;

use App\Contracts\SmsGateway;
use App\Models\TeacherProfile;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Log;

/**
 * Texts the operations mobile when a teacher submits a verification
 * application, so a new application does not sit unnoticed in the admin queue.
 *
 * The alert is best effort: a provider outage must never cost the teacher their
 * submission, so every failure is logged and swallowed, and a blank
 * `studylikepro.sms.admin_alert_mobile` simply turns the alert off.
 */
class VerificationAlertService
{
    public function __construct(private readonly SmsGateway $sms) {}

    public function notifyNewSubmission(TeacherProfile $profile): void
    {
        $mobile = PhoneNumber::normalize((string) config('studylikepro.sms.admin_alert_mobile'));

        if ($mobile === null) {
            return;
        }

        $profile->loadMissing('user');

        try {
            $this->sms->send($mobile, $this->message($profile));
        } catch (\Throwable $e) {
            Log::warning('The admin alert SMS for a new verification was not delivered.', [
                'mobile' => PhoneNumber::format($mobile),
                'teacher_profile_id' => $profile->getKey(),
                'reason' => $e->getMessage(),
            ]);
        }
    }

    private function message(TeacherProfile $profile): string
    {
        $name = $profile->user?->displayName() ?: 'a teacher';

        return 'New teacher verification: '.$name.'.';
    }
}
