<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('settings.index') }}" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300 transition-colors" title="{{ __('Back to Settings') }}">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">
                {{ __('Theme Settings') }}
            </h2>
        </div>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-8"
         x-data="{
            selectedPreset: '{{ $user->theme['preset'] ?? 'classic' }}',
            selectedAccent: '{{ $user->theme['accent'] ?? 'indigo' }}',
            selectedMode: '{{ $user->theme['mode'] ?? 'system' }}',
            selectedSidebarStyle: '{{ $user->theme['sidebarStyle'] ?? 'flat' }}',

            previewTheme(preset, accent, mode, sidebarStyle) {
                this.selectedPreset = preset;
                this.selectedAccent = accent;
                this.selectedMode = mode;
                this.selectedSidebarStyle = sidebarStyle;

                // Dispatch live preview event
                window.dispatchEvent(new CustomEvent('theme-changed', {
                    detail: { preset, accent, mode, sidebarStyle }
                }));

                // Update localStorage for persistence across page nav
                localStorage.setItem('themePreset', preset);
                localStorage.setItem('themeAccent', accent);
                localStorage.setItem('themeMode', mode);
                localStorage.setItem('sidebarStyle', sidebarStyle);
            },

            presets: [
                {
                    id: 'classic',
                    name: 'Classic',
                    description: 'Clean, professional look with indigo accents',
                    sidebar: 'bg-white',
                    body: 'bg-slate-50',
                    accentBar: 'from-indigo-500 to-blue-500',
                    badgeBg: 'bg-indigo-100 text-indigo-700',
                },
                {
                    id: 'forest',
                    name: 'Forest',
                    description: 'Earthy emerald tones inspired by nature',
                    sidebar: 'bg-white',
                    body: 'bg-emerald-50/40',
                    accentBar: 'from-emerald-500 to-teal-500',
                    badgeBg: 'bg-emerald-100 text-emerald-700',
                },
                {
                    id: 'midnight',
                    name: 'Midnight',
                    description: 'Sleek dark mode with subtle grid patterns',
                    sidebar: 'bg-slate-950',
                    body: 'bg-slate-900',
                    accentBar: 'from-slate-600 to-indigo-600',
                    badgeBg: 'bg-slate-700 text-slate-200',
                },
                {
                    id: 'sunset',
                    name: 'Sunset',
                    description: 'Warm amber and rose hues with dotted patterns',
                    sidebar: 'bg-white/90',
                    body: 'bg-amber-50/40',
                    accentBar: 'from-amber-500 via-rose-500 to-indigo-500',
                    badgeBg: 'bg-amber-100 text-amber-700',
                },
                {
                    id: 'glass',
                    name: 'Glass',
                    description: 'Frosted glassmorphism with translucent layers',
                    sidebar: 'bg-white/70 backdrop-blur-lg',
                    body: 'bg-slate-100',
                    accentBar: 'from-violet-500 to-fuchsia-500',
                    badgeBg: 'bg-violet-100 text-violet-700',
                },
                {
                    id: 'ocean',
                    name: 'Ocean',
                    description: 'Refreshing cyan and blue wave-inspired design',
                    sidebar: 'bg-white/80 backdrop-blur-md',
                    body: 'bg-cyan-50/30',
                    accentBar: 'from-cyan-400 via-blue-500 to-indigo-500',
                    badgeBg: 'bg-cyan-100 text-cyan-700',
                },
            ],

            accents: [
                { id: 'indigo', color: 'bg-indigo-500', ring: 'ring-indigo-500' },
                { id: 'violet', color: 'bg-violet-500', ring: 'ring-violet-500' },
                { id: 'emerald', color: 'bg-emerald-500', ring: 'ring-emerald-500' },
                { id: 'rose', color: 'bg-rose-500', ring: 'ring-rose-500' },
                { id: 'amber', color: 'bg-amber-500', ring: 'ring-amber-500' },
                { id: 'blue', color: 'bg-blue-500', ring: 'ring-blue-500' },
                { id: 'cyan', color: 'bg-cyan-500', ring: 'ring-cyan-500' },
            ],

            modes: [
                { id: 'light', label: 'Light', icon: 'sun' },
                { id: 'dark', label: 'Dark', icon: 'moon' },
                { id: 'system', label: 'System', icon: 'monitor' },
            ],

            sidebarStyles: [
                { id: 'flat', label: 'Flat', description: 'Clean edges' },
                { id: 'floating', label: 'Floating', description: 'Rounded with gap' },
            ],
         }">

        {{-- Success Message --}}
        @if (session('status') === 'theme-updated')
            <div x-data="{ show: true }"
                 x-show="show"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 -translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-2"
                 class="flex items-center justify-between rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-100 dark:bg-emerald-900/60">
                        <svg class="h-5 w-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold">Theme preferences saved!</p>
                        <p class="text-xs text-emerald-600 dark:text-emerald-400">Your theme has been applied successfully.</p>
                    </div>
                </div>
                <button @click="show = false" class="rounded-lg p-1.5 text-emerald-500 hover:bg-emerald-100 dark:hover:bg-emerald-900/50 transition-colors">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        @endif

        {{-- Section 1: Theme Presets --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
            <div class="mb-6">
                <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100">Theme Presets</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Choose a pre-designed theme to change the entire look and feel of your dashboard.</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <template x-for="preset in presets" :key="preset.id">
                    <button type="button"
                            @click="previewTheme(preset.id, selectedAccent, selectedMode, selectedSidebarStyle)"
                            class="group relative flex flex-col overflow-hidden rounded-2xl border-2 text-left transition-all duration-200"
                            :class="selectedPreset === preset.id
                                ? 'border-primary shadow-lg shadow-primary/10 ring-2 ring-primary/20 dark:border-primary dark:ring-primary/30'
                                : 'border-slate-200 hover:border-slate-300 hover:shadow-md dark:border-slate-800 dark:hover:border-slate-700'">

                        {{-- Thumbnail Preview --}}
                        <div class="relative h-28 w-full overflow-hidden" :class="preset.body">
                            {{-- Mini sidebar strip --}}
                            <div class="absolute left-0 top-0 h-full w-12 border-r border-slate-200/50 dark:border-slate-800/50" :class="preset.sidebar">
                                {{-- Mini logo dot --}}
                                <div class="absolute left-3 top-3 h-2 w-2 rounded-full" :class="preset.accentBar.replace('from-', 'bg-').split(' ')[0].replace('to-', '')"></div>
                                {{-- Mini nav items --}}
                                <div class="absolute left-3 top-8 space-y-1.5">
                                    <div class="h-1 w-5 rounded-full bg-slate-300/60 dark:bg-slate-600/60"></div>
                                    <div class="h-1 w-4 rounded-full bg-slate-300/60 dark:bg-slate-600/60"></div>
                                    <div class="h-1 w-3 rounded-full bg-slate-300/60 dark:bg-slate-600/60"></div>
                                </div>
                            </div>
                            {{-- Mini content area --}}
                            <div class="absolute left-16 right-3 top-3 space-y-1.5">
                                <div class="h-1.5 w-16 rounded-full bg-slate-300/50 dark:bg-slate-600/50"></div>
                                <div class="h-1 w-24 rounded-full bg-slate-200/50 dark:bg-slate-700/50"></div>
                                <div class="mt-2 grid grid-cols-2 gap-1">
                                    <div class="h-7 rounded-lg bg-white/80 dark:bg-slate-800/80 shadow-sm"></div>
                                    <div class="h-7 rounded-lg bg-white/80 dark:bg-slate-800/80 shadow-sm"></div>
                                </div>
                            </div>
                            {{-- Accent bar at bottom --}}
                            <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r" :class="preset.accentBar"></div>
                        </div>

                        {{-- Card Info --}}
                        <div class="flex items-center justify-between border-t border-slate-100 px-4 py-3 dark:border-slate-800">
                            <div>
                                <h4 class="text-sm font-semibold text-slate-800 dark:text-slate-200" x-text="preset.name"></h4>
                                <p class="text-[11px] text-slate-400 dark:text-slate-500" x-text="preset.description"></p>
                            </div>
                            {{-- Check indicator --}}
                            <div class="flex h-6 w-6 items-center justify-center rounded-full border-2 transition-all"
                                 :class="selectedPreset === preset.id
                                    ? 'border-primary bg-primary text-white'
                                    : 'border-slate-300 dark:border-slate-600'">
                                <svg x-show="selectedPreset === preset.id" class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                        </div>
                    </button>
                </template>
            </div>
        </div>

        {{-- Section 2: Accent Color --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
            <div class="mb-6">
                <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100">Accent Color</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Pick a primary accent color used for buttons, links, and highlights.</p>
            </div>

            <div class="flex flex-wrap items-center gap-4">
                <template x-for="accent in accents" :key="accent.id">
                    <button type="button"
                            @click="previewTheme(selectedPreset, accent.id, selectedMode, selectedSidebarStyle)"
                            class="group relative flex flex-col items-center gap-2 transition-transform hover:scale-110">
                        <div class="relative flex h-12 w-12 items-center justify-center rounded-2xl transition-all duration-200"
                             :class="selectedAccent === accent.id
                                ? 'ring-2 ring-offset-2 ' + accent.ring + ' dark:ring-offset-slate-900'
                                : 'hover:ring-2 hover:ring-offset-2 hover:ring-slate-300 dark:hover:ring-offset-slate-900'">
                            <div class="h-8 w-8 rounded-xl shadow-md" :class="accent.color"></div>
                        </div>
                        <span class="text-[11px] font-medium capitalize text-slate-500 dark:text-slate-400"
                              x-text="accent.id"></span>
                        {{-- Active dot --}}
                        <span class="absolute -bottom-1 h-1.5 w-1.5 rounded-full bg-primary transition-opacity"
                              :class="selectedAccent === accent.id ? 'opacity-100' : 'opacity-0'"></span>
                    </button>
                </template>
            </div>
        </div>

        {{-- Section 3: Mode Toggle --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
            <div class="mb-6">
                <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100">Color Mode</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Choose between light, dark, or follow your system preference.</p>
            </div>

            <div class="inline-flex rounded-xl bg-slate-100 p-1 dark:bg-slate-800">
                <template x-for="mode in modes" :key="mode.id">
                    <button type="button"
                            @click="previewTheme(selectedPreset, selectedAccent, mode.id, selectedSidebarStyle)"
                            class="flex items-center gap-2 rounded-lg px-5 py-2.5 text-sm font-medium transition-all duration-200"
                            :class="selectedMode === mode.id
                                ? 'bg-white text-slate-800 shadow-sm dark:bg-slate-700 dark:text-slate-100'
                                : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'">
                        {{-- Sun icon --}}
                        <svg x-show="mode.icon === 'sun'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        {{-- Moon icon --}}
                        <svg x-show="mode.icon === 'moon'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                        </svg>
                        {{-- Monitor icon --}}
                        <svg x-show="mode.icon === 'monitor'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        <span x-text="mode.label"></span>
                    </button>
                </template>
            </div>
        </div>

        {{-- Section 4: Sidebar Style --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
            <div class="mb-6">
                <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100">Sidebar Style</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Choose the sidebar appearance.</p>
            </div>

            <div class="flex flex-wrap gap-4">
                <template x-for="style in sidebarStyles" :key="style.id">
                    <button type="button"
                            @click="previewTheme(selectedPreset, selectedAccent, selectedMode, style.id)"
                            class="flex flex-col items-center gap-3 rounded-2xl border-2 p-5 transition-all duration-200 min-w-[140px]"
                            :class="selectedSidebarStyle === style.id
                                ? 'border-primary bg-primary/5 shadow-md dark:border-primary dark:bg-primary/10'
                                : 'border-slate-200 hover:border-slate-300 dark:border-slate-800 dark:hover:border-slate-700'">
                        {{-- Mini sidebar preview icon --}}
                        <div class="flex items-center gap-1.5">
                            <div class="flex h-14 w-10 flex-col gap-1 rounded-lg border border-slate-300 p-1.5 dark:border-slate-600"
                                 :class="style.id === 'floating' ? 'rounded-2xl' : 'rounded-lg'">
                                <div class="h-1 w-6 rounded-full bg-primary/60"></div>
                                <div class="h-1 w-5 rounded-full bg-slate-300 dark:bg-slate-600"></div>
                                <div class="h-1 w-4 rounded-full bg-slate-300 dark:bg-slate-600"></div>
                                <div class="mt-auto h-1 w-3 rounded-full bg-slate-300 dark:bg-slate-600"></div>
                            </div>
                            <div class="flex h-14 w-14 flex-col gap-1 rounded-lg border border-slate-300 p-1.5 dark:border-slate-600">
                                <div class="h-1 w-10 rounded-full bg-slate-300 dark:bg-slate-600"></div>
                                <div class="h-1 w-8 rounded-full bg-slate-200 dark:bg-slate-700"></div>
                                <div class="mt-1 h-3 w-full rounded bg-slate-100 dark:bg-slate-800"></div>
                            </div>
                        </div>
                        <div class="text-center">
                            <h4 class="text-sm font-semibold text-slate-800 dark:text-slate-200" x-text="style.label"></h4>
                            <p class="text-[11px] text-slate-400 dark:text-slate-500" x-text="style.description"></p>
                        </div>
                    </button>
                </template>
            </div>
        </div>

        {{-- Save Button --}}
        <div class="sticky bottom-4 z-10 flex items-center justify-end gap-4 rounded-2xl border border-slate-200 bg-white/90 px-6 py-4 backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/90">
            <p class="mr-auto text-xs text-slate-400 dark:text-slate-500">
                Previewing: <span class="font-semibold text-slate-600 dark:text-slate-300 capitalize" x-text="selectedPreset"></span>
                &middot; <span class="capitalize" x-text="selectedAccent"></span>
                &middot; <span class="capitalize" x-text="selectedMode"></span>
            </p>
            <form method="POST" action="{{ route('settings.theme.update') }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="theme_preset" :value="selectedPreset">
                <input type="hidden" name="theme_accent" :value="selectedAccent">
                <input type="hidden" name="theme_mode" :value="selectedMode">
                <input type="hidden" name="theme_sidebarStyle" :value="selectedSidebarStyle">
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-xl bg-primary px-6 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary/25 transition-all hover:shadow-xl hover:shadow-primary/30 hover:brightness-110 active:scale-95">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Save Preferences
                </button>
            </form>
        </div>
    </div>
</x-app-layout>
