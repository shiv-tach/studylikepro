<?php

use App\Models\User;
use App\Notifications\AdminNotice;
use App\Notifications\BookingConfirmed;
use Illuminate\Support\Facades\Notification;

/**
 * Emails are the only part of the product people take out of the app, so they
 * carry the brand: the markdown theme in resources/views/vendor/mail styles the
 * framework templates, and this checks the rendered HTML really uses it.
 */
it('renders notifications with the branded mail theme', function () {
    $user = User::factory()->student()->create();

    Notification::send($user, new AdminNotice('Your verification needs another look', 'Upload a clearer photo of your ID.', route('dashboard')));

    $html = deliveredMailHtml();

    expect($html)
        ->toContain('Studylikepro')
        ->toContain('#4f46e5')
        ->toContain('Upload a clearer photo of your ID.');
});

it('brands lifecycle emails too', function () {
    $scenario = paidBookingScenario();

    Notification::send($scenario['student'], new BookingConfirmed($scenario['booking']));

    $html = deliveredMailHtml();

    expect($html)
        ->toContain('Studylikepro')
        ->toContain('#4f46e5')
        ->toContain('View my lesson');
});

it('skips the mail copy for users who turned emails off', function () {
    $user = User::factory()->student()->create([
        'notification_preferences' => ['email' => false],
    ]);

    Notification::send($user, new AdminNotice('Quiet please', 'Body copy.'));

    expect(mailMessageCount())->toBe(0)
        ->and($user->notifications()->count())->toBe(1);
});

/**
 * The rendered HTML of every email the app handed to the (array) transport.
 */
function deliveredMailHtml(): string
{
    $messages = app('mailer')->getSymfonyTransport()->messages();

    expect($messages)->not->toBeEmpty();

    return $messages->map(fn ($message) => (string) $message->getOriginalMessage()->getHtmlBody())->implode("\n");
}

function mailMessageCount(): int
{
    return app('mailer')->getSymfonyTransport()->messages()->count();
}
