@php
    $cardBase = 'rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900';
    $timezone = auth()->user()->studentProfile?->timezone ?? config('studylikepro.default_display_timezone');
    $refunded = $refunds->where('status', \App\Enums\RefundStatus::Processed)->sum('amount_minor');
    $netPaid = $payment->amount_minor - $refunded;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <a href="{{ route('student.bookings.show', $booking) }}" class="text-xs font-semibold text-slate-400 transition-colors hover:text-primary">&larr; {{ __('Back to the lesson') }}</a>
                <h2 class="mt-1 font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Receipt') }}</h2>
            </div>
            <button type="button" onclick="window.print()"
                    class="inline-flex items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800 print:hidden">
                {{ __('Print / save as PDF') }}
            </button>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-6">
        <div class="{{ $cardBase }}">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ config('app.name') }}</p>
                    <h3 class="mt-1 text-lg font-bold text-slate-800 dark:text-slate-100">{{ __('Lesson receipt') }}</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ __('Receipt #:ref · :date', [
                            'ref' => str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT),
                            'date' => $payment->captured_at?->copy()->setTimezone($timezone)->format('d M Y, H:i') ?? $booking->created_at->format('d M Y'),
                        ]) }}
                    </p>
                </div>
                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $payment->status->badgeClasses() }}">{{ $payment->status->label() }}</span>
            </div>

            <dl class="mt-6 grid gap-4 border-t border-slate-200 pt-6 text-sm sm:grid-cols-2 dark:border-slate-800">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Billed to') }}</dt>
                    <dd class="mt-1 font-semibold text-slate-800 dark:text-slate-100">{{ $payment->student->name }}</dd>
                    <dd class="text-slate-500 dark:text-slate-400">{{ $payment->student->email }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Lesson') }}</dt>
                    <dd class="mt-1 font-semibold text-slate-800 dark:text-slate-100">
                        {{ $booking->subject?->name }}@if ($booking->lesson) · {{ $booking->lesson->name }}@endif
                    </dd>
                    <dd class="text-slate-500 dark:text-slate-400">
                        {{ $booking->starts_at->copy()->setTimezone($timezone)->format('D d M Y, H:i') }}–{{ $booking->ends_at->copy()->setTimezone($timezone)->format('H:i') }}
                        · {{ $booking->durationMinutes() }} {{ __('minutes') }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Teacher') }}</dt>
                    <dd class="mt-1 font-semibold text-slate-800 dark:text-slate-100">{{ $booking->teacherProfile->user->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Learner') }}</dt>
                    <dd class="mt-1 font-semibold text-slate-800 dark:text-slate-100">{{ $booking->learner_name ?? $payment->student->name }}</dd>
                    @if ($booking->learnerGrade)
                        <dd class="text-slate-500 dark:text-slate-400">{{ $booking->learnerGrade->label }}</dd>
                    @endif
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Payment method') }}</dt>
                    <dd class="mt-1 font-semibold text-slate-800 dark:text-slate-100">{{ strtoupper((string) ($payment->method ?? $payment->gateway)) }}</dd>
                    <dd class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ $payment->gateway_payment_id }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Order reference') }}</dt>
                    <dd class="mt-1 font-mono text-xs text-slate-600 dark:text-slate-300">{{ $payment->gateway_order_id }}</dd>
                </div>
            </dl>

            <table class="mt-6 w-full border-t border-slate-200 text-sm dark:border-slate-800">
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                    <tr>
                        <td class="py-3 text-slate-600 dark:text-slate-300">{{ __('Lesson fee') }}</td>
                        <td class="py-3 text-right font-semibold text-slate-800 dark:text-slate-100">{{ platform_settings()->formatMinor($booking->price_minor) }}</td>
                    </tr>
                    @if ($booking->booking_fee_minor > 0)
                        <tr>
                            <td class="py-3 text-slate-600 dark:text-slate-300">{{ __('Platform booking fee') }}</td>
                            <td class="py-3 text-right font-semibold text-slate-800 dark:text-slate-100">{{ platform_settings()->formatMinor($booking->booking_fee_minor) }}</td>
                        </tr>
                    @endif
                    @if ($booking->hasBookingFeeDiscount())
                        <tr>
                            <td class="py-3 text-slate-600 dark:text-slate-300">
                                {{ __('Special offer') }}
                                @if ($booking->bookingFeePromotion)
                                    <span class="block text-xs text-emerald-600 dark:text-emerald-400">{{ $booking->bookingFeePromotion->name }}</span>
                                @endif
                            </td>
                            <td class="py-3 text-right font-semibold text-emerald-600 dark:text-emerald-400">−{{ platform_settings()->formatMinor($booking->booking_fee_discount_minor) }}</td>
                        </tr>
                    @endif
                    @foreach ($refunds as $refund)
                        <tr>
                            <td class="py-3 text-slate-600 dark:text-slate-300">
                                {{ __('Refund (:percent%)', ['percent' => $refund->percent]) }}
                                @if ($refund->reason)
                                    <span class="block text-xs text-slate-400">{{ $refund->reason }}</span>
                                @endif
                            </td>
                            <td class="py-3 text-right font-semibold text-emerald-600 dark:text-emerald-400">−{{ platform_settings()->formatMinor($refund->amount_minor) }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td class="py-3 text-sm font-bold text-slate-800 dark:text-slate-100">{{ $refunds->isEmpty() ? __('Total paid') : __('Net paid') }}</td>
                        <td class="py-3 text-right text-lg font-extrabold tracking-tight text-slate-900 dark:text-slate-100">{{ platform_settings()->formatMinor($netPaid) }}</td>
                    </tr>
                </tbody>
            </table>

            <p class="mt-6 border-t border-slate-200 pt-4 text-xs leading-relaxed text-slate-500 dark:border-slate-800 dark:text-slate-400">
                {{ platform_settings()->string('cancellation_policy_text') }}
            </p>
        </div>
    </div>
</x-app-layout>
