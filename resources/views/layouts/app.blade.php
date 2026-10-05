<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Studylikepro') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

        <!-- Blocking theme script: prevents flash of light theme on page load -->
        <script>
            (function() {
                const mode = localStorage.getItem('themeMode') || '{{ $userTheme['mode'] ?? 'system' }}';
                const isDark = mode === 'dark' || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                if (isDark) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }

                const accent = localStorage.getItem('themeAccent') || '{{ $userTheme['accent'] ?? 'indigo' }}';
                const colors = { indigo: '99 102 241', violet: '139 92 246', emerald: '16 185 129', rose: '244 63 94', amber: '245 158 11', blue: '59 130 246', cyan: '6 182 212' };
                document.documentElement.style.setProperty('--primary', colors[accent] || colors.indigo);

                // Add the correct theme background class to documentElement immediately to avoid page load flashing
                const preset = localStorage.getItem('themePreset') || '{{ $userTheme['preset'] ?? 'classic' }}';
                let bgClass = 'bg-slate-50';
                if (isDark) {
                    bgClass = 'bg-slate-950';
                } else {
                    if (preset === 'midnight') bgClass = 'bg-slate-900';
                    else if (preset === 'sunset') bgClass = 'bg-amber-50/40';
                    else if (preset === 'glass') bgClass = 'bg-slate-100';
                    else if (preset === 'ocean') bgClass = 'bg-cyan-50/30';
                }
                document.documentElement.classList.add(bgClass);
            })();
        </script>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    @php
        $preset = $userTheme['preset'] ?? 'classic';
        $bgClass = 'bg-slate-50 dark:bg-slate-950';
        if ($preset === 'midnight') {
            $bgClass = 'bg-slate-900 dark:bg-slate-950 bg-pattern-grid';
        } elseif ($preset === 'sunset') {
            $bgClass = 'bg-amber-50/40 dark:bg-slate-950 bg-pattern-dots';
        } elseif ($preset === 'glass') {
            $bgClass = 'bg-slate-100 dark:bg-slate-950 bg-pattern-grid';
        } elseif ($preset === 'ocean') {
            $bgClass = 'bg-cyan-50/30 dark:bg-slate-950 bg-pattern-dots';
        }
    @endphp
    <body class="font-sans antialiased text-slate-900 dark:text-slate-100 {{ $bgClass }}"
          x-data="{ 
              sidebarOpen: false,
              themeTransitionEnabled: false,
              themePreset: localStorage.getItem('themePreset') || '{{ $preset }}',
              themeAccent: localStorage.getItem('themeAccent') || '{{ $userTheme['accent'] ?? 'indigo' }}',
              themeMode: localStorage.getItem('themeMode') || '{{ $userTheme['mode'] ?? 'system' }}',
              sidebarStyle: localStorage.getItem('sidebarStyle') || '{{ $userTheme['sidebarStyle'] ?? 'flat' }}',
              initTheme() {
                  this.applyThemeMode();
                  this.applyThemeAccent();
                  
                  // Enable transitions only after initial render to avoid flash
                  this.$nextTick(() => {
                      setTimeout(() => {
                          this.themeTransitionEnabled = true;
                      }, 50);
                  });
                  
                  // Setup sync with local storage updates
                  window.addEventListener('theme-changed', (e) => {
                      this.themePreset = e.detail.preset;
                      this.themeAccent = e.detail.accent;
                      this.themeMode = e.detail.mode;
                      this.sidebarStyle = e.detail.sidebarStyle;
                      this.applyThemeMode();
                      this.applyThemeAccent();
                  });
              },
              applyThemeMode() {
                  let isDark = false;
                  if (this.themeMode === 'dark') {
                      isDark = true;
                  } else if (this.themeMode === 'light') {
                      isDark = false;
                  } else {
                      isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                  }
                  if (isDark) {
                      document.documentElement.classList.add('dark');
                  } else {
                      document.documentElement.classList.remove('dark');
                  }
              },
              applyThemeAccent() {
                  const colors = {
                      indigo: '99 102 241',
                      violet: '139 92 246',
                      emerald: '16 185 129',
                      rose: '244 63 94',
                      amber: '245 158 11',
                      blue: '59 130 246',
                      cyan: '6 182 212'
                  };
                  const colorVal = colors[this.themeAccent] || colors.indigo;
                  document.documentElement.style.setProperty('--primary', colorVal);
              }
          }" 
          x-init="initTheme()"
          :class="{
              'transition-colors duration-300': themeTransitionEnabled,
              'bg-slate-50 dark:bg-slate-950': themePreset === 'classic' || themePreset === 'forest',
              'bg-slate-900 dark:bg-slate-950 bg-pattern-grid': themePreset === 'midnight',
              'bg-amber-50/40 dark:bg-slate-950 bg-pattern-dots': themePreset === 'sunset',
              'bg-slate-100 dark:bg-slate-950 bg-pattern-grid': themePreset === 'glass',
              'bg-cyan-50/30 dark:bg-slate-950 bg-pattern-dots': themePreset === 'ocean'
          }">
        <div class="min-h-screen flex flex-col md:flex-row relative">
            <!-- Decorative top sunset gradient bar -->
            <div x-show="themePreset === 'sunset'" class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-amber-500 via-rose-500 to-indigo-500 z-50" style="display: none;"></div>
            <!-- Decorative top ocean gradient bar -->
            <div x-show="themePreset === 'ocean'" class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-cyan-400 via-blue-500 to-indigo-500 z-50" style="display: none;"></div>
            
            <!-- Navigation Sidebar -->
            @include('layouts.navigation')

            <!-- Main Layout Content -->
            <div class="flex-1 flex flex-col min-h-screen transition-all duration-300 ease-in-out md:pl-64">
                <!-- Page Top Header -->
                <header class="sticky top-0 z-20 flex h-16 w-full items-center justify-between border-b border-slate-200/80 bg-white/80 px-4 backdrop-blur-md dark:border-slate-800/80 dark:bg-slate-900/80 sm:px-6 lg:px-8"
                        :class="{'bg-white/70 dark:bg-slate-900/70 backdrop-blur-lg border-slate-200/40 dark:border-slate-800/40': themePreset === 'glass' || themePreset === 'ocean'}">
                    <div class="flex items-center gap-4">
                        <!-- Sidebar mobile toggle -->
                        <button @click="sidebarOpen = !sidebarOpen" class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 md:hidden">
                            <span class="sr-only">Toggle Sidebar</span>
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
                            </svg>
                        </button>

                        @isset($header)
                            <div class="flex items-center">
                                {{ $header }}
                            </div>
                        @endisset
                    </div>

                    <!-- Right utility nav -->
                    <div class="flex items-center gap-4">
                        <!-- Notification bell -->
                        <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                            <button @click="open = !open" class="relative rounded-xl p-2 text-slate-500 transition-colors hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800" aria-label="{{ __('Notifications') }}">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                </svg>
                                @if (($unreadNotificationCount ?? 0) > 0)
                                    <span class="absolute -right-0.5 -top-0.5 inline-flex min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 py-0.5 text-[10px] font-bold text-white">{{ $unreadNotificationCount > 9 ? '9+' : $unreadNotificationCount }}</span>
                                @endif
                            </button>

                            <div x-show="open"
                                 x-transition:enter="transition ease-out duration-100"
                                 x-transition:enter-start="transform opacity-0 scale-95"
                                 x-transition:enter-end="transform opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-75"
                                 x-transition:leave-start="transform opacity-100 scale-100"
                                 x-transition:leave-end="transform opacity-0 scale-95"
                                 class="absolute right-0 mt-2 w-80 origin-top-right rounded-xl border border-slate-200 bg-white shadow-lg ring-1 ring-black/5 dark:border-slate-800 dark:bg-slate-900 dark:ring-white/5 sm:w-96"
                                 style="display: none;">
                                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-slate-800">
                                    <p class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('Notifications') }}</p>
                                    @if (($unreadNotificationCount ?? 0) > 0)
                                        <form method="POST" action="{{ route('notifications.read-all') }}">
                                            @csrf
                                            <button type="submit" class="text-xs font-semibold text-primary hover:underline">{{ __('Mark all read') }}</button>
                                        </form>
                                    @endif
                                </div>

                                <div class="max-h-80 overflow-y-auto">
                                    @forelse (($recentNotifications ?? collect()) as $notification)
                                        <form method="POST" action="{{ route('notifications.open', $notification->id) }}">
                                            @csrf
                                            <button type="submit" class="flex w-full items-start gap-3 px-4 py-3 text-left transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/60 {{ $notification->read_at === null ? 'bg-primary/5' : '' }}">
                                                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $notification->read_at === null ? 'bg-primary' : 'bg-slate-300 dark:bg-slate-600' }}"></span>
                                                <span class="min-w-0">
                                                    <span class="block truncate text-sm font-semibold text-slate-700 dark:text-slate-200">{{ data_get($notification->data, 'title', 'Notification') }}</span>
                                                    <span class="mt-0.5 block text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ \Illuminate\Support\Str::limit((string) data_get($notification->data, 'body', ''), 110) }}</span>
                                                    <span class="mt-1 block text-[10px] uppercase tracking-wide text-slate-400">{{ $notification->created_at->diffForHumans() }}</span>
                                                </span>
                                            </button>
                                        </form>
                                    @empty
                                        <p class="px-4 py-6 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('Nothing yet — lesson updates show up here.') }}</p>
                                    @endforelse
                                </div>

                                <a href="{{ route('notifications.index') }}" class="block border-t border-slate-200 px-4 py-3 text-center text-sm font-semibold text-primary hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/60">
                                    {{ __('View all notifications') }}
                                </a>
                            </div>
                        </div>

                        <!-- Profile Dropdown -->
                        <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                            <button @click="open = !open" class="flex items-center gap-2 rounded-xl p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                                @if (Auth::user()->avatarUrl())
                                    <img src="{{ Auth::user()->avatarUrl() }}" alt="{{ Auth::user()->name }}" class="h-8 w-8 rounded-xl object-cover shadow-md" />
                                @else
                                    <div class="h-8 w-8 rounded-xl bg-gradient-to-tr from-primary to-purple-500 text-white flex items-center justify-center font-semibold text-sm shadow-md shadow-primary/20">
                                        {{ substr(Auth::user()->name, 0, 2) }}
                                    </div>
                                @endif
                                <span class="hidden text-sm font-medium text-slate-700 dark:text-slate-300 md:block">{{ Auth::user()->name }}</span>
                                <svg class="hidden h-4 w-4 text-slate-400 md:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            <!-- Profile dropdown panel -->
                            <div x-show="open" 
                                 x-transition:enter="transition ease-out duration-100"
                                 x-transition:enter-start="transform opacity-0 scale-95"
                                 x-transition:enter-end="transform opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-75"
                                 x-transition:leave-start="transform opacity-100 scale-100"
                                 x-transition:leave-end="transform opacity-0 scale-95"
                                 class="absolute right-0 mt-2 w-48 origin-top-right rounded-xl border border-slate-200 bg-white p-1 shadow-lg ring-1 ring-black/5 dark:border-slate-800 dark:bg-slate-900 dark:ring-white/5"
                                 style="display: none;">
                                <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800 transition-colors">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                    {{ __('Profile') }}
                                </a>
                                <a href="{{ route('settings.index') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800 transition-colors">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    </svg>
                                    {{ __('Settings') }}
                                </a>
                                <hr class="my-1 border-slate-200 dark:border-slate-800" />
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <a href="{{ route('logout') }}"
                                       onclick="event.preventDefault(); this.closest('form').submit();"
                                       class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/30 transition-colors">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                        </svg>
                                        {{ __('Log Out') }}
                                    </a>
                                </form>
                            </div>
                        </div>
                    </div>
                </header>

                <!-- Page Content -->
                <main class="flex-1 p-4 md:p-6 lg:p-8">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
