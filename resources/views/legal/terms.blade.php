@php
    $card = 'rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900';
    $heading = 'text-base font-bold text-slate-800 dark:text-slate-100';
    $body = 'mt-2 space-y-3 text-sm leading-relaxed text-slate-600 dark:text-slate-300';
@endphp

<x-public-layout :title="__('Terms of service')">
    <article class="space-y-6">
        <header>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-slate-50">{{ __('Terms of service') }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $updatedOn }} · {{ __('Policy version :version', ['version' => $policyVersion]) }}</p>
        </header>

        <section class="{{ $card }}">
            <h2 class="{{ $heading }}">{{ __('What Studylikepro is') }}</h2>
            <div class="{{ $body }}">
                <p>{{ __('Studylikepro is a marketplace that connects students with independent teachers for live, one-to-one online lessons. We provide the matching, scheduling, payment, classroom and support tools. The lesson itself is delivered by the teacher, who is not our employee.') }}</p>
                <p>{{ __('By creating an account you agree to these terms and to the :privacy and :refunds policies.', ['privacy' => __('privacy policy'), 'refunds' => __('cancellation & refund policy')]) }}</p>
            </div>
        </section>

        <section class="{{ $card }}">
            <h2 class="{{ $heading }}">{{ __('Accounts') }}</h2>
            <div class="{{ $body }}">
                <ul class="list-disc space-y-2 pl-5">
                    <li>{{ __('Give accurate details and keep your password private. You are responsible for what happens under your account.') }}</li>
                    <li>{{ __('One account per person. Teachers must be 18 or older and must complete verification before taking paid lessons.') }}</li>
                    <li>{{ __('Students under 18 may only use the platform with a parent or guardian\'s agreement.') }}</li>
                    <li>{{ __('We may suspend an account that breaks these terms, and will say why.') }}</li>
                </ul>
            </div>
        </section>

        <section class="{{ $card }}">
            <h2 class="{{ $heading }}">{{ __('Lessons and conduct') }}</h2>
            <div class="{{ $body }}">
                <ul class="list-disc space-y-2 pl-5">
                    <li>{{ __('Be on time and ready. Joining links open 15 minutes before the lesson and close 30 minutes after it ends.') }}</li>
                    <li>{{ __('Keep conversations in the lesson chat. Contact details shared to avoid the platform are visible to both sides and are not supported.') }}</li>
                    <li>{{ __('Harassment, discrimination, sexual content, and asking for answers to a live exam are grounds for immediate suspension.') }}</li>
                    <li>{{ __('Teachers may reschedule with reasonable notice; where a lesson cannot happen, the refund policy applies.') }}</li>
                </ul>
            </div>
        </section>

        <section class="{{ $card }}">
            <h2 class="{{ $heading }}">{{ __('Payments, commission and payouts') }}</h2>
            <div class="{{ $body }}">
                <ul class="list-disc space-y-2 pl-5">
                    <li>{{ __('Students pay Studylikepro at the time of booking. A slot is held for a limited time while the payment completes.') }}</li>
                    <li>{{ __('Studylikepro retains a :percent% commission on each lesson; the rest is owed to the teacher.', ['percent' => $commissionPercent]) }}</li>
                    <li>{{ __('A teacher\'s balance becomes available after the lesson is delivered, and is paid out in the next payout run.') }}</li>
                    <li>{{ __('Refunds are issued according to the :refunds.', ['refunds' => __('cancellation & refund policy')]) }}</li>
                </ul>
            </div>
        </section>

        <section class="{{ $card }}">
            <h2 class="{{ $heading }}">{{ __('Content you upload') }}</h2>
            <div class="{{ $body }}">
                <p>{{ __('You keep ownership of the questions, files and messages you upload, and you grant us a licence to store, process and show them to the other party in the lesson and to our support team when a report is raised. Do not upload material you do not have the right to share, and do not upload other people\'s personal documents.') }}</p>
                <p>{{ __('Lesson recordings, platform content and the Studylikepro name and logo belong to us or our licensors.') }}</p>
            </div>
        </section>

        <section class="{{ $card }}">
            <h2 class="{{ $heading }}">{{ __('Disputes and liability') }}</h2>
            <div class="{{ $body }}">
                <p>{{ __('Report a problem from the lesson chat. Support reviews the booking, the payment records and the chat, then decides — refund, warning, suspension or dismissal. That decision is final for the money the platform holds.') }}</p>
                <p>{{ __('We work to keep the platform available and safe but cannot promise uninterrupted service. To the extent the law allows, our liability is limited to the value of the lesson in question. Nothing here limits rights you have under Sri Lankan consumer law.') }}</p>
            </div>
        </section>

        <section class="{{ $card }}">
            <h2 class="{{ $heading }}">{{ __('Changes and the law') }}</h2>
            <div class="{{ $body }}">
                <p>{{ __('We may update these terms; material changes are announced in-app before they take effect. These terms are governed by the laws of Sri Lanka, with the courts at our registered address having jurisdiction.') }}</p>
                <p>{{ __('Questions: :email · :phone · :hours.', ['email' => $supportEmail, 'phone' => $supportPhone, 'hours' => $supportHours]) }}</p>
            </div>
        </section>
    </article>
</x-public-layout>
