<!DOCTYPE html>

<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&amp;display=swap"
        rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
        rel="stylesheet" />
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            display: inline-block;
            line-height: 1;
            text-transform: none;
            letter-spacing: normal;
            word-wrap: normal;
            white-space: nowrap;
            direction: ltr;
        }

        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "on-secondary-fixed": "#0f172a",
                        "on-primary": "#ffffff",
                        "background": "#f6f6f8",
                        "on-primary-fixed-variant": "#1d4ed8",
                        "inverse-surface": "#1e293b",
                        "primary": "#1152d4",
                        "on-surface-variant": "#64748b",
                        "primary-fixed": "#dbeafe",
                        "secondary": "#475569",
                        "inverse-on-surface": "#f8fafc",
                        "on-error": "#ffffff",
                        "tertiary": "#f59e0b",
                        "on-secondary-container": "#1e293b",
                        "on-error-container": "#991b1b",
                        "tertiary-fixed-dim": "#fed7aa",
                        "surface-container-lowest": "#ffffff",
                        "on-tertiary-container": "#92400e",
                        "surface-tint": "#1152d4",
                        "on-secondary-fixed-variant": "#334155",
                        "surface-container": "#ffffff",
                        "secondary-fixed-dim": "#cbd5e1",
                        "primary-container": "#eef2ff",
                        "surface": "#f6f6f8",
                        "surface-dim": "#d9d9e4",
                        "on-primary-fixed": "#1e3a8a",
                        "error-container": "#fee2e2",
                        "surface-container-high": "#ffffff",
                        "primary-fixed-dim": "#bfdbfe",
                        "outline": "#e2e8f0",
                        "surface-variant": "#f1f5f9",
                        "on-background": "#0f172a",
                        "inverse-primary": "#b4c5ff",
                        "outline-variant": "#cbd5e1",
                        "tertiary-container": "#fef3c7",
                        "on-secondary": "#ffffff",
                        "secondary-fixed": "#e2e8f0",
                        "surface-container-low": "#ffffff",
                        "secondary-container": "#f1f5f9",
                        "on-primary-container": "#1152d4",
                        "error": "#ef4444",
                        "on-tertiary": "#ffffff",
                        "on-tertiary-fixed": "#7c2d12",
                        "surface-bright": "#ffffff",
                        "surface-container-highest": "#ffffff",
                        "on-tertiary-fixed-variant": "#9a3412",
                        "tertiary-fixed": "#ffedd5",
                        "on-surface": "#0f172a"
                    },
                    fontFamily: {
                        "headline": ["Inter"],
                        "body": ["Inter"],
                        "label": ["Inter"]
                    },
                    borderRadius: { "DEFAULT": "0.125rem", "lg": "0.25rem", "xl": "0.5rem", "full": "0.75rem" },
                },
            },
        }
    </script>
</head>

<body class="bg-surface text-on-surface">
    <!-- SideNavBar -->
    <aside
        class="fixed left-0 top-0 h-screen w-64 flex flex-col bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 z-[60]">
        <div class="p-6">
            <div class="flex items-center gap-3 mb-8">
                <div class="bg-primary rounded-lg p-2 text-white">
                    <span class="material-symbols-outlined">directions_car</span>
                </div>
                <div>
                    <h1 class="text-lg font-bold leading-none">AutoFix Pro</h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Repair Management</p>
                </div>
            </div>
            <nav class="space-y-1">
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    href="dashboardadmin.php">
                    <span class="material-symbols-outlined text-[22px]">dashboard</span>
                    <span class="font-medium">Dashboard</span>
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    href="repairjobsadmin.php">
                    <span class="material-symbols-outlined text-[22px]">build</span>
                    <span class="font-medium">Repair Jobs</span>
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    href="vehicleadmin.php">
                    <span class="material-symbols-outlined text-[22px]">directions_car</span>
                    <span class="font-medium">Vehicles</span>
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    href="appointmentadmin.php">
                    <span class="material-symbols-outlined text-[22px]">event</span>
                    <span class="font-medium">Appointments</span>
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-primary/10 text-primary font-bold" 
                    href="reportsadmin.php">
                    <span class="material-symbols-outlined text-[22px]">description</span>
                    <span class="font-medium">Reports</span>
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    href="inventoryadmin.php">
                    <span class="material-symbols-outlined text-[22px]">inventory_2</span>
                    <span class="font-medium">Inventory</span>
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    href="customeradmin.php">
                    <span class="material-symbols-outlined text-[22px]">group</span>
                    <span class="font-medium">Customers</span>
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    href="paymentsadmin.php">
                    <span class="material-symbols-outlined text-[22px]">payments</span>
                    <span class="font-medium">Payments</span>
                </a>
                <div class="pt-4 mt-4 border-t border-slate-100 dark:border-slate-800">
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                        href="settingsadmin.php">
                        <span class="material-symbols-outlined text-[22px]">settings</span>
                        <span class="font-medium">Settings</span>
                    </a>
                </div>
            </nav>
        </div>
        <div class="mt-auto w-full p-4 border-t border-slate-200 dark:border-slate-800">
            <div class="flex items-center gap-3">
                <div
                    class="size-10 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center overflow-hidden">
                    <img alt="Admin Profile" class="w-full h-full object-cover"
                        data-alt="User avatar for admin profile picture"
                        src="https://lh3.googleusercontent.com/aida-public/AB6AXuDeh_igjzq55wP-MQUqlN5a7g7ERzT91RAZllys2xTPdmr_K6ugTc7NEPOG48E87bvkhiEKuMOE9TZ0njKOCLQ7Nhccix3HVxsYdR2tXeyTCkjam7s1q8ngQOzslzdGRLROqouBtkGpnSewuAyIscdu673vBatOqI9TKHP1RCzarhxH8GqVYpWDnccgDrczUMroOqof3VFA7U9HLzMcDyURIrkC9dU2KtSkusqfbOvLaUs_zR14qlpZVSgASdGK8sw1SCeDf4A38q-8" />
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold truncate">Marcus Smith</p>
                    <p class="text-xs text-slate-500 truncate">Shop Manager</p>
                </div>
                <button class="text-slate-400 hover:text-error transition-colors">
                    <span class="material-symbols-outlined text-xl">logout</span>
                </button>
            </div>
        </div>
    </aside>
    <!-- Main Content Canvas -->
    <main class="ml-64 min-h-screen bg-background">
        <!-- Top Nav Bar -->
        <header
            class="sticky top-0 z-40 w-full border-b border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md flex items-center justify-between px-8 h-16">
            <div class="flex items-center gap-6">
                <h2 class="text-lg font-black text-slate-900 dark:white tracking-tight">Reports Management</h2>
                <div class="relative hidden lg:block">
                    <span
                        class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">search</span>
                    <input
                        class="bg-surface-variant border-none rounded-lg pl-10 pr-4 py-1.5 text-sm w-64 focus:ring-2 focus:ring-primary/20"
                        placeholder="Search reports..." type="text" />
                </div>
            </div>
            <div class="flex items-center gap-4">
                <button class="p-2 text-slate-500 hover:text-primary transition-all">
                    <span class="material-symbols-outlined">notifications</span>
                </button>
                <button class="p-2 text-slate-500 hover:text-primary transition-all">
                    <span class="material-symbols-outlined">help_outline</span>
                </button>
                <div class="h-8 w-px bg-slate-200 mx-2"></div>
                <div class="flex items-center gap-3">
                    <div class="text-right hidden sm:block">
                        <p class="text-xs font-bold text-on-background">Alex Rivet</p>
                        <p class="text-[10px] text-slate-500 uppercase font-semibold">Service Lead</p>
                    </div>
                    <img alt="Manager Avatar" class="h-10 w-10 rounded-full border-2 border-primary/20 object-cover"
                        data-alt="professional male service manager portrait in modern automotive office environment"
                        src="https://lh3.googleusercontent.com/aida-public/AB6AXuC_w4TZYv-DoCr0hxBNhV2Z-nUsRKiJWSSjgi__Y4oVCvZsEnAXH-GvsZk4qUV8VfyOd_rN5mqWnBeNlMb7An_00pBDPbF7FGZDqw2HhZ4MbeNkgRRsmuE6r3t2yOO4P5sHcWAMkVgXaheA3Z2LKA0Fo_mIUP0qh9KRyragtZ_zvLR-U7pm-kWc645Yi3rN0Mm0P9km9Kt3Fp4fKCU5i33aRJsonLoG5k45EuFpDDTP2CbZiarn81pTDjiPcRHLtpdJg1O47dGsJUD2" />
                </div>
            </div>
        </header>
        <!-- Changed max-w-7xl mx-auto to max-w-none and removed centering -->
        <div class="p-8 max-w-none">
            <!-- Header Section -->
            <div class="flex justify-between items-end mb-8">
                <div>
                    <h1 class="text-[30px] font-black text-on-background tracking-tight">Performance Reports</h1>
                    <p class="text-on-surface-variant font-medium mt-1">Detailed operational and financial analytics for
                        the current period.</p>
                </div>
                <button
                    class="bg-primary hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg font-bold text-sm flex items-center gap-2 transition-all shadow-sm">
                    <span class="material-symbols-outlined text-[18px]" data-icon="download">download</span>
                    Export Report
                </button>
            </div>
            <!-- Filter Bar: Ensuring it spans but the elements are naturally left-aligned -->
            <div class="bg-white border border-slate-200 rounded-xl p-4 mb-8 flex flex-wrap gap-4 items-center">
                <div class="w-64">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 px-1">Date
                        Range</label>
                    <div class="relative">
                        <select
                            class="w-full bg-slate-50 border-slate-200 text-sm rounded-lg py-2 pl-3 pr-10 appearance-none focus:ring-blue-500 focus:border-blue-500">
                            <option>Last 30 Days</option>
                            <option>This Quarter</option>
                            <option>Year to Date</option>
                            <option>Custom Range</option>
                        </select>
                        <span
                            class="material-symbols-outlined absolute right-3 top-2.5 text-slate-400 pointer-events-none">calendar_month</span>
                    </div>
                </div>
                <div class="w-64">
                    <label
                        class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 px-1">Technician</label>
                    <select
                        class="w-full bg-slate-50 border-slate-200 text-sm rounded-lg py-2 px-3 focus:ring-blue-500 focus:border-blue-500">
                        <option>All Technicians</option>
                        <option>Marcus Miller</option>
                        <option>Sarah Chen</option>
                        <option>David Rodriguez</option>
                    </select>
                </div>
                <div class="w-64">
                    <label
                        class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 px-1">Service
                        Type</label>
                    <select
                        class="w-full bg-slate-50 border-slate-200 text-sm rounded-lg py-2 px-3 focus:ring-blue-500 focus:border-blue-500">
                        <option>All Services</option>
                        <option>Engine Maintenance</option>
                        <option>Brake Systems</option>
                        <option>Transmission</option>
                        <option>Electrical</option>
                    </select>
                </div>
                <div class="flex items-end h-full self-end">
                    <button
                        class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2.5 rounded-lg text-sm font-bold transition-colors">Apply
                        Filters</button>
                </div>
            </div>
            <!-- Metrics Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Revenue -->
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-2 bg-blue-50 rounded-lg text-primary">
                            <span class="material-symbols-outlined" data-icon="payments">payments</span>
                        </div>
                        <span
                            class="text-[10px] font-bold text-green-600 bg-green-50 px-2 py-0.5 rounded-full">+12.4%</span>
                    </div>
                    <p class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1 text-left">Total Revenue
                        (Month)</p>
                    <h3 class="text-2xl font-black text-slate-900 text-left">$142,580.00</h3>
                </div>
                <!-- Avg Repair Cost -->
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-2 bg-blue-50 rounded-lg text-primary">
                            <span class="material-symbols-outlined" data-icon="average_pace">avg_pace</span>
                        </div>
                        <span
                            class="text-[10px] font-bold text-slate-400 bg-slate-50 px-2 py-0.5 rounded-full">-2.1%</span>
                    </div>
                    <p class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1 text-left">Average Repair
                        Cost</p>
                    <h3 class="text-2xl font-black text-slate-900 text-left">$842.15</h3>
                </div>
                <!-- Tech Productivity -->
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-2 bg-blue-50 rounded-lg text-primary">
                            <span class="material-symbols-outlined" data-icon="bolt">bolt</span>
                        </div>
                        <span
                            class="text-[10px] font-bold text-green-600 bg-green-50 px-2 py-0.5 rounded-full">+4.2%</span>
                    </div>
                    <p class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1 text-left">Technician
                        Productivity</p>
                    <h3 class="text-2xl font-black text-slate-900 text-left">94.8%</h3>
                </div>
                <!-- Customer Sat -->
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-2 bg-blue-50 rounded-lg text-primary">
                            <span class="material-symbols-outlined"
                                data-icon="sentiment_very_satisfied">sentiment_very_satisfied</span>
                        </div>
                        <span
                            class="text-[10px] font-bold text-slate-400 bg-slate-50 px-2 py-0.5 rounded-full">Stable</span>
                    </div>
                    <p class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1 text-left">Customer
                        Satisfaction</p>
                    <h3 class="text-2xl font-black text-slate-900 text-left">4.9/5.0</h3>
                </div>
            </div>
            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
                    <div class="flex justify-between items-center mb-6">
                        <h4 class="text-sm font-bold uppercase tracking-widest text-slate-400 text-left">Service Volume
                            by Category</h4>
                        <span class="material-symbols-outlined text-slate-300">more_horiz</span>
                    </div>
                    <div class="space-y-4">
                        <div class="group">
                            <div class="flex justify-between text-xs font-bold mb-1.5">
                                <span class="text-slate-600">Engine Maintenance</span>
                                <span class="text-slate-400">142 jobs</span>
                            </div>
                            <div class="w-full bg-slate-50 h-3 rounded-full overflow-hidden">
                                <div class="bg-primary h-full rounded-full" style="width: 85%"></div>
                            </div>
                        </div>
                        <div class="group">
                            <div class="flex justify-between text-xs font-bold mb-1.5">
                                <span class="text-slate-600">Brake Systems</span>
                                <span class="text-slate-400">98 jobs</span>
                            </div>
                            <div class="w-full bg-slate-50 h-3 rounded-full overflow-hidden">
                                <div class="bg-primary/70 h-full rounded-full" style="width: 65%"></div>
                            </div>
                        </div>
                        <div class="group">
                            <div class="flex justify-between text-xs font-bold mb-1.5">
                                <span class="text-slate-600">Transmission</span>
                                <span class="text-slate-400">45 jobs</span>
                            </div>
                            <div class="w-full bg-slate-50 h-3 rounded-full overflow-hidden">
                                <div class="bg-primary/50 h-full rounded-full" style="width: 35%"></div>
                            </div>
                        </div>
                        <div class="group">
                            <div class="flex justify-between text-xs font-bold mb-1.5">
                                <span class="text-slate-600">Diagnostics</span>
                                <span class="text-slate-400">120 jobs</span>
                            </div>
                            <div class="w-full bg-slate-50 h-3 rounded-full overflow-hidden">
                                <div class="bg-primary/30 h-full rounded-full" style="width: 75%"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm relative overflow-hidden">
                    <div class="flex justify-between items-center mb-6">
                        <h4 class="text-sm font-bold uppercase tracking-widest text-slate-400 text-left">Revenue Trends
                        </h4>
                        <div class="flex gap-2">
                            <span class="flex items-center gap-1 text-[10px] font-bold text-primary"><span
                                    class="w-2 h-2 rounded-full bg-primary"></span> Revenue</span>
                            <span class="flex items-center gap-1 text-[10px] font-bold text-slate-300"><span
                                    class="w-2 h-2 rounded-full bg-slate-300"></span> Projections</span>
                        </div>
                    </div>
                    <div class="h-48 flex items-end justify-start gap-2 relative z-10">
                        <!-- Bar widths and gaps adjusted for left alignment -->
                        <div class="bg-blue-100 w-12 h-[40%] rounded-t-sm"></div>
                        <div class="bg-blue-200 w-12 h-[55%] rounded-t-sm"></div>
                        <div class="bg-blue-300 w-12 h-[45%] rounded-t-sm"></div>
                        <div class="bg-blue-400 w-12 h-[65%] rounded-t-sm"></div>
                        <div class="bg-blue-500 w-12 h-[80%] rounded-t-sm"></div>
                        <div class="bg-primary w-12 h-[95%] rounded-t-sm"></div>
                        <div class="bg-blue-700 w-12 h-[85%] rounded-t-sm"></div>
                    </div>
                    <div class="flex justify-start gap-2 mt-2 px-1">
                        <span class="w-12 text-center text-[10px] font-bold text-slate-400">MON</span>
                        <span class="w-12 text-center text-[10px] font-bold text-slate-400">TUE</span>
                        <span class="w-12 text-center text-[10px] font-bold text-slate-400">WED</span>
                        <span class="w-12 text-center text-[10px] font-bold text-slate-400">THU</span>
                        <span class="w-12 text-center text-[10px] font-bold text-slate-400">FRI</span>
                        <span class="w-12 text-center text-[10px] font-bold text-slate-400">SAT</span>
                        <span class="w-12 text-center text-[10px] font-bold text-slate-400">SUN</span>
                    </div>
                    <!-- Decorative background texture -->
                    <div class="absolute inset-0 opacity-[0.03] pointer-events-none"
                        style="background-image: radial-gradient(#1152d4 1px, transparent 1px); background-size: 20px 20px;">
                    </div>
                </div>
            </div>
            <!-- Staff Performance Table -->
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden mb-8">
                <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center">
                    <h4 class="text-sm font-bold uppercase tracking-widest text-slate-900 text-left">Staff Performance
                        Rankings</h4>
                    <button class="text-primary text-xs font-bold hover:underline">View All Technicians</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-slate-50/50">
                                <th
                                    class="px-6 py-3 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-left">
                                    Technician Name</th>
                                <th
                                    class="px-6 py-3 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-left">
                                    Completed Jobs</th>
                                <th
                                    class="px-6 py-3 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-left">
                                    Efficiency</th>
                                <th
                                    class="px-6 py-3 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-left">
                                    Revenue Generated</th>
                                <th
                                    class="px-6 py-3 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-right">
                                    Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="h-8 w-8 rounded-full bg-slate-200 overflow-hidden">
                                            <img alt="Technician"
                                                data-alt="headshot of a technician wearing a grey workshop uniform with a focused expression"
                                                src="https://lh3.googleusercontent.com/aida-public/AB6AXuAVwrn3zha0xfxSRheGgWYTcEG6WOD0srvbDxoiXs_i8o4ZAijGPwvorictljs7iGllrh2wcFOFUrgzGr74nOgasY6ca-pTKF3Nr5dJ1djY41ATHVcw12Eooc8FVgUw2ZaBAHeeYqTd3R4-7Om5ifZu1vR6vjIYI39UX0c4wj6rksiVka6hxhHfta-9pFqj6TrBSIUlWmyf5UzuMiMAh1aF2s2mUXi7d31jYvqQNjsxkgowRbwAtkoujhrxo1hin1dvdet_uNkgO5PM" />
                                        </div>
                                        <div class="text-left">
                                            <p class="text-sm font-bold text-slate-900">Marcus Miller</p>
                                            <p class="text-[10px] text-slate-400">Senior Technician</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm font-medium text-slate-600 text-left">42 Jobs</td>
                                <td class="px-6 py-4 text-left">
                                    <div class="flex items-center gap-2">
                                        <div class="w-16 bg-slate-100 h-1.5 rounded-full overflow-hidden">
                                            <div class="bg-green-500 h-full" style="width: 98%"></div>
                                        </div>
                                        <span class="text-xs font-bold text-green-600">98%</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm font-black text-slate-900 text-left">$34,250.00</td>
                                <td class="px-6 py-4 text-right">
                                    <button
                                        class="p-1.5 hover:bg-slate-100 rounded-md transition-colors text-slate-400">
                                        <span class="material-symbols-outlined text-[18px]"
                                            data-icon="visibility">visibility</span>
                                    </button>
                                </td>
                            </tr>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="h-8 w-8 rounded-full bg-slate-200 overflow-hidden">
                                            <img alt="Technician"
                                                data-alt="professional headshot of a female technician with her hair tied back, wearing a workshop overall"
                                                src="https://lh3.googleusercontent.com/aida-public/AB6AXuBBbVM3aY7k7J8R_z99emn_LJ86namX0oHTWb6kexA4PmKKsAEexU5WVLeOUJb3eUwzm2i-iG7YfGbHLrG0-jAYlc_vMBhsoybLEnD9GQ4Rl3CHM5oyhaOyo1sqbHk8dD7pZh0FLBzzl5wQo4uKImltjW7SWCPyM97vy27FOrqEJs5RbZcCcDUlkLxfCXe-fK6w4XJqP4TsP36TWVivfjFBbLlEkG3ve7TTu2vtYBzkPgvNaQufxFqVHzVvqYrOArritH2Z5GMYW8Up" />
                                        </div>
                                        <div class="text-left">
                                            <p class="text-sm font-bold text-slate-900">Sarah Chen</p>
                                            <p class="text-[10px] text-slate-400">Master Diagnostic Specialist</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm font-medium text-slate-600 text-left">38 Jobs</td>
                                <td class="px-6 py-4 text-left">
                                    <div class="flex items-center gap-2">
                                        <div class="w-16 bg-slate-100 h-1.5 rounded-full overflow-hidden">
                                            <div class="bg-blue-500 h-full" style="width: 92%"></div>
                                        </div>
                                        <span class="text-xs font-bold text-blue-600">92%</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm font-black text-slate-900 text-left">$29,800.00</td>
                                <td class="px-6 py-4 text-right">
                                    <button
                                        class="p-1.5 hover:bg-slate-100 rounded-md transition-colors text-slate-400">
                                        <span class="material-symbols-outlined text-[18px]"
                                            data-icon="visibility">visibility</span>
                                    </button>
                                </td>
                            </tr>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="h-8 w-8 rounded-full bg-slate-200 overflow-hidden">
                                            <img alt="Technician"
                                                data-alt="headshot of a male technician with a friendly smile, short beard, in a dark navy work shirt"
                                                src="https://lh3.googleusercontent.com/aida-public/AB6AXuB4ljfSe3WH4HXahcTYvEWR1PgXJujMH1_Ke_5yg4zaOs1Se3MfwwcEkzo8Vexeo_IhY6bbbJ90kUa1Lpqpy6vofXiKNwMY-BX07ZIt1eKL0ehxOG52q5olaLr9PQPUix90zGYlNYylYIMUSv7toNw5oblVCWEaCuHtiRX6Oln7fPhj5GRZM4d5k6omTcNFzNrQaFSuhtfbH9xMt10IJo0uxLFjJ4vaKGPNTzVN195JjoCCSZs51niSYQvR7H-J4Rh9tj-PVORujdMa" />
                                        </div>
                                        <div class="text-left">
                                            <p class="text-sm font-bold text-slate-900">David Rodriguez</p>
                                            <p class="text-[10px] text-slate-400">Service Technician</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm font-medium text-slate-600 text-left">45 Jobs</td>
                                <td class="px-6 py-4 text-left">
                                    <div class="flex items-center gap-2">
                                        <div class="w-16 bg-slate-100 h-1.5 rounded-full overflow-hidden">
                                            <div class="bg-orange-500 h-full" style="width: 84%"></div>
                                        </div>
                                        <span class="text-xs font-bold text-orange-600">84%</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm font-black text-slate-900 text-left">$21,400.00</td>
                                <td class="px-6 py-4 text-right">
                                    <button
                                        class="p-1.5 hover:bg-slate-100 rounded-md transition-colors text-slate-400">
                                        <span class="material-symbols-outlined text-[18px]"
                                            data-icon="visibility">visibility</span>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</body>

</html>