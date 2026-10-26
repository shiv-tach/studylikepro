@php
    // Shared bank-account fields for the create form and each inline edit form.
    $editing = $account ?? null;
    $suffix = $editing?->id ?? 'new';
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <x-input-label :for="'bank_name-'.$suffix" :value="__('Bank name')" />
        <x-text-input :id="'bank_name-'.$suffix" name="bank_name" type="text" class="mt-1 block w-full"
                      :value="old('bank_name', $editing?->bank_name)" required />
        @error('bank_name')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <x-input-label :for="'account_name-'.$suffix" :value="__('Account name')" />
        <x-text-input :id="'account_name-'.$suffix" name="account_name" type="text" class="mt-1 block w-full"
                      :value="old('account_name', $editing?->account_name)" required />
        @error('account_name')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <x-input-label :for="'account_number-'.$suffix" :value="__('Account number')" />
        <x-text-input :id="'account_number-'.$suffix" name="account_number" type="text" class="mt-1 block w-full font-mono"
                      :value="old('account_number', $editing?->account_number)" required />
        @error('account_number')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <x-input-label :for="'branch-'.$suffix" :value="__('Branch (optional)')" />
        <x-text-input :id="'branch-'.$suffix" name="branch" type="text" class="mt-1 block w-full"
                      :value="old('branch', $editing?->branch)" />
        @error('branch')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
    </div>

    <div class="sm:col-span-2">
        <x-input-label :for="'note-'.$suffix" :value="__('Note shown to students (optional)')" />
        <x-text-input :id="'note-'.$suffix" name="note" type="text" class="mt-1 block w-full"
                      :value="old('note', $editing?->note)"
                      placeholder="{{ __('e.g. Use your booking reference as the deposit remark') }}" />
        @error('note')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <x-input-label :for="'sort_order-'.$suffix" :value="__('Sort order')" />
        <input id="sort_order-{{ $suffix }}" name="sort_order" type="number" min="0" max="999"
               value="{{ old('sort_order', $editing?->sort_order ?? 0) }}" class="{{ $fieldClasses }}">
        @error('sort_order')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
    </div>

    <div class="flex items-end gap-5">
        <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
            <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-primary focus:ring-primary dark:border-slate-700"
                   @checked(old('is_active', $editing?->is_active ?? true))>
            {{ __('Show at checkout') }}
        </label>

        <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
            <input type="checkbox" name="is_default" value="1" class="rounded border-slate-300 text-primary focus:ring-primary dark:border-slate-700"
                   @checked(old('is_default', $editing?->is_default ?? false))>
            {{ __('Default account') }}
        </label>
    </div>
</div>
