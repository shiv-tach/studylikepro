<!-- Mobile Sidebar Overlay -->
<div x-show="sidebarOpen" 
     x-transition:enter="transition-opacity ease-linear duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition-opacity ease-linear duration-300"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-30 bg-slate-900/50 backdrop-blur-sm md:hidden"
     @click="sidebarOpen = false"
     style="display: none;"></div>

<!-- Sidebar Container -->
<aside :class="{
           'bg-white border-slate-200/80 dark:border-slate-800/80 dark:bg-slate-900': themePreset === 'classic' || themePreset === 'forest',
           'bg-slate-950 dark:bg-slate-950 border-slate-900 dark:border-slate-900/60 text-slate-100': themePreset === 'midnight',
           'bg-white/90 dark:bg-slate-900/85 border-slate-200/50 dark:border-slate-800/50': themePreset === 'sunset',
           'bg-white/70 dark:bg-slate-900/70 backdrop-blur-lg border-slate-200/40 dark:border-slate-800/40': themePreset === 'glass',
           'bg-white/80 dark:bg-slate-900/80 backdrop-blur-md border-cyan-200/60 dark:border-cyan-800/40': themePreset === 'ocean'
       }"
       :style="sidebarOpen ? 'transform: translateX(0)' : ''"
       class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r transition-all duration-300 ease-in-out -translate-x-full md:translate-x-0">
    
    <!-- Sidebar Header (Logo and Close) -->
    <div class="flex h-16 items-center justify-between px-6 border-b border-slate-200/50 dark:border-slate-800/50"
         :class="{'border-slate-800/80': themePreset === 'midnight', 'border-cyan-200/50 dark:border-cyan-800/30': themePreset === 'ocean'}">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 font-bold text-lg text-primary">
            <x-application-logo class="h-8 w-auto fill-current text-primary" />
            <span class="tracking-tight" :class="{'text-slate-100': themePreset === 'midnight'}">AuraAdmin</span>
        </a>
        
        <!-- Mobile close button -->
        <button @click="sidebarOpen = false" class="rounded-lg p-1 text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 md:hidden">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <!-- Navigation List -->
    <nav class="flex-1 space-y-1.5 px-4 py-6 overflow-y-auto">
        <!-- Dashboard Link -->
        <a href="{{ route('dashboard') }}" 
           class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition-all"
           :class="{
               'text-primary bg-primary/10 dark:bg-primary/20 shadow-sm shadow-primary/5': {{ request()->routeIs('dashboard') ? 'true' : 'false' }},
               'text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800/50': !{{ request()->routeIs('dashboard') ? 'true' : 'false' }} && themePreset !== 'midnight',
               'text-slate-300 hover:bg-slate-850 hover:text-white': !{{ request()->routeIs('dashboard') ? 'true' : 'false' }} && themePreset === 'midnight'
           }">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z" />
            </svg>
            <span>Dashboard</span>
        </a>

        <!-- Analytics (Dummy Link) -->
        <a href="#" 
           class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition-all"
           :class="{
               'text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800/50': themePreset !== 'midnight',
               'text-slate-300 hover:bg-slate-850 hover:text-white': themePreset === 'midnight'
           }">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 002 2h2a2 2 0 002-2" />
            </svg>
            <span>Analytics</span>
        </a>

        <!-- Users (Dummy Link) -->
        <a href="#" 
           class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition-all"
           :class="{
               'text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800/50': themePreset !== 'midnight',
               'text-slate-300 hover:bg-slate-850 hover:text-white': themePreset === 'midnight'
           }">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span>Users</span>
        </a>

        <!-- Products (Dummy Link) -->
        <a href="#" 
           class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition-all"
           :class="{
               'text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800/50': themePreset !== 'midnight',
               'text-slate-300 hover:bg-slate-850 hover:text-white': themePreset === 'midnight'
           }">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
            <span>Products</span>
        </a>

        <!-- Sales (Dummy Link) -->
        <a href="#" 
           class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition-all"
           :class="{
               'text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800/50': themePreset !== 'midnight',
               'text-slate-300 hover:bg-slate-850 hover:text-white': themePreset === 'midnight'
           }">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>Sales</span>
        </a>

        <div class="pt-4 pb-2">
            <div class="px-4 text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Management</div>
        </div>

        <!-- Settings -->
        <a href="{{ route('settings.index') }}" 
           class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition-all"
           :class="{
               'text-primary bg-primary/10 dark:bg-primary/20 shadow-sm shadow-primary/5': {{ request()->routeIs('settings*') ? 'true' : 'false' }},
               'text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800/50': !{{ request()->routeIs('settings*') ? 'true' : 'false' }} && themePreset !== 'midnight',
               'text-slate-300 hover:bg-slate-850 hover:text-white': !{{ request()->routeIs('settings*') ? 'true' : 'false' }} && themePreset === 'midnight'
           }">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span>Settings</span>
        </a>
    </nav>

    <!-- Sidebar Footer -->
    <div class="border-t border-slate-200/50 p-4 dark:border-slate-800/50"
         :class="{'border-slate-800/80': themePreset === 'midnight', 'border-cyan-200/50 dark:border-cyan-800/30': themePreset === 'ocean'}">
        <div class="flex items-center gap-3 rounded-xl bg-slate-50 p-3 dark:bg-slate-800/30"
             :class="{'bg-slate-900/60': themePreset === 'midnight'}">
            <div class="h-9 w-9 rounded-xl bg-primary text-white flex items-center justify-center font-bold text-sm shadow-md shadow-primary/20">
                {{ substr(Auth::user()->name, 0, 2) }}
            </div>
            <div class="flex-1 overflow-hidden">
                <h4 class="truncate text-sm font-semibold text-slate-700 dark:text-slate-300" :class="{'text-slate-200': themePreset === 'midnight'}">{{ Auth::user()->name }}</h4>
                <p class="truncate text-xs text-slate-400">{{ Auth::user()->email }}</p>
            </div>
        </div>
    </div>
</aside>
