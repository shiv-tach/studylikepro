<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <x-favicon-links />

        <title>{{ config('app.name', 'Studylikepro') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

        <!-- Blocking theme script: prevents flash of light theme on page load -->
        <script>
            (function() {
                const mode = localStorage.getItem('themeMode') || '{{ $userTheme['mode'] ?? 'light' }}';
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
              themeTransitionEnabled: false,
              themePreset: localStorage.getItem('themePreset') || '{{ $preset }}',
              themeAccent: localStorage.getItem('themeAccent') || '{{ $userTheme['accent'] ?? 'indigo' }}',
              themeMode: localStorage.getItem('themeMode') || '{{ $userTheme['mode'] ?? 'light' }}',
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
            <!-- Main Layout Content -->
            <div class="flex-1 min-w-0 flex flex-col min-h-screen transition-all duration-300 ease-in-out md:pl-64">
                {{-- The sidebar, top bar and mobile drawer are rendered by
                     resources/js/app-shell; this include drops the mount point and
                     its JSON island into the content column. --}}
                @include('layouts.navigation')

                <!-- Page Content -->
                <main class="flex-1 p-4 md:p-6 lg:p-8">
                    {{ $slot }}
                </main>
            </div>
        </div>

        @vite('resources/js/app-shell/main.js')
    </body>
</html>
