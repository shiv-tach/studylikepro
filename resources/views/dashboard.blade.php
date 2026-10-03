<x-app-layout>
    <!-- Header custom title -->
    <x-slot name="header">
        <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">
            {{ __('Overview') }}
        </h2>
    </x-slot>

    <div class="space-y-6">
        <!-- Welcome banner -->
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-primary via-primary/80 to-purple-600 p-6 text-white shadow-lg shadow-primary/10 dark:shadow-primary/5">
            <div class="relative z-10 max-w-md">
                <h3 class="text-xl font-bold md:text-2xl">Good morning, {{ Auth::user()->name }}! 👋</h3>
                <p class="mt-1 text-sm text-white/90">Here's what's happening with your store today. You have 3 pending tasks and 45 unprocessed orders.</p>
            </div>
            <!-- Decorative graphics inside banner -->
            <div class="absolute right-0 top-0 -mr-6 -mt-6 h-36 w-36 rounded-full bg-white/10 blur-xl"></div>
            <div class="absolute right-20 bottom-0 -mb-10 h-28 w-28 rounded-full bg-white/10 blur-lg"></div>
        </div>

        <!-- Metrics Grid -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Card 1: Total Revenue -->
            <div class="group rounded-2xl border p-6 transition-all hover:shadow-md hover:border-primary/40 dark:hover:border-primary/30"
                 :class="themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Revenue</span>
                    <div class="rounded-xl bg-primary/10 p-2.5 text-primary transition-colors duration-300">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-4">
                    <h3 class="text-2xl font-bold tracking-tight text-slate-800 dark:text-slate-100">$48,259.00</h3>
                    <div class="mt-2 flex items-center gap-1 text-xs">
                        <span class="inline-flex items-center gap-0.5 rounded-full bg-emerald-50 px-1.5 py-0.5 font-medium text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400">
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                            </svg>
                            12.5%
                        </span>
                        <span class="text-slate-400">from last month</span>
                    </div>
                </div>
            </div>

            <!-- Card 2: Active Users -->
            <div class="group rounded-2xl border p-6 transition-all hover:shadow-md hover:border-primary/40 dark:hover:border-primary/30"
                 :class="themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Active Users</span>
                    <div class="rounded-xl bg-blue-500/10 p-2.5 text-blue-500 dark:bg-blue-500/20 dark:text-blue-400">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-4">
                    <h3 class="text-2xl font-bold tracking-tight text-slate-800 dark:text-slate-100">10,289</h3>
                    <div class="mt-2 flex items-center gap-1 text-xs">
                        <span class="inline-flex items-center gap-0.5 rounded-full bg-emerald-50 px-1.5 py-0.5 font-medium text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400">
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                            </svg>
                            8.2%
                        </span>
                        <span class="text-slate-400">from last week</span>
                    </div>
                </div>
            </div>

            <!-- Card 3: Sales Conversion -->
            <div class="group rounded-2xl border p-6 transition-all hover:shadow-md hover:border-primary/40 dark:hover:border-primary/30"
                 :class="themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Conversion Rate</span>
                    <div class="rounded-xl bg-amber-500/10 p-2.5 text-amber-500 dark:bg-amber-500/20 dark:text-amber-400">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-4">
                    <h3 class="text-2xl font-bold tracking-tight text-slate-800 dark:text-slate-100">2.84%</h3>
                    <div class="mt-2 flex items-center gap-1 text-xs">
                        <span class="inline-flex items-center gap-0.5 rounded-full bg-rose-50 px-1.5 py-0.5 font-medium text-rose-600 dark:bg-rose-950/40 dark:text-rose-400">
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                            </svg>
                            1.5%
                        </span>
                        <span class="text-slate-400">from last week</span>
                    </div>
                </div>
            </div>

            <!-- Card 4: Pending Orders -->
            <div class="group rounded-2xl border p-6 transition-all hover:shadow-md hover:border-primary/40 dark:hover:border-primary/30"
                 :class="themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Pending Orders</span>
                    <div class="rounded-xl bg-rose-500/10 p-2.5 text-rose-500 dark:bg-rose-500/20 dark:text-rose-400">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                    </div>
                </div>
                <div class="mt-4">
                    <h3 class="text-2xl font-bold tracking-tight text-slate-800 dark:text-slate-100">45</h3>
                    <div class="mt-2 flex items-center gap-1 text-xs">
                        <span class="inline-flex items-center gap-0.5 rounded-full bg-rose-50 px-1.5 py-0.5 font-medium text-rose-600 dark:bg-rose-950/40 dark:text-rose-400">
                            Action required
                        </span>
                        <span class="text-slate-400">needs processing</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Grid Section -->
        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Left Column: Weekly Performance Area Chart -->
            <div class="lg:col-span-2 rounded-2xl border p-6 transition-all hover:border-primary/20"
                 :class="themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">Weekly Performance</h3>
                        <p class="text-xs text-slate-400">Store sales analytics from the past 7 days</p>
                    </div>
                    <div class="flex items-center gap-2 rounded-xl bg-slate-50 p-1 dark:bg-slate-950 border border-slate-200/50 dark:border-slate-800/50">
                        <button class="rounded-lg px-3 py-1 text-xs font-semibold bg-white shadow-sm dark:bg-slate-900 text-slate-700 dark:text-slate-200">Sales</button>
                        <button class="rounded-lg px-3 py-1 text-xs font-semibold text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">Views</button>
                    </div>
                </div>

                <!-- Custom SVG Area Chart with Gradients -->
                <div class="relative mt-6 h-64 w-full">
                    <svg viewBox="0 0 700 240" class="h-full w-full overflow-visible" preserveAspectRatio="none">
                        <defs>
                            <!-- Linear Gradient for Area Fill -->
                            <linearGradient id="chartGradient" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="rgb(var(--primary))" stop-opacity="0.3"></stop>
                                <stop offset="100%" stop-color="rgb(var(--primary))" stop-opacity="0.0"></stop>
                            </linearGradient>
                        </defs>

                        <!-- Chart grid horizontal lines -->
                        <line x1="0" y1="40" x2="700" y2="40" stroke="currentColor" class="text-slate-100 dark:text-slate-800/60" stroke-dasharray="4"></line>
                        <line x1="0" y1="90" x2="700" y2="90" stroke="currentColor" class="text-slate-100 dark:text-slate-800/60" stroke-dasharray="4"></line>
                        <line x1="0" y1="140" x2="700" y2="140" stroke="currentColor" class="text-slate-100 dark:text-slate-800/60" stroke-dasharray="4"></line>
                        <line x1="0" y1="190" x2="700" y2="190" stroke="currentColor" class="text-slate-100 dark:text-slate-800/60" stroke-dasharray="4"></line>

                        <!-- Area Fill -->
                        <path d="M 0 190 Q 58 130 116 140 T 232 110 T 348 80 T 464 120 T 580 60 T 700 40 L 700 210 L 0 210 Z" fill="url(#chartGradient)"></path>

                        <!-- Line Path -->
                        <path d="M 0 190 Q 58 130 116 140 T 232 110 T 348 80 T 464 120 T 580 60 T 700 40" fill="none" stroke="rgb(var(--primary))" stroke-width="3" stroke-linecap="round"></path>

                        <!-- Data point markers (dots) -->
                        <circle cx="116" cy="140" r="5" fill="rgb(var(--primary))" stroke="#ffffff" stroke-width="2" class="cursor-pointer transition-all hover:r-7"></circle>
                        <circle cx="232" cy="110" r="5" fill="rgb(var(--primary))" stroke="#ffffff" stroke-width="2" class="cursor-pointer transition-all hover:r-7"></circle>
                        <circle cx="348" cy="80" r="5" fill="rgb(var(--primary))" stroke="#ffffff" stroke-width="2" class="cursor-pointer transition-all hover:r-7"></circle>
                        <circle cx="464" cy="120" r="5" fill="rgb(var(--primary))" stroke="#ffffff" stroke-width="2" class="cursor-pointer transition-all hover:r-7"></circle>
                        <circle cx="580" cy="60" r="5" fill="rgb(var(--primary))" stroke="#ffffff" stroke-width="2" class="cursor-pointer transition-all hover:r-7"></circle>
                        <circle cx="700" cy="40" r="5" fill="rgb(var(--primary))" stroke="#ffffff" stroke-width="2" class="cursor-pointer transition-all hover:r-7"></circle>
                    </svg>

                    <!-- Chart X-axis Labels -->
                    <div class="absolute bottom-[-16px] left-0 right-0 flex justify-between px-1 text-[10px] font-bold text-slate-400">
                        <span>Mon</span>
                        <span>Tue</span>
                        <span>Wed</span>
                        <span>Thu</span>
                        <span>Fri</span>
                        <span>Sat</span>
                        <span>Sun</span>
                    </div>
                </div>
            </div>

            <!-- Right Column: Sales by Category Doughnut Widget -->
            <div class="rounded-2xl border p-6 transition-all hover:border-primary/20"
                 :class="themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">Traffic Source</h3>
                        <p class="text-xs text-slate-400">Visitors split by category channel</p>
                    </div>
                </div>

                <!-- Circular Doughnut Visualizer -->
                <div class="relative mt-8 flex flex-col items-center justify-center">
                    <div class="relative h-40 w-40">
                        <svg viewBox="0 0 36 36" class="h-full w-full transform -rotate-90">
                            <!-- Background Circle -->
                            <circle cx="18" cy="18" r="15.915" fill="none" stroke="currentColor" class="text-slate-100 dark:text-slate-800" stroke-width="3"></circle>
                            <!-- Direct Segment (55%) using primary dynamic color -->
                            <circle cx="18" cy="18" r="15.915" fill="none" stroke="rgb(var(--primary))" stroke-width="3" stroke-dasharray="55 100" stroke-dashoffset="0"></circle>
                            <!-- Search Segment (30%) -->
                            <circle cx="18" cy="18" r="15.915" fill="none" stroke="#3b82f6" stroke-width="3" stroke-dasharray="30 100" stroke-dashoffset="-55"></circle>
                            <!-- Referral Segment (15%) -->
                            <circle cx="18" cy="18" r="15.915" fill="none" stroke="#10b981" stroke-width="3" stroke-dasharray="15 100" stroke-dashoffset="-85"></circle>
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-2xl font-extrabold text-slate-800 dark:text-slate-100">12.5k</span>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total visits</span>
                        </div>
                    </div>

                    <!-- Labels list with colors -->
                    <div class="mt-6 w-full space-y-2">
                        <div class="flex items-center justify-between text-xs font-medium">
                            <div class="flex items-center gap-2 text-slate-600 dark:text-slate-400">
                                <span class="h-2.5 w-2.5 rounded-full bg-primary"></span>
                                <span>Direct</span>
                            </div>
                            <span class="text-slate-700 dark:text-slate-300">55%</span>
                        </div>
                        <div class="flex items-center justify-between text-xs font-medium">
                            <div class="flex items-center gap-2 text-slate-600 dark:text-slate-400">
                                <span class="h-2.5 w-2.5 rounded-full bg-blue-500"></span>
                                <span>Organic Search</span>
                            </div>
                            <span class="text-slate-700 dark:text-slate-300">30%</span>
                        </div>
                        <div class="flex items-center justify-between text-xs font-medium">
                            <div class="flex items-center gap-2 text-slate-600 dark:text-slate-400">
                                <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                                <span>Referral</span>
                            </div>
                            <span class="text-slate-700 dark:text-slate-300">15%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Transactions Table Section -->
        <div class="rounded-2xl border transition-all"
             :class="themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'">
            <div class="flex items-center justify-between px-6 py-5 border-b border-slate-100 dark:border-slate-800/50"
                 :class="{'border-slate-200/40 dark:border-slate-800/40': themePreset === 'glass' || themePreset === 'ocean'}">
                <div>
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">Recent Transactions</h3>
                    <p class="text-xs text-slate-400">View and manage latest customer activities</p>
                </div>
                <button class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-800 dark:text-slate-400 dark:hover:bg-slate-800 transition-colors">
                    Export CSV
                </button>
            </div>

            <!-- Responsive Table Layout -->
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/50 text-[11px] font-bold text-slate-400 uppercase tracking-wider dark:bg-slate-900/50">
                            <th class="px-6 py-4">Customer</th>
                            <th class="px-6 py-4">Date</th>
                            <th class="px-6 py-4">Amount</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50 text-sm"
                           :class="{'divide-slate-200/40 dark:divide-slate-800/40': themePreset === 'glass' || themePreset === 'ocean'}">
                        <!-- Row 1 -->
                        <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/20 transition-colors">
                            <td class="px-6 py-4 flex items-center gap-3">
                                <div class="h-9 w-9 rounded-xl bg-primary/10 text-primary font-bold flex items-center justify-center text-xs dark:bg-primary/20">
                                    OL
                                </div>
                                <div>
                                    <h4 class="font-semibold text-slate-700 dark:text-slate-300">Olivia Logan</h4>
                                    <p class="text-xs text-slate-400">olivia.logan@example.com</p>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-slate-500 dark:text-slate-400">Jul 02, 2026</td>
                            <td class="px-6 py-4 font-semibold text-slate-700 dark:text-slate-300">$340.50</td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                    Paid
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button class="text-xs font-bold text-primary hover:opacity-85 transition-opacity">Manage</button>
                            </td>
                        </tr>

                        <!-- Row 2 -->
                        <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/20 transition-colors">
                            <td class="px-6 py-4 flex items-center gap-3">
                                <div class="h-9 w-9 rounded-xl bg-primary/10 text-primary font-bold flex items-center justify-center text-xs dark:bg-primary/20">
                                    JH
                                </div>
                                <div>
                                    <h4 class="font-semibold text-slate-700 dark:text-slate-300">Jackson Harris</h4>
                                    <p class="text-xs text-slate-400">j.harris@example.com</p>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-slate-500 dark:text-slate-400">Jul 01, 2026</td>
                            <td class="px-6 py-4 font-semibold text-slate-700 dark:text-slate-300">$1,290.00</td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-600 dark:bg-amber-950/40 dark:text-amber-400">
                                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                    Pending
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button class="text-xs font-bold text-primary hover:opacity-85 transition-opacity">Manage</button>
                            </td>
                        </tr>

                        <!-- Row 3 -->
                        <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/20 transition-colors">
                            <td class="px-6 py-4 flex items-center gap-3">
                                <div class="h-9 w-9 rounded-xl bg-primary/10 text-primary font-bold flex items-center justify-center text-xs dark:bg-primary/20">
                                    EC
                                </div>
                                <div>
                                    <h4 class="font-semibold text-slate-700 dark:text-slate-300">Emma Cooper</h4>
                                    <p class="text-xs text-slate-400">emma.c@example.com</p>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-slate-500 dark:text-slate-400">Jun 30, 2026</td>
                            <td class="px-6 py-4 font-semibold text-slate-700 dark:text-slate-300">$89.00</td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2 py-0.5 text-xs font-semibold text-rose-600 dark:bg-rose-950/40 dark:text-rose-400">
                                    <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                    Failed
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button class="text-xs font-bold text-primary hover:opacity-85 transition-opacity">Manage</button>
                            </td>
                        </tr>

                        <!-- Row 4 -->
                        <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/20 transition-colors">
                            <td class="px-6 py-4 flex items-center gap-3">
                                <div class="h-9 w-9 rounded-xl bg-primary/10 text-primary font-bold flex items-center justify-center text-xs dark:bg-primary/20">
                                    LM
                                </div>
                                <div>
                                    <h4 class="font-semibold text-slate-700 dark:text-slate-300">Liam Miller</h4>
                                    <p class="text-xs text-slate-400">liam.m@example.com</p>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-slate-500 dark:text-slate-400">Jun 28, 2026</td>
                            <td class="px-6 py-4 font-semibold text-slate-700 dark:text-slate-300">$450.00</td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                    Paid
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button class="text-xs font-bold text-primary hover:opacity-85 transition-opacity">Manage</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
