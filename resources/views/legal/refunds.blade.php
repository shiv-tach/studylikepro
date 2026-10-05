@php
    $card = 'rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900';
    $heading = 'text-base font-bold text-slate-800 dark:text-slate-100';
    $body = 'mt-2 space-y-3 text-sm leading-relaxed text-slate-600 dark:text-slate-300';
@endphp

<x-public-layout :title="__('Cancellation & refunds')">
    <article class="space-y-6">
        <header>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-slate-50">{{ __('Cancellation & refund policy') }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $updatedOn }} · {{ __('Policy version :version', ['version' => $policyVersion]) }}</p>
        </header>

        <section class="{{ $card }}">
            <h2 class="{{ $heading }}">{{ __('The short version') }}</h2>
            <div class="{{ $body }}">
                <p>{{ $cancellationPolicy }}</p>
                <p>{{ __('Money moves through Studylikepro, never between you and the other party directly. Refund decisions are made here in the platform, based on this policy and the lesson record.') }}</p>
            </div>
        </section>

        <section class="{{ $card }}">
            <h2 class="{{ $heading }}">{{ __('If you cancel') }}</h2>
            <div class="{{ $body }}">
                <ul class="list-disc space-y-2 pl-5">
                    <li>{{ __('More than :hours hours before the lesson: you are refunded :percent% and the slot is released.', ['hours' => $cancelWindowHours, 'percent' => $studentRefundPercent]) }}</li>
                    <li>{{ __('Inside :hours hours: the teacher has already blocked the time, so the automatic refund does not apply. Contact support — we look at the lesson history before deciding.', ['hours' => $cancelWindowHours]) }}</li>
                    <li>{{ __('If the teacher cancels, you are always refunded :percent%, whatever the timing.', ['percent' => $teacherRefundPercent]) }}</li>
                </ul>
            </div>
        </section>

        <section class="{{ $card }}">
            <h2 class="{{ $heading }}">{{ __('If a lesson does not happen') }}</h2>
            <div class="{{ $body }}">
                <ul class="list-disc space-y-2 pl-5">
                    <li>{{ __('Nobody joined: report it from the lesson chat within 48 hours. We check the classroom record and refund in full where the teacher was responsible.') }}</li>
                    <li>{{ __('The lesson was shorter or different from what was agreed: support can refund part of the fee — the percentage depends on what actually happened.') }}</li>
                    <li>{{ __('A lesson nobody closes is completed automatically after it ends, and the teacher is paid for it.') }}</li>
                </ul>
            </div>
        </section>

        <section class="{{ $card }}">
            <h2 class="{{ $heading }}">{{ __('How refunds are paid') }}</h2>
            <div class="{{ $body }}">
                <p>{{ __('Refunds go back the way the payment came in. Card, UPI and net-banking refunds are issued through our payment provider and typically reach you in 5–7 working days; the exact timing is set by your bank.') }}</p>
                <p>{{ __('Every refund appears on the lesson receipt and in your notifications, with the amount and the reason recorded.') }}</p>
            </div>
        </section>

        <section class="{{ $card }}">
            <h2 class="{{ $heading }}">{{ __('Payouts to teachers') }}</h2>
            <div class="{{ $body }}">
                <p>{{ __('Studylikepro keeps a :percent% platform commission on each lesson.', ['percent' => $commissionPercent]) }} {{ __('The rest becomes available to the teacher once the lesson is delivered, and is transferred in the next payout run. If a lesson is refunded, the matching payout is adjusted before it is sent.') }}</p>
            </div>
        </section>

        <section class="{{ $card }}">
            <h2 class="{{ $heading }}">{{ __('Questions or a dispute') }}</h2>
            <div class="{{ $body }}">
                <p>{{ __('Use "Report a problem" inside the lesson chat, or write to :email. Support reviews the booking, the payment and the chat before deciding, and both sides are told the outcome.', ['email' => $supportEmail]) }}</p>
            </div>
        </section>
    </article>
</x-public-layout>
