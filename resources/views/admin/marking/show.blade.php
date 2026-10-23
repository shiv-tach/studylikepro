@php
    use App\Enums\MarkingOrderStatus;

    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);
    $timezone = config('studylikepro.default_display_timezone');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('admin.marking.index') }}" class="text-xs font-semibold text-slate-400 transition-colors hover:text-primary">&larr; {{ __('Paper marking orders') }}</a>
            <h2 class="mt-1 font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">
                {{ __('Marking order') }} #{{ $order->id }}
            </h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ $order->purchase->student->name }} · {{ $order->teacherProfile->user->name }} · {{ $order->subject?->name }}
            </p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
        @if (session('status') === 'marking-resolved')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                {{ __('The marking order was resolved.') }}
            </div>
        @elseif (session('status') === 'marking-refunded')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                {{ __('Refund issued for the marking order.') }}
            </div>
        @endif

        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <dl class="grid gap-4 text-sm sm:grid-cols-3">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Status') }}</dt>
                    <dd class="mt-1"><span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $order->status->badgeClasses() }}">{{ $order->status->label() }}</span></dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Price') }}</dt>
                    <dd class="mt-1 font-bold text-slate-800 dark:text-slate-100">{{ $money($order->purchase->price_minor) }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Deadline') }}</dt>
                    <dd class="mt-1 {{ $order->isOverdue() ? 'font-semibold text-rose-500' : 'font-semibold text-slate-700 dark:text-slate-200' }}">
                        {{ display_datetime($order->deadline_at?->copy()->setTimezone($timezone)) ?? '—' }}
                        @if ($order->isOverdue()) · {{ __('overdue') }} @endif
                    </dd>
                </div>
            </dl>
            <dl class="mt-4 grid gap-4 border-t border-slate-200 pt-4 text-sm sm:grid-cols-3 dark:border-slate-800">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Submitted') }}</dt>
                    <dd class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ display_datetime($order->submitted_at?->copy()->setTimezone($timezone)) ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Returned') }}</dt>
                    <dd class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ display_datetime($order->returned_at?->copy()->setTimezone($timezone)) ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Paid') }}</dt>
                    <dd class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ display_datetime($order->purchase->paid_at?->copy()->setTimezone($timezone)) ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('Uploaded paper') }}</h3>
            <p class="mt-1 text-xs text-slate-400">{{ __(':count file(s) · :pages pages', ['count' => count($order->files ?? []), 'pages' => $order->page_count ?? '—']) }}</p>
            <ul class="mt-3 space-y-1 text-sm">
                @foreach ($order->files ?? [] as $file)
                    <li class="truncate text-slate-600 dark:text-slate-300">📄 {{ $file['original_name'] ?? $file['path'] }}</li>
                @endforeach
            </ul>
        </div>

        @if (! empty($order->feedback) || ! empty($order->marks) || ! empty($order->marked_files))
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('Teacher feedback') }}</h3>
                <p class="mt-2 whitespace-pre-line text-sm text-slate-600 dark:text-slate-300">{{ $order->feedback }}</p>
                @if (! empty($order->marks))
                    <dl class="mt-4 grid gap-2 sm:grid-cols-2">
                        @foreach ($order->marks as $label => $mark)
                            <div class="flex justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800/50">
                                <dt class="text-slate-500 dark:text-slate-400">{{ $label }}</dt>
                                <dd class="font-semibold text-slate-700 dark:text-slate-200">{{ $mark }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
                <ul class="mt-3 space-y-1 text-sm">
                    @foreach ($order->marked_files ?? [] as $file)
                        <li class="truncate text-slate-600 dark:text-slate-300">📄 {{ $file['original_name'] ?? $file['path'] }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($order->dispute_reason)
            <div class="rounded-2xl border border-rose-200/80 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300">
                <p class="font-semibold">{{ __('Student dispute') }}</p>
                <p class="mt-1">{{ $order->dispute_reason }}</p>
            </div>
        @endif

        @if ($order->status === MarkingOrderStatus::Disputed)
            <div class="{{ $cardBase }} space-y-4" :class="{{ $cardTheme }}">
                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('Resolve the dispute') }}</h3>
                <form method="POST" action="{{ route('admin.marking.resolve', $order) }}" class="space-y-3">
                    @csrf
                    <x-input-label for="decision" :value="__('Decision')" />
                    <select id="decision" name="decision" required
                            class="block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                        <option value="accept">{{ __('Side with the teacher — student keeps the result') }}</option>
                        <option value="refund">{{ __('Side with the student — refund in full') }}</option>
                    </select>
                    <x-input-label for="note" :value="__('Note (optional)')" />
                    <textarea id="note" name="note" rows="2"
                              class="block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">{{ old('note') }}</textarea>
                    <button type="submit"
                            class="inline-flex items-center rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary/90">
                        {{ __('Resolve') }}
                    </button>
                </form>
            </div>
        @endif

        @if (! $order->status->isFinal())
            <form method="POST" action="{{ route('admin.marking.refund', $order) }}" class="{{ $cardBase }} space-y-3" :class="{{ $cardTheme }}">
                @csrf
                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('Refund without dispute') }}</h3>
                <x-input-label for="reason" :value="__('Reason')" />
                <textarea id="reason" name="reason" rows="2" required
                          class="block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">{{ old('reason') }}</textarea>
                <x-input-error :messages="$errors->get('reason')" />
                <button type="submit"
                        class="inline-flex items-center rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-rose-500">
                    {{ __('Refund') }}
                </button>
            </form>
        @endif
    </div>
</x-app-layout>
