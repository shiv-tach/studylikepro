@php
    $fieldClasses = 'mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';
    $defaultAccount = collect($manual['accounts'])->firstWhere('isDefault', true) ?? collect($manual['accounts'])->first();
    $chosenAccount = collect($manual['accounts'])->firstWhere('id', $payment->bank_account_id);
    $slipSizeLabel = round($manual['slipMaxKb'] / 1024, 1);
@endphp

<form method="POST" action="{{ route('student.payments.slip.store', $payment) }}" enctype="multipart/form-data" class="mt-5 space-y-4 border-t border-slate-200 pt-5 dark:border-slate-800">
    @csrf

    <div>
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Which account did you pay into?') }}</p>

        @if (empty($manual['accounts']))
            <p class="mt-2 rounded-xl bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-950/30 dark:text-amber-300">
                {{ __('Bank details have not been set up yet. Please contact support before sending any money.') }}
            </p>
        @else
            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                @foreach ($manual['accounts'] as $account)
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 text-sm transition hover:border-primary/60 dark:border-slate-800">
                        <input type="radio" name="bank_account_id" value="{{ $account['id'] }}" class="mt-1 border-slate-300 text-primary focus:ring-primary dark:border-slate-700"
                               @checked($payment->bank_account_id === $account['id'] || ($payment->bank_account_id === null && $defaultAccount && $defaultAccount['id'] === $account['id']))>
                        <span>
                            <span class="block font-semibold text-slate-800 dark:text-slate-100">{{ $account['bankName'] }}</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">
                                {{ $account['accountName'] }} · <span class="font-mono">{{ $account['accountNumber'] }}</span>
                            </span>
                            @if ($account['branch'])
                                <span class="block text-xs text-slate-400">{{ $account['branch'] }}</span>
                            @endif
                        </span>
                    </label>
                @endforeach
            </div>
            @error('bank_account_id')
                <p class="mt-2 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
            @enderror
        @endif
    </div>

    <div>
        <label for="slip-reference-{{ $payment->id }}" class="block text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Bank reference (optional)') }}</label>
        <input id="slip-reference-{{ $payment->id }}" name="reference" type="text" maxlength="100"
               class="{{ $fieldClasses }}"
               value="{{ old('reference', $manual['bankReference'] ?: $manual['suggestedReference']) }}"
               placeholder="{{ $manual['suggestedReference'] }}">
        <p class="mt-1 text-xs text-slate-400">{{ __('Add the reference shown on the slip so we can match the payment faster.') }}</p>
        @error('reference')
            <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="slip-file-{{ $payment->id }}" class="block text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Payment slip / proof') }}</label>
        <input id="slip-file-{{ $payment->id }}" name="slip" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" required
               class="mt-1 block w-full cursor-pointer rounded-xl border border-dashed border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-primary/10 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-primary hover:border-primary/60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
        <p class="mt-1 text-xs text-slate-400">{{ __('JPG, PNG, WEBP or PDF · up to :size MB', ['size' => $slipSizeLabel]) }}</p>
        @error('slip')
            <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
        @enderror
    </div>

    <button type="submit"
            class="inline-flex items-center rounded-xl bg-primary px-4 py-2.5 text-sm font-bold text-white shadow-md shadow-primary/20 transition hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-60"
            @disabled(empty($manual['accounts']))>
        {{ $submitLabel }}
    </button>
</form>
