<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactMessageRequest;
use App\Models\ContactMessage;
use App\Models\User;
use App\Notifications\AdminNotice;
use App\Services\PlatformSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

/**
 * The public policy pages and the contact form. Figures on the refund page come
 * from the admin-editable platform settings, so the policy never drifts from
 * what the marketplace actually does.
 */
class LegalController extends Controller
{
    public function privacy(PlatformSettings $settings): View
    {
        return view('legal.privacy', $this->shared($settings));
    }

    public function terms(PlatformSettings $settings): View
    {
        return view('legal.terms', $this->shared($settings));
    }

    public function refunds(PlatformSettings $settings): View
    {
        return view('legal.refunds', $this->shared($settings));
    }

    public function contact(PlatformSettings $settings): View
    {
        return view('legal.contact', $this->shared($settings));
    }

    public function submitContact(ContactMessageRequest $request): RedirectResponse
    {
        $message = ContactMessage::query()->create([
            'user_id' => $request->user()?->id,
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'subject' => $request->validated('subject'),
            'message' => $request->validated('message'),
            'ip_address' => $request->ip(),
        ]);

        Notification::send(
            User::query()->role(User::ROLE_ADMIN)->get(),
            new AdminNotice(
                'Contact form: '.$message->subject,
                $message->name.' ('.$message->email.') wrote: '.$message->message,
                route('admin.dashboard'),
            ),
        );

        return redirect()
            ->route('legal.contact')
            ->with('status', 'contact-sent');
    }

    /**
     * @return array<string, mixed>
     */
    private function shared(PlatformSettings $settings): array
    {
        return [
            'supportEmail' => (string) config('studylikepro.support.email'),
            'supportPhone' => (string) config('studylikepro.support.phone'),
            'supportHours' => (string) config('studylikepro.support.hours'),
            'policyVersion' => (string) config('studylikepro.support.policy_version'),
            'updatedOn' => 'Reviewed on '.now()->format('d F Y'),
            'cancelWindowHours' => $settings->int('student_cancel_window_hours'),
            'studentRefundPercent' => $settings->int('refund_student_percent'),
            'teacherRefundPercent' => $settings->int('refund_teacher_percent'),
            'cancellationPolicy' => $settings->string('cancellation_policy_text'),
            'commissionPercent' => $settings->int('commission_percent'),
        ];
    }
}
