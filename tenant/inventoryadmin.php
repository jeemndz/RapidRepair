<!DOCTYPE html>

<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>AutoFix Admin - Inventory Management</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&amp;display=swap"
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

        .active-nav {
            background-color: rgba(17, 82, 212, 0.1);
            border-left: 4px solid #1152d4;
        }
    </style>
</head>

<body class="bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <aside
            class="w-64 flex-shrink-0 border-r border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-y-auto relative flex flex-col">
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
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                        href="appointmentadmin.php">
                        <span class="material-symbols-outlined text-[22px]">event</span>
                        Appointments
                    </a>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                        href="reportsadmin.php">
                        <span class="material-symbols-outlined text-[22px]">description</span>
                        Reports
                    </a>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-primary/10 text-primary font-medium"
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
        <!-- Main Content Wrapper -->
        <div class="flex-1 flex flex-col min-w-0 bg-background-light dark:bg-background-dark overflow-y-auto">
            <!-- Top Nav Bar -->
            <header
                class="sticky top-0 z-40 w-full border-b border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md flex items-center justify-between px-8 h-20">
                <div class="flex items-center gap-6">
                    <h2 class="text-lg font-black text-slate-900 dark:white tracking-tight">Inventory Management</h2>
                    <div class="relative hidden lg:block">
                        <span
                            class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">search</span>
                        <input
                                class="bg-surface-variant border-none rounded-lg pl-10 pr-4 py-1.5 text-sm w-64 focus:ring-2 focus:ring-primary/20"
                            placeholder="Search inventory..." type="text" />
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
            <main class="p-8 max-w-[1600px] mx-auto w-full">
                <!-- Page Title and Primary Action -->
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                    <div>
                        <h2 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight">Inventory
                            Management</h2>
                        <p class="text-slate-500 dark:text-slate-400">Manage parts, track stock levels, and coordinate
                            with suppliers.</p>
                    </div>
                    <button
                        class="flex items-center justify-center gap-2 bg-primary text-white px-6 py-2.5 rounded-lg font-bold hover:bg-primary/90 transition-all shadow-lg shadow-primary/20">
                        <span class="material-symbols-outlined text-lg">add</span>
                        Add New Part
                    </button>
                </div>
                <!-- Summary Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div
                        class="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                        <div class="flex justify-between items-start mb-4">
                            <div
                                class="p-2 bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-lg">
                                <span class="material-symbols-outlined">category</span>
                            </div>
                            <span
                                class="text-xs font-bold text-green-600 bg-green-100 px-2 py-1 rounded-full">+2.4%</span>
                        </div>
                        <p class="text-slate-500 dark:text-slate-400 text-sm font-medium">Total Items</p>
                        <h3 class="text-2xl font-bold text-slate-900 dark:text-white">1,240</h3>
                    </div>
                    <div
                        class="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                        <div class="flex justify-between items-start mb-4">
                            <div
                                class="p-2 bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 rounded-lg">
                                <span class="material-symbols-outlined">warning</span>
                            </div>
                            <span
                                class="text-xs font-bold text-amber-600 bg-amber-100 px-2 py-1 rounded-full">Warning</span>
                        </div>
                        <p class="text-slate-500 dark:text-slate-400 text-sm font-medium">Low Stock Items</p>
                        <h3 class="text-2xl font-bold text-slate-900 dark:text-white">18</h3>
                    </div>
                    <div
                        class="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                        <div class="flex justify-between items-start mb-4">
                            <div class="p-2 bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 rounded-lg">
                                <span class="material-symbols-outlined">error</span>
                            </div>
                            <span
                                class="text-xs font-bold text-red-600 bg-red-100 px-2 py-1 rounded-full">Critical</span>
                        </div>
                        <p class="text-slate-500 dark:text-slate-400 text-sm font-medium">Out of Stock</p>
                        <h3 class="text-2xl font-bold text-slate-900 dark:text-white">5</h3>
                    </div>
                    <div
                        class="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                        <div class="flex justify-between items-start mb-4">
                            <div
                                class="p-2 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 rounded-lg">
                                <span class="material-symbols-outlined">payments</span>
                            </div>
                            <span
                                class="text-xs font-bold text-emerald-600 bg-emerald-100 px-2 py-1 rounded-full">Value</span>
                        </div>
                        <p class="text-slate-500 dark:text-slate-400 text-sm font-medium">Total Inventory Value</p>
                        <h3 class="text-2xl font-bold text-slate-900 dark:text-white">$45,200.00</h3>
                    </div>
                </div>
                <!-- Filters and List Area -->
                <div class="flex flex-col lg:flex-row gap-8">
                    <div class="flex-1 min-w-0">
                        <!-- Filters -->
                        <div
                            class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 mb-6 flex flex-wrap items-center gap-4">
                            <div class="flex-1 min-w-[200px] relative">
                                <span
                                    class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">search</span>
                                <input
                                    class="w-full text-sm bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 rounded-lg pl-9 pr-3 py-2 focus:ring-primary"
                                    placeholder="Filter by part name..." type="text" />
                            </div>
                            <select
                                class="text-sm bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 rounded-lg py-2 pl-3 pr-8 focus:ring-primary">
                                <option>All Categories</option>
                                <option>Engine</option>
                                <option>Brakes</option>
                                <option>Fluids</option>
                                <option>Tires</option>
                                <option>Electronics</option>
                            </select>
                            <select
                                class="text-sm bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 rounded-lg py-2 pl-3 pr-8 focus:ring-primary">
                                <option>All Status</option>
                                <option>In Stock</option>
                                <option>Low Stock</option>
                                <option>Out of Stock</option>
                            </select>
                            <button
                                class="p-2 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 transition-colors">
                                <span class="material-symbols-outlined text-slate-500">filter_list</span>
                            </button>
                        </div>
                        <!-- Main Inventory Table -->
                        <div
                            class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr
                                        class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-800">
                                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                            Part Name / ID</th>
                                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                            Category</th>
                                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                            Stock Level</th>
                                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                            Unit Price</th>
                                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                            Supplier</th>
                                        <th
                                            class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-right">
                                            Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col">
                                                <span class="text-sm font-bold text-slate-900 dark:text-white">Synthetic
                                                    Oil 5W-30</span>
                                                <span class="text-xs text-slate-500">ID: #FL-2093</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span
                                                class="px-2.5 py-1 text-xs font-medium bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 rounded-lg">Fluids</span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="w-full max-w-[100px]">
                                                <div class="flex justify-between items-center mb-1">
                                                    <span
                                                        class="text-xs font-bold text-slate-700 dark:text-slate-300">45
                                                        units</span>
                                                </div>
                                                <div
                                                    class="h-1.5 w-full bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                                                    <div class="h-full bg-green-500 w-[75%]"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm font-medium text-slate-900 dark:text-white">$12.50
                                        </td>
                                        <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">Shell Global
                                        </td>
                                        <td class="px-6 py-4 text-right space-x-1">
                                            <button
                                                class="p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg text-slate-400 hover:text-primary transition-colors">
                                                <span class="material-symbols-outlined text-lg">edit</span>
                                            </button>
                                            <button
                                                class="p-1.5 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg text-slate-400 hover:text-red-500 transition-colors">
                                                <span class="material-symbols-outlined text-lg">delete</span>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col">
                                                <span class="text-sm font-bold text-slate-900 dark:text-white">Brake Pad
                                                    Set - Front</span>
                                                <span class="text-xs text-slate-500">ID: #BR-4421</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span
                                                class="px-2.5 py-1 text-xs font-medium bg-purple-50 dark:bg-purple-900/20 text-purple-600 dark:text-purple-400 rounded-lg">Brakes</span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="w-full max-w-[100px]">
                                                <div class="flex justify-between items-center mb-1">
                                                    <span class="text-xs font-bold text-amber-600">8 units</span>
                                                </div>
                                                <div
                                                    class="h-1.5 w-full bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                                                    <div class="h-full bg-amber-500 w-[15%]"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm font-medium text-slate-900 dark:text-white">$48.90
                                        </td>
                                        <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">Brembo Parts
                                            Co.</td>
                                        <td class="px-6 py-4 text-right space-x-1">
                                            <button
                                                class="p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg text-slate-400 hover:text-primary transition-colors">
                                                <span class="material-symbols-outlined text-lg">edit</span>
                                            </button>
                                            <button
                                                class="p-1.5 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg text-slate-400 hover:text-red-500 transition-colors">
                                                <span class="material-symbols-outlined text-lg">delete</span>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col">
                                                <span class="text-sm font-bold text-slate-900 dark:text-white">Spark
                                                    Plug Platinum</span>
                                                <span class="text-xs text-slate-500">ID: #EN-1082</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span
                                                class="px-2.5 py-1 text-xs font-medium bg-orange-50 dark:bg-orange-900/20 text-orange-600 dark:text-orange-400 rounded-lg">Engine</span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="w-full max-w-[100px]">
                                                <div class="flex justify-between items-center mb-1">
                                                    <span class="text-xs font-bold text-red-600">0 units</span>
                                                </div>
                                                <div
                                                    class="h-1.5 w-full bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                                                    <div class="h-full bg-red-500 w-[0%]"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm font-medium text-slate-900 dark:text-white">$8.25
                                        </td>
                                        <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">Bosch
                                            Automotive</td>
                                        <td class="px-6 py-4 text-right space-x-1">
                                            <button
                                                class="p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg text-slate-400 hover:text-primary transition-colors">
                                                <span class="material-symbols-outlined text-lg">edit</span>
                                            </button>
                                            <button
                                                class="p-1.5 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg text-slate-400 hover:text-red-500 transition-colors">
                                                <span class="material-symbols-outlined text-lg">delete</span>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col">
                                                <span class="text-sm font-bold text-slate-900 dark:text-white">Headlight
                                                    Bulb H7</span>
                                                <span class="text-xs text-slate-500">ID: #EL-5582</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span
                                                class="px-2.5 py-1 text-xs font-medium bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 rounded-lg">Electronics</span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="w-full max-w-[100px]">
                                                <div class="flex justify-between items-center mb-1">
                                                    <span
                                                        class="text-xs font-bold text-slate-700 dark:text-slate-300">32
                                                        units</span>
                                                </div>
                                                <div
                                                    class="h-1.5 w-full bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                                                    <div class="h-full bg-green-500 w-[55%]"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm font-medium text-slate-900 dark:text-white">$14.00
                                        </td>
                                        <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">Philips
                                            Lighting</td>
                                        <td class="px-6 py-4 text-right space-x-1">
                                            <button
                                                class="p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg text-slate-400 hover:text-primary transition-colors">
                                                <span class="material-symbols-outlined text-lg">edit</span>
                                            </button>
                                            <button
                                                class="p-1.5 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg text-slate-400 hover:text-red-500 transition-colors">
                                                <span class="material-symbols-outlined text-lg">delete</span>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <div
                                class="bg-slate-50 dark:bg-slate-800/50 px-6 py-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
                                <span class="text-xs text-slate-500 font-medium">Showing 1-4 of 1,240 results</span>
                                <div class="flex gap-2">
                                    <button
                                        class="px-3 py-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded text-xs font-bold hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">Previous</button>
                                    <button
                                        class="px-3 py-1 bg-primary text-white rounded text-xs font-bold transition-colors">1</button>
                                    <button
                                        class="px-3 py-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded text-xs font-bold hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">2</button>
                                    <button
                                        class="px-3 py-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded text-xs font-bold hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">Next</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Side Low Stock Alerts -->
                    <aside class="w-full lg:w-80 shrink-0">
                        <div
                            class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm">
                            <div class="flex items-center justify-between mb-6">
                                <h3 class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                    <span class="material-symbols-outlined text-amber-500">notifications_active</span>
                                    Low Stock Alerts
                                </h3>
                                <span
                                    class="text-[10px] font-bold bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full uppercase tracking-tight">Active</span>
                            </div>
                            <div class="space-y-4">
                                <div class="p-3 bg-red-50 dark:bg-red-900/10 border-l-4 border-red-500 rounded-r-lg">
                                    <div class="flex justify-between items-start mb-1">
                                        <p class="text-sm font-bold text-slate-900 dark:text-white">Spark Plug Platinum
                                        </p>
                                        <span class="text-[10px] font-black text-red-600">OUT OF STOCK</span>
                                    </div>
                                    <p class="text-xs text-slate-500 mb-2">Required for 3 scheduled jobs this week.</p>
                                    <button
                                        class="text-xs font-bold text-primary hover:underline flex items-center gap-1">
                                        Order now <span class="material-symbols-outlined text-sm">chevron_right</span>
                                    </button>
                                </div>
                                <div
                                    class="p-3 bg-amber-50 dark:bg-amber-900/10 border-l-4 border-amber-500 rounded-r-lg">
                                    <div class="flex justify-between items-start mb-1">
                                        <p class="text-sm font-bold text-slate-900 dark:text-white">Brake Pad Set -
                                            Front</p>
                                        <span class="text-[10px] font-black text-amber-600">8 LEFT</span>
                                    </div>
                                    <p class="text-xs text-slate-500 mb-2">Reorder threshold: 15 units.</p>
                                    <button
                                        class="text-xs font-bold text-primary hover:underline flex items-center gap-1">
                                        View supplier <span
                                            class="material-symbols-outlined text-sm">chevron_right</span>
                                    </button>
                                </div>
                                <div
                                    class="p-3 bg-amber-50 dark:bg-amber-900/10 border-l-4 border-amber-500 rounded-r-lg">
                                    <div class="flex justify-between items-start mb-1">
                                        <p class="text-sm font-bold text-slate-900 dark:text-white">Transmission Fluid
                                        </p>
                                        <span class="text-[10px] font-black text-amber-600">12 LEFT</span>
                                    </div>
                                    <p class="text-xs text-slate-500 mb-2">Reorder threshold: 20 units.</p>
                                    <button
                                        class="text-xs font-bold text-primary hover:underline flex items-center gap-1">
                                        View details <span
                                            class="material-symbols-outlined text-sm">chevron_right</span>
                                    </button>
                                </div>
                            </div>
                            <button
                                class="w-full mt-6 py-2 border-2 border-slate-100 dark:border-slate-800 rounded-lg text-sm font-bold text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                View All Alerts
                            </button>
                        </div>
                        <!-- Supplier Card -->
                        <div class="bg-primary/5 rounded-xl border border-primary/20 p-6 mt-6">
                            <h4 class="font-bold text-primary mb-2 flex items-center gap-2">
                                <span class="material-symbols-outlined">local_shipping</span>
                                Recent Shipments
                            </h4>
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-xs font-bold text-slate-900 dark:text-white">Brembo Parts</p>
                                        <p class="text-[10px] text-slate-500">Order #TX-9921 • In Transit</p>
                                    </div>
                                    <span class="text-[10px] font-bold text-blue-600">May 24</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-xs font-bold text-slate-900 dark:text-white">Bosch Auto</p>
                                        <p class="text-[10px] text-slate-500">Order #TX-9844 • Delivered</p>
                                    </div>
                                    <span class="text-[10px] font-bold text-green-600">May 20</span>
                                </div>
                            </div>
                        </div>
                    </aside>
                </div>
            </main>
        </div>
    </div>
</body>

</html>