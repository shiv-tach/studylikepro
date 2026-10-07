@php
    $card = 'rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900';
    $heading = 'text-base font-bold text-slate-800 dark:text-slate-100';
    $body = 'mt-2 space-y-3 text-sm leading-relaxed text-slate-600 dark:text-slate-300';
@endphp

<x-public-layout :title="__('Privacy policy')">
    <article class="space-y-6">
        <header>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-slate-50">{{ __('Privacy policy') }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $updatedOn }} · {{ __('Policy version :version', ['version' => $policyVersion]) }}</p>
        </header>

        <section class="{{ $card }}">
            <h2 class="{{ $heading }}">{{ __('What we collect') }}</h2>
            <div class="{{ $body }}">
                <ul class="list-disc space-y-2 pl-5">
                    <li>{{ __('Account details: your name, email address and a securely hashed password.') }}</li>
                    <li>{{ __('Profile details: for students, grade level, learning goals and the subjects and lessons you pick; for teachers, headline, experience, education, languages, subjects, lessons, availability and rates.') }}</li>
                    <li>{{ __('Lesson content: the questions and photos you upload, your messages in lesson chats, and the details of every booking.') }}</li>
                    <li>{{ __('Payment records: amounts, currency, status and the payment provider\'s reference. Card and UPI details are handled by the payment provider — they never reach our servers.') }}</li>
                    <li>{{ __('Technical data: IP address and request metadata, kept for security and abuse prevention.') }}</li>
                </ul>
            </div>
        </section>

        <section class="{{ $card }}">
            <h2 class="{{ $heading }}">{{ __('Why we collect it') }}</h2>
            <div class="{{ $body }}">
                <ul class="list-disc space-y-2 pl-5">
                    <li>{{ __('To match a request with a verified teacher, schedule the lesson, take the payment and give you access to the classroom.') }}</li>
                    <li>{{ __('To keep both sides safe: verification documents, chat reports and refund decisions are all part of running a marketplace.') }}</li>
                    <li>{{ __('To send service messages — booking confirmations, reminders, receipts and refund updates. You can turn the email copies off in Settings; the in-app record always stays.') }}</li>
                    <li>{{ __('To improve teaching quality with aggregated, non-identifying statistics such as average rating per subject.') }}</li>
                </ul>
            </div>
        </section>

        <section class="{{ $card }}">
            <h2 class="{{ $heading }}">{{ __('Who we share it with') }}</h2>
            <div class="{{ $body }}">
                <p>{{ __('Only the partners needed to run the lesson, and only what they need:') }}</p>
                <ul class="list-disc space-y-2 pl-5">
                    <li>{{ __('The other party in the lesson — your first name, profile and the lesson details.') }}</li>
                    <li>{{ __('The payment provider, for collecting payments and issuing refunds.') }}</li>
                    <li>{{ __('The video provider, to create the private lesson room and its join links.') }}</li>
                    <li>{{ __('The AI provider, to read your uploaded question and suggest a subject and lesson.') }}</li>
                    <li>{{ __('Email and infrastructure providers, to deliver notifications and host the service.') }}</li>
                </ul>
                <p>{{ __('We do not sell personal data, and we do not use your questions or chats to train third-party models.') }}</p>
            </div>
        </section>

        <section class="{{ $card }}">
            <h2 class="{{ $heading }}">{{ __('How long we keep it') }}</h2>
            <div class="{{ $body }}">
                <p>{{ __('Account and lesson records are kept while your account is open and for as long as tax and accounting rules require afterwards. Verification documents are kept while a teacher is active and are deleted on request once verification is no longer needed. Question photos and chat attachments stay with the lesson they belong to so a later dispute can be reviewed fairly.') }}</p>
            </div>
        </section>

        <section class="{{ $card }}">
            <h2 class="{{ $heading }}">{{ __('Your choices') }}</h2>
            <div class="{{ $body }}">
                <ul class="list-disc space-y-2 pl-5">
                    <li>{{ __('Correct your profile details at any time from your profile and settings pages.') }}</li>
                    <li>{{ __('Turn email copies of notifications off in Settings.') }}</li>
                    <li>{{ __('Ask us to delete your account by writing to :email from the address you signed up with.', ['email' => $supportEmail]) }}</li>
                </ul>
                <p>{{ __('Teachers must be 18 or older. Students under 18 need a parent or guardian to agree to these terms; guardians can contact support to review or remove a young learner\'s data.') }}</p>
            </div>
        </section>

        <section class="{{ $card }}">
            <h2 class="{{ $heading }}">{{ __('Contacting us') }}</h2>
            <div class="{{ $body }}">
                <p>{{ __('Privacy questions, data requests and complaints: :email · :phone · :hours.', ['email' => $supportEmail, 'phone' => $supportPhone, 'hours' => $supportHours]) }}</p>
            </div>
        </section>
    </article>
</x-public-layout>
