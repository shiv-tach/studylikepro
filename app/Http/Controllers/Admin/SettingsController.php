<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Services\PlatformSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
                'int' => ['nullable', 'integer', 'min:0', 'max:100000'],
                'float' => ['nullable', 'numeric', 'min:0', 'max:1'],
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
