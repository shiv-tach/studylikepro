@php
    // Payment setup is the home for the two manual methods: the bank accounts
    // students pay into, the switches that offer each method, and the hold
    // windows that keep the slot reserved while the proof is checked.
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $fieldClasses = 'mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Payment setup') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Bank accounts, checkout methods and the payment hold windows.') }}</p>
            </div>
            <a href="{{ route('admin.payments.index') }}"
               class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition-colors hover:border-primary hover:text-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                {{ __('View payments') }}
            </a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6">
        @if (session('status'))
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                {{ match (session('status')) {
                    'bank-account-created' => __('Bank account added.'),
                    'bank-account-updated' => __('Bank account updated.'),
                    'bank-account-deleted' => __('Bank account deleted.'),
                    'bank-account-default' => __('Default bank account updated.'),
                    'bank-account-toggled' => __('Bank account visibility updated.'),
                    'payment-settings-saved' => __('Payment settings saved.'),
                    default => __('Saved.'),
                } }}
            </div>
        @endif

        @error('account')
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300">
                {{ $message }}
            </div>
        @enderror

        <!-- Checkout methods and hold windows -->
        <form method="POST" action="{{ route('admin.payment-setup.settings.update') }}" class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            @csrf
            @method('PUT')

            <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Checkout methods') }}</h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ __('Card payments stay hidden behind “Coming soon” until the gateway is live. Transfers and deposits are confirmed by an admin after the student uploads a slip.') }}
            </p>

            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                @foreach ($definitions as $key => $definition)
                    <div class="{{ $definition['type'] === 'text' ? 'sm:col-span-2' : '' }}">
                        <x-input-label :for="'setting-'.$key" :value="$definition['label']" />

                        @if ($definition['type'] === 'select')
                            <select id="setting-{{ $key }}" name="{{ $key }}" class="{{ $fieldClasses }}">
                                @foreach ($definition['options'] ?? [] as $optionValue => $optionLabel)
                                    <option value="{{ $optionValue }}" @selected((string) old($key, $values[$key] ?? '') === (string) $optionValue)>{{ $optionLabel }}</option>
                                @endforeach
                            </select>
                        @elseif ($definition['type'] === 'text')
                            <textarea id="setting-{{ $key }}" name="{{ $key }}" rows="2" class="{{ $fieldClasses }}">{{ old($key, $values[$key] ?? '') }}</textarea>
                        @else
                            <input id="setting-{{ $key }}" name="{{ $key }}" type="number"
                                   min="{{ $definition['min'] ?? 0 }}" max="{{ $definition['max'] ?? 100000 }}" step="1"
                                   value="{{ old($key, $values[$key] ?? '') }}" class="{{ $fieldClasses }}">
                        @endif

                        <p class="mt-1 text-xs text-slate-400">{{ $definition['description'] }}</p>

                        @error($key)
                            <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-3 border-t border-slate-200 pt-5 dark:border-slate-800">
                <x-primary-button>{{ __('Save payment settings') }}</x-primary-button>
                <a href="{{ route('admin.settings.edit') }}" class="text-sm font-semibold text-slate-500 hover:text-primary">{{ __('Other platform settings') }}</a>
            </div>
        </form>

        <!-- Bank accounts -->
        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Bank accounts') }}</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ __('Students see active accounts at checkout. The default one is preselected.') }}
                    </p>
                </div>
                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                    {{ trans_choice(':count account|:count accounts', $accounts->count(), ['count' => $accounts->count()]) }}
                </span>
            </div>

            @if ($accounts->isEmpty())
                <p class="mt-4 rounded-xl border border-dashed border-slate-300 p-4 text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                    {{ __('No accounts yet. Add the first one below so students can pay by transfer or deposit.') }}
                </p>
            @else
                <div class="mt-5 space-y-4">
                    @foreach ($accounts as $account)
                        <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-800">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-bold text-slate-800 dark:text-slate-100">
                                        {{ $account->bank_name }}
                                        @if ($account->is_default)
                                            <span class="ml-1 rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-semibold text-primary">{{ __('Default') }}</span>
                                        @endif
                                        @unless ($account->is_active)
                                            <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-300">{{ __('Hidden') }}</span>
                                        @endunless
                                    </p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                        {{ $account->account_name }} · <span class="font-mono">{{ $account->account_number }}</span>
                                        @if ($account->branch) · {{ $account->branch }} @endif
                                    </p>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    @unless ($account->is_default)
                                        <form method="POST" action="{{ route('admin.payment-setup.bank-accounts.default', $account) }}">
                                            @csrf
                                            <button type="submit" class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-600 transition-colors hover:border-primary hover:text-primary dark:border-slate-700 dark:text-slate-300">
                                                {{ __('Make default') }}
                                            </button>
                                        </form>
                                    @endunless

                                    <form method="POST" action="{{ route('admin.payment-setup.bank-accounts.toggle', $account) }}">
                                        @csrf
                                        <button type="submit" class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-600 transition-colors hover:border-primary hover:text-primary dark:border-slate-700 dark:text-slate-300">
                                            {{ $account->is_active ? __('Hide at checkout') : __('Show at checkout') }}
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('admin.payment-setup.bank-accounts.destroy', $account) }}"
                                          onsubmit="return confirm('{{ __('Delete this bank account? Payments already made into it keep their reference.') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-lg border border-rose-200 px-2.5 py-1 text-xs font-semibold text-rose-600 transition-colors hover:bg-rose-50 dark:border-rose-900/50 dark:text-rose-400">
                                            {{ __('Delete') }}
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <details class="mt-3">
                                <summary class="cursor-pointer text-xs font-semibold text-primary hover:underline">{{ __('Edit this account') }}</summary>

                                <form method="POST" action="{{ route('admin.payment-setup.bank-accounts.update', $account) }}" class="mt-3 space-y-4">
                                    @csrf
                                    @method('PUT')

                                    @include('admin.payment-setup._fields', ['account' => $account, 'fieldClasses' => $fieldClasses])

                                    <x-primary-button>{{ __('Save changes') }}</x-primary-button>
                                </form>
                            </details>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="mt-6 border-t border-slate-200 pt-5 dark:border-slate-800">
                <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ __('Add a bank account') }}</h4>

                <form method="POST" action="{{ route('admin.payment-setup.bank-accounts.store') }}" class="mt-3 space-y-4">
                    @csrf

                    @include('admin.payment-setup._fields', ['account' => null, 'fieldClasses' => $fieldClasses])

                    <x-primary-button>{{ __('Add the account') }}</x-primary-button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
