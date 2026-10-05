<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /**
     * Display the main settings home page.
     */
    public function index(Request $request): View
    {
        return view('settings.index', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Toggle the global email preference. In-app notifications are unaffected.
     */
    public function updateNotifications(Request $request): RedirectResponse
    {
        $request->user()->update([
            'notification_preferences' => [
                'email' => $request->boolean('email'),
            ],
        ]);

        return redirect()
            ->route('settings.index')
            ->with('status', 'notifications-updated');
    }

    /**
     * Display the theme settings page.
     */
    public function edit(Request $request): View
    {
        return view('settings.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update user settings (theme preferences).
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'theme_preset' => ['required', 'string', 'in:classic,forest,midnight,sunset,glass,ocean'],
            'theme_accent' => ['required', 'string', 'in:indigo,violet,emerald,rose,amber,blue,cyan'],
            'theme_mode' => ['required', 'string', 'in:light,dark,system'],
            'theme_sidebarStyle' => ['required', 'string', 'in:flat,floating'],
        ]);

        $request->user()->update([
            'theme' => [
                'preset' => $validated['theme_preset'],
                'accent' => $validated['theme_accent'],
                'mode' => $validated['theme_mode'],
                'sidebarStyle' => $validated['theme_sidebarStyle'],
            ],
        ]);

        return redirect()
            ->route('settings.theme')
            ->with('status', 'theme-updated');
    }
}
