<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Services\ActivityLogger;
use App\Services\PlatformSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Payment setup: the bank accounts students pay into for manual settlements,
 * plus the checkouts that are on offer and how long their holds last.
 */
class PaymentSetupController extends Controller
{
    public function index(PlatformSettings $settings): View
    {
        $definitions = collect(PlatformSettings::definitions())
            ->filter(fn (array $definition) => ($definition['group'] ?? null) === 'payments')
            ->all();

        return view('admin.payment-setup.index', [
            'accounts' => BankAccount::query()->ordered()->get(),
            'definitions' => $definitions,
            'values' => $settings->all(),
        ]);
    }

    public function store(Request $request, ActivityLogger $activity): RedirectResponse
    {
        $validated = $this->validated($request);

        $account = DB::transaction(function () use ($validated) {
            $account = BankAccount::query()->create($validated);

            // The first account is always the default.
            if (BankAccount::query()->count() === 1) {
                $account->forceFill(['is_default' => true, 'is_active' => true])->save();
            }

            $this->syncDefault($account);

            return $account->refresh();
        });

        $activity->describe('Added bank account '.$account->summary());

        return redirect()
            ->route('admin.payment-setup.index')
            ->with('status', 'bank-account-created');
    }

    public function update(Request $request, BankAccount $bankAccount, ActivityLogger $activity): RedirectResponse
    {
        $validated = $this->validated($request);

        DB::transaction(function () use ($bankAccount, $validated) {
            $bankAccount->update($validated);
            $this->syncDefault($bankAccount->refresh());
        });

        $activity->describe('Updated bank account '.$bankAccount->summary());

        return redirect()
            ->route('admin.payment-setup.index')
            ->with('status', 'bank-account-updated');
    }

    public function destroy(BankAccount $bankAccount, ActivityLogger $activity): RedirectResponse
    {
        // Payments keep their history, so an account that has been paid into is
        // deactivated rather than deleted.
        if ($bankAccount->payments()->exists()) {
            return redirect()
                ->route('admin.payment-setup.index')
                ->withErrors(['account' => __('This account is used by existing payments — deactivate it instead of deleting it.')]);
        }

        $summary = $bankAccount->summary();
        $bankAccount->delete();

        // Never leave the list without a default.
        if (! BankAccount::query()->where('is_default', true)->exists()) {
            BankAccount::query()->active()->ordered()->first()?->forceFill(['is_default' => true])->save();
        }

        $activity->describe('Deleted bank account '.$summary);

        return redirect()
            ->route('admin.payment-setup.index')
            ->with('status', 'bank-account-deleted');
    }

    public function makeDefault(BankAccount $bankAccount, ActivityLogger $activity): RedirectResponse
    {
        if (! $bankAccount->is_active) {
            return redirect()
                ->route('admin.payment-setup.index')
                ->withErrors(['account' => __('Activate this account before making it the default.')]);
        }

        DB::transaction(function () use ($bankAccount) {
            BankAccount::query()->whereKeyNot($bankAccount->id)->update(['is_default' => false]);
            $bankAccount->forceFill(['is_default' => true])->save();
        });

        $activity->describe('Made '.$bankAccount->summary().' the default bank account');

        return redirect()
            ->route('admin.payment-setup.index')
            ->with('status', 'bank-account-default');
    }

    public function toggle(BankAccount $bankAccount, ActivityLogger $activity): RedirectResponse
    {
        $bankAccount->forceFill(['is_active' => ! $bankAccount->is_active])->save();

        $activity->describe(sprintf(
            '%s bank account %s',
            $bankAccount->is_active ? 'Activated' : 'Deactivated',
            $bankAccount->summary(),
        ));

        return redirect()
            ->route('admin.payment-setup.index')
            ->with('status', 'bank-account-toggled');
    }

    /**
     * Save the payments group of platform settings (method switches, hold
     * windows and the instructions shown at checkout).
     */
    public function updateSettings(Request $request, PlatformSettings $settings, ActivityLogger $activity): RedirectResponse
    {
        $definitions = collect(PlatformSettings::definitions())
            ->filter(fn (array $definition) => ($definition['group'] ?? null) === 'payments')
            ->all();

        $rules = [];

        foreach ($definitions as $key => $definition) {
            $rules[$key] = match ($definition['type']) {
                'int' => ['nullable', 'integer', 'min:'.($definition['min'] ?? 0), 'max:'.($definition['max'] ?? 100000)],
                'float' => ['nullable', 'numeric', 'min:'.($definition['min'] ?? 0), 'max:'.($definition['max'] ?? 1)],
                'select' => ['nullable', 'string', Rule::in(array_keys($definition['options'] ?? []))],
                default => ['nullable', 'string', 'max:500'],
            };
        }

        $validated = $request->validate($rules);
        $changed = [];

        foreach ($definitions as $key => $definition) {
            $value = $validated[$key] ?? null;

            // Free-text instructions may be cleared (an empty value restores
            // the built-in copy); other fields only save a real value so a
            // blank submit never zeroes a switch or a window.
            if ($definition['type'] === 'text') {
                $value = (string) $value;
            } elseif ($value === null || $value === '') {
                continue;
            }

            $typed = match ($definition['type']) {
                'int' => (int) $value,
                'float' => (float) $value,
                default => (string) $value,
            };

            if ($settings->get($key) != $typed) {
                $changed[] = $key;
            }

            $settings->set($key, $typed);
        }

        $activity->describe('Updated payment settings: '.($changed === [] ? 'no changes' : implode(', ', $changed)));

        return redirect()
            ->route('admin.payment-setup.index')
            ->with('status', 'payment-settings-saved');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'bank_name' => ['required', 'string', 'max:120'],
            'account_name' => ['required', 'string', 'max:120'],
            'account_number' => ['required', 'string', 'max:50'],
            'branch' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_default'] = $request->boolean('is_default');
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);

        return $validated;
    }

    private function syncDefault(BankAccount $account): void
    {
        if ($account->is_default) {
            BankAccount::query()->whereKeyNot($account->id)->update(['is_default' => false]);

            return;
        }

        if (! BankAccount::query()->where('is_default', true)->exists()) {
            $account->forceFill(['is_default' => true])->save();
        }
    }
}
