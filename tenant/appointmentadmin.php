<!DOCTYPE html>

<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Cobalt Auto | Appointment Management</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&amp;display=swap"
        rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
        rel="stylesheet" />
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#1152d4",
                        "on-secondary-fixed": "#0f172a",
                        "on-primary": "#ffffff",
                        "background": "#f6f6f8",
                        "on-primary-fixed-variant": "#1d4ed8",
                        "inverse-surface": "#1e293b",
                        "on-surface-variant": "#64748b",
                        "inverse-on-surface": "#f8fafc",
                        "on-error": "#ffffff",
                        "primary-fixed": "#dbeafe",
                        "secondary": "#475569",
                        "tertiary-fixed-dim": "#fed7aa",
                        "surface-container-lowest": "#ffffff",
                        "on-tertiary-container": "#92400e",
                        "tertiary": "#f59e0b",
                        "on-secondary-container": "#1e293b",
                        "on-error-container": "#991b1b",
                        "on-secondary-fixed-variant": "#334155",
                        "surface-tint": "#1152d4",
                        "surface-container": "#ffffff",
                        "secondary-fixed-dim": "#cbd5e1",
                        "primary-container": "#eef2ff",
                        "error-container": "#fee2e2",
                        "surface-container-high": "#ffffff",
                        "primary-fixed-dim": "#bfdbfe",
                        "outline": "#e2e8f0",
                        "surface": "#f6f6f8",
                        "surface-dim": "#d9d9e4",
                        "on-primary-fixed": "#1e3a8a",
                        "surface-variant": "#f1f5f9",
                        "inverse-primary": "#b4c5ff",
                        "outline-variant": "#cbd5e1",
                        "on-background": "#0f172a",
                        "secondary-fixed": "#e2e8f0",
                        "surface-container-low": "#ffffff",
                        "tertiary-container": "#fef3c7",
                        "on-secondary": "#ffffff",
                        "error": "#ef4444",
                        "on-tertiary": "#ffffff",
                        "secondary-container": "#f1f5f9",
                        "on-primary-container": "#1152d4",
                        "surface-container-highest": "#ffffff",
                        "on-tertiary-fixed": "#7c2d12",
                        "surface-bright": "#ffffff",
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
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
    </style>
</head>

<body class="bg-background text-on-background antialiased flex">
    <!-- Sidebar Navigation -->
    <aside
        class="w-64 flex-shrink-0 border-r border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 h-screen sticky top-0 flex flex-col overflow-y-auto">
        <div class="p-6 flex-1">
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
                    Dashboard
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    href="repairjobsadmin.php">
                    <span class="material-symbols-outlined text-[22px]">build</span>
                    Repair Jobs
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    href="vehicleadmin.php">
                    <span class="material-symbols-outlined text-[22px]">directions_car</span>
                    Vehicles
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-primary/10 text-primary font-medium"
                    href="appointmentadmin.php">
                    <span class="material-symbols-outlined text-[22px]">event</span>
                    Appointments
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    href="reportsadmin.php">
                    <span class="material-symbols-outlined text-[22px]">description</span>
                    Reports
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    href="inventoryadmin.php">
                    <span class="material-symbols-outlined text-[22px]">inventory_2</span>
                    Inventory
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    href="customeradmin.php">
                    <span class="material-symbols-outlined text-[22px]">group</span>
                    Customers
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    href="paymentsadmin.php">
                    <span class="material-symbols-outlined text-[22px]">payments</span>
                    Payments
                </a>
                <div class="pt-4 mt-4 border-t border-slate-100 dark:border-slate-800">
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                        href="settingsadmin.php">
                        <span class="material-symbols-outlined text-[22px]">settings</span>
                        Settings
                    </a>
                </div>
            </nav>
        </div>
        <div class="p-4 border-t border-slate-200 dark:border-slate-800">
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
    <main class="flex-1 min-h-screen">
        <!-- Top Nav Bar -->
        <header
            class="sticky top-0 z-40 w-full border-b border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md flex items-center justify-between px-8 h-16">
            <div class="flex items-center gap-6">
                <h2 class="text-lg font-black text-slate-900 dark:white tracking-tight">Appointment Management</h2>
                <div class="relative hidden lg:block">
                    <span
                        class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">search</span>
                    <input
                        class="bg-surface-variant border-none rounded-lg pl-10 pr-4 py-1.5 text-sm w-64 focus:ring-2 focus:ring-primary/20"
                        placeholder="Search appointments..." type="text" />
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
        <div class="p-8 space-y-8">
            <!-- 1. Top Metrics Bar -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Today's Load</span>
                        <div class="p-1.5 bg-primary-container rounded-lg">
                            <span class="material-symbols-outlined text-primary text-sm">precision_manufacturing</span>
                        </div>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl font-black text-on-background">14/18</span>
                        <span
                            class="text-[10px] font-bold text-green-600 bg-green-100 px-1.5 py-0.5 rounded-full">78%</span>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">4 slots remaining today</p>
                </div>
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Next Available</span>
                        <div class="p-1.5 bg-tertiary-container rounded-lg">
                            <span class="material-symbols-outlined text-tertiary text-sm">schedule</span>
                        </div>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl font-black text-on-background">Tomorrow</span>
                        <span class="text-xs font-semibold text-slate-500">09:30 AM</span>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">Bay 3 - Express Lane</p>
                </div>
                <div class="bg-white p-5 rounded-xl border-2 border-primary/20 shadow-sm relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-16 h-16 bg-primary/5 -mr-8 -mt-8 rounded-full"></div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold text-primary uppercase tracking-wider">Pending Bookings</span>
                        <div class="p-1.5 bg-primary rounded-lg">
                            <span class="material-symbols-outlined text-white text-sm">pending_actions</span>
                        </div>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl font-black text-on-background">08</span>
                        <span
                            class="text-[10px] font-bold text-primary bg-primary-container px-1.5 py-0.5 rounded-full">Needs
                            Action</span>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">3 critical repair requests</p>
                </div>
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Weekly Capacity</span>
                        <div class="p-1.5 bg-secondary-container rounded-lg">
                            <span class="material-symbols-outlined text-secondary text-sm">bar_chart</span>
                        </div>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl font-black text-on-background">92%</span>
                        <span class="text-[10px] font-bold text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded-full">High
                            Load</span>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">Average 16 orders / day</p>
                </div>
            </div>
            <div class="flex flex-col lg:flex-row gap-8">
                <div class="flex-1 space-y-8 min-w-0">
                    <!-- 2. Weekly Repair Schedule -->
                    <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                            <div>
                                <h3 class="text-lg font-bold text-slate-900 tracking-tight">Weekly Repair Schedule</h3>
                                <p class="text-xs text-slate-500 font-medium">October 21 - October 25, 2024</p>
                            </div>
                            <div class="flex bg-white rounded-lg border border-slate-200 p-1">
                                <button
                                    class="px-3 py-1 text-xs font-bold text-primary bg-primary-container rounded-md">Week</button>
                                <button
                                    class="px-3 py-1 text-xs font-semibold text-slate-500 hover:text-primary transition-colors">Day</button>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-slate-100">
                                        <th
                                            class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-widest bg-slate-50/30 w-32 text-center">
                                            Technician</th>
                                        <th
                                            class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-widest bg-slate-50/30">
                                            Mon 21</th>
                                        <th
                                            class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-widest bg-slate-50/30">
                                            Tue 22</th>
                                        <th
                                            class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-widest bg-slate-50/30">
                                            Wed 23</th>
                                        <th
                                            class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-widest bg-slate-50/30">
                                            Thu 24</th>
                                        <th
                                            class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-widest bg-slate-50/30">
                                            Fri 25</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="border-b border-slate-50">
                                        <td class="p-4 border-r border-slate-50">
                                            <div class="text-center">
                                                <p class="text-sm font-bold text-slate-900 leading-tight">Marcus V.</p>
                                                <p class="text-[10px] text-primary font-bold">L3 Master</p>
                                            </div>
                                        </td>
                                        <td class="p-2">
                                            <div
                                                class="bg-primary/10 border-l-4 border-primary p-2 rounded text-[11px] font-medium text-primary leading-tight">
                                                Transmission<br /><span class="text-[9px] opacity-70">R. Miller - BMW
                                                    X5</span></div>
                                        </td>
                                        <td class="p-2">
                                            <div
                                                class="bg-primary/10 border-l-4 border-primary p-2 rounded text-[11px] font-medium text-primary leading-tight">
                                                Engine Diag<br /><span class="text-[9px] opacity-70">J. Smith - Audi
                                                    A4</span></div>
                                        </td>
                                        <td class="p-2">
                                            <div
                                                class="border border-dashed border-slate-200 p-2 rounded text-[11px] text-center text-slate-400 font-bold uppercase tracking-tighter">
                                                Available</div>
                                        </td>
                                        <td class="p-2">
                                            <div
                                                class="bg-primary/10 border-l-4 border-primary p-2 rounded text-[11px] font-medium text-primary leading-tight">
                                                Brake Swap<br /><span class="text-[9px] opacity-70">S. Lee - Tesla
                                                    M3</span></div>
                                        </td>
                                        <td class="p-2">
                                            <div
                                                class="bg-primary/10 border-l-4 border-primary p-2 rounded text-[11px] font-medium text-primary leading-tight">
                                                Transmission<br /><span class="text-[9px] opacity-70">Cont. Work</span>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr class="border-b border-slate-50">
                                        <td class="p-4 border-r border-slate-50">
                                            <div class="text-center">
                                                <p class="text-sm font-bold text-slate-900 leading-tight">Sarah K.</p>
                                                <p class="text-[10px] text-tertiary font-bold">Electrical</p>
                                            </div>
                                        </td>
                                        <td class="p-2">
                                            <div
                                                class="border border-dashed border-slate-200 p-2 rounded text-[11px] text-center text-slate-400 font-bold uppercase tracking-tighter">
                                                Available</div>
                                        </td>
                                        <td class="p-2">
                                            <div
                                                class="bg-primary/10 border-l-4 border-primary p-2 rounded text-[11px] font-medium text-primary leading-tight">
                                                Wiring Fault<br /><span class="text-[9px] opacity-70">T. Chen - Ford
                                                    F150</span></div>
                                        </td>
                                        <td class="p-2">
                                            <div
                                                class="bg-primary/10 border-l-4 border-primary p-2 rounded text-[11px] font-medium text-primary leading-tight">
                                                ECU Flash<br /><span class="text-[9px] opacity-70">P. Davis - VW
                                                    Golf</span></div>
                                        </td>
                                        <td class="p-2">
                                            <div
                                                class="border border-dashed border-slate-200 p-2 rounded text-[11px] text-center text-slate-400 font-bold uppercase tracking-tighter">
                                                Available</div>
                                        </td>
                                        <td class="p-2">
                                            <div
                                                class="bg-primary/10 border-l-4 border-primary p-2 rounded text-[11px] font-medium text-primary leading-tight">
                                                Sensor Calib<br /><span class="text-[9px] opacity-70">L. Grey - Merc
                                                    S500</span></div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="p-4 border-r border-slate-50">
                                            <div class="text-center">
                                                <p class="text-sm font-bold text-slate-900 leading-tight">Damien T.</p>
                                                <p class="text-[10px] text-slate-500 font-bold">Service Tech</p>
                                            </div>
                                        </td>
                                        <td class="p-2">
                                            <div
                                                class="bg-primary/10 border-l-4 border-primary p-2 rounded text-[11px] font-medium text-primary leading-tight">
                                                Oil &amp; Filters<br /><span class="text-[9px] opacity-70">M. Reed -
                                                    Jeep GC</span></div>
                                        </td>
                                        <td class="p-2">
                                            <div
                                                class="bg-primary/10 border-l-4 border-primary p-2 rounded text-[11px] font-medium text-primary leading-tight">
                                                Tire Rotation<br /><span class="text-[9px] opacity-70">D. Wu - Honda
                                                    CRV</span></div>
                                        </td>
                                        <td class="p-2">
                                            <div
                                                class="bg-primary/10 border-l-4 border-primary p-2 rounded text-[11px] font-medium text-primary leading-tight">
                                                Annual Svc<br /><span class="text-[9px] opacity-70">A. Bell - Toyota
                                                    R4</span></div>
                                        </td>
                                        <td class="p-2">
                                            <div
                                                class="bg-primary/10 border-l-4 border-primary p-2 rounded text-[11px] font-medium text-primary leading-tight">
                                                Brake Pads<br /><span class="text-[9px] opacity-70">J. Doe - Nissan
                                                    Z</span></div>
                                        </td>
                                        <td class="p-2">
                                            <div
                                                class="border border-dashed border-slate-200 p-2 rounded text-[11px] text-center text-slate-400 font-bold uppercase tracking-tighter">
                                                Available</div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                    <!-- 3. Pending Customer Bookings Table -->
                    <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <h3 class="text-lg font-bold text-slate-900 tracking-tight">Pending Customer Bookings
                                </h3>
                                <span class="bg-primary text-white text-[10px] font-black px-2 py-0.5 rounded-full">NEW
                                    REQUESTS</span>
                            </div>
                            <button class="text-sm font-bold text-primary hover:underline">View All History</button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead class="bg-slate-50/50">
                                    <tr>
                                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                            Customer Name</th>
                                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                            Vehicle (Y/M/M)</th>
                                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                            Requested Service</th>
                                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                            Date / Time</th>
                                        <th
                                            class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-right">
                                            Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <div
                                                    class="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center text-primary font-bold text-xs">
                                                    EL</div>
                                                <span class="text-sm font-semibold text-slate-900">Eleanor Lewis</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-slate-600">2021 Porsche Macan</td>
                                        <td class="px-6 py-4">
                                            <span
                                                class="bg-error-container text-error text-[10px] font-bold px-2 py-1 rounded-full uppercase tracking-tighter">Brake
                                                Fluid Leak</span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <p class="text-sm font-bold text-slate-900">Oct 24, 2024</p>
                                            <p class="text-xs text-slate-500">08:00 AM</p>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <button
                                                    class="px-3 py-1.5 border border-slate-200 text-slate-600 text-xs font-bold rounded hover:bg-slate-50 transition-all">Review</button>
                                                <button
                                                    class="px-3 py-1.5 bg-primary text-white text-xs font-bold rounded hover:bg-primary-hover shadow-sm transition-all">Accept</button>
                                                <button
                                                    class="p-1.5 text-slate-400 hover:text-error transition-colors"><span
                                                        class="material-symbols-outlined text-sm">close</span></button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <div
                                                    class="h-8 w-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 font-bold text-xs">
                                                    RJ</div>
                                                <span class="text-sm font-semibold text-slate-900">Robert Jackson</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-slate-600">2018 Toyota RAV4</td>
                                        <td class="px-6 py-4">
                                            <span
                                                class="bg-slate-100 text-slate-600 text-[10px] font-bold px-2 py-1 rounded-full uppercase tracking-tighter">Standard
                                                Oil Service</span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <p class="text-sm font-bold text-slate-900">Oct 24, 2024</p>
                                            <p class="text-xs text-slate-500">11:30 AM</p>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <button
                                                    class="px-3 py-1.5 border border-slate-200 text-slate-600 text-xs font-bold rounded hover:bg-slate-50 transition-all">Review</button>
                                                <button
                                                    class="px-3 py-1.5 bg-primary text-white text-xs font-bold rounded hover:bg-primary-hover shadow-sm transition-all">Accept</button>
                                                <button
                                                    class="p-1.5 text-slate-400 hover:text-error transition-colors"><span
                                                        class="material-symbols-outlined text-sm">close</span></button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <div
                                                    class="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center text-primary font-bold text-xs">
                                                    MA</div>
                                                <span class="text-sm font-semibold text-slate-900">Maria Alvera</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-slate-600">2023 Tesla Model Y</td>
                                        <td class="px-6 py-4">
                                            <span
                                                class="bg-tertiary-container text-on-tertiary-container text-[10px] font-bold px-2 py-1 rounded-full uppercase tracking-tighter">Software
                                                Sync Issue</span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <p class="text-sm font-bold text-slate-900">Oct 25, 2024</p>
                                            <p class="text-xs text-slate-500">02:00 PM</p>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <button
                                                    class="px-3 py-1.5 border border-slate-200 text-slate-600 text-xs font-bold rounded hover:bg-slate-50 transition-all">Review</button>
                                                <button
                                                    class="px-3 py-1.5 bg-primary text-white text-xs font-bold rounded hover:bg-primary-hover shadow-sm transition-all">Accept</button>
                                                <button
                                                    class="p-1.5 text-slate-400 hover:text-error transition-colors"><span
                                                        class="material-symbols-outlined text-sm">close</span></button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
                <!-- 4. Queue Next Sidebar -->
                <aside class="w-full lg:w-80 space-y-6">
                    <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="p-6 bg-slate-900 text-white">
                            <h3 class="text-base font-black tracking-tight flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary-fixed-dim">speed</span>
                                Queue Next
                            </h3>
                            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-1">Confirmed
                                Arrivals</p>
                        </div>
                        <div class="divide-y divide-slate-100">
                            <!-- Arrival Item 1 -->
                            <div class="p-4 hover:bg-slate-50 transition-colors group cursor-pointer">
                                <div class="flex justify-between items-start mb-2">
                                    <span
                                        class="text-[10px] font-black bg-primary/10 text-primary px-2 py-0.5 rounded">08:30
                                        AM</span>
                                    <span
                                        class="material-symbols-outlined text-slate-300 group-hover:text-primary text-sm">drag_indicator</span>
                                </div>
                                <h4 class="text-sm font-bold text-slate-900">David G. - Mercedes GLE</h4>
                                <p class="text-xs text-slate-500 mt-0.5">30k Mile Service Pack</p>
                                <div class="flex items-center gap-2 mt-3">
                                    <div class="h-1.5 w-1.5 rounded-full bg-green-500"></div>
                                    <span class="text-[10px] font-bold text-slate-400 uppercase">Confirmed via
                                        SMS</span>
                                </div>
                            </div>
                            <!-- Arrival Item 2 -->
                            <div class="p-4 hover:bg-slate-50 transition-colors group cursor-pointer">
                                <div class="flex justify-between items-start mb-2">
                                    <span
                                        class="text-[10px] font-black bg-slate-100 text-slate-500 px-2 py-0.5 rounded">09:15
                                        AM</span>
                                    <span
                                        class="material-symbols-outlined text-slate-300 group-hover:text-primary text-sm">drag_indicator</span>
                                </div>
                                <h4 class="text-sm font-bold text-slate-900">Sarah M. - Honda Accord</h4>
                                <p class="text-xs text-slate-500 mt-0.5">Unidentified Rattling (Front)</p>
                                <div class="flex items-center gap-2 mt-3">
                                    <div class="h-1.5 w-1.5 rounded-full bg-tertiary"></div>
                                    <span class="text-[10px] font-bold text-slate-400 uppercase">Checking In Now</span>
                                </div>
                            </div>
                            <!-- Arrival Item 3 -->
                            <div class="p-4 hover:bg-slate-50 transition-colors group cursor-pointer">
                                <div class="flex justify-between items-start mb-2">
                                    <span
                                        class="text-[10px] font-black bg-slate-100 text-slate-500 px-2 py-0.5 rounded">10:00
                                        AM</span>
                                    <span
                                        class="material-symbols-outlined text-slate-300 group-hover:text-primary text-sm">drag_indicator</span>
                                </div>
                                <h4 class="text-sm font-bold text-slate-900">Ken T. - Chevy Silverado</h4>
                                <p class="text-xs text-slate-500 mt-0.5">Tire Replacement (x4)</p>
                                <div class="flex items-center gap-2 mt-3">
                                    <div class="h-1.5 w-1.5 rounded-full bg-slate-300"></div>
                                    <span class="text-[10px] font-bold text-slate-400 uppercase">En Route</span>
                                </div>
                            </div>
                        </div>
                        <div class="p-4 bg-slate-50 border-t border-slate-100">
                            <button
                                class="w-full py-2 text-xs font-black text-slate-600 uppercase tracking-widest hover:text-primary transition-colors">Manage
                                Full Queue</button>
                        </div>
                    </section>
                    <div class="relative rounded-xl overflow-hidden aspect-[4/3] shadow-sm border border-slate-200">
                        <img alt="Facility Exterior" class="w-full h-full object-cover"
                            data-alt="architectural clean view of modern corporate garage facility exterior with sharp blue glass accents"
                            src="https://lh3.googleusercontent.com/aida-public/AB6AXuCDCGu1rooafd2kPw-CSeIxpliRjlBvP8U1-7QNnRDoiPJAY6dfl_p6lylEfeAjPEwfZuVYkEZOesbZ5v6qfUdCr-4nMPQhaYph7MnKgU1mCLflGtMiwtVdNQIsMLcWJd_wPWZ6aKYiCFLC_eOm0OVelbwxxhEnBJ7jb0HDieaeqrTtK_zyESzXohYGrg1NbuTSmttdWIp3JUR3eu8oQ7Ijzvbh0ans1WCyLyMldybhui_n5tHtWgg8XQbkhljQ_fPjo7q-e2R_jFX6" />
                        <div
                            class="absolute inset-0 bg-gradient-to-t from-slate-900/80 to-transparent flex flex-col justify-end p-5">
                            <p class="text-white text-xs font-bold">Bay Availability</p>
                            <div class="flex gap-1 mt-2">
                                <div class="h-1 flex-1 bg-primary rounded-full"></div>
                                <div class="h-1 flex-1 bg-primary rounded-full"></div>
                                <div class="h-1 flex-1 bg-primary rounded-full"></div>
                                <div class="h-1 flex-1 bg-slate-500 rounded-full"></div>
                                <div class="h-1 flex-1 bg-slate-500 rounded-full"></div>
                            </div>
                            <p class="text-[10px] text-slate-300 mt-2 font-semibold">3 of 5 Lifts active</p>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </main>
</body>

</html>