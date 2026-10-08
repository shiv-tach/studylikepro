@php
    use App\Enums\PurchaseStatus;

    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $timezone = config('studylikepro.default_display_timezone');
    $isPlan = $purchase->isLessonPlan();
    $remaining = $isPlan ? $purchase->remainingMinutes() : 0;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('student.purchases.index') }}" class="text-xs font-semibold text-slate-400 transition-colors hover:text-primary">&larr; {{ __('My purchases') }}</a>
            <h2 class="mt-1 font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">
                {{ $purchase->type()->icon() }} {{ $purchase->scopeLabel() }}
            </h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ $purchase->type()->label() }} · {{ $purchase->teacherProfile->user->name }}
            </p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
        @if (session('status') === 'purchase-created')
            <div class="rounded-2xl border border-primary/30 bg-primary/5 p-4 text-sm text-primary">
                {{ __('Your purchase is ready — pay now to activate it.') }}
            </div>
        @elseif (session('status') === 'purchase-paid')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                {{ __('Payment received — your purchase is now active.') }}
            </div>
        @elseif (session('status') === 'payment-pending')
            <div class="rounded-2xl border border-amber-200/80 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300">
                {{ __('We are still waiting for the payment to reach us. Refresh in a moment — nothing has been charged twice.') }}
            </div>
        @elseif (session('status') === 'purchase-cancelled')
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">
                {{ __('The purchase was cancelled.') }}
            </div>
        @elseif (session('status') === 'session-booked')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                {{ __('Session booked — it is already paid for from your plan.') }}
            </div>
        @elseif (session('status') === 'session-cancelled')
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">
                {{ __('The session was cancelled and its time returned to your plan.') }}
            </div>
        @endif

        @if ($errors->has('status'))
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300">
                {{ $errors->first('status') }}
            </div>
        @endif

        <!-- Status + money -->
        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Status') }}</p>
                    <span class="mt-1 inline-block rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $purchase->status->badgeClasses() }}">{{ $purchase->status->label() }}</span>
                    @if ($purchase->expires_at)
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                            {{ $purchase->status === PurchaseStatus::PendingPayment ? __('Payment hold until') : __('Valid until') }}
                            {{ $purchase->expires_at->copy()->setTimezone($timezone)->format('d M Y, H:i') }}
                        </p>
                    @endif
                </div>
                <div class="text-right">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Total price') }}</p>
                    <p class="mt-1 text-2xl font-extrabold tracking-tight text-slate-900 dark:text-slate-100">{{ platform_settings()->formatMinor($purchase->price_minor) }}</p>
                    @if ($purchase->booking_fee_minor > 0)
                        <p class="mt-1 text-xs text-slate-400">{{ __('includes :fee platform fee', ['fee' => platform_settings()->formatMinor($purchase->netBookingFeeMinor())]) }}</p>
                    @endif
                </div>
            </div>

            @if ($purchase->status === PurchaseStatus::PendingPayment)
                <div class="mt-5 flex flex-wrap gap-3 border-t border-slate-200 pt-5 dark:border-slate-800">
                    <form method="POST" action="{{ route('student.purchases.checkout', $purchase) }}">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary/90">
                            {{ __('Pay now') }} — {{ platform_settings()->formatMinor($purchase->totalMinor()) }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('student.purchases.cancel', $purchase) }}">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                            {{ __('Cancel purchase') }}
                        </button>
                    </form>
                </div>
            @endif

            @if ($capturedPayment)
                <p class="mt-4 text-xs text-slate-400">
                    {{ __('Paid :when via :gateway', [
                        'when' => $capturedPayment->captured_at?->setTimezone($timezone)->format('d M Y, H:i'),
                        'gateway' => $capturedPayment->gateway,
                    ]) }}
                </p>
            @endif
        </div>

        <!-- Lesson plan: balance + sessions -->
        @if ($isPlan && $purchase->status === PurchaseStatus::Active)
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('Your plan') }}</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            {{ __(':total minutes split into sessions the way you like.', ['total' => $purchase->allocation_minutes]) }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Time left') }}</p>
                        <p class="mt-1 text-2xl font-extrabold tracking-tight {{ $remaining > 0 ? 'text-primary' : 'text-emerald-600 dark:text-emerald-400' }}">
                            {{ $remaining > 0 ? $remaining.' '.__('min') : __('Done') }}
                        </p>
                    </div>
                </div>

                <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                    @php $percent = $purchase->allocation_minutes > 0 ? min(100, round(($purchase->allocation_minutes - $remaining) * 100 / $purchase->allocation_minutes)) : 0; @endphp
                    <div class="h-full rounded-full bg-primary transition-all" style="width: {{ $percent }}%"></div>
                </div>

                @if ($remaining > 0)
                    <div class="mt-5">
                        @if ($purchase->sessionsRemaining() === 0)
                            <p class="text-sm text-amber-700 dark:text-amber-400">{{ __('This plan has reached its session limit.') }}</p>
                        @else
                            <a href="{{ route('student.purchase-sessions.create', $purchase) }}"
                               class="inline-flex items-center rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary/90">
                                {{ __('Book a session') }}
                            </a>
                        @endif
                    </div>
                @endif
            </div>
        @endif

        <!-- Summary service -->
        @if ($purchase->type() === \App\Enums\ServiceType::Summary)
            <a href="{{ route('student.summaries.show', $purchase) }}"
               class="{{ $cardBase }} block transition-all hover:-translate-y-0.5 hover:shadow-md" :class="{{ $cardTheme }}">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('Lesson summary') }}</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            @if ($purchase->summary?->isDelivered())
                                {{ __('Delivered — tap to read.') }}
                            @else
                                {{ __('Your teacher is writing it — you will be notified when it is ready.') }}
                            @endif
                        </p>
                    </div>
                    <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $purchase->summary?->isDelivered() ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' }}">
                        {{ $purchase->summary?->isDelivered() ? __('Ready') : __('In progress') }}
                    </span>
                </div>
            </a>
        @endif

        <!-- Paper marking service -->
        @if ($purchase->type() === \App\Enums\ServiceType::PaperMarking && $purchase->markingOrder)
            <a href="{{ route('student.marking-orders.show', $purchase->markingOrder) }}"
               class="{{ $cardBase }} block transition-all hover:-translate-y-0.5 hover:shadow-md" :class="{{ $cardTheme }}">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('Paper marking') }}</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            @if ($purchase->markingOrder->status === \App\Enums\MarkingOrderStatus::Returned)
                                {{ __('Your marked paper is ready — open it to accept or dispute.') }}
                            @elseif ($purchase->markingOrder->isOverdue())
                                {{ __('Past the turnaround deadline — contact support if nothing arrives soon.') }}
                            @else
                                {{ __('Being marked — usually back within :hours hours.', ['hours' => $purchase->markingOrder->turnaround_hours ?? config('studylikepro.services.marking_default_turnaround_hours')]) }}
                            @endif
                        </p>
                    </div>
                    <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $purchase->markingOrder->status->badgeClasses() }}">{{ $purchase->markingOrder->status->label() }}</span>
                </div>
            </a>
        @endif

        <!-- Sessions list -->
        @if ($purchase->sessions->isNotEmpty())
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('Sessions') }}</h3>
                <ul class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($purchase->sessions as $session)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                            <a href="{{ route('student.bookings.show', $session) }}" class="group flex-1">
                                <p class="text-sm font-semibold text-slate-700 group-hover:text-primary dark:text-slate-200">
                                    {{ $session->starts_at->copy()->setTimezone($timezone)->format('D, d M Y · H:i') }}
                                    · {{ $session->durationMinutes() }} {{ __('min') }}
                                </p>
                                @if ($session->subject || $session->lesson)
                                    <p class="text-xs text-slate-400">{{ $session->subject?->name }}@if ($session->lesson) · {{ $session->lesson->name }}@endif</p>
                                @endif
                            </a>
                            <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $session->status->badgeClasses() }}">{{ $session->status->label() }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</x-app-layout>
