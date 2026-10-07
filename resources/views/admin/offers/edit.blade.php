@php
    use App\Enums\BookingFeeDiscountType;

    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $selectClasses = 'mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900';
    $displayTimezone = config('studylikepro.default_display_timezone');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('admin.offers.index') }}" class="text-xs font-semibold text-slate-400 transition-colors hover:text-primary">&larr; {{ __('Special offers') }}</a>
            <h2 class="mt-1 font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ $offer->name }}</h2>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-6" x-data="{ type: '{{ old('discount_type', $offer->discount_type->value) }}' }">
        @if (session('status') === 'offer-created' || session('status') === 'offer-updated')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                {{ __('Offer saved. Bookings already priced keep the discount they were given.') }}
            </div>
        @endif

        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $offer->describeDiscount() }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {{ __('Applied to :count bookings so far.', ['count' => $offer->bookings()->count()]) }}
                    </p>
                </div>
                @if ($offer->isRunning())
                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">{{ __('Running now') }}</span>
                @endif
            </div>
        </div>

        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Offer details') }}</h3>
            <form method="POST" action="{{ route('admin.offers.update', $offer) }}" class="mt-5 space-y-5">
                @csrf
                @method('PUT')

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <x-input-label for="name" :value="__('Name')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $offer->name)" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="discount_type" :value="__('Discount')" />
                        <select id="discount_type" name="discount_type" x-model="type" class="{{ $selectClasses }}">
                            @foreach ($types as $option)
                                <option value="{{ $option->value }}" @selected(old('discount_type', $offer->discount_type->value) === $option->value)>{{ $option->label() }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('discount_type')" class="mt-2" />
                    </div>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div x-show="type === 'percent'" x-cloak>
                        <x-input-label for="discount_percent" :value="__('Percent off (1–100)')" />
                        <x-text-input id="discount_percent" name="discount_percent" type="number" min="1" max="100" class="mt-1 block w-full"
                                      :value="old('discount_percent', $offer->discount_type === BookingFeeDiscountType::Percent ? $offer->discount_value : '')" />
                        <x-input-error :messages="$errors->get('discount_percent')" class="mt-2" />
                    </div>
                    <div x-show="type === 'fixed'" x-cloak>
                        <x-input-label for="discount_amount" :value="__('Amount off (:currency)', ['currency' => platform_settings()->currencySymbol()])" />
                        <x-text-input id="discount_amount" name="discount_amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full"
                                      :value="old('discount_amount', $offer->discount_type === BookingFeeDiscountType::Fixed ? number_format($offer->discount_value / 100, 2, '.', '') : '')" />
                        <x-input-error :messages="$errors->get('discount_amount')" class="mt-2" />
                    </div>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <x-input-label for="starts_at" :value="__('Starts (optional)')" />
                        <x-text-input id="starts_at" name="starts_at" type="datetime-local" class="mt-1 block w-full"
                                      :value="old('starts_at', $offer->starts_at?->copy()->setTimezone($displayTimezone)->format('Y-m-d\TH:i'))" />
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __(':timezone time.', ['timezone' => $displayTimezone]) }}</p>
                        <x-input-error :messages="$errors->get('starts_at')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="ends_at" :value="__('Ends (optional)')" />
                        <x-text-input id="ends_at" name="ends_at" type="datetime-local" class="mt-1 block w-full"
                                      :value="old('ends_at', $offer->ends_at?->copy()->setTimezone($displayTimezone)->format('Y-m-d\TH:i'))" />
                        <x-input-error :messages="$errors->get('ends_at')" class="mt-2" />
                    </div>
                </div>

                <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $offer->is_active))
                           class="rounded border-slate-300 text-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900" />
                    {{ __('Active — applies to new bookings inside its window') }}
                </label>

                <div class="flex items-center justify-between border-t border-slate-200 pt-5 dark:border-slate-800">
                    <x-primary-button>{{ __('Save offer') }}</x-primary-button>
                </div>
            </form>
        </div>

        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ __('Delete') }}</h3>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Bookings already priced with this offer keep the discount they were given.') }}</p>
            <form method="POST" action="{{ route('admin.offers.destroy', $offer) }}" class="mt-4"
                  onsubmit="return confirm('{{ __('Delete this offer?') }}');">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-500 transition-colors hover:border-rose-300 hover:text-rose-600 dark:border-slate-700 dark:text-slate-400">
                    {{ __('Delete offer') }}
                </button>
            </form>
        </div>
    </div>
</x-app-layout>
