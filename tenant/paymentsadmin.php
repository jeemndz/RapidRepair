<!DOCTYPE html>
<html class="light" lang="en">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Payment Management | Cobalt Precision</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&amp;display=swap"
        rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
        rel="stylesheet">
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "on-tertiary-fixed-variant": "#9a3412",
                        "surface": "#f6f6f8",
                        "surface-dim": "#d9d9e4",
                        "on-tertiary-container": "#92400e",
                        "primary-fixed-dim": "#bfdbfe",
                        "on-error": "#ffffff",
                        "surface-container-highest": "#ffffff",
                        "on-secondary-container": "#1e293b",
                        "on-secondary": "#ffffff",
                        "on-surface": "#0f172a",
                        "surface-container": "#ffffff",
                        "secondary-fixed-dim": "#cbd5e1",
                        "on-tertiary": "#ffffff",
                        "on-primary-fixed-variant": "#1d4ed8",
                        "error-container": "#fee2e2",
                        "tertiary": "#f59e0b",
                        "surface-container-low": "#ffffff",
                        "primary-fixed": "#dbeafe",
                        "primary-container": "#eef2ff",
                        "tertiary-container": "#fef3c7",
                        "on-secondary-fixed": "#0f172a",
                        "on-primary-container": "#1152d4",
                        "on-primary-fixed": "#1e3a8a",
                        "on-error-container": "#991b1b",
                        "primary": "#1152d4",
                        "tertiary-fixed-dim": "#fed7aa",
                        "background": "#f6f6f8",
                        "tertiary-fixed": "#ffedd5",
                        "outline": "#e2e8f0",
                        "outline-variant": "#cbd5e1",
                        "secondary-container": "#f1f5f9",
                        "on-tertiary-fixed": "#7c2d12",
                        "inverse-surface": "#1e293b",
                        "error": "#ef4444",
                        "surface-tint": "#1152d4",
                        "surface-bright": "#ffffff",
                        "on-secondary-fixed-variant": "#334155",
                        "secondary-fixed": "#e2e8f0",
                        "on-primary": "#ffffff",
                        "inverse-primary": "#b4c5ff",
                        "on-background": "#0f172a",
                        "surface-container-high": "#ffffff",
                        "surface-container-lowest": "#ffffff",
                        "inverse-on-surface": "#f8fafc",
                        "secondary": "#475569",
                        "on-surface-variant": "#64748b",
                        "surface-variant": "#f1f5f9"
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
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-background text-on-surface antialiased">
    <!-- SideNavBar Shell -->
    <aside
        class="fixed inset-y-0 left-0 w-64 flex flex-col border-r border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-y-auto z-50">
        <div class="p-6 flex-1">
            <div class="flex items-center gap-3 mb-8">
                <div class="bg-primary rounded-lg p-2 text-white">
                    <span class="material-symbols-outlined" style="">directions_car</span>
                </div>
                <div>
                    <h1 class="text-lg font-bold leading-none" style="">AutoFix Pro</h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1" style="">Repair Management</p>
                </div>
            </div>
            <nav class="space-y-1">
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="dashboardadmin.php" style="">
                    <span class="material-symbols-outlined text-[22px]" style="">dashboard</span>
                    Dashboard
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="repairjobsadmin.php" style="">
                    <span class="material-symbols-outlined text-[22px]" style="">build</span>
                    Repair Jobs
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="vehicleadmin.php" style="">
                    <span class="material-symbols-outlined text-[22px]" style="">directions_car</span>
                    Vehicles
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="appointmentadmin.php" style="">
                    <span class="material-symbols-outlined text-[22px]" style="">event</span>
                    Appointments
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="reportsadmin.php" style="">
                    <span class="material-symbols-outlined text-[22px]" style="">description</span>
                    Reports
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="inventoryadmin.php" style="">
                    <span class="material-symbols-outlined text-[22px]" style="">inventory_2</span>
                    Inventory
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="customeradmin.php" style="">
                    <span class="material-symbols-outlined text-[22px]" style="">group</span>
                    Customers
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-primary/10 text-primary font-medium"
                    href="paymentsadmin.php" style="">
                    <span class="material-symbols-outlined text-[22px]" style="">payments</span>
                    Payments
                </a>
                <div class="pt-4 mt-4 border-t border-slate-100">
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                        href="settingsadmin.php" style="">
                        <span class="material-symbols-outlined text-[22px]" style="">settings</span>
                        Settings
                    </a>
                </div>
            </nav>
        </div>
        <div class="mt-auto w-full p-4 border-t border-slate-200 dark:border-slate-800">
            <div class="flex items-center gap-3">
                <div
                    class="size-10 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center overflow-hidden">
                    <img alt="Admin Profile" class="w-full h-full object-cover"
                        src="https://lh3.googleusercontent.com/aida-public/AB6AXuDeh_igjzq55wP-MQUqlN5a7g7ERzT91RAZllys2xTPdmr_K6ugTc7NEPOG48E87bvkhiEKuMOE9TZ0njKOCLQ7Nhccix3HVxsYdR2tXeyTCkjam7s1q8ngQOzslzdGRLROqouBtkGpnSewuAyIscdu673vBatOqI9TKHP1RCzarhxH8GqVYpWDnccgDrczUMroOqof3VFA7U9HLzMcDyURIrkC9dU2KtSkusqfbOvLaUs_zR14qlpZVSgASdGK8sw1SCeDf4A38q-8"
                        style="">
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold truncate" style="">Marcus Smith</p>
                    <p class="text-xs text-slate-500 truncate" style="">Shop Manager</p>
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
                <h2 class="text-lg font-black text-slate-900 dark:white tracking-tight">Payments Management</h2>
                <div class="relative hidden lg:block">
                    <span
                        class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">search</span>
                    <input
                        class="bg-surface-variant border-none rounded-lg pl-10 pr-4 py-1.5 text-sm w-64 focus:ring-2 focus:ring-primary/20"
                        placeholder="Search payments..." type="text">
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
                        src="https://lh3.googleusercontent.com/aida-public/AB6AXuC_w4TZYv-DoCr0hxBNhV2Z-nUsRKiJWSSjgi__Y4oVCvZsEnAXH-GvsZk4qUV8VfyOd_rN5mqWnBeNlMb7An_00pBDPbF7FGZDqw2HhZ4MbeNkgRRsmuE6r3t2yOO4P5sHcWAMkVgXaheA3Z2LKA0Fo_mIUP0qh9KRyragtZ_zvLR-U7pm-kWc645Yi3rN0Mm0P9km9Kt3Fp4fKCU5i33aRJsonLoG5k45EuFpDDTP2CbZiarn81pTDjiPcRHLtpdJg1O47dGsJUD2">
                </div>
            </div>
        </header>
        <div class="p-8">
        <!-- Header Section -->
        <div class="flex items-end justify-between mb-8">
            <div>
                <h2 class="text-3xl font-black text-on-surface tracking-tight" style="">Payments &amp; Invoices</h2>
                <p class="text-on-surface-variant mt-1" style="">Comprehensive overview of shop revenue and billing
                    cycles.</p>
            </div>
            <div class="flex space-x-3">
                <button
                    class="flex items-center px-4 py-2 bg-white border border-outline text-secondary text-sm font-bold rounded-lg hover:bg-surface transition-all"
                    style="">
                    <span class="material-symbols-outlined text-sm mr-2" data-icon="file_download"
                        style="">file_download</span>
                    Export Financial Report
                </button>
                <button
                    class="flex items-center px-4 py-2 bg-white border border-outline text-secondary text-sm font-bold rounded-lg hover:bg-surface transition-all"
                    style="">
                    <span class="material-symbols-outlined text-sm mr-2" data-icon="date_range"
                        style="">date_range</span>
                    May 2024
                </button>
            </div>
        </div>
        <!-- Metric Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <div class="p-2 bg-primary-container rounded-lg">
                        <span class="material-symbols-outlined text-primary" data-icon="account_balance_wallet"
                            style="font-variation-settings: &quot;FILL&quot; 1;">account_balance_wallet</span>
                    </div>
                    <span class="text-xs font-semibold text-green-600 bg-green-50 px-2 py-1 rounded-full"
                        style="">+12.5%</span>
                </div>
                <p class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider" style="">Total Revenue
                </p>
                <h3 class="text-2xl font-bold text-on-surface mt-1" style="">$142,580.00</h3>
            </div>
            <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <div class="p-2 bg-tertiary-container rounded-lg">
                        <span class="material-symbols-outlined text-tertiary" data-icon="pending_actions"
                            style="font-variation-settings: &quot;FILL&quot; 1;">pending_actions</span>
                    </div>
                </div>
                <p class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider" style="">Pending
                    Invoices</p>
                <h3 class="text-2xl font-bold text-on-surface mt-1" style="">18</h3>
            </div>
            <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <div class="p-2 bg-blue-50 rounded-lg">
                        <span class="material-symbols-outlined text-primary" data-icon="event_available"
                            style="font-variation-settings: &quot;FILL&quot; 1;">event_available</span>
                    </div>
                    <span class="text-xs font-semibold text-green-600 bg-green-50 px-2 py-1 rounded-full"
                        style="">+4.2%</span>
                </div>
                <p class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider" style="">Paid This
                    Week</p>
                <h3 class="text-2xl font-bold text-on-surface mt-1" style="">$12,450.00</h3>
            </div>
            <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <div class="p-2 bg-error-container rounded-lg">
                        <span class="material-symbols-outlined text-error" data-icon="warning"
                            style="font-variation-settings: &quot;FILL&quot; 1;">warning</span>
                    </div>
                </div>
                <p class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider" style="">Overdue
                    Payments</p>
                <h3 class="text-2xl font-bold text-on-surface mt-1" style="">4</h3>
            </div>
        </div>
        <!-- Active Invoices Section -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-8">
            <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                <h4 class="text-lg font-bold text-on-surface" style="">Active Invoices</h4>
                <span
                    class="px-2 py-1 bg-primary/10 text-primary text-[10px] font-bold rounded uppercase tracking-wider"
                    style="">Action Required</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-surface">
                        <tr>
                            <th class="px-6 py-4 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest"
                                style="">Invoice ID</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest"
                                style="">Customer</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest"
                                style="">Date</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest"
                                style="">Amount</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest"
                                style="">Status</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest text-right"
                                style="">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4 text-sm font-bold text-on-surface" style="">#INV-2055</td>
                            <td class="px-6 py-4" style="">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center text-[10px] font-bold text-primary mr-3"
                                        style="">EB</div>
                                    <span class="text-sm font-medium text-on-surface" style="">Elena Brooks</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-on-surface-variant" style="">May 14, 2024</td>
                            <td class="px-6 py-4 text-sm font-bold text-on-surface" style="">$1,420.00</td>
                            <td class="px-6 py-4" style="">
                                <span
                                    class="px-3 py-1 text-[11px] font-bold text-tertiary-fixed-variant bg-tertiary-container rounded-full"
                                    style="">Draft</span>
                            </td>
                            <td class="px-6 py-4 text-right" style="">
                                <div class="flex justify-end space-x-2">
                                    <button
                                        class="px-3 py-1.5 text-[11px] font-bold text-secondary border border-outline rounded-lg hover:bg-surface transition-colors"
                                        style="">Review</button>
                                    <button
                                        class="px-3 py-1.5 text-[11px] font-bold text-white bg-primary rounded-lg hover:bg-primary/90 transition-colors shadow-sm shadow-primary/20"
                                        style="">Accept</button>
                                </div>
                            </td>
                        </tr>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4 text-sm font-bold text-on-surface" style="">#INV-2054</td>
                            <td class="px-6 py-4" style="">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-[10px] font-bold text-slate-500 mr-3"
                                        style="">DT</div>
                                    <span class="text-sm font-medium text-on-surface" style="">David Thompson</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-on-surface-variant" style="">May 14, 2024</td>
                            <td class="px-6 py-4 text-sm font-bold text-on-surface" style="">$315.75</td>
                            <td class="px-6 py-4" style="">
                                <span class="px-3 py-1 text-[11px] font-bold text-blue-700 bg-blue-50 rounded-full"
                                    style="">Pending approval</span>
                            </td>
                            <td class="px-6 py-4 text-right" style="">
                                <div class="flex justify-end space-x-2">
                                    <button
                                        class="px-3 py-1.5 text-[11px] font-bold text-secondary border border-outline rounded-lg hover:bg-surface transition-colors"
                                        style="">Review</button>
                                    <button
                                        class="px-3 py-1.5 text-[11px] font-bold text-white bg-primary rounded-lg hover:bg-primary/90 transition-colors shadow-sm shadow-primary/20"
                                        style="">Accept</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- Asymmetric Layout: Chart & Activity Feed -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
            <!-- Revenue Chart Card -->
            <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm p-6 overflow-hidden">
                <div class="flex items-center justify-between mb-8">
                    <h4 class="text-lg font-bold text-on-surface" style="">Revenue Over Time</h4>
                    <select class="text-xs font-bold bg-surface-variant border-none rounded-lg focus:ring-primary/20">
                        <option>Last 6 Months</option>
                        <option>Last 12 Months</option>
                    </select>
                </div>
                <!-- Visual Placeholder for Line Chart -->
                <div class="relative h-64 w-full mt-4 flex items-end justify-between space-x-2">
                    <div class="absolute inset-0 bg-gradient-to-t from-primary/5 to-transparent rounded-lg"></div>
                    <div class="w-full h-[60%] border-b-2 border-primary relative">
                        <svg class="absolute bottom-0 w-full h-full" preserveAspectRatio="none" viewBox="0 0 400 100">
                            <path d="M0,80 L50,60 L100,75 L150,40 L200,55 L250,20 L300,35 L350,10 L400,15" fill="none"
                                stroke="#1152d4" stroke-linecap="round" stroke-linejoin="round" stroke-width="3"></path>
                        </svg>
                    </div>
                </div>
                <div
                    class="flex justify-between mt-4 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest px-2">
                    <span class="" style="">Jan</span><span class="" style="">Feb</span><span class=""
                        style="">Mar</span><span class="" style="">Apr</span><span class="" style="">May</span><span
                        class="" style="">Jun</span>
                </div>
            </div>
            <!-- Recent Activity Sidebar -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm flex flex-col">
                <div class="p-6 border-b border-slate-100">
                    <h4 class="text-lg font-bold text-on-surface" style="">Recent Billing Activity</h4>
                </div>
                <div class="flex-1 overflow-y-auto p-6 space-y-6">
                    <div class="flex space-x-4">
                        <div
                            class="mt-1 w-2 h-2 rounded-full bg-green-500 shrink-0 shadow-[0_0_8px_rgba(34,197,94,0.4)]">
                        </div>
                        <div>
                            <p class="text-sm font-bold text-on-surface leading-none" style="">Payment Received</p>
                            <p class="text-xs text-on-surface-variant mt-1" style="">Invoice #INV-2048 from Robert
                                Miller</p>
                            <span class="text-[10px] font-semibold text-slate-400 mt-2 block uppercase tracking-tight"
                                style="">14 mins ago</span>
                        </div>
                    </div>
                    <div class="flex space-x-4">
                        <div class="mt-1 w-2 h-2 rounded-full bg-blue-500 shrink-0"></div>
                        <div>
                            <p class="text-sm font-bold text-on-surface leading-none" style="">New Invoice Generated</p>
                            <p class="text-xs text-on-surface-variant mt-1" style="">Invoice #INV-2051 for Brake Repair
                            </p>
                            <span class="text-[10px] font-semibold text-slate-400 mt-2 block uppercase tracking-tight"
                                style="">2 hours ago</span>
                        </div>
                    </div>
                    <div class="flex space-x-4">
                        <div class="mt-1 w-2 h-2 rounded-full bg-error shrink-0 shadow-[0_0_8px_rgba(239,68,68,0.3)]">
                        </div>
                        <div>
                            <p class="text-sm font-bold text-on-surface leading-none" style="">Invoice Overdue</p>
                            <p class="text-xs text-on-surface-variant mt-1" style="">Invoice #INV-1982 - Sarah Jenkins
                            </p>
                            <span class="text-[10px] font-semibold text-slate-400 mt-2 block uppercase tracking-tight"
                                style="">Yesterday</span>
                        </div>
                    </div>
                    <div class="flex space-x-4">
                        <div class="mt-1 w-2 h-2 rounded-full bg-green-500 shrink-0"></div>
                        <div>
                            <p class="text-sm font-bold text-on-surface leading-none" style="">Payment Received</p>
                            <p class="text-xs text-on-surface-variant mt-1" style="">Invoice #INV-2045 from Apex
                                Logistics</p>
                            <span class="text-[10px] font-semibold text-slate-400 mt-2 block uppercase tracking-tight"
                                style="">2 days ago</span>
                        </div>
                    </div>
                </div>
                <button
                    class="m-6 p-2 text-xs font-bold text-primary bg-primary-container rounded-lg hover:bg-primary hover:text-white transition-colors"
                    style="">
                    View All Activity
                </button>
            </div>
        </div>
        <!-- Transaction Table Section -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                <h4 class="text-lg font-bold text-on-surface" style="">Recent Transactions</h4>
                <div class="flex items-center space-x-2">
                    <span class="text-xs text-on-surface-variant font-medium" style="">Filter by:</span>
                    <button class="px-3 py-1 bg-surface-variant text-[11px] font-bold rounded-full" style="">All
                        Status</button>
                    <button
                        class="px-3 py-1 text-[11px] font-bold text-on-surface-variant hover:bg-surface rounded-full"
                        style="">Paid</button>
                    <button
                        class="px-3 py-1 text-[11px] font-bold text-on-surface-variant hover:bg-surface rounded-full"
                        style="">Pending</button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-surface">
                        <tr>
                            <th class="px-6 py-4 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest"
                                style="">Invoice ID</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest"
                                style="">Customer</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest"
                                style="">Service Type</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest text-right"
                                style="">Amount</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest"
                                style="">Status</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest"
                                style="">Date</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest"
                                style=""></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4 text-sm font-bold text-on-surface" style="">#INV-2051</td>
                            <td class="px-6 py-4" style="">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-[10px] font-bold text-slate-500 mr-3"
                                        style="">JM</div>
                                    <span class="text-sm font-medium text-on-surface" style="">John Macallan</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-on-surface-variant" style="">Full Engine Tune-up</td>
                            <td class="px-6 py-4 text-sm font-bold text-on-surface text-right" style="">$845.00</td>
                            <td class="px-6 py-4" style="">
                                <span class="px-3 py-1 text-[11px] font-bold text-blue-700 bg-blue-50 rounded-full"
                                    style="">Pending</span>
                            </td>
                            <td class="px-6 py-4 text-sm text-on-surface-variant" style="">May 12, 2024</td>
                            <td class="px-6 py-4 text-right" style="">
                                <button class="text-slate-400 hover:text-primary" style=""><span
                                        class="material-symbols-outlined text-xl" data-icon="more_horiz"
                                        style="">more_horiz</span></button>
                            </td>
                        </tr>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4 text-sm font-bold text-on-surface" style="">#INV-2048</td>
                            <td class="px-6 py-4" style="">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-[10px] font-bold text-slate-500 mr-3"
                                        style="">RM</div>
                                    <span class="text-sm font-medium text-on-surface" style="">Robert Miller</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-on-surface-variant" style="">Oil Change &amp; Filter</td>
                            <td class="px-6 py-4 text-sm font-bold text-on-surface text-right" style="">$125.50</td>
                            <td class="px-6 py-4" style="">
                                <span class="px-3 py-1 text-[11px] font-bold text-green-700 bg-green-50 rounded-full"
                                    style="">Paid</span>
                            </td>
                            <td class="px-6 py-4 text-sm text-on-surface-variant" style="">May 11, 2024</td>
                            <td class="px-6 py-4 text-right" style="">
                                <button class="text-slate-400 hover:text-primary" style=""><span
                                        class="material-symbols-outlined text-xl" data-icon="more_horiz"
                                        style="">more_horiz</span></button>
                            </td>
                        </tr>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4 text-sm font-bold text-on-surface" style="">#INV-2042</td>
                            <td class="px-6 py-4" style="">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-[10px] font-bold text-slate-500 mr-3"
                                        style="">AL</div>
                                    <span class="text-sm font-medium text-on-surface" style="">Apex Logistics</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-on-surface-variant" style="">Fleet Maintenance (12 units)
                            </td>
                            <td class="px-6 py-4 text-sm font-bold text-on-surface text-right" style="">$4,200.00</td>
                            <td class="px-6 py-4" style="">
                                <span class="px-3 py-1 text-[11px] font-bold text-green-700 bg-green-50 rounded-full"
                                    style="">Paid</span>
                            </td>
                            <td class="px-6 py-4 text-sm text-on-surface-variant" style="">May 10, 2024</td>
                            <td class="px-6 py-4 text-right" style="">
                                <button class="text-slate-400 hover:text-primary" style=""><span
                                        class="material-symbols-outlined text-xl" data-icon="more_horiz"
                                        style="">more_horiz</span></button>
                            </td>
                        </tr>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4 text-sm font-bold text-on-surface" style="">#INV-1982</td>
                            <td class="px-6 py-4" style="">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-[10px] font-bold text-slate-500 mr-3"
                                        style="">SJ</div>
                                    <span class="text-sm font-medium text-on-surface" style="">Sarah Jenkins</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-on-surface-variant" style="">Transmission Repair</td>
                            <td class="px-6 py-4 text-sm font-bold text-on-surface text-right" style="">$2,150.00</td>
                            <td class="px-6 py-4" style="">
                                <span class="px-3 py-1 text-[11px] font-bold text-error bg-error-container rounded-full"
                                    style="">Overdue</span>
                            </td>
                            <td class="px-6 py-4 text-sm text-on-surface-variant" style="">April 28, 2024</td>
                            <td class="px-6 py-4 text-right" style="">
                                <button class="text-slate-400 hover:text-primary" style=""><span
                                        class="material-symbols-outlined text-xl" data-icon="more_horiz"
                                        style="">more_horiz</span></button>
                            </td>
                        </tr>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4 text-sm font-bold text-on-surface" style="">#INV-2040</td>
                            <td class="px-6 py-4" style="">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-[10px] font-bold text-slate-500 mr-3"
                                        style="">TP</div>
                                    <span class="text-sm font-medium text-on-surface" style="">Thomas Prince</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-on-surface-variant" style="">Brake Pad Replacement</td>
                            <td class="px-6 py-4 text-sm font-bold text-on-surface text-right" style="">$240.00</td>
                            <td class="px-6 py-4" style="">
                                <span class="px-3 py-1 text-[11px] font-bold text-green-700 bg-green-50 rounded-full"
                                    style="">Paid</span>
                            </td>
                            <td class="px-6 py-4 text-sm text-on-surface-variant" style="">May 09, 2024</td>
                            <td class="px-6 py-4 text-right" style="">
                                <button class="text-slate-400 hover:text-primary" style=""><span
                                        class="material-symbols-outlined text-xl" data-icon="more_horiz"
                                        style="">more_horiz</span></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-slate-100 flex items-center justify-between">
                <p class="text-xs text-on-surface-variant font-medium" style="">Showing 5 of 1,248 transactions</p>
                <div class="flex space-x-1">
                    <button
                        class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-surface-variant text-on-surface-variant transition-colors"
                        style="">
                        <span class="material-symbols-outlined text-sm" data-icon="chevron_left"
                            style="">chevron_left</span>
                    </button>
                    <button
                        class="w-8 h-8 flex items-center justify-center rounded-lg bg-primary text-white text-xs font-bold"
                        style="">1</button>
                    <button
                        class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-surface-variant text-on-surface-variant text-xs font-bold transition-colors"
                        style="">2</button>
                    <button
                        class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-surface-variant text-on-surface-variant text-xs font-bold transition-colors"
                        style="">3</button>
                    <button
                        class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-surface-variant text-on-surface-variant transition-colors"
                        style="">
                        <span class="material-symbols-outlined text-sm" data-icon="chevron_right"
                            style="">chevron_right</span>
                    </button>
                </div>
            </div>
        </div>
    </main>
</body>

</html>