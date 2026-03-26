<!DOCTYPE html>

<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Repair Jobs - Cobalt Precision</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&amp;display=swap"
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
                        "on-secondary-fixed-variant": "#334155",
                        "surface-container-highest": "#ffffff",
                        "tertiary": "#f59e0b",
                        "on-background": "#0f172a",
                        "primary": "#1152d4",
                        "on-tertiary-container": "#92400e",
                        "on-primary-fixed-variant": "#1d4ed8",
                        "surface-tint": "#1152d4",
                        "surface-container-low": "#ffffff",
                        "secondary": "#475569",
                        "tertiary-container": "#fef3c7",
                        "on-surface": "#0f172a",
                        "outline": "#e2e8f0",
                        "primary-fixed": "#dbeafe",
                        "on-primary": "#ffffff",
                        "surface-dim": "#d9d9e4",
                        "outline-variant": "#cbd5e1",
                        "on-primary-fixed": "#1e3a8a",
                        "inverse-surface": "#1e293b",
                        "error": "#ef4444",
                        "on-primary-container": "#1152d4",
                        "on-error": "#ffffff",
                        "on-secondary": "#ffffff",
                        "surface-variant": "#f1f5f9",
                        "on-tertiary-fixed-variant": "#9a3412",
                        "error-container": "#fee2e2",
                        "surface-container-high": "#ffffff",
                        "tertiary-fixed-dim": "#fed7aa",
                        "secondary-fixed": "#e2e8f0",
                        "surface-container-lowest": "#ffffff",
                        "surface-container": "#ffffff",
                        "secondary-fixed-dim": "#cbd5e1",
                        "on-error-container": "#991b1b",
                        "on-secondary-container": "#1e293b",
                        "inverse-primary": "#b4c5ff",
                        "inverse-on-surface": "#f8fafc",
                        "on-tertiary-fixed": "#7c2d12",
                        "surface": "#f6f6f8",
                        "on-surface-variant": "#64748b",
                        "primary-container": "#eef2ff",
                        "on-secondary-fixed": "#0f172a",
                        "surface-bright": "#ffffff",
                        "background": "#f6f6f8",
                        "primary-fixed-dim": "#bfdbfe",
                        "secondary-container": "#f1f5f9",
                        "on-tertiary": "#ffffff",
                        "tertiary-fixed": "#ffedd5"
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
            vertical-align: middle;
        }

        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-surface text-on-surface antialiased">
    <!-- SideNavBar (Updated to match SCREEN_106 structure) -->
    <aside class="fixed left-0 top-0 bottom-0 w-64 border-r border-slate-200 bg-white z-50 flex flex-col h-full">
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
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors font-medium"
                    href="dashboardadmin.php">
                    <span class="material-symbols-outlined text-[22px]">dashboard</span>
                    Dashboard
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-primary/10 text-primary font-medium"
                    href="repairjobsadmin.php">
                    <span class="material-symbols-outlined text-[22px]">build</span>
                    Repair Jobs
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="vehicleadmin.php">
                    <span class="material-symbols-outlined text-[22px]">directions_car</span>
                    Vehicles
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="appointmentadmin.php">
                    <span class="material-symbols-outlined text-[22px]">event</span>
                    Appointments
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="reportsadmin.php">
                    <span class="material-symbols-outlined text-[22px]">description</span>
                    Reports
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="inventoryadmin.php">
                    <span class="material-symbols-outlined text-[22px]">inventory_2</span>
                    Inventory
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="customeradmin.php">
                    <span class="material-symbols-outlined text-[22px]">group</span>
                    Customers
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="paymentsadmin.php">
                    <span class="material-symbols-outlined text-[22px]">payments</span>
                    Payments
                </a>
                <div class="pt-4 mt-4 border-t border-slate-100">
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                        href="settingsadmin.php">
                        <span class="material-symbols-outlined text-[22px]">settings</span>
                        Settings
                    </a>
                </div>
            </nav>
        </div>
        <div class="mt-auto w-full p-4 border-t border-slate-200">
            <div class="flex items-center gap-3">
                <div
                    class="w-10 h-10 rounded-full bg-slate-200 flex items-center justify-center overflow-hidden shrink-0">
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
                <h2 class="text-lg font-black text-slate-900 dark:white tracking-tight">Repair Jobs Management</h2>
                <div class="relative hidden lg:block">
                    <span
                        class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">search</span>
                    <input
                        class="bg-surface-variant border-none rounded-lg pl-10 pr-4 py-1.5 text-sm w-64 focus:ring-2 focus:ring-primary/20"
                        placeholder="Search repair jobs..." type="text" />
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
        <div class="px-8 pb-12 pt-8">
        <!-- Header Section -->
        <div class="mb-8 flex items-end justify-between">
            <div>
                <h2 class="text-3xl font-black tracking-tight text-on-background">Repair Jobs</h2>
                <p class="text-on-surface-variant font-medium mt-1">Real-time floor management and job tracking.</p>
            </div>
            <div class="flex gap-3">
                <button
                    class="flex items-center gap-2 px-4 py-2 bg-white border border-outline text-on-surface text-sm font-bold rounded shadow-sm hover:bg-surface-variant transition-colors">
                    <span class="material-symbols-outlined text-lg">filter_list</span>
                    Filters
                </button>
                <button
                    class="flex items-center gap-2 px-4 py-2 bg-white border border-outline text-on-surface text-sm font-bold rounded shadow-sm hover:bg-surface-variant transition-colors">
                    <span class="material-symbols-outlined text-lg">download</span>
                    Export Log
                </button>
            </div>
        </div>
        <!-- Bento Grid for Quick Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-blue-50 rounded-lg">
                        <span class="material-symbols-outlined text-primary"
                            style="font-variation-settings: 'FILL' 1;">precision_manufacturing</span>
                    </div>
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">In Workshop</span>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="text-2xl font-black">12</span>
                    <span class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded">+2 vs
                        yest</span>
                </div>
            </div>
            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-amber-50 rounded-lg">
                        <span class="material-symbols-outlined text-amber-600"
                            style="font-variation-settings: 'FILL' 1;">pending_actions</span>
                    </div>
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Waiting Parts</span>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="text-2xl font-black">04</span>
                    <span class="text-[10px] font-bold text-amber-600 bg-amber-50 px-1.5 py-0.5 rounded">-1 vs
                        yest</span>
                </div>
            </div>
            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-emerald-50 rounded-lg">
                        <span class="material-symbols-outlined text-emerald-600"
                            style="font-variation-settings: 'FILL' 1;">task_alt</span>
                    </div>
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Ready for Pickup</span>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="text-2xl font-black">07</span>
                    <span
                        class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded">Steady</span>
                </div>
            </div>
            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-slate-50 rounded-lg">
                        <span class="material-symbols-outlined text-slate-600"
                            style="font-variation-settings: 'FILL' 1;">timer</span>
                    </div>
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Avg. Cycle Time</span>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="text-2xl font-black">4.2<span
                            class="text-sm font-bold text-slate-400 ml-1">hrs</span></span>
                    <span class="text-[10px] font-bold text-error bg-error-container px-1.5 py-0.5 rounded">+0.5h</span>
                </div>
            </div>
        </div>
        <!-- Maintenance Progress Table (Primary Focus) -->
        <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-8">
            <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Maintenance Progress</h3>
                    <p class="text-xs text-slate-500 font-medium">Live workshop status updates for active bay units.</p>
                </div>
                <div class="flex items-center gap-2">
                    <span
                        class="flex items-center gap-1.5 px-2.5 py-1 bg-primary/5 text-primary text-[10px] font-black uppercase rounded">
                        <span class="w-1.5 h-1.5 bg-primary rounded-full animate-pulse"></span>
                        Live Update
                    </span>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-slate-50/50">
                            <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">Job ID
                                / Vehicle</th>
                            <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">
                                Customer</th>
                            <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">
                                Technician</th>
                            <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">Bay No.
                            </th>
                            <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">Status
                                Update</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <!-- Row 1 -->
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-bold text-sm text-slate-900">#RO-8821</div>
                                <div class="text-xs text-slate-500">2021 BMW M4 • Dravit Grey</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-slate-700">Julian Casablancas</div>
                                <div class="text-[10px] text-slate-400 uppercase font-bold">VIP Priority</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <img alt="tech-1" class="w-6 h-6 rounded-full grayscale"
                                        data-alt="close up headshot of a young male technician with a focused and professional appearance"
                                        src="https://lh3.googleusercontent.com/aida-public/AB6AXuBOzFwcOLHdovwMXUqWCu0_HoDY3TFST6ZPRE8OWsZ_V8ODeFeOA6sR9gJ_f01EkXuW9SPfgVSIhFj6Cr8Qk-nT_xlk0GIJj5wrHiDeCtnkmP8AcU1O3nz8JdtIIVeiuQZtdvRu4bazfBy_ZB9_ETzP852kqHsOTS0PfdKB-A_q6RPLWNxL3WEUS3_WkcwjVjYMunzFloZ8DsZ6UOJFCae235rdyo45FR8Ds2pBhuSoo-3JIljPty-jmjSDgkudES0lm0a9UkXL-WmE" />
                                    <span class="text-sm font-medium text-slate-600">Dave R.</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm font-bold text-slate-500">Bay 04</td>
                            <td class="px-6 py-4">
                                <select
                                    class="bg-amber-50 border-none text-amber-800 text-xs font-bold py-1.5 px-3 rounded-lg focus:ring-0 cursor-pointer w-40">
                                    <option value="diagnostics">Diagnostics</option>
                                    <option selected="" value="in_progress">In Progress</option>
                                    <option value="waiting_parts">Waiting for Parts</option>
                                    <option value="quality_check">Quality Check</option>
                                    <option value="ready">Ready for Pickup</option>
                                </select>
                            </td>
                        </tr>
                        <!-- Row 2 -->
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-bold text-sm text-slate-900">#RO-8825</div>
                                <div class="text-xs text-slate-500">2019 Audi RS6 • Matte Black</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-slate-700">Sarah Jenkins</div>
                                <div class="text-[10px] text-slate-400 uppercase font-bold">Scheduled</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <img alt="tech-2" class="w-6 h-6 rounded-full grayscale"
                                        data-alt="headshot of a female automotive technician with tied back hair and a professional smile"
                                        src="https://lh3.googleusercontent.com/aida-public/AB6AXuDMpbYx5kLbm2MRhIBgga0kmXnGxzghKUUkVbjyTB9iYMcPJxZrAFGsAzMyfmlalEZj2LRDgdKzpIGzCeLRvmamMdWyHfPaawAGsQiaCWJ5Xvwll8-Mt-vPXJCfMsP5k1B4EDg0G6nVgY0k08HdOmS04Pu9zP0lMpPJ9ILPleCYa8TCV0Xi3TrqpyNzsKpfk-4ziuSA9aY_WaB2ToqImBMO6gs7wTnWh0BboTTd07yYEpLsAhqyHqUAUPGWUcmwzNGbRTR6_flgUAMB" />
                                    <span class="text-sm font-medium text-slate-600">Eliza W.</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm font-bold text-slate-500">Bay 01</td>
                            <td class="px-6 py-4">
                                <select
                                    class="bg-blue-50 border-none text-blue-800 text-xs font-bold py-1.5 px-3 rounded-lg focus:ring-0 cursor-pointer w-40">
                                    <option selected="" value="diagnostics">Diagnostics</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="waiting_parts">Waiting for Parts</option>
                                    <option value="quality_check">Quality Check</option>
                                    <option value="ready">Ready for Pickup</option>
                                </select>
                            </td>
                        </tr>
                        <!-- Row 3 -->
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-bold text-sm text-slate-900">#RO-8819</div>
                                <div class="text-xs text-slate-500">2023 Tesla Model S • Pearl White</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-slate-700">Kenji Watanabe</div>
                                <div class="text-[10px] text-slate-400 uppercase font-bold">Standard</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <img alt="tech-3" class="w-6 h-6 rounded-full grayscale"
                                        data-alt="professional portrait of a male technician in a clean uniform"
                                        src="https://lh3.googleusercontent.com/aida-public/AB6AXuB_hh34hlEuQi2K5_07vAqOfwRuFEXBJy_Ufa-bIyTjkC7yBBOH7kq7eea5oEVQoGl2EmJacPgYsrRPwIuzXh5VMAhsICpGp-12GhINgXKTxFxgEHnAdrLVUqc5UL48hEBmEPrG1Om79bflZudlRLlr_Ar1drACPifDXPNGpaEzr1OjsMCDmwNPwDS9nzhbQ1X5pEftAHiKp6fVNGGM2G7NHGD_l5q5_1SteG7XvlOm2GouZDKecG-BAmNzPlVi-H38-oLKQ5w163KW" />
                                    <span class="text-sm font-medium text-slate-600">Sam L.</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm font-bold text-slate-500">Bay 09</td>
                            <td class="px-6 py-4">
                                <select
                                    class="bg-error-container border-none text-on-error-container text-xs font-bold py-1.5 px-3 rounded-lg focus:ring-0 cursor-pointer w-40">
                                    <option value="diagnostics">Diagnostics</option>
                                    <option value="in_progress">In Progress</option>
                                    <option selected="" value="waiting_parts">Waiting for Parts</option>
                                    <option value="quality_check">Quality Check</option>
                                    <option value="ready">Ready for Pickup</option>
                                </select>
                            </td>
                        </tr>
                        <!-- Row 4 -->
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-bold text-sm text-slate-900">#RO-8812</div>
                                <div class="text-xs text-slate-500">2022 Porsche 911 • Guards Red</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-slate-700">Linda Hamilton</div>
                                <div class="text-[10px] text-slate-400 uppercase font-bold">Emergency</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <img alt="tech-4" class="w-6 h-6 rounded-full grayscale"
                                        data-alt="headshot of a senior mechanic with years of experience visible in a confident smile"
                                        src="https://lh3.googleusercontent.com/aida-public/AB6AXuAXEHw19mWSCCJS_HU99K6rM0QRhwxxndjhsyqArDrsm4_UorSXQzkhvgsCPgEVJNsYHFXR-d7yshP5u9vOX46p6MXDVgUiduEJ9SJySZZIcJbf_G85HVYdArSF93d3xmCBiD7w5Nslo4XHe1ZFONMmmZsE1OfEgBpJneffUfkbBQ3529Ed3ronmhf6MW36wKD3SZElYmMLjHqSwpm5bvxxHKz0woFHZdcWf6k741zsGg41ENZxP7QPu7KVg6O5itpaXu3k6cdQ3UwS" />
                                    <span class="text-sm font-medium text-slate-600">Marcus T.</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm font-bold text-slate-500">Bay 02</td>
                            <td class="px-6 py-4">
                                <select
                                    class="bg-slate-100 border-none text-slate-800 text-xs font-bold py-1.5 px-3 rounded-lg focus:ring-0 cursor-pointer w-40">
                                    <option value="diagnostics">Diagnostics</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="waiting_parts">Waiting for Parts</option>
                                    <option selected="" value="quality_check">Quality Check</option>
                                    <option value="ready">Ready for Pickup</option>
                                </select>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4 bg-slate-50/30 border-t border-slate-100 flex items-center justify-between">
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Showing 4 of 12 jobs in
                    progress</span>
                <div class="flex gap-2">
                    <button
                        class="p-1.5 rounded border border-slate-200 bg-white text-slate-400 hover:text-primary transition-colors">
                        <span class="material-symbols-outlined text-sm">chevron_left</span>
                    </button>
                    <button
                        class="p-1.5 rounded border border-slate-200 bg-white text-slate-400 hover:text-primary transition-colors">
                        <span class="material-symbols-outlined text-sm">chevron_right</span>
                    </button>
                </div>
            </div>
        </section>
        <!-- Active Repair Jobs Table -->
        <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Active Repair Jobs</h3>
                    <p class="text-xs text-slate-500 font-medium">Full archive and management of all active work orders.
                    </p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-slate-50/50">
                            <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">Order
                                Details</th>
                            <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">
                                Services</th>
                            <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">Est.
                                Total</th>
                            <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">Labor
                                Hrs</th>
                            <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-bold text-sm text-slate-900">#RO-8805</div>
                                <div class="text-xs text-slate-500">2020 Land Rover Defender</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        class="px-2 py-0.5 bg-slate-100 text-[10px] font-bold text-slate-600 rounded">Full
                                        Service</span>
                                    <span
                                        class="px-2 py-0.5 bg-slate-100 text-[10px] font-bold text-slate-600 rounded">Brake
                                        Pad Replace</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm font-bold text-slate-900">$1,450.00</td>
                            <td class="px-6 py-4 text-sm font-medium text-slate-600">3.5 / 5.0</td>
                            <td class="px-6 py-4">
                                <button class="text-primary hover:underline text-xs font-bold">View Order</button>
                            </td>
                        </tr>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-bold text-sm text-slate-900">#RO-8801</div>
                                <div class="text-xs text-slate-500">2022 Mercedes-AMG G63</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        class="px-2 py-0.5 bg-slate-100 text-[10px] font-bold text-slate-600 rounded">Tire
                                        Rotation</span>
                                    <span
                                        class="px-2 py-0.5 bg-slate-100 text-[10px] font-bold text-slate-600 rounded">Oil
                                        Change</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm font-bold text-slate-900">$480.20</td>
                            <td class="px-6 py-4 text-sm font-medium text-slate-600">1.2 / 1.5</td>
                            <td class="px-6 py-4">
                                <button class="text-primary hover:underline text-xs font-bold">View Order</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
        </div>
    </main>
    <!-- FAB for quick action -->
    <button
        class="fixed bottom-8 right-8 w-14 h-14 bg-primary text-white rounded-full flex items-center justify-center shadow-lg hover:scale-105 active:scale-95 transition-all z-40 group">
        <span class="material-symbols-outlined text-3xl group-hover:rotate-90 transition-transform">add</span>
    </button>
</body>

</html>