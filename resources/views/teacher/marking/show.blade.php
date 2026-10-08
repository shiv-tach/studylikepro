@php
    use App\Enums\MarkingOrderStatus;

    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $fieldClasses = 'rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';
    $deadline = $order->deadlineAt();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('teacher.marking.index') }}" class="text-xs font-semibold text-slate-400 transition-colors hover:text-primary">&larr; {{ __('Marking inbox') }}</a>
            <h2 class="mt-1 font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Marking order') }} · {{ $order->subject?->name ?? __('Paper marking') }}</h2>
        </div>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
        @if (session('status') === 'marking-returned')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                {{ __('Marked copy delivered — the student has been notified.') }}
            </div>
        @endif

        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $order->status->badgeClasses() }}">{{ $order->status->label() }}</span>
                    @if ($order->isOverdue())
                        <span class="rounded-full bg-rose-100 px-2.5 py-0.5 text-[11px] font-semibold text-rose-700 dark:bg-rose-900/30 dark:text-rose-400">{{ __('Overdue') }}</span>
                    @endif
                </div>
                <div class="text-right text-xs text-slate-400">
                    <p>👤 {{ $order->student?->name }}</p>
                    @if ($deadline && in_array($order->status, [MarkingOrderStatus::Submitted, MarkingOrderStatus::InProgress], true))
                        <p class="mt-1">{{ __('Due :time', ['time' => $deadline->format('d M Y, H:i')]) }}</p>
                    @endif
                    @if ($order->page_count)
                        <p class="mt-1">{{ __(':pages pages', ['pages' => $order->page_count]) }}</p>
                    @endif
                </div>
            </div>
            @if ($order->purchase)
                <p class="mt-3 text-xs text-slate-400">{{ __('Price :amount — paid :when', ['amount' => platform_settings()->formatMinor($order->purchase->price_minor), 'when' => $order->purchase->paid_at?->format('d M Y, H:i') ?? '—']) }}</p>
            @endif
        </div>

        <!-- Student's uploaded paper -->
        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('Uploaded paper') }}</h3>
            <ul class="mt-4 space-y-2">
                @forelse ($order->files ?? [] as $file)
                    <li class="flex items-center justify-between gap-3 rounded-xl border border-slate-200/80 px-3 py-2 dark:border-slate-800">
                        <span class="min-w-0 truncate text-sm text-slate-600 dark:text-slate-300">{{ $file['original_name'] ?? $file['path'] }}</span>
                        <a href="{{ route('teacher.marking.download', [$order, 'files', $loop->index]) }}"
                           class="shrink-0 text-xs font-semibold text-primary hover:underline">{{ __('Download') }}</a>
                    </li>
                @empty
                    <p class="text-xs text-slate-400">{{ __('No files uploaded.') }}</p>
                @endforelse
            </ul>
        </div>

        @if (in_array($order->status, [MarkingOrderStatus::Submitted, MarkingOrderStatus::InProgress], true))
            <!-- Delivery form -->
            <form method="POST" action="{{ route('teacher.marking.mark', $order) }}" enctype="multipart/form-data"
                  x-data="{ rows: [{ label: '', value: '' }] }"
                  class="space-y-6 {{ $cardBase }}" :class="{{ $cardTheme }}">
                @csrf

                <div>
                    <x-input-label for="marked_files" :value="__('Marked copy')" />
                    <input id="marked_files" name="marked_files[]" type="file" multiple required
                           accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf"
                           class="mt-1 block w-full text-sm text-slate-600 file:mr-4 file:rounded-xl file:border-0 file:bg-primary/10 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-primary hover:file:bg-primary/20 dark:text-slate-300">
                    <p class="mt-2 text-xs text-slate-400">{{ __('Upload the marked scans or PDFs — up to :count files, 5 MB each.', ['count' => $maxFiles]) }}</p>
                    <x-input-error :messages="$errors->get('marked_files')" class="mt-2" />
                    <x-input-error :messages="$errors->get('marked_files.*')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="feedback" :value="__('Feedback')" />
                    <textarea id="feedback" name="feedback" rows="5" maxlength="5000"
                              class="mt-1 block w-full {{ $fieldClasses }}">{{ old('feedback') }}</textarea>
                    <x-input-error :messages="$errors->get('feedback')" class="mt-2" />
                </div>

                <div>
                    <div class="flex items-center justify-between gap-3">
                        <x-input-label :value="__('Marks (optional)')" />
                        <button type="button" @click="rows.push({ label: '', value: '' })"
                                class="text-xs font-semibold text-primary hover:underline">{{ __('+ Add mark row') }}</button>
                    </div>
                    <div class="mt-2 space-y-2">
                        <template x-for="(row, index) in rows" :key="index">
                            <div class="flex items-center gap-2">
                                <input type="text" x-model="row.label" :name="`marks[${index}][label]`" placeholder="{{ __('Question / section') }}"
                                       class="block w-2/5 {{ $fieldClasses }}">
                                <input type="text" x-model="row.value" :name="`marks[${index}][value]`" placeholder="{{ __('Marks') }}"
                                       class="block w-2/5 {{ $fieldClasses }}">
                                <button type="button" @click="rows.splice(index, 1)"
                                        class="text-slate-400 transition-colors hover:text-rose-500" aria-label="{{ __('Remove') }}">&times;</button>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="flex justify-end border-t border-slate-200 pt-6 dark:border-slate-800">
                    <x-primary-button>{{ __('Deliver marking') }}</x-primary-button>
                </div>
            </form>
        @elseif (! empty($order->marked_files ?? []))
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('Your delivery') }}</h3>
                @if ($order->feedback)
                    <div class="mt-4 whitespace-pre-line rounded-xl bg-slate-50 p-4 text-sm leading-relaxed text-slate-600 dark:bg-slate-900/60 dark:text-slate-300">{{ $order->feedback }}</div>
                @endif
                <ul class="mt-4 space-y-2">
                    @foreach ($order->marked_files as $file)
                        <li class="flex items-center justify-between gap-3 rounded-xl border border-slate-200/80 px-3 py-2 dark:border-slate-800">
                            <span class="min-w-0 truncate text-sm text-slate-600 dark:text-slate-300">{{ $file['original_name'] ?? $file['path'] }}</span>
                            <a href="{{ route('teacher.marking.download', [$order, 'marked_files', $loop->index]) }}"
                               class="shrink-0 text-xs font-semibold text-primary hover:underline">{{ __('Download') }}</a>
                        </li>
                    @endforeach
                </ul>
                @if ($order->status === MarkingOrderStatus::Disputed && $order->dispute_reason)
                    <div class="mt-4 rounded-xl border border-rose-200/80 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300">
                        <p class="font-semibold">{{ __('The student disputed this delivery:') }}</p>
                        <p class="mt-1 whitespace-pre-line">{{ $order->dispute_reason }}</p>
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-app-layout>
