<!DOCTYPE html>

<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Audit Logs | Cobalt Precision Superadmin</title>
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
                        "secondary-fixed": "#e2e8f0",
                        "on-secondary": "#ffffff",
                        "on-secondary-container": "#1e293b",
                        "tertiary": "#f59e0b",
                        "on-tertiary-fixed-variant": "#9a3412",
                        "inverse-surface": "#1e293b",
                        "on-tertiary": "#ffffff",
                        "primary-container": "#eef2ff",
                        "on-error-container": "#991b1b",
                        "tertiary-fixed": "#ffedd5",
                        "tertiary-container": "#fef3c7",
                        "outline": "#e2e8f0",
                        "on-secondary-fixed": "#0f172a",
                        "on-surface-variant": "#64748b",
                        "surface-container-lowest": "#ffffff",
                        "error-container": "#fee2e2",
                        "tertiary-fixed-dim": "#fed7aa",
                        "surface-bright": "#ffffff",
                        "secondary-container": "#f1f5f9",
                        "on-primary-fixed": "#1e3a8a",
                        "surface-tint": "#1152d4",
                        "surface-dim": "#d9d9e4",
                        "error": "#ef4444",
                        "on-tertiary-container": "#92400e",
                        "primary-fixed": "#dbeafe",
                        "on-secondary-fixed-variant": "#334155",
                        "surface": "#f6f6f8",
                        "background": "#f6f6f8",
                        "outline-variant": "#cbd5e1",
                        "on-primary": "#ffffff",
                        "inverse-on-surface": "#f8fafc",
                        "on-tertiary-fixed": "#7c2d12",
                        "surface-container": "#ffffff",
                        "secondary": "#475569",
                        "primary": "#1152d4",
                        "on-primary-container": "#1152d4",
                        "surface-container-highest": "#ffffff",
                        "primary-fixed-dim": "#bfdbfe",
                        "on-surface": "#0f172a",
                        "on-background": "#0f172a",
                        "inverse-primary": "#b4c5ff",
                        "surface-container-high": "#ffffff",
                        "surface-variant": "#f1f5f9",
                        "on-primary-fixed-variant": "#1d4ed8",
                        "on-error": "#ffffff",
                        "surface-container-low": "#ffffff",
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
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }

        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-surface text-on-surface antialiased overflow-x-hidden">
    <!-- Side Navigation -->
    <aside
        class="flex flex-col fixed left-0 top-0 h-full z-40 h-screen w-64 border-r border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 font-['Inter'] antialiased tracking-tight shadow-sm dark:shadow-none">
        <!-- Brand Header -->
        <div class="p-6 flex items-center gap-3">
            <div class="bg-primary rounded-lg p-2 text-white">
                <span class="material-symbols-outlined block text-2xl">directions_car</span>
            </div>
            <h2 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white leading-none">
                RapidRepair <span class="text-primary">SuperAdmin</span>
            </h2>
        </div>
           <!-- Navigation Links -->
        <nav class="flex-1 px-4 space-y-1 mt-4">
            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors rounded-lg active:scale-95"
                href="superadd.php">
                <span class="material-symbols-outlined" data-icon="dashboard">dashboard</span>
                <span class="text-sm">Dashboard</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors rounded-lg active:scale-95"
                href="superaddtenants.php">
                <span class="material-symbols-outlined" data-icon="groups">groups</span>
                <span class="text-sm">Tenants</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors rounded-lg active:scale-95"
                href="superreports.php">
                <span class="material-symbols-outlined" data-icon="bar_chart">bar_chart</span>
                <span class="text-sm">Reports</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors rounded-lg active:scale-95"
                href="subscriptionmanage.php">
                <span class="material-symbols-outlined" data-icon="subscriptions">subscriptions</span>
                <span class="text-sm">Subscriptions</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors rounded-lg active:scale-95"
                href="supersalesreport.php">
                <span class="material-symbols-outlined" data-icon="monitoring">monitoring</span>
                <span class="text-sm">Sales Reports</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400 font-bold border-r-4 border-blue-700 dark:border-blue-500 rounded-lg active:scale-95"
                href="superauditlogs.php">
                <span class="material-symbols-outlined" data-icon="assignment">assignment</span>
                <span class="text-sm">Audit Logs</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors rounded-lg active:scale-95"
                href="supersettings.php">
                <span class="material-symbols-outlined" data-icon="settings">settings</span>
                <span class="text-sm">Settings</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors rounded-lg active:scale-95"
                href="backup_restore.php">
                <span class="material-symbols-outlined" data-icon="backup"
                    style="font-variation-settings: 'FILL' 1;">backup</span>
                <span class="text-sm">System Backup</span>
            </a>
        </nav>
        <!-- Footer Actions matching SCREEN_11 -->
        <div class="p-4 border-t border-slate-100 dark:border-slate-800 space-y-2">
            <div
                class="flex items-center gap-3 p-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer transition-colors">
                <div class="w-10 h-10 rounded-full bg-slate-200 dark:bg-slate-700 bg-cover bg-center"
                    data-alt="Alex Rivera headshot professional portrait"
                    style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuAA7ZvS0RT24pYl7zsQUKsnC9inrzmoUQVQC8PvdcW5_q4FtMWEC8ZD9Ke8mBa8iRwi4vfG0NbuLhEY9U_mYTQt3gBMRoNS0jNV_aJYQ-QCLtauVwWdyP53SHmFLjb5bQvwjbvvF24yHFp3moy4K6rJ0tVvtMIzdIUNohESEbLUilTPScnQYQQutAW0bzWhFZkGsX1GwwAl_2_9yXjauFnRNg0uTHfeR3lnfDRxLlk9Jo_hIr7N64rr5SWZq57QEfMdbFLkygzUgb-A')">
                </div>
                <div class="flex flex-col min-w-0">
                    <h3 class="text-sm font-semibold truncate text-slate-900 dark:text-white">Alex Rivera</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 truncate">Superadmin</p>
                </div>
            </div>
            <div
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-900/10 transition-colors cursor-pointer">
                <span class="material-symbols-outlined">logout</span>
                <p class="text-sm font-medium">Logout</p>
            </div>
        </div>
    </aside>
    <!-- TopNavBar matching SCREEN_11 -->
    <header
        class="flex items-center justify-between px-8 sticky top-0 z-30 ml-64 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md h-16 border-b border-slate-200 dark:border-slate-800">
        <div class="flex items-center gap-4">
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-on-surface-variant">
                    <span class="material-symbols-outlined text-lg" data-icon="search">search</span>
                </span>
                <input
                    class="pl-10 pr-4 py-1.5 bg-slate-100 dark:bg-slate-800 border-none text-sm rounded-lg focus:ring-2 focus:ring-primary w-64 transition-all outline-none"
                    placeholder="Search audit logs by user, action, or tenant..." type="text" />
            </div>
        </div>
        <div class="flex items-center gap-4">
            <button class="p-2 text-slate-500 hover:text-primary transition-colors">
                <span class="material-symbols-outlined">notifications</span>
            </button>
            <button class="p-2 text-slate-500 hover:text-primary transition-colors">
                <span class="material-symbols-outlined">help_outline</span>
            </button>
        </div>
    </header>
    <!-- Main Content -->
    <main class="ml-64 p-8 min-h-[calc(100vh-16px)] bg-surface">
        <div class="max-w-7xl mx-auto">
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
                <div>
                    <h1 class="text-[1.875rem] font-black text-on-surface tracking-tight font-headline">Audit Logs</h1>
                    <p class="text-on-surface-variant text-sm mt-1">Comprehensive system activity monitoring and
                        compliance tracking.</p>
                </div>
                <div class="flex items-center gap-3">
                    <button
                        class="inline-flex items-center px-4 py-2 bg-white border border-outline hover:bg-slate-50 text-on-surface text-sm font-semibold rounded-lg transition-all shadow-sm">
                        <span class="material-symbols-outlined mr-2 text-lg" data-icon="download">download</span>
                        Export CSV
                    </button>
                    <button
                        class="inline-flex items-center px-4 py-2 bg-white border border-outline hover:bg-slate-50 text-on-surface text-sm font-semibold rounded-lg transition-all shadow-sm">
                        <span class="material-symbols-outlined mr-2 text-lg"
                            data-icon="picture_as_pdf">picture_as_pdf</span>
                        Export PDF
                    </button>
                </div>
            </div>
            <!-- Filter Bar -->
            <div class="bg-white rounded-xl border border-slate-200 p-4 mb-6 shadow-sm">
                <div class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-5 gap-4">
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest px-1">Date
                            Range</label>
                        <select
                            class="w-full bg-slate-50 border-slate-200 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 py-2">
                            <option>Last 24 Hours</option>
                            <option>Last 7 Days</option>
                            <option selected="">Last 30 Days</option>
                            <option>Custom Range</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest px-1">Action
                            Category</label>
                        <select
                            class="w-full bg-slate-50 border-slate-200 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 py-2">
                            <option>All Actions</option>
                            <option>Create</option>
                            <option>Update</option>
                            <option>Delete</option>
                            <option>Security/Login</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label
                            class="text-[10px] font-bold text-slate-500 uppercase tracking-widest px-1">Admin/User</label>
                        <select
                            class="w-full bg-slate-50 border-slate-200 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 py-2">
                            <option>All Users</option>
                            <option>Superadmins</option>
                            <option>Tenant Admins</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label
                            class="text-[10px] font-bold text-slate-500 uppercase tracking-widest px-1">Tenant</label>
                        <select
                            class="w-full bg-slate-50 border-slate-200 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 py-2">
                            <option>Global System</option>
                            <option>Acme Corp</option>
                            <option>Global Industries</option>
                            <option>TechNova</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button
                            class="w-full bg-blue-700 hover:bg-blue-800 text-white font-bold text-sm py-2 px-4 rounded-lg transition-colors flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-lg" data-icon="filter_list">filter_list</span>
                            Apply Filters
                        </button>
                    </div>
                </div>
            </div>
            <!-- Audit Table -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200">
                            <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">User /
                                Administrator</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Action
                            </th>
                            <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Target
                                Entity</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Change
                                Details</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                                Timestamp</th>
                            <th
                                class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-right">
                                Reference</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <!-- Log Entry 1: Create -->
                        <tr class="hover:bg-slate-50 transition-colors group">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="h-8 w-8 rounded bg-primary-container text-primary flex items-center justify-center font-bold text-xs">
                                        AD</div>
                                    <div>
                                        <div class="text-sm font-bold text-on-surface">Alex Donovan</div>
                                        <div class="text-xs text-on-surface-variant">Superadmin</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 uppercase tracking-wider">
                                    Create
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-on-surface">Tenant: SolarSystems LLC</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-on-surface-variant max-w-xs truncate">Provisioned new
                                    infrastructure cluster for Western region.</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-on-surface">Oct 24, 2023</div>
                                <div class="text-[10px] text-on-surface-variant">14:22:15.034</div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button class="text-slate-400 hover:text-blue-600 transition-colors">
                                    <span class="material-symbols-outlined" data-icon="info">info</span>
                                </button>
                            </td>
                        </tr>
                        <!-- Log Entry 2: Update -->
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="h-8 w-8 rounded bg-slate-100 text-slate-500 flex items-center justify-center font-bold text-xs">
                                        SK</div>
                                    <div>
                                        <div class="text-sm font-bold text-on-surface">Sarah Koppel</div>
                                        <div class="text-xs text-on-surface-variant">Financial Admin</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 uppercase tracking-wider">
                                    Update
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-on-surface">Billing Configuration</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-on-surface-variant max-w-xs truncate">Modified subscription
                                    tier from 'Pro' to 'Enterprise' for Tenant #882.</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-on-surface">Oct 24, 2023</div>
                                <div class="text-[10px] text-on-surface-variant">13:45:10.992</div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button class="text-slate-400 hover:text-blue-600 transition-colors">
                                    <span class="material-symbols-outlined" data-icon="info">info</span>
                                </button>
                            </td>
                        </tr>
                        <!-- Log Entry 3: Delete (Critical) -->
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="h-8 w-8 rounded bg-red-50 text-red-600 flex items-center justify-center font-bold text-xs">
                                        JM</div>
                                    <div>
                                        <div class="text-sm font-bold text-on-surface">James Miller</div>
                                        <div class="text-xs text-on-surface-variant">System Architect</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-800 uppercase tracking-wider">
                                    Delete
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-on-surface">Legacy API Key</div>
                            </td>
                            <td class="px-6 py-4">
                                <div
                                    class="text-sm text-on-surface-variant max-w-xs truncate font-mono bg-slate-50 p-1 rounded">
                                    DEPRECATED_V1_KEY_...</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-on-surface">Oct 24, 2023</div>
                                <div class="text-[10px] text-on-surface-variant">11:12:05.118</div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button class="text-slate-400 hover:text-blue-600 transition-colors">
                                    <span class="material-symbols-outlined" data-icon="info">info</span>
                                </button>
                            </td>
                        </tr>
                        <!-- Log Entry 4: Login -->
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="h-8 w-8 rounded bg-slate-100 text-slate-500 flex items-center justify-center font-bold text-xs">
                                        MW</div>
                                    <div>
                                        <div class="text-sm font-bold text-on-surface">Marcus Wong</div>
                                        <div class="text-xs text-on-surface-variant">Support Tier 2</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600 uppercase tracking-wider">
                                    Login
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-on-surface">System Access</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-on-surface-variant">Successful authentication from IP
                                    192.168.1.44 (San Francisco, CA)</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-on-surface">Oct 24, 2023</div>
                                <div class="text-[10px] text-on-surface-variant">09:05:32.441</div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button class="text-slate-400 hover:text-blue-600 transition-colors">
                                    <span class="material-symbols-outlined" data-icon="info">info</span>
                                </button>
                            </td>
                        </tr>
                        <!-- Log Entry 5: Update -->
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="h-8 w-8 rounded bg-primary-container text-primary flex items-center justify-center font-bold text-xs">
                                        AD</div>
                                    <div>
                                        <div class="text-sm font-bold text-on-surface">Alex Donovan</div>
                                        <div class="text-xs text-on-surface-variant">Superadmin</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 uppercase tracking-wider">
                                    Update
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-on-surface">System Setting: Backup Frequency</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-on-surface-variant">Changed global backup frequency from 24h to
                                    12h.</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-on-surface">Oct 23, 2023</div>
                                <div class="text-[10px] text-on-surface-variant">22:40:01.000</div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button class="text-slate-400 hover:text-blue-600 transition-colors">
                                    <span class="material-symbols-outlined" data-icon="info">info</span>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <!-- Pagination -->
                <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
                    <div class="text-sm text-on-surface-variant">
                        Showing <span class="font-bold text-on-surface">1 - 5</span> of <span
                            class="font-bold text-on-surface">1,248</span> logs
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            class="p-1 rounded hover:bg-slate-200 text-slate-400 transition-colors disabled:opacity-30"
                            disabled="">
                            <span class="material-symbols-outlined" data-icon="chevron_left">chevron_left</span>
                        </button>
                        <button
                            class="h-8 w-8 flex items-center justify-center rounded bg-blue-700 text-white text-xs font-bold">1</button>
                        <button
                            class="h-8 w-8 flex items-center justify-center rounded hover:bg-slate-200 text-on-surface text-xs font-bold transition-colors">2</button>
                        <button
                            class="h-8 w-8 flex items-center justify-center rounded hover:bg-slate-200 text-on-surface text-xs font-bold transition-colors">3</button>
                        <span class="px-1 text-slate-400">...</span>
                        <button
                            class="h-8 w-8 flex items-center justify-center rounded hover:bg-slate-200 text-on-surface text-xs font-bold transition-colors">250</button>
                        <button class="p-1 rounded hover:bg-slate-200 text-slate-400 transition-colors">
                            <span class="material-symbols-outlined" data-icon="chevron_right">chevron_right</span>
                        </button>
                    </div>
                </div>
            </div>
            <!-- Summary Stats (Bento Grid Style) -->
            <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex items-center gap-4">
                    <div class="h-12 w-12 rounded-lg bg-blue-50 flex items-center justify-center text-blue-700">
                        <span class="material-symbols-outlined" data-icon="security">security</span>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Active Sessions
                        </div>
                        <div class="text-2xl font-black text-on-surface">142</div>
                    </div>
                </div>
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex items-center gap-4">
                    <div class="h-12 w-12 rounded-lg bg-emerald-50 flex items-center justify-center text-emerald-700">
                        <span class="material-symbols-outlined" data-icon="history">history</span>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Logs Today</div>
                        <div class="text-2xl font-black text-on-surface">3,892</div>
                    </div>
                </div>
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex items-center gap-4">
                    <div class="h-12 w-12 rounded-lg bg-amber-50 flex items-center justify-center text-amber-700">
                        <span class="material-symbols-outlined" data-icon="warning">warning</span>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Critical Actions
                        </div>
                        <div class="text-2xl font-black text-on-surface">18</div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>

</html>