@php
    // /settings/theme is rendered by resources/js/settings-theme. Blade owns the
    // option lists, the saved choice, the copy and the route map; the visual
    // class maps for each preset live in resources/js/settings-theme/presets.js
    // because Tailwind can only compile classes it literally sees.
    $user = Auth::user();
    $theme = $user->theme ?? [];

    // A rejected save comes back through old(), so the picker reopens on the
    // values the user just submitted rather than the stored ones.
    $selected = [
        'preset' => old('theme_preset', $theme['preset'] ?? 'classic'),
        'accent' => old('theme_accent', $theme['accent'] ?? 'indigo'),
        'mode' => old('theme_mode', $theme['mode'] ?? 'light'),
        'sidebarStyle' => old('theme_sidebarStyle', $theme['sidebarStyle'] ?? 'flat'),
    ];

    $presets = [
        ['id' => 'classic', 'name' => __('Classic'), 'description' => __('Clean, professional look with indigo accents')],
        ['id' => 'forest', 'name' => __('Forest'), 'description' => __('Earthy emerald tones inspired by nature')],
        ['id' => 'midnight', 'name' => __('Midnight'), 'description' => __('Sleek dark mode with subtle grid patterns')],
        ['id' => 'sunset', 'name' => __('Sunset'), 'description' => __('Warm amber and rose hues with dotted patterns')],
        ['id' => 'glass', 'name' => __('Glass'), 'description' => __('Frosted glassmorphism with translucent layers')],
        ['id' => 'ocean', 'name' => __('Ocean'), 'description' => __('Refreshing cyan and blue wave-inspired design')],
    ];

    $accents = collect(['indigo', 'violet', 'emerald', 'rose', 'amber', 'blue', 'cyan'])
        ->map(fn ($id) => ['id' => $id, 'label' => ucfirst($id)])
        ->all();

    $modes = [
        ['id' => 'light', 'label' => __('Light'), 'icon' => 'sun'],
        ['id' => 'dark', 'label' => __('Dark'), 'icon' => 'moon'],
        ['id' => 'system', 'label' => __('System'), 'icon' => 'monitor'],
    ];

    $sidebarStyles = [
        ['id' => 'flat', 'label' => __('Flat'), 'description' => __('Clean edges')],
        ['id' => 'floating', 'label' => __('Floating'), 'description' => __('Rounded with gap')],
    ];

    $themePayload = [
        'appName' => config('app.name', 'Studylikepro'),
        'csrfToken' => csrf_token(),
        'theme' => ['preset' => $theme['preset'] ?? 'classic'],
        'selected' => $selected,
        'presets' => $presets,
        'accents' => $accents,
        'modes' => $modes,
        'sidebarStyles' => $sidebarStyles,
        'status' => [
            'updated' => session('status') === 'theme-updated',
        ],
        'copy' => [
            'updatedTitle' => __('Theme preferences saved!'),
            'updatedHint' => __('Your theme has been applied successfully.'),
            'presetsTitle' => __('Theme Presets'),
            'presetsHint' => __('Choose a pre-designed theme to change the entire look and feel of your dashboard.'),
            'accentTitle' => __('Accent Color'),
            'accentHint' => __('Pick a primary accent color used for buttons, links, and highlights.'),
            'modeTitle' => __('Color Mode'),
            'modeHint' => __('Choose between light, dark, or follow your system preference.'),
            'sidebarTitle' => __('Sidebar Style'),
            'sidebarHint' => __('Choose the sidebar appearance.'),
            'previewPrefix' => __('Previewing:'),
            'save' => __('Save Preferences'),
            'saving' => __('Saving...'),
        ],
        'urls' => [
            'update' => route('settings.theme.update'),
            'back' => route('settings.index'),
        ],
    ];

    // JSON_UNESCAPED_SLASHES keeps the URLs readable; the HEX flags keep the
    // island safe inside a <script> tag.
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('settings.index') }}" class="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300" title="{{ __('Back to Settings') }}">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">
                {{ __('Theme Settings') }}
            </h2>
        </div>
    </x-slot>

    <script type="application/json" id="settings-theme-config">@json($themePayload, $jsonFlags)</script>

    <div id="settings-theme-app"></div>

    {{-- Fallback for a browser without JavaScript: the same four choices as a
         real PATCH form. --}}
    <noscript>
        <div class="mx-auto max-w-5xl space-y-8">
            @if (session('status') === 'theme-updated')
                <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                    {{ __('Theme preferences saved!') }}
                </div>
            @endif

            <form method="POST" action="{{ route('settings.theme.update') }}" class="space-y-6 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                @csrf
                @method('PATCH')

                <div>
                    <x-input-label for="theme_preset" :value="__('Theme Preset')" />
                    <select id="theme_preset" name="theme_preset" class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900">
                        @foreach ($presets as $preset)
                            <option value="{{ $preset['id'] }}" @selected($selected['preset'] === $preset['id'])>{{ $preset['name'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="theme_accent" :value="__('Accent Color')" />
                    <select id="theme_accent" name="theme_accent" class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900">
                        @foreach ($accents as $accent)
                            <option value="{{ $accent['id'] }}" @selected($selected['accent'] === $accent['id'])>{{ $accent['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <x-input-label for="theme_mode" :value="__('Color Mode')" />
                        <select id="theme_mode" name="theme_mode" class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900">
                            @foreach ($modes as $mode)
                                <option value="{{ $mode['id'] }}" @selected($selected['mode'] === $mode['id'])>{{ $mode['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label for="theme_sidebarStyle" :value="__('Sidebar Style')" />
                        <select id="theme_sidebarStyle" name="theme_sidebarStyle" class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900">
                            @foreach ($sidebarStyles as $style)
                                <option value="{{ $style['id'] }}" @selected($selected['sidebarStyle'] === $style['id'])>{{ $style['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="flex justify-end">
                    <x-primary-button>{{ __('Save Preferences') }}</x-primary-button>
                </div>
            </form>
        </div>
    </noscript>

    @vite('resources/js/settings-theme/main.js')
</x-app-layout>
