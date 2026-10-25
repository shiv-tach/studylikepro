<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Services\PlatformSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(PlatformSettings $settings): View
    {
        return view('admin.settings.edit', [
            'definitions' => PlatformSettings::definitions(),
            'values' => $settings->all(),
        ]);
    }

    public function update(Request $request, PlatformSettings $settings, ActivityLogger $activity): RedirectResponse
    {
        $rules = [];

        foreach (PlatformSettings::definitions() as $key => $definition) {
            $rules[$key] = match ($definition['type']) {
                // Money settings are entered in whole currency units (Rs), so the
                // same bounds as the other numeric fields apply.
                'money' => ['nullable', 'numeric', 'min:0', 'max:100000'],
                // Integer settings carry their own bounds when the definition
                // needs tighter limits (e.g. a 4-8 digit passcode).
                'int' => ['nullable', 'integer', 'min:'.($definition['min'] ?? 0), 'max:'.($definition['max'] ?? 100000)],
                // Float settings carry their own bounds (the booking fee
                // percentage is 0-100).
                'float' => ['nullable', 'numeric', 'min:'.($definition['min'] ?? 0), 'max:'.($definition['max'] ?? 1)],
                'select' => ['nullable', 'string', Rule::in(array_keys($definition['options'] ?? []))],
                // API keys are encrypted by PlatformSettings; an empty value
                // means "keep the saved key", never "erase it".
                'secret' => ['nullable', 'string', 'max:255'],
                default => ['nullable', 'string', 'max:500'],
            };
        }

        $validated = $request->validate($rules);
        $changed = [];

        foreach (PlatformSettings::definitions() as $key => $definition) {
            $value = $validated[$key] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $typed = match ($definition['type']) {
                'money' => (int) round(((float) $value) * 100),
                'int' => (int) $value,
                'float' => (float) $value,
                default => (string) $value,
            };

            if ($settings->get($key) != $typed) {
                $changed[] = $key;
            }

            $settings->set($key, $typed);
        }

        $activity->describe('Updated platform settings: '.($changed === [] ? 'no changes' : implode(', ', $changed)));

        return redirect()
            ->route('admin.settings.edit')
            ->with('status', 'settings-saved');
    }
}
