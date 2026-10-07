@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $fieldClasses = 'rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';
    $selectClasses = 'mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900';
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);
    $displayTimezone = config('studylikepro.default_display_timezone');
    $window = fn ($offer) => ($offer->starts_at?->copy()->setTimezone($displayTimezone)->format('d M Y, H:i') ?? 'Always')
        .' → '.($offer->ends_at?->copy()->setTimezone($displayTimezone)->format('d M Y, H:i') ?? 'Until switched off');

    // Where an offer sits relative to its window, for the list badge.
    $status = function ($offer) {
        if (! $offer->is_active) {
            return ['Paused', 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'];
        }

        if ($offer->isRunning()) {
            return ['Running', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'];
        }

        if ($offer->starts_at?->isFuture()) {
            return ['Scheduled', 'bg-primary/10 text-primary'];
        }

        return ['Ended', 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'];
    };
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Special offers') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Discount or waive the student booking fee for a set period.') }}</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-6" x-data="{ showCreate: {{ $errors->any() ? 'true' : 'false' }} }">
        @if (session('status') === 'offer-created')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                {{ __('Offer created. It applies to bookings made from its start time.') }}
            </div>
        @elseif (session('status') === 'offer-deleted')
            <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-4 text-sm text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">
                {{ __('Offer deleted. Lessons already priced with it keep their discount.') }}
            </div>
        @endif

        <!-- Today's fee -->
        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Booking fee per lesson') }}</p>
                    <p class="mt-1 text-2xl font-extrabold tracking-tight text-slate-900 dark:text-slate-100">{{ $money($bookingFeeMinor) }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {{ __('Charged to students on top of the lesson price.') }}
                        <a href="{{ route('admin.settings.edit') }}" class="font-semibold text-primary hover:underline">{{ __('Change the default') }}</a>
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Students pay right now') }}</p>
                    <p class="mt-1 text-2xl font-extrabold tracking-tight {{ $feeQuote['net_minor'] < $feeQuote['booking_fee_minor'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-900 dark:text-slate-100' }}">
                        {{ $money($feeQuote['net_minor']) }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        @if ($feeQuote['promotion'])
                            {{ __(':offer is running', ['offer' => $feeQuote['promotion']->name]) }}
                        @else
                            {{ __('No running offer') }}
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Offers apply at the moment a student reserves a slot; the discount is snapshotted on that booking.') }}</p>
            <button @click="showCreate = ! showCreate"
                    class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-bold text-white shadow-md shadow-primary/20 transition hover:bg-primary/90">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                {{ __('New offer') }}
            </button>
        </div>

        <!-- Create form -->
        <div x-show="showCreate" x-transition class="{{ $cardBase }}" :class="{{ $cardTheme }}" style="display: none;"
             x-data="{ type: '{{ old('discount_type', 'waive') }}' }">
            <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('New special offer') }}</h3>
            <form method="POST" action="{{ route('admin.offers.store') }}" class="mt-5 space-y-5">
                @csrf
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <x-input-label for="name" :value="__('Name')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" placeholder="e.g. Free booking weekend" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="discount_type" :value="__('Discount')" />
                        <select id="discount_type" name="discount_type" x-model="type" class="{{ $selectClasses }}">
                            @foreach ($types as $type)
                                <option value="{{ $type->value }}" @selected(old('discount_type') === $type->value)>{{ $type->label() }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('discount_type')" class="mt-2" />
                    </div>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div x-show="type === 'percent'" x-cloak>
                        <x-input-label for="discount_percent" :value="__('Percent off (1–100)')" />
                        <x-text-input id="discount_percent" name="discount_percent" type="number" min="1" max="100" class="mt-1 block w-full" :value="old('discount_percent')" />
                        <x-input-error :messages="$errors->get('discount_percent')" class="mt-2" />
                    </div>
                    <div x-show="type === 'fixed'" x-cloak>
                        <x-input-label for="discount_amount" :value="__('Amount off (:currency)', ['currency' => platform_settings()->currencySymbol()])" />
                        <x-text-input id="discount_amount" name="discount_amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" :value="old('discount_amount')" />
                        <x-input-error :messages="$errors->get('discount_amount')" class="mt-2" />
                    </div>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <x-input-label for="starts_at" :value="__('Starts (optional)')" />
                        <x-text-input id="starts_at" name="starts_at" type="datetime-local" class="mt-1 block w-full" :value="old('starts_at')" />
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __(':timezone time. Empty starts immediately.', ['timezone' => $displayTimezone]) }}</p>
                        <x-input-error :messages="$errors->get('starts_at')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="ends_at" :value="__('Ends (optional)')" />
                        <x-text-input id="ends_at" name="ends_at" type="datetime-local" class="mt-1 block w-full" :value="old('ends_at')" />
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Empty runs until you pause it.') }}</p>
                        <x-input-error :messages="$errors->get('ends_at')" class="mt-2" />
                    </div>
                </div>

                <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input type="checkbox" name="is_active" value="1" checked
                           class="rounded border-slate-300 text-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900" />
                    {{ __('Active — applies to new bookings inside its window') }}
                </label>

                <div class="flex justify-end">
                    <x-primary-button>{{ __('Create offer') }}</x-primary-button>
                </div>
            </form>
        </div>

        <!-- Offer list -->
        <div class="{{ $cardBase }} p-0" :class="{{ $cardTheme }}">
            @if ($offers->isEmpty())
                <div class="p-10 text-center">
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('No special offers yet — create the first one above.') }}</p>
                </div>
            @else
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($offers as $offer)
                        @php [$label, $badgeClasses] = $status($offer); @endphp
                        <li class="flex flex-wrap items-center justify-between gap-4 px-6 py-4">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $offer->name }}</p>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide {{ $badgeClasses }}">{{ $label }}</span>
                                </div>
                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                    {{ $offer->describeDiscount() }} · {{ $window($offer) }}
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-4 text-xs text-slate-400">
                                <span>{{ $offer->bookings_count }} {{ Str::plural('booking', $offer->bookings_count) }}</span>
                                <a href="{{ route('admin.offers.edit', $offer) }}"
                                   class="rounded-xl bg-primary/10 px-3 py-1.5 text-xs font-semibold text-primary transition-colors hover:bg-primary/20">
                                    {{ __('Edit') }}
                                </a>
                            </div>
                        </li>
                    @endforeach
                </ul>

                <div class="border-t border-slate-100 px-6 py-4 dark:border-slate-800">
                    {{ $offers->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
