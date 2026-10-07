@php
    use App\Enums\DisputeStatus;

    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $chip = 'inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300';
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);
    $booking = $dispute->booking;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">
                {{ __('Dispute #:id', ['id' => str_pad((string) $dispute->id, 5, '0', STR_PAD_LEFT)]) }}
            </h2>
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $dispute->status->badgeClasses() }}">{{ $dispute->status->label() }}</span>
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6">
        <a href="{{ route('admin.disputes.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 transition-colors hover:text-primary dark:text-slate-400">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            {{ __('Back to disputes') }}
        </a>

        @if (session('status') === 'dispute-reviewing')
            <div class="rounded-2xl border border-amber-200/80 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300">
                {{ __('Marked as in review — the reporter has not been told yet.') }}
            </div>
        @elseif (session('status') === 'dispute-resolved')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                {{ __('Dispute closed and both parties notified.') }}
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- The report -->
            <div class="{{ $cardBase }} lg:col-span-2" :class="{{ $cardTheme }}">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('The report') }}</h3>

                <dl class="mt-4 grid gap-5 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Reason') }}</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $dispute->reasonLabel() }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Raised') }}</dt>
                        <dd class="mt-1 text-sm text-slate-700 dark:text-slate-200">{{ $dispute->created_at->copy()->setTimezone($timezone)->format('d M Y, H:i') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Reported by') }}</dt>
                        <dd class="mt-1 text-sm">
                            <a href="{{ route('admin.users.show', $dispute->raisedBy) }}" class="font-semibold text-primary hover:underline">{{ $dispute->raisedBy?->name }}</a>
                            <p class="text-xs text-slate-400">{{ $dispute->raisedBy?->email }}</p>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('About') }}</dt>
                        <dd class="mt-1 text-sm">
                            @if ($dispute->against)
                                <a href="{{ route('admin.users.show', $dispute->against) }}" class="font-semibold text-primary hover:underline">{{ $dispute->against->name }}</a>
                                @if ($dispute->against->isSuspended())
                                    <span class="mt-1 inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-semibold text-rose-700 dark:bg-rose-900/30 dark:text-rose-400">{{ __('Suspended') }}</span>
                                @endif
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </dd>
                    </div>
                </dl>

                @if ($dispute->details)
                    <div class="mt-5 rounded-xl bg-slate-50 p-4 text-sm text-slate-700 dark:bg-slate-800/40 dark:text-slate-200">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('What they said') }}</p>
                        <p class="mt-1 whitespace-pre-line">{{ $dispute->details }}</p>
                    </div>
                @endif
            </div>

            <!-- Outcome -->
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Outcome') }}</h3>

                @if ($dispute->status->isClosed())
                    <dl class="mt-4 space-y-3 text-sm">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Decision') }}</dt>
                            <dd class="mt-1 text-slate-700 dark:text-slate-200">{{ $dispute->resolutionLabel() }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Closed') }}</dt>
                            <dd class="mt-1 text-slate-700 dark:text-slate-200">
                                {{ $dispute->resolved_at?->copy()->setTimezone($timezone)->format('d M Y, H:i') }}
                                @if ($dispute->resolvedBy) · {{ $dispute->resolvedBy->name }} @endif
                            </dd>
                        </div>
                        @if ($dispute->refund)
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Refund') }}</dt>
                                <dd class="mt-1 text-slate-700 dark:text-slate-200">
                                    {{ $money($dispute->refund->amount_minor) }} ({{ $dispute->refund->percent }}%)
                                    · <span class="{{ $chip }}">{{ $dispute->refund->status->label() }}</span>
                                </dd>
                            </div>
                        @endif
                        @if ($dispute->resolution_notes)
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Notes') }}</dt>
                                <dd class="mt-1 text-slate-700 dark:text-slate-200">{{ $dispute->resolution_notes }}</dd>
                            </div>
                        @endif
                    </dl>
                @else
                    @if ($dispute->status === DisputeStatus::Open)
                        <form method="POST" action="{{ route('admin.disputes.review', $dispute) }}" class="mt-4">
                            @csrf
                            <button type="submit"
                                    class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition-colors hover:border-primary hover:text-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                {{ __('Pick this up for review') }}
                            </button>
                        </form>
                    @else
                        <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">
                            {{ __('You picked this case up — close it below when you have decided.') }}
                        </p>
                    @endif

                    <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">{{ __('Closing the case notifies both parties and records the decision in the audit log.') }}</p>
                @endif
            </div>
        </div>

        <!-- The lesson -->
        @if ($booking)
            <div class="{{ $cardBase }} p-0" :class="{{ $cardTheme }}">
                <div class="border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('The lesson') }}</h3>
                </div>
                <div class="grid gap-5 px-6 py-5 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Lesson') }}</p>
                        <a href="{{ route('admin.bookings.show', $booking) }}" class="mt-1 block text-sm font-semibold text-primary hover:underline">
                            #{{ str_pad((string) $booking->id, 6, '0', STR_PAD_LEFT) }}
                        </a>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $booking->subject?->name }}@if ($booking->lesson) · {{ $booking->lesson->name }}@endif</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('When') }}</p>
                        <p class="mt-1 text-sm text-slate-700 dark:text-slate-200">{{ $booking->starts_at->copy()->setTimezone($timezone)->format('d M Y, H:i') }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $booking->status->badgeClasses() }}">{{ $booking->status->label() }}</span>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Students') }}</p>
                        <p class="mt-1 text-sm text-slate-700 dark:text-slate-200">{{ $booking->student->name }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Teacher: :name', ['name' => $booking->teacherProfile->user->name]) }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Money') }}</p>
                        <p class="mt-1 text-sm text-slate-700 dark:text-slate-200">{{ $money($booking->price_minor) }}</p>
                        @if ($payment)
                            <a href="{{ route('admin.payments.show', $payment) }}" class="text-xs font-semibold text-primary hover:underline">
                                {{ $payment->status->label() }} · {{ $money($payment->refundableMinor()) }} {{ __('refundable') }}
                            </a>
                        @else
                            <p class="text-xs text-slate-400">{{ __('No payment') }}</p>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Chat evidence -->
            <div class="{{ $cardBase }} p-0 lg:col-span-2" :class="{{ $cardTheme }}">
                <div class="border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Chat around the report') }}</h3>
                </div>

                <ul class="max-h-[28rem] space-y-3 overflow-y-auto px-6 py-4">
                    @forelse ($dispute->conversation?->messages->sortBy('id') ?? collect() as $message)
                        <li class="text-sm">
                            <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">
                                {{ $message->isSystem() ? __('Studylikepro') : ($message->sender?->name ?? __('Unknown')) }}
                                · {{ $message->created_at->copy()->setTimezone($timezone)->format('d M Y, H:i') }}
                            </p>
                            <p class="mt-0.5 whitespace-pre-line {{ $message->isSystem() ? 'italic text-slate-500 dark:text-slate-400' : 'text-slate-700 dark:text-slate-200' }}">{{ $message->body }}</p>
                        </li>
                    @empty
                        <li class="py-4 text-sm text-slate-500 dark:text-slate-400">{{ __('No chat attached to this report.') }}</li>
                    @endforelse
                </ul>
            </div>

            <!-- Resolve -->
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Close the case') }}</h3>

                @if ($dispute->status->isClosed())
                    <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">{{ __('This dispute is already closed.') }}</p>
                @else
                    <form method="POST" action="{{ route('admin.disputes.resolve', $dispute) }}" class="mt-4 space-y-3"
                          x-data="{ resolution: '{{ old('resolution', \App\Models\Dispute::RESOLUTION_REFUND_FULL) }}' }">
                        @csrf
                        <div>
                            <x-input-label for="resolution" :value="__('Decision')" />
                            <select id="resolution" name="resolution" x-model="resolution" class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                @foreach ($resolutions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('resolution') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div x-show="resolution === '{{ \App\Models\Dispute::RESOLUTION_REFUND_PARTIAL }}'" x-cloak>
                            <x-input-label for="percent" :value="__('Refund percentage')" />
                            <select id="percent" name="percent" class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                @foreach ([25, 50, 75] as $percent)
                                    <option value="{{ $percent }}" @selected(old('percent') == $percent)>{{ $percent }}%</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <x-input-label for="notes" :value="__('Notes for the record')" />
                            <textarea id="notes" name="notes" rows="3"
                                      class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">{{ old('notes') }}</textarea>
                            @error('notes')
                                <p class="mt-1 text-xs text-rose-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <x-primary-button>{{ __('Close the case') }}</x-primary-button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
