<!DOCTYPE html>

<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap"
        rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@100..700,0..1&amp;display=swap"
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
                        "background-light": "#f6f6f8",
                        "background-dark": "#101622",
                    },
                    fontFamily: {
                        "display": ["Inter", "sans-serif"]
                    },
                    borderRadius: { "DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px" },
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

<body class="bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <aside
            class="w-64 flex-shrink-0 border-r border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-y-auto">
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
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-primary/10 text-primary font-medium"
                        href="#">
                        <span class="material-symbols-outlined text-[22px]">dashboard</span>
                        Dashboard
                    </a>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                        href="#">
                        <span class="material-symbols-outlined text-[22px]">build</span>
                        Repair Jobs
                    </a>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                        href="#">
                        <span class="material-symbols-outlined text-[22px]">group</span>
                        Customers
                    </a>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                        href="#">
                        <span class="material-symbols-outlined text-[22px]">inventory_2</span>
                        Inventory
                    </a>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                        href="#">
                        <span class="material-symbols-outlined text-[22px]">event</span>
                        Appointments
                    </a>
                    <div class="pt-4 mt-4 border-t border-slate-100 dark:border-slate-800">
                        <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                            href="#">
                            <span class="material-symbols-outlined text-[22px]">settings</span>
                            Settings
                        </a>
                    </div>
                </nav>
            </div>
            <div class="absolute bottom-0 w-64 p-4 border-t border-slate-200 dark:border-slate-800">
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
                </div>
            </div>
        </aside>
        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto">
            <!-- Header -->
            <header
                class="sticky top-0 z-10 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md border-b border-slate-200 dark:border-slate-800 px-8 py-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-bold tracking-tight">Dashboard Overview</h2>
                    <div class="flex items-center gap-4">
                        <div class="relative">
                            <span
                                class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
                            <input
                                class="pl-10 pr-4 py-2 bg-slate-100 dark:bg-slate-800 border-none rounded-lg text-sm focus:ring-2 focus:ring-primary w-64"
                                placeholder="Search orders, parts..." type="text" />
                        </div>
                        <a href="logout.php"
                            class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 text-sm font-semibold hover:bg-red-100 dark:hover:bg-red-900/35 transition-colors">
                            <span class="material-symbols-outlined text-[20px]">logout</span>
                            Logout
                        </a>
                        <button
                            class="p-2 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 relative">
                            <span class="material-symbols-outlined">notifications</span>
                            <span
                                class="absolute top-2 right-2 size-2 bg-red-500 rounded-full ring-2 ring-white dark:ring-slate-900"></span>
                        </button>
                    </div>
                </div>
            </header>
            <div class="p-8">
                <!-- Stats Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div
                        class="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-2 bg-primary/10 rounded-lg text-primary">
                                <span class="material-symbols-outlined">payments</span>
                            </div>
                            <span
                                class="text-xs font-semibold text-green-600 bg-green-100 dark:bg-green-900/30 px-2 py-1 rounded">+12.5%</span>
                        </div>
                        <p class="text-sm text-slate-500 dark:text-slate-400 font-medium">Monthly Revenue</p>
                        <p class="text-2xl font-bold mt-1">$48,250.00</p>
                    </div>
                    <div
                        class="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-2 bg-orange-100 dark:bg-orange-900/20 rounded-lg text-orange-600">
                                <span class="material-symbols-outlined">car_repair</span>
                            </div>
                            <span
                                class="text-xs font-semibold text-slate-500 bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded">8
                                In Progress</span>
                        </div>
                        <p class="text-sm text-slate-500 dark:text-slate-400 font-medium">Active Repair Jobs</p>
                        <p class="text-2xl font-bold mt-1">24</p>
                    </div>
                    <div
                        class="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-2 bg-purple-100 dark:bg-purple-900/20 rounded-lg text-purple-600">
                                <span class="material-symbols-outlined">calendar_month</span>
                            </div>
                            <span
                                class="text-xs font-semibold text-slate-500 bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded">Next
                                48h</span>
                        </div>
                        <p class="text-sm text-slate-500 dark:text-slate-400 font-medium">Upcoming Appts</p>
                        <p class="text-2xl font-bold mt-1">18</p>
                    </div>
                    <div
                        class="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-2 bg-red-100 dark:bg-red-900/20 rounded-lg text-red-600">
                                <span class="material-symbols-outlined">warning</span>
                            </div>
                            <span
                                class="text-xs font-semibold text-red-600 bg-red-100 dark:bg-red-900/30 px-2 py-1 rounded">Urgent</span>
                        </div>
                        <p class="text-sm text-slate-500 dark:text-slate-400 font-medium">Inventory Alerts</p>
                        <p class="text-2xl font-bold mt-1 text-red-600">5 Low Stock</p>
                    </div>
                </div>
                <!-- Charts & Lists -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Weekly Revenue Chart -->
                    <div
                        class="lg:col-span-2 bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800">
                        <div class="flex items-center justify-between mb-8">
                            <div>
                                <h3 class="text-lg font-bold">Weekly Performance</h3>
                                <p class="text-sm text-slate-500">Revenue tracking for the last 7 days</p>
                            </div>
                            <select
                                class="bg-slate-100 dark:bg-slate-800 border-none rounded-lg text-xs font-semibold focus:ring-primary">
                                <option>This Week</option>
                                <option>Last Week</option>
                            </select>
                        </div>
                        <div class="h-64 flex items-end gap-3 px-2">
                            <div class="flex-1 flex flex-col items-center gap-2 group">
                                <div
                                    class="w-full bg-slate-100 dark:bg-slate-800 rounded-t-lg relative h-32 overflow-hidden">
                                    <div
                                        class="absolute bottom-0 w-full bg-primary/40 h-1/2 group-hover:bg-primary/60 transition-all">
                                    </div>
                                </div>
                                <span class="text-xs font-medium text-slate-500">Mon</span>
                            </div>
                            <div class="flex-1 flex flex-col items-center gap-2 group">
                                <div
                                    class="w-full bg-slate-100 dark:bg-slate-800 rounded-t-lg relative h-48 overflow-hidden">
                                    <div
                                        class="absolute bottom-0 w-full bg-primary/40 h-3/4 group-hover:bg-primary/60 transition-all">
                                    </div>
                                </div>
                                <span class="text-xs font-medium text-slate-500">Tue</span>
                            </div>
                            <div class="flex-1 flex flex-col items-center gap-2 group">
                                <div
                                    class="w-full bg-slate-100 dark:bg-slate-800 rounded-t-lg relative h-40 overflow-hidden">
                                    <div
                                        class="absolute bottom-0 w-full bg-primary/40 h-2/3 group-hover:bg-primary/60 transition-all">
                                    </div>
                                </div>
                                <span class="text-xs font-medium text-slate-500">Wed</span>
                            </div>
                            <div class="flex-1 flex flex-col items-center gap-2 group">
                                <div
                                    class="w-full bg-slate-100 dark:bg-slate-800 rounded-t-lg relative h-56 overflow-hidden">
                                    <div
                                        class="absolute bottom-0 w-full bg-primary/40 h-full group-hover:bg-primary/60 transition-all">
                                    </div>
                                </div>
                                <span class="text-xs font-medium text-slate-500">Thu</span>
                            </div>
                            <div class="flex-1 flex flex-col items-center gap-2 group">
                                <div
                                    class="w-full bg-slate-100 dark:bg-slate-800 rounded-t-lg relative h-44 overflow-hidden">
                                    <div
                                        class="absolute bottom-0 w-full bg-primary/40 h-4/5 group-hover:bg-primary/60 transition-all">
                                    </div>
                                </div>
                                <span class="text-xs font-medium text-slate-500">Fri</span>
                            </div>
                            <div class="flex-1 flex flex-col items-center gap-2 group">
                                <div
                                    class="w-full bg-slate-100 dark:bg-slate-800 rounded-t-lg relative h-24 overflow-hidden">
                                    <div
                                        class="absolute bottom-0 w-full bg-primary/40 h-1/3 group-hover:bg-primary/60 transition-all">
                                    </div>
                                </div>
                                <span class="text-xs font-medium text-slate-500">Sat</span>
                            </div>
                            <div class="flex-1 flex flex-col items-center gap-2 group">
                                <div
                                    class="w-full bg-slate-100 dark:bg-slate-800 rounded-t-lg relative h-20 overflow-hidden">
                                    <div
                                        class="absolute bottom-0 w-full bg-primary/40 h-1/4 group-hover:bg-primary/60 transition-all">
                                    </div>
                                </div>
                                <span class="text-xs font-medium text-slate-500">Sun</span>
                            </div>
                        </div>
                    </div>
                    <!-- Recent Activity -->
                    <div
                        class="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 flex flex-col">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-bold">Recent Activity</h3>
                            <button class="text-xs text-primary font-semibold hover:underline">View All</button>
                        </div>
                        <div class="space-y-6 flex-1">
                            <div class="flex gap-4">
                                <div
                                    class="size-2 mt-2 rounded-full bg-green-500 ring-4 ring-green-100 dark:ring-green-900/20 shrink-0">
                                </div>
                                <div>
                                    <p class="text-sm font-semibold">Service Completed</p>
                                    <p class="text-xs text-slate-500">BMW X5 Oil Change for David Miller</p>
                                    <p class="text-[10px] text-slate-400 mt-1 uppercase font-bold">15 Mins ago</p>
                                </div>
                            </div>
                            <div class="flex gap-4">
                                <div
                                    class="size-2 mt-2 rounded-full bg-blue-500 ring-4 ring-blue-100 dark:ring-blue-900/20 shrink-0">
                                </div>
                                <div>
                                    <p class="text-sm font-semibold">New Appointment</p>
                                    <p class="text-xs text-slate-500">Sarah Johnson - Brake Inspection</p>
                                    <p class="text-[10px] text-slate-400 mt-1 uppercase font-bold">1 Hour ago</p>
                                </div>
                            </div>
                            <div class="flex gap-4">
                                <div
                                    class="size-2 mt-2 rounded-full bg-orange-500 ring-4 ring-orange-100 dark:ring-orange-900/20 shrink-0">
                                </div>
                                <div>
                                    <p class="text-sm font-semibold">Inventory Alert</p>
                                    <p class="text-xs text-slate-500">Brake pads (SKU: BP-04) low stock</p>
                                    <p class="text-[10px] text-slate-400 mt-1 uppercase font-bold">3 Hours ago</p>
                                </div>
                            </div>
                            <div class="flex gap-4">
                                <div
                                    class="size-2 mt-2 rounded-full bg-slate-300 ring-4 ring-slate-100 dark:ring-slate-800/20 shrink-0">
                                </div>
                                <div>
                                    <p class="text-sm font-semibold">Payment Received</p>
                                    <p class="text-xs text-slate-500">Invoice #20492 - $1,240.00</p>
                                    <p class="text-[10px] text-slate-400 mt-1 uppercase font-bold">5 Hours ago</p>
                                </div>
                            </div>
                        </div>
                        <button
                            class="w-full py-2.5 mt-6 border border-slate-200 dark:border-slate-800 rounded-lg text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                            Generate Daily Report
                        </button>
                    </div>
                </div>
                <!-- Inventory Alerts & Ongoing Jobs (Optional Extra Layer) -->
                <div class="mt-8 grid grid-cols-1 gap-6">
                    <div
                        class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
                        <div
                            class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                            <h3 class="text-lg font-bold">Active Repair Status</h3>
                            <div class="flex gap-2">
                                <span class="size-3 rounded-full bg-blue-500"></span>
                                <span class="text-xs text-slate-500 font-medium">Diagnostic</span>
                                <span class="size-3 rounded-full bg-orange-500 ml-2"></span>
                                <span class="text-xs text-slate-500 font-medium">Repairing</span>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead
                                    class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 text-xs font-bold uppercase tracking-wider">
                                    <tr>
                                        <th class="px-6 py-4">Customer</th>
                                        <th class="px-6 py-4">Vehicle</th>
                                        <th class="px-6 py-4">Technician</th>
                                        <th class="px-6 py-4">Status</th>
                                        <th class="px-6 py-4">Progress</th>
                                        <th class="px-6 py-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition-colors">
                                        <td class="px-6 py-4 font-semibold text-sm">Robert Wilson</td>
                                        <td class="px-6 py-4 text-sm">2020 Toyota Camry</td>
                                        <td class="px-6 py-4 text-sm">Mike Ross</td>
                                        <td class="px-6 py-4">
                                            <span
                                                class="px-2 py-1 bg-orange-100 dark:bg-orange-900/30 text-orange-600 rounded text-[10px] font-bold">REPAIRING</span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div
                                                class="w-32 h-1.5 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                                                <div class="bg-orange-500 h-full w-[65%]"></div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <button class="text-slate-400 hover:text-primary transition-colors">
                                                <span class="material-symbols-outlined">more_horiz</span>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition-colors">
                                        <td class="px-6 py-4 font-semibold text-sm">Linda Cheng</td>
                                        <td class="px-6 py-4 text-sm">2022 Tesla Model 3</td>
                                        <td class="px-6 py-4 text-sm">Chris P.</td>
                                        <td class="px-6 py-4">
                                            <span
                                                class="px-2 py-1 bg-blue-100 dark:bg-blue-900/30 text-blue-600 rounded text-[10px] font-bold">DIAGNOSTIC</span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div
                                                class="w-32 h-1.5 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                                                <div class="bg-blue-500 h-full w-[30%]"></div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <button class="text-slate-400 hover:text-primary transition-colors">
                                                <span class="material-symbols-outlined">more_horiz</span>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>

</html>