<!DOCTYPE html>

<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Customer Management - Cobalt Precision Service</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;300;400;500;600;700;800;900&amp;display=swap"
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
                        "tertiary": "#f59e0b",
                        "outline": "#e2e8f0",
                        "on-tertiary-fixed": "#7c2d12",
                        "on-primary-fixed-variant": "#1d4ed8",
                        "surface-variant": "#f1f5f9",
                        "surface-container-lowest": "#ffffff",
                        "surface-dim": "#d9d9e4",
                        "on-primary": "#ffffff",
                        "outline-variant": "#cbd5e1",
                        "on-surface-variant": "#64748b",
                        "on-error": "#ffffff",
                        "error-container": "#fee2e2",
                        "on-primary-fixed": "#1e3a8a",
                        "on-tertiary-fixed-variant": "#9a3412",
                        "tertiary-fixed": "#ffedd5",
                        "surface-tint": "#1152d4",
                        "tertiary-fixed-dim": "#fed7aa",
                        "secondary-container": "#f1f5f9",
                        "primary-fixed-dim": "#bfdbfe",
                        "primary": "#1152d4",
                        "on-secondary-container": "#1e293b",
                        "on-secondary-fixed-variant": "#334155",
                        "primary-container": "#eef2ff",
                        "on-secondary": "#ffffff",
                        "surface-container-low": "#ffffff",
                        "surface-container-high": "#ffffff",
                        "on-surface": "#0f172a",
                        "error": "#ef4444",
                        "surface": "#f6f6f8",
                        "primary-fixed": "#dbeafe",
                        "on-tertiary": "#ffffff",
                        "inverse-surface": "#1e293b",
                        "secondary": "#475569",
                        "on-tertiary-container": "#92400e",
                        "background": "#f6f6f8",
                        "on-secondary-fixed": "#0f172a",
                        "on-background": "#0f172a",
                        "on-primary-container": "#1152d4",
                        "inverse-primary": "#b4c5ff",
                        "inverse-on-surface": "#f8fafc",
                        "secondary-fixed": "#e2e8f0",
                        "surface-container": "#ffffff",
                        "surface-bright": "#ffffff",
                        "on-error-container": "#991b1b",
                        "tertiary-container": "#fef3c7",
                        "surface-container-highest": "#ffffff",
                        "secondary-fixed-dim": "#cbd5e1"
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
            vertical-align: middle;
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }
    </style>
</head>

<body class="bg-background text-on-background antialiased overflow-hidden flex h-screen">
    <!-- SideNavBar Component -->
    <aside
        class="w-64 flex-shrink-0 border-r border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-y-auto flex flex-col relative">
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
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    href="inventoryadmin.php">
                    <span class="material-symbols-outlined text-[22px]">inventory_2</span>
                    Inventory
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-primary/10 text-primary font-medium"
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
                    class="size-10 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center overflow-hidden shrink-0">
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
    <!-- Main Content Shell -->
    <main class="flex-1 flex flex-col h-full overflow-hidden">
        <!-- Top Nav Bar -->
        <header
            class="sticky top-0 z-40 w-full border-b border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md flex items-center justify-between px-8 h-16">
            <div class="flex items-center gap-6">
                <h2 class="text-lg font-black text-slate-900 dark:white tracking-tight">Customer Management</h2>
                <div class="relative hidden lg:block">
                    <span
                        class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">search</span>
                    <input
                        class="bg-surface-variant border-none rounded-lg pl-10 pr-4 py-1.5 text-sm w-64 focus:ring-2 focus:ring-primary/20"
                        placeholder="Search customers..." type="text" />
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
        <!-- Canvas -->
        <div class="flex-1 overflow-y-auto p-8 bg-[#f6f6f8]">
            <!-- Header & Action Row -->
            <div class="flex justify-between items-end mb-8">
                <div>
                    <h2 class="text-[30px] font-black tracking-tight text-on-surface">Customer Directory</h2>
                    <p class="text-secondary mt-1 font-medium">Manage and monitor customer relationships and vehicle
                        service history.</p>
                </div>
                <div class="flex items-center gap-3">
                    <button
                        class="px-4 py-2.5 border border-outline bg-white text-secondary font-bold text-sm rounded-lg flex items-center gap-2 hover:bg-slate-50 transition-colors shadow-sm">
                        <span class="material-symbols-outlined text-lg" data-icon="file_download">file_download</span>
                        Export List
                    </button>
                    <button
                        class="px-6 py-2.5 bg-primary text-white font-bold text-sm rounded-lg flex items-center gap-2 hover:brightness-110 transition-all shadow-sm active:scale-95">
                        <span class="material-symbols-outlined text-lg" data-icon="person_add">person_add</span>
                        Add New Customer
                    </button>
                </div>
            </div>
            <!-- Metric Cards (Bento Style) -->
            <div class="grid grid-cols-4 gap-6 mb-8">
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-2 bg-primary-container rounded-lg">
                            <span class="material-symbols-outlined text-primary" data-icon="group">group</span>
                        </div>
                        <span class="text-[10px] font-bold text-green-600 bg-green-50 px-2 py-1 rounded-full">+12% vs
                            LY</span>
                    </div>
                    <p class="text-slate-500 text-xs font-bold uppercase tracking-wider">Total Customers</p>
                    <h3 class="text-2xl font-black text-on-surface mt-1">2,842</h3>
                </div>
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-2 bg-primary-container rounded-lg">
                            <span class="material-symbols-outlined text-primary"
                                data-icon="person_add">person_add</span>
                        </div>
                        <span
                            class="text-[10px] font-bold text-green-600 bg-green-50 px-2 py-1 rounded-full">+4.3%</span>
                    </div>
                    <p class="text-slate-500 text-xs font-bold uppercase tracking-wider">New This Month</p>
                    <h3 class="text-2xl font-black text-on-surface mt-1">158</h3>
                </div>
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-2 bg-primary-container rounded-lg">
                            <span class="material-symbols-outlined text-primary"
                                data-icon="rebase_edit">rebase_edit</span>
                        </div>
                        <span
                            class="text-[10px] font-bold text-slate-500 bg-slate-100 px-2 py-1 rounded-full">Stable</span>
                    </div>
                    <p class="text-slate-500 text-xs font-bold uppercase tracking-wider">Returning Rate</p>
                    <h3 class="text-2xl font-black text-on-surface mt-1">74.2%</h3>
                </div>
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-2 bg-primary-container rounded-lg">
                            <span class="material-symbols-outlined text-primary"
                                data-icon="engineering">engineering</span>
                        </div>
                        <span
                            class="text-[10px] font-bold text-primary bg-primary-container px-2 py-1 rounded-full">Live</span>
                    </div>
                    <p class="text-slate-500 text-xs font-bold uppercase tracking-wider">Active Service Requests</p>
                    <h3 class="text-2xl font-black text-on-surface mt-1">42</h3>
                </div>
            </div>
            <div class="grid grid-cols-12 gap-8">
                <!-- Main Directory Table -->
                <div
                    class="col-span-12 lg:col-span-9 bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
                    <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
                        <h3 class="font-bold text-on-surface">Active Customers</h3>
                        <div class="flex gap-2">
                            <button class="p-1.5 hover:bg-slate-50 rounded text-slate-400"><span
                                    class="material-symbols-outlined text-lg"
                                    data-icon="filter_list">filter_list</span></button>
                            <button class="p-1.5 hover:bg-slate-50 rounded text-slate-400"><span
                                    class="material-symbols-outlined text-lg" data-icon="sort">sort</span></button>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead>
                                <tr
                                    class="bg-slate-50/50 text-slate-500 text-[10px] uppercase font-bold tracking-widest">
                                    <th class="px-6 py-4">Customer Name</th>
                                    <th class="px-6 py-4">Contact Info</th>
                                    <th class="px-6 py-4">Vehicles</th>
                                    <th class="px-6 py-4">Last Visit</th>
                                    <th class="px-6 py-4">Status</th>
                                    <th class="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <img class="w-9 h-9 rounded-lg border border-slate-200"
                                                data-alt="professional male customer headshot with neutral background"
                                                src="https://lh3.googleusercontent.com/aida-public/AB6AXuCmYlowwU26tLrCllwmqzZvTA8nzFLkBAPFa2cfCRSmSqGAV3XJyzd2nLPuXhWM4cEHCCAE_Mb4xNyZoKm3Bf60StxfSKq9VyFBYV_pwdnJ332rteKyn_fAEQjgb-OvggneZq_MR19t9FLoqObSdBzAS_gUO21suOJBnuKxYTRoGBBS31lS9xWZFDSs-ZcnWncNFAKzmeHq_XNayb8snuPBxmEmUDYdnVHFIBrEO1qE1j2wazPRtQ8_enmfDVzzTD3j5mpRnpCho5me" />
                                            <div>
                                                <p class="text-sm font-bold text-on-surface">Julian D. Sterling</p>
                                                <p class="text-[11px] text-slate-500 font-medium">ID: #CS-8921</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="text-sm text-on-surface">julian.s@example.com</p>
                                        <p class="text-xs text-slate-500">(555) 012-4492</p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="px-2 py-0.5 bg-slate-100 text-slate-700 text-[11px] font-bold rounded">2</span>
                                            <span class="text-sm text-on-surface">2022 Porsche 911</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="text-sm text-on-surface">Oct 12, 2023</p>
                                        <p class="text-[11px] text-primary font-bold">Annual Inspection</p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-700 uppercase tracking-tighter">Active</span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex justify-end gap-2">
                                            <button
                                                class="p-1.5 hover:bg-primary-container text-primary rounded transition-colors"
                                                title="View History">
                                                <span class="material-symbols-outlined text-lg"
                                                    data-icon="history">history</span>
                                            </button>
                                            <button
                                                class="p-1.5 hover:bg-slate-100 text-secondary rounded transition-colors"
                                                title="Edit Profile">
                                                <span class="material-symbols-outlined text-lg"
                                                    data-icon="edit">edit</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <img class="w-9 h-9 rounded-lg border border-slate-200"
                                                data-alt="professional female customer headshot with neutral background"
                                                src="https://lh3.googleusercontent.com/aida-public/AB6AXuDzkySTUDDFKo6VHPxN2Rs6wYpYqbr5BP-TwIEOZseULDT24d4Su_loc0F62_A6q4lj4cseocTeBhoRJ7Qp99976G557PVWQamdVM4YwHfVisjFWQEL3E6-Zp3wPXaUN9GVw4ZeZ6unKKmP9m_upHdl5gtUia06g-l7OUlOSzKQmTXOMGFlLLIuec10BkDLatMJrFj4BWVG2OEOLYzrMdLkmO73CRF7fqRvf9zMxETRwM2PthnK4mXEN7ym0ThmTMjOuTq3gLTQW7WJ" />
                                            <div>
                                                <p class="text-sm font-bold text-on-surface">Elena Moretti</p>
                                                <p class="text-[11px] text-slate-500 font-medium">ID: #CS-7712</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="text-sm text-on-surface">e.moretti@domain.com</p>
                                        <p class="text-xs text-slate-500">(555) 901-3321</p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="px-2 py-0.5 bg-slate-100 text-slate-700 text-[11px] font-bold rounded">1</span>
                                            <span class="text-sm text-on-surface">2019 Audi RS6</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="text-sm text-on-surface">Nov 02, 2023</p>
                                        <p class="text-[11px] text-primary font-bold">Brake Service</p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-700 uppercase tracking-tighter">Active</span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex justify-end gap-2">
                                            <button
                                                class="p-1.5 hover:bg-primary-container text-primary rounded transition-colors">
                                                <span class="material-symbols-outlined text-lg"
                                                    data-icon="history">history</span>
                                            </button>
                                            <button
                                                class="p-1.5 hover:bg-slate-100 text-secondary rounded transition-colors">
                                                <span class="material-symbols-outlined text-lg"
                                                    data-icon="edit">edit</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <img class="w-9 h-9 rounded-lg border border-slate-200"
                                                data-alt="professional male customer headshot with neutral background"
                                                src="https://lh3.googleusercontent.com/aida-public/AB6AXuBy6Ifmae3P0P2HdlPZ9_NM4vLl8t9wEVaKVgmTr8YiOEXnVI6ueDWTbJgEtXZlS5QLf8HWUf0CJnoeH_lp1PocuyJt2-TW2NGUrqQJXrqNb5GHH_NRghkc99Ey8-RgSOVtzwk5LNojrCCHNu8cnRoFt9p2xxvO-AmSqUM8oVpwViFqf-GyzaUIWl8uyrbyE_wIEmDfi2sVAa75VaVYByj5S0E7cjMzYWwqbAq9oYR4U5DPyHaNObLnfpPkAEZRxrTLIKeEfMZdjdDe" />
                                            <div>
                                                <p class="text-sm font-bold text-on-surface">Robert Kincaid</p>
                                                <p class="text-[11px] text-slate-500 font-medium">ID: #CS-4410</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="text-sm text-on-surface">rob.k@techcorp.io</p>
                                        <p class="text-xs text-slate-500">(555) 234-9981</p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="px-2 py-0.5 bg-slate-100 text-slate-700 text-[11px] font-bold rounded">3</span>
                                            <span class="text-sm text-on-surface">2021 BMW M5</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="text-sm text-on-surface">May 15, 2023</p>
                                        <p class="text-[11px] text-slate-400 font-bold">In-Transit</p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500 uppercase tracking-tighter">Inactive</span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex justify-end gap-2">
                                            <button
                                                class="p-1.5 hover:bg-primary-container text-primary rounded transition-colors">
                                                <span class="material-symbols-outlined text-lg"
                                                    data-icon="history">history</span>
                                            </button>
                                            <button
                                                class="p-1.5 hover:bg-slate-100 text-secondary rounded transition-colors">
                                                <span class="material-symbols-outlined text-lg"
                                                    data-icon="edit">edit</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div
                        class="mt-auto px-6 py-4 border-t border-slate-100 flex items-center justify-between bg-slate-50/30">
                        <p class="text-xs text-slate-500 font-medium">Showing <span
                                class="text-on-surface font-bold">1-10</span> of 2,842 customers</p>
                        <div class="flex gap-1">
                            <button
                                class="p-2 border border-outline rounded bg-white hover:bg-slate-50 disabled:opacity-50"><span
                                    class="material-symbols-outlined text-sm"
                                    data-icon="chevron_left">chevron_left</span></button>
                            <button class="px-3 py-1 bg-primary text-white text-xs font-bold rounded">1</button>
                            <button class="px-3 py-1 hover:bg-slate-100 text-xs font-bold rounded">2</button>
                            <button class="px-3 py-1 hover:bg-slate-100 text-xs font-bold rounded">3</button>
                            <button class="p-2 border border-outline rounded bg-white hover:bg-slate-50"><span
                                    class="material-symbols-outlined text-sm"
                                    data-icon="chevron_right">chevron_right</span></button>
                        </div>
                    </div>
                </div>
                <!-- Activity Feed Sidebar -->
                <div class="col-span-12 lg:col-span-3 space-y-6">
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                        <h3 class="font-bold text-on-surface mb-6 flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary text-xl"
                                data-icon="notifications_active">notifications_active</span>
                            Recent Activity
                        </h3>
                        <div
                            class="space-y-6 relative before:absolute before:left-[11px] before:top-2 before:bottom-2 before:w-[2px] before:bg-slate-100">
                            <!-- Activity Item 1 -->
                            <div class="relative pl-8">
                                <div
                                    class="absolute left-0 top-1 w-[24px] h-[24px] bg-primary text-white rounded-full flex items-center justify-center z-10">
                                    <span class="material-symbols-outlined text-xs"
                                        data-icon="person_add">person_add</span>
                                </div>
                                <p class="text-sm font-bold text-on-surface">New Registration</p>
                                <p class="text-xs text-slate-500 mt-0.5">Marcus Webb registered as a new customer.</p>
                                <p class="text-[10px] text-slate-400 font-bold uppercase mt-2">12 mins ago</p>
                            </div>
                            <!-- Activity Item 2 -->
                            <div class="relative pl-8">
                                <div
                                    class="absolute left-0 top-1 w-[24px] h-[24px] bg-tertiary text-white rounded-full flex items-center justify-center z-10">
                                    <span class="material-symbols-outlined text-xs"
                                        data-icon="directions_car">directions_car</span>
                                </div>
                                <p class="text-sm font-bold text-on-surface">Vehicle Updated</p>
                                <p class="text-xs text-slate-500 mt-0.5">Julian D. Sterling added a 2024 Tesla Model S.
                                </p>
                                <p class="text-[10px] text-slate-400 font-bold uppercase mt-2">1 hour ago</p>
                            </div>
                            <!-- Activity Item 3 -->
                            <div class="relative pl-8">
                                <div
                                    class="absolute left-0 top-1 w-[24px] h-[24px] bg-green-500 text-white rounded-full flex items-center justify-center z-10">
                                    <span class="material-symbols-outlined text-xs" data-icon="task_alt">task_alt</span>
                                </div>
                                <p class="text-sm font-bold text-on-surface">Service Completed</p>
                                <p class="text-xs text-slate-500 mt-0.5">Elena Moretti's Audi RS6 service finalized.</p>
                                <p class="text-[10px] text-slate-400 font-bold uppercase mt-2">3 hours ago</p>
                            </div>
                            <!-- Activity Item 4 -->
                            <div class="relative pl-8">
                                <div
                                    class="absolute left-0 top-1 w-[24px] h-[24px] bg-slate-400 text-white rounded-full flex items-center justify-center z-10">
                                    <span class="material-symbols-outlined text-xs"
                                        data-icon="edit_square">edit_square</span>
                                </div>
                                <p class="text-sm font-bold text-on-surface">Profile Modified</p>
                                <p class="text-xs text-slate-500 mt-0.5">Contact info updated for Robert Kincaid.</p>
                                <p class="text-[10px] text-slate-400 font-bold uppercase mt-2">Yesterday</p>
                            </div>
                        </div>
                        <button
                            class="w-full mt-6 py-2 text-xs font-bold text-primary hover:bg-primary-container rounded transition-colors">
                            View All Activity
                        </button>
                    </div>
                    <!-- Quick Insight Card -->
                    <div
                        class="bg-gradient-to-br from-primary to-blue-800 rounded-xl p-6 text-white shadow-md relative overflow-hidden">
                        <div class="relative z-10">
                            <p class="text-xs font-bold uppercase tracking-widest opacity-80">Customer Loyalty</p>
                            <h4 class="text-xl font-black mt-2 leading-tight">642 Priority Customers</h4>
                            <p class="text-xs mt-3 opacity-90 leading-relaxed">Priority customers represent 78% of your
                                monthly revenue. Consider a seasonal campaign.</p>
                            <button
                                class="mt-6 w-full py-2.5 bg-white text-primary font-bold text-xs rounded shadow-lg active:scale-95 transition-all">Launch
                                Campaign</button>
                        </div>
                        <div class="absolute -right-4 -bottom-4 opacity-10">
                            <span class="material-symbols-outlined text-[120px]"
                                data-icon="auto_awesome">auto_awesome</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>

</html>