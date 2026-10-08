@php
    use App\Enums\MarkingOrderStatus;
    use App\Enums\PurchaseStatus;

    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $fieldClasses = 'rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';
    $deadline = $order->deadlineAt();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('student.purchases.show', $order->purchase) }}" class="text-xs font-semibold text-slate-400 transition-colors hover:text-primary">&larr; {{ __('Back to purchase') }}</a>
            <h2 class="mt-1 font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Paper marking') }} · {{ $order->subject?->name ?? $order->offering?->title }}</h2>
        </div>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
        @if (session('status') === 'marking-accepted')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                {{ __('Thanks — the marking is accepted and the teacher has been paid out.') }}
            </div>
        @elseif (session('status') === 'marking-disputed')
            <div class="rounded-2xl border border-rose-200/80 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300">
                {{ __('Your dispute has been opened — support will review it and get back to you.') }}
            </div>
        @endif

        @error('reason')
            <div class="rounded-2xl border border-rose-200/80 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300">
                {{ $message }}
            </div>
        @enderror

        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $order->status->badgeClasses() }}">{{ $order->status->label() }}</span>
                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                        {{ $order->purchase?->status->label() }}
                    </span>
                </div>
                <p class="text-xs text-slate-400">
                    {{ __('Submitted') }} {{ $order->submitted_at?->format('d M Y, H:i') }}
                </p>
            </div>

            @if ($order->purchase?->status === PurchaseStatus::PendingPayment)
                <div class="mt-4 rounded-xl border border-amber-200/80 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300">
                    {{ __('Your paper is uploaded. Pay on the purchase page to send it to the teacher.') }}
                    <a href="{{ route('student.purchases.show', $order->purchase) }}" class="ml-1 font-semibold underline">{{ __('Go to payment') }}</a>
                </div>
            @elseif (in_array($order->status, [MarkingOrderStatus::Submitted, MarkingOrderStatus::InProgress], true))
                @if ($deadline)
                    <p class="mt-4 text-sm {{ $order->isOverdue() ? 'font-semibold text-rose-600 dark:text-rose-400' : 'text-slate-500 dark:text-slate-400' }}">
                        @if ($order->isOverdue())
                            {{ __('⚠️ The deadline passed :time — the teacher is late. You can dispute once the paper is returned.', ['time' => $deadline->diffForHumans()]) }}
                        @else
                            {{ __('Expected back by :time (:hours hours after payment).', ['time' => $deadline->format('d M Y, H:i'), 'hours' => $order->effectiveTurnaroundHours()]) }}
                        @endif
                    </p>
                @endif
            @endif
        </div>

        <!-- Uploaded paper -->
        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('Your uploaded paper') }}</h3>
            @if ($order->page_count)
                <p class="mt-1 text-xs text-slate-400">{{ __(':pages pages', ['pages' => $order->page_count]) }}</p>
            @endif
            <ul class="mt-4 space-y-2">
                @forelse ($order->files ?? [] as $file)
                    <li class="flex items-center justify-between gap-3 rounded-xl border border-slate-200/80 px-3 py-2 dark:border-slate-800">
                        <span class="min-w-0 truncate text-sm text-slate-600 dark:text-slate-300">{{ $file['original_name'] ?? $file['path'] }}</span>
                        <a href="{{ route('student.marking-orders.download', [$order, 'files', $loop->index]) }}"
                           class="shrink-0 text-xs font-semibold text-primary hover:underline">{{ __('Download') }}</a>
                    </li>
                @empty
                    <p class="text-xs text-slate-400">{{ __('No files uploaded.') }}</p>
                @endforelse
            </ul>
        </div>

        <!-- Returned delivery -->
        @if ($order->status === MarkingOrderStatus::Returned)
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('Marked copy & feedback') }}</h3>

                @if ($order->feedback)
                    <div class="mt-4 whitespace-pre-line rounded-xl bg-slate-50 p-4 text-sm leading-relaxed text-slate-600 dark:bg-slate-900/60 dark:text-slate-300">{{ $order->feedback }}</div>
                @endif

                @if (! empty($order->marks))
                    <table class="mt-4 w-full overflow-hidden rounded-xl text-left text-sm">
                        <thead>
                            <tr class="bg-slate-50 text-xs uppercase tracking-wider text-slate-400 dark:bg-slate-900/60">
                                <th class="px-4 py-2 font-semibold">{{ __('Item') }}</th>
                                <th class="px-4 py-2 font-semibold">{{ __('Marks') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                            @foreach ($order->marks as $mark)
                                <tr>
                                    <td class="px-4 py-2 text-slate-600 dark:text-slate-300">{{ $mark['label'] ?? '' }}</td>
                                    <td class="px-4 py-2 font-semibold text-slate-800 dark:text-slate-100">{{ $mark['value'] ?? '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                <ul class="mt-4 space-y-2">
                    @foreach ($order->marked_files ?? [] as $file)
                        <li class="flex items-center justify-between gap-3 rounded-xl border border-slate-200/80 px-3 py-2 dark:border-slate-800">
                            <span class="min-w-0 truncate text-sm text-slate-600 dark:text-slate-300">{{ $file['original_name'] ?? $file['path'] }}</span>
                            <a href="{{ route('student.marking-orders.download', [$order, 'marked_files', $loop->index]) }}"
                               class="shrink-0 text-xs font-semibold text-primary hover:underline">{{ __('Download') }}</a>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-6 grid gap-4 border-t border-slate-200 pt-6 dark:border-slate-800 sm:grid-cols-2">
                    <form method="POST" action="{{ route('student.marking-orders.accept', $order) }}">
                        @csrf
                        <x-primary-button class="w-full justify-center">
                            {{ __('Accept marking') }}
                        </x-primary-button>
                        <p class="mt-2 text-center text-xs text-slate-400">{{ __('Happy? Accept and close the order.') }}</p>
                    </form>

                    <form method="POST" action="{{ route('student.marking-orders.dispute', $order) }}" x-data="{ open: false }">
                        @csrf
                        <button type="button" @click="open = !open"
                                class="w-full rounded-xl border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600 transition-colors hover:bg-rose-50 dark:border-rose-900/40 dark:text-rose-400 dark:hover:bg-rose-950/30">
                            {{ __('Dispute marking') }}
                        </button>
                        <div x-show="open" x-cloak class="mt-3 space-y-2">
                            <x-input-label for="reason" :value="__('What went wrong?')" />
                            <textarea id="reason" name="reason" rows="4" required minlength="10" maxlength="2000"
                                      class="mt-1 block w-full {{ $fieldClasses }}">{{ old('reason') }}</textarea>
                            <x-input-error :messages="$errors->get('reason')" />
                            <button type="submit"
                                    class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-rose-700">
                                {{ __('Open dispute') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @elseif (in_array($order->status, [MarkingOrderStatus::Accepted, MarkingOrderStatus::Disputed, MarkingOrderStatus::Resolved], true))
            @if ($order->feedback || ! empty($order->marked_files))
                <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                    <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('Marked copy & feedback') }}</h3>
                    @if ($order->feedback)
                        <div class="mt-4 whitespace-pre-line rounded-xl bg-slate-50 p-4 text-sm leading-relaxed text-slate-600 dark:bg-slate-900/60 dark:text-slate-300">{{ $order->feedback }}</div>
                    @endif
                    <ul class="mt-4 space-y-2">
                        @foreach ($order->marked_files ?? [] as $file)
                            <li class="flex items-center justify-between gap-3 rounded-xl border border-slate-200/80 px-3 py-2 dark:border-slate-800">
                                <span class="min-w-0 truncate text-sm text-slate-600 dark:text-slate-300">{{ $file['original_name'] ?? $file['path'] }}</span>
                                <a href="{{ route('student.marking-orders.download', [$order, 'marked_files', $loop->index]) }}"
                                   class="shrink-0 text-xs font-semibold text-primary hover:underline">{{ __('Download') }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if ($order->status === MarkingOrderStatus::Disputed && $order->dispute_reason)
                <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                    <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('Your dispute') }}</h3>
                    <p class="mt-2 whitespace-pre-line text-sm text-slate-600 dark:text-slate-300">{{ $order->dispute_reason }}</p>
                    <p class="mt-3 text-xs text-slate-400">{{ __('Support is reviewing this and will resolve it shortly.') }}</p>
                </div>
            @endif
        @endif
    </div>
</x-app-layout>
