<!DOCTYPE html>

<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Shop Settings | Cobalt Precision Admin</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&amp;display=swap"
        rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
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
                        "inverse-primary": "#b4c5ff",
                        "primary": "#1152d4",
                        "secondary": "#475569",
                        "surface-bright": "#ffffff",
                        "on-tertiary-container": "#92400e",
                        "tertiary-fixed": "#ffedd5",
                        "surface-container-lowest": "#ffffff",
                        "primary-fixed": "#dbeafe",
                        "on-surface-variant": "#64748b",
                        "surface-container-highest": "#ffffff",
                        "inverse-surface": "#1e293b",
                        "surface-container": "#ffffff",
                        "primary-container": "#eef2ff",
                        "tertiary": "#f59e0b",
                        "surface-dim": "#d9d9e4",
                        "on-primary-container": "#1152d4",
                        "on-surface": "#0f172a",
                        "surface-container-low": "#ffffff",
                        "on-secondary-fixed": "#0f172a",
                        "on-secondary": "#ffffff",
                        "surface-tint": "#1152d4",
                        "tertiary-container": "#fef3c7",
                        "on-secondary-fixed-variant": "#334155",
                        "on-primary": "#ffffff",
                        "error": "#ef4444",
                        "on-tertiary": "#ffffff",
                        "on-tertiary-fixed-variant": "#9a3412",
                        "on-secondary-container": "#1e293b",
                        "tertiary-fixed-dim": "#fed7aa",
                        "secondary-fixed": "#e2e8f0",
                        "on-primary-fixed-variant": "#1d4ed8",
                        "error-container": "#fee2e2",
                        "surface": "#f6f6f8",
                        "on-error-container": "#991b1b",
                        "primary-fixed-dim": "#bfdbfe",
                        "surface-variant": "#f1f5f9",
                        "on-background": "#0f172a",
                        "surface-container-high": "#ffffff",
                        "secondary-fixed-dim": "#cbd5e1",
                        "outline-variant": "#cbd5e1",
                        "on-primary-fixed": "#1e3a8a",
                        "on-error": "#ffffff",
                        "background": "#f6f6f8",
                        "on-tertiary-fixed": "#7c2d12",
                        "secondary-container": "#f1f5f9",
                        "outline": "#e2e8f0",
                        "inverse-on-surface": "#f8fafc"
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

<body class="bg-surface text-on-surface antialiased">
    <div class="flex h-screen overflow-hidden">
        <!-- Side Navigation Shell -->
        <!-- Updated SideNavBar Implementation based on SCREEN_106 -->
        <aside
            class="w-64 flex-shrink-0 border-r border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 h-screen sticky top-0 flex flex-col overflow-y-auto">
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
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-primary/10 text-primary font-medium"
                        href="settingsadmin.php">
                        <span class="material-symbols-outlined text-[22px]">settings</span>
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
                        data-alt="User avatar for admin profile picture"
                        src="https://lh3.googleusercontent.com/aida-public/AB6AXuDeh_igjzq55wP-MQUqlN5a7g7ERzT91RAZllys2xTPdmr_K6ugTc7NEPOG48E87bvkhiEKuMOE9TZ0njKOCLQ7Nhccix3HVxsYdR2tXeyTCkjam7s1q8ngQOzslzdGRLROqouBtkGpnSewuAyIscdu673vBatOqI9TKHP1RCzarhxH8GqVYpWDnccgDrczUMroOqof3VFA7U9HLzMcDyURIrkC9dU2KtSkusqfbOvLaUs_zR14qlpZVSgASdGK8sw1SCeDf4A38q-8" />
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold truncate">Marcus Smith</p>
                    <p class="text-xs text-slate-500 truncate">Shop Manager</p>
                </div>
                <form method="post" action="../logout/logout.php" class="inline">
                    <input type="hidden" name="action" value="confirm" />
                    <button type="submit" class="text-slate-400 hover:text-error transition-colors" title="Logout">
                        <span class="material-symbols-outlined text-xl">logout</span>
                    </button>
                </form>
            </div>
        </div>
    </aside>
        <!-- Main Content Canvas -->
        <main class="flex-1 overflow-y-auto flex flex-col">
        <!-- Top Nav Bar -->
        <header
            class="sticky top-0 z-40 w-full border-b border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md flex items-center justify-between px-8 h-16">
            <div class="flex items-center gap-6">
                <h2 class="text-lg font-black text-slate-900 dark:white tracking-tight">Settings Management</h2>
                <div class="relative hidden lg:block">
                    <span
                        class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">search</span>
                    <input
                        class="bg-surface-variant border-none rounded-lg pl-10 pr-4 py-1.5 text-sm w-64 focus:ring-2 focus:ring-primary/20"
                        placeholder="Search settings..." type="text" />
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
        <!-- Content Area -->
        <div class="p-8 space-y-8 flex-1">
            <!-- Bento Grid Section: Stats Overview -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="bg-white border border-slate-200 p-6 rounded-lg shadow-sm flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-2 bg-primary-container rounded-lg">
                            <span class="material-symbols-outlined text-primary text-xl">group</span>
                        </div>
                        <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2 py-1 rounded-full">+2 this
                            month</span>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-500 mb-1">Total Staff</p>
                        <p class="text-2xl font-black text-slate-900">12 Members</p>
                    </div>
                </div>
                <div class="bg-white border border-slate-200 p-6 rounded-lg shadow-sm flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-2 bg-primary-container rounded-lg">
                            <span class="material-symbols-outlined text-primary text-xl">construction</span>
                        </div>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-500 mb-1">Active Services</p>
                        <p class="text-2xl font-black text-slate-900">48 Catalog Items</p>
                    </div>
                </div>
                <div class="md:col-span-2 bg-slate-900 text-white p-6 rounded-lg shadow-sm relative overflow-hidden">
                    <div class="relative z-10 flex flex-col h-full justify-between">
                        <div>
                            <h3 class="text-lg font-bold mb-1">System Integrity</h3>
                            <p class="text-slate-400 text-sm">All operational modules are running at peak efficiency.
                            </p>
                        </div>
                        <div class="flex gap-4 mt-4">
                            <div class="flex items-center gap-2">
                                <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                                <span class="text-xs font-mono uppercase tracking-widest text-slate-300">Live
                                    Server</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                                <span class="text-xs font-mono uppercase tracking-widest text-slate-300">Sync
                                    Active</span>
                            </div>
                        </div>
                    </div>
                    <!-- Decorative Background Texture -->
                    <div class="absolute -right-10 -bottom-10 opacity-10">
                        <span class="material-symbols-outlined text-[160px]"
                            style="font-variation-settings: 'FILL' 1;">precision_manufacturing</span>
                    </div>
                </div>
            </div>
            <!-- User Role Management Section -->
            <section class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-xl font-bold text-slate-900 tracking-tight">User Role Management</h3>
                        <p class="text-sm text-slate-500">Manage permissions and access levels for shop technicians and
                            office staff.</p>
                    </div>
                    <button id="addRoleBtn"
                        class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-bold flex items-center gap-2 hover:bg-blue-800 transition-colors shadow-sm">
                        <span class="material-symbols-outlined text-sm">add</span>
                        Add New User
                    </button>
                </div>
                <div class="bg-white border border-slate-200 rounded-lg shadow-sm overflow-hidden">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200">
                                <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Name
                                </th>
                                <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Username
                                </th>
                                <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Role
                                </th>
                                <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Access Scope
                                </th>
                                <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Status
                                </th>
                                <th
                                    class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-right">
                                    Actions</th>
                            </tr>
                        </thead>
                        <tbody id="rolesTableBody" class="divide-y divide-slate-100">
                            <tr>
                                <td class="px-6 py-8 text-center text-sm text-slate-500" colspan="6">Loading roles...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
            <!-- Service Management Section -->
            <section class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-xl font-bold text-slate-900 tracking-tight">Service Management</h3>
                        <p class="text-sm text-slate-500">Configure service pricing, durations, and category tags.</p>
                    </div>
                    <button id="addServiceBtn"
                        class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-bold flex items-center gap-2 hover:bg-blue-800 transition-colors shadow-sm">
                        <span class="material-symbols-outlined text-sm">add_circle</span>
                        Add New Service
                    </button>
                </div>
                <div id="servicesContainer" class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4">
                    <!-- Services will be loaded dynamically here -->
                    <div class="col-span-full flex justify-center items-center py-12">
                        <div class="text-center">
                            <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-4">
                                <span class="material-symbols-outlined text-slate-400">hourglass_empty</span>
                            </div>
                            <p class="text-slate-500 font-medium">Loading services...</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Activity Logs Link -->
            <section class="space-y-4">
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-bold text-slate-900 tracking-tight flex items-center gap-2">
                                <span class="material-symbols-outlined text-blue-600">history</span>
                                System Activity Logs
                            </h3>
                            <p class="text-sm text-slate-600 mt-1">Monitor all system activities, user actions, and data changes in your shop.</p>
                        </div>
                        <a href="tenantslogs.php<?= isset($_GET['shop']) ? '?shop=' . urlencode($_GET['shop']) : '' ?>"
                            class="bg-primary text-white px-6 py-3 rounded-lg font-bold flex items-center gap-2 hover:bg-blue-800 transition-colors shadow-sm whitespace-nowrap">
                            <span class="material-symbols-outlined text-sm">arrow_forward</span>
                            View Activity Logs
                        </a>
                    </div>
                </div>
            </section>
        </div>
        <!-- Footer: Performance Summary -->
        <footer class="bg-white border-t border-slate-200 px-8 py-4 flex items-center justify-between">
            <div class="flex gap-12">
                <div class="flex items-center gap-3">
                    <div class="p-1.5 bg-blue-50 rounded">
                        <span class="material-symbols-outlined text-primary text-base">analytics</span>
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-400 font-black uppercase tracking-widest">Shop Utilization</p>
                        <p class="text-sm font-bold text-slate-900">84.2% <span
                                class="text-emerald-500 text-[10px] font-bold">↑ 2.4%</span></p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="p-1.5 bg-blue-50 rounded">
                        <span class="material-symbols-outlined text-primary text-base">monetization_on</span>
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-400 font-black uppercase tracking-widest">Revenue Per Hour</p>
                        <p class="text-sm font-bold text-slate-900">PHP 218.40 <span
                            class="text-slate-400 text-[10px] font-bold">(Target PHP 200)</span></p>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-4 text-xs font-medium text-slate-400">
                <span>© 2024 Cobalt Precision Systems</span>
                <span class="w-1 h-1 rounded-full bg-slate-300"></span>
                <span>v2.4.1-stable</span>
            </div>
        </footer>
    </main>
    </div>

    <!-- Service Modal -->
    <div id="serviceModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-lg max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between p-8 border-b border-slate-200 sticky top-0 bg-white">
                <div>
                    <h3 id="modalTitle" class="text-2xl font-bold text-slate-900">Add New Service</h3>
                    <p class="text-sm text-slate-500 mt-1">Add or edit service details including pricing and duration</p>
                </div>
                <button id="closeModalBtn" class="text-slate-400 hover:text-slate-600 p-2">
                    <span class="material-symbols-outlined text-2xl">close</span>
                </button>
            </div>
            <form id="serviceForm" class="p-8">
                <input type="hidden" id="serviceId" name="service_id">
                
                <!-- Two Container Layout -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Left Container: Basic Information -->
                    <div class="space-y-5">
                        <div class="bg-slate-50 p-6 rounded-lg border border-slate-200">
                            <h4 class="font-bold text-slate-900 mb-4 text-sm uppercase tracking-wider text-slate-600">Basic Information</h4>
                            
                            <div class="space-y-4">
                                <div>
                                    <label for="serviceName" class="block text-sm font-semibold text-slate-700 mb-2">Service Name *</label>
                                    <input type="text" id="serviceName" name="service_name" 
                                        class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary" 
                                        placeholder="e.g., Oil Change" required>
                                </div>

                                <div>
                                    <label for="serviceCategory" class="block text-sm font-semibold text-slate-700 mb-2">Category</label>
                                    <select id="serviceCategory" name="category" 
                                        class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                                        <option value="">Select a category</option>
                                        <option value="Engine">Engine</option>
                                        <option value="Electrical">Electrical</option>
                                        <option value="Maintenance">Maintenance</option>
                                        <option value="Brakes">Brakes</option>
                                        <option value="Suspension">Suspension</option>
                                        <option value="Transmission">Transmission</option>
                                        <option value="Cooling System">Cooling System</option>
                                        <option value="Diagnostics">Diagnostics</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>

                                <!-- Custom Category Field (Hidden by default) -->
                                <div id="customCategoryContainer" class="hidden">
                                    <label for="customCategory" class="block text-sm font-semibold text-slate-700 mb-2">Custom Category Name</label>
                                    <input type="text" id="customCategory" name="custom_category" 
                                        class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary" 
                                        placeholder="Enter custom category name">
                                    <p class="text-xs text-slate-500 mt-1">This will be saved as a custom category</p>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label for="servicePrice" class="block text-sm font-semibold text-slate-700 mb-2">Price (PHP) *</label>
                                        <input type="number" id="servicePrice" name="price" step="0.01" min="0"
                                            class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary" 
                                            placeholder="0.00" required>
                                    </div>

                                    <div>
                                        <label for="serviceDuration" class="block text-sm font-semibold text-slate-700 mb-2">Duration (min)</label>
                                        <input type="number" id="serviceDuration" name="duration_minutes" min="0"
                                            class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary" 
                                            placeholder="60">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Container: Additional Details -->
                    <div class="space-y-5">
                        <div class="bg-slate-50 p-6 rounded-lg border border-slate-200">
                            <h4 class="font-bold text-slate-900 mb-4 text-sm uppercase tracking-wider text-slate-600">Additional Details</h4>
                            
                            <div class="space-y-4">
                                <div>
                                    <label for="serviceDescription" class="block text-sm font-semibold text-slate-700 mb-2">Description</label>
                                    <textarea id="serviceDescription" name="description" rows="4"
                                        class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary resize-none" 
                                        placeholder="Detailed description of the service..."></textarea>
                                </div>

                                <div>
                                    <label for="serviceStatus" class="block text-sm font-semibold text-slate-700 mb-2">Status</label>
                                    <select id="serviceStatus" name="status" 
                                        class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                                        <option value="Active">Active</option>
                                        <option value="Inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex gap-3 pt-8 border-t border-slate-200 mt-8">
                    <button type="button" id="cancelBtn" class="flex-1 px-6 py-3 border border-slate-300 text-slate-700 font-semibold rounded-lg hover:bg-slate-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="flex-1 px-6 py-3 bg-primary text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined">check_circle</span>
                        Save Service
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Role Modal -->
    <div id="roleModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between p-6 border-b border-slate-200 sticky top-0 bg-white">
                <div>
                    <h3 id="roleModalTitle" class="text-2xl font-bold text-slate-900">Add New User</h3>
                    <p class="text-sm text-slate-500 mt-1">Create or edit role-based user access.</p>
                </div>
                <button id="closeRoleModalBtn" class="text-slate-400 hover:text-slate-600 p-2">
                    <span class="material-symbols-outlined text-2xl">close</span>
                </button>
            </div>
            <form id="roleForm" class="p-6 space-y-4">
                <input type="hidden" id="roleId" name="role_id">

                <div class="bg-slate-50 p-4 rounded-lg border border-slate-200 mb-4">
                    <h4 class="text-sm font-bold text-slate-700 mb-4">Personal Information</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="roleFirstName" class="block text-sm font-semibold text-slate-700 mb-2">First Name *</label>
                            <input type="text" id="roleFirstName" name="first_name"
                                class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
                                placeholder="e.g., James" required>
                        </div>
                        <div>
                            <label for="roleLastName" class="block text-sm font-semibold text-slate-700 mb-2">Last Name *</label>
                            <input type="text" id="roleLastName" name="last_name"
                                class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
                                placeholder="e.g., Davis" required>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-50 p-4 rounded-lg border border-slate-200">
                    <h4 class="text-sm font-bold text-slate-700 mb-4">Account Information</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="roleUsername" class="block text-sm font-semibold text-slate-700 mb-2">Username *</label>
                            <input type="text" id="roleUsername" name="username"
                                class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
                                placeholder="e.g., jamesd" required>
                        </div>
                        <div>
                            <label for="roleEmail" class="block text-sm font-semibold text-slate-700 mb-2">Email *</label>
                            <input type="email" id="roleEmail" name="email"
                                class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
                                placeholder="name@shop.com" required>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label for="rolePassword" class="block text-sm font-semibold text-slate-700 mb-2">Password <span id="passwordHint" class="text-xs font-normal text-slate-400">*</span></label>
                            <input type="password" id="rolePassword" name="password"
                                class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
                                placeholder="Enter secure password">
                        </div>
                        <div>
                            <label for="roleScope" class="block text-sm font-semibold text-slate-700 mb-2">Access Scope</label>
                            <input type="text" id="roleScope" name="access_scope"
                                class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
                                placeholder="e.g., Shop Floor, Front Desk">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label for="roleStatus" class="block text-sm font-semibold text-slate-700 mb-2">Status</label>
                            <select id="roleStatus" name="status"
                                class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                        <div>
                            <label for="roleName" class="block text-sm font-semibold text-slate-700 mb-2">Role Title</label>
                            <input type="text" id="roleName" name="role_name"
                                class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
                                placeholder="e.g., Senior Technician">
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 pt-4 border-t border-slate-200">
                    <button type="button" id="cancelRoleBtn" class="flex-1 px-6 py-3 border border-slate-300 text-slate-700 font-semibold rounded-lg hover:bg-slate-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="flex-1 px-6 py-3 bg-primary text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined">check_circle</span>
                        Save User
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Role Management JavaScript
        class RoleManager {
            constructor() {
                this.modal = document.getElementById('roleModal');
                this.form = document.getElementById('roleForm');
                this.tableBody = document.getElementById('rolesTableBody');
                this.addBtn = document.getElementById('addRoleBtn');
                this.closeBtn = document.getElementById('closeRoleModalBtn');
                this.cancelBtn = document.getElementById('cancelRoleBtn');
                this.modalTitle = document.getElementById('roleModalTitle');
                this.roleId = document.getElementById('roleId');
                this.passwordInput = document.getElementById('rolePassword');
                this.passwordHint = document.getElementById('passwordHint');
            }

            init() {
                this.attachEventListeners();
                this.loadRoles();
            }

            attachEventListeners() {
                this.addBtn.addEventListener('click', () => this.openModal());
                this.closeBtn.addEventListener('click', () => this.closeModal());
                this.cancelBtn.addEventListener('click', () => this.closeModal());
                this.form.addEventListener('submit', (e) => this.handleFormSubmit(e));
            }

            openModal(roleId = null) {
                this.form.reset();
                this.roleId.value = '';

                if (roleId) {
                    this.modalTitle.textContent = 'Edit User Role';
                    this.passwordInput.required = false;
                    this.passwordInput.placeholder = 'Leave blank to keep current password';
                    this.passwordHint.textContent = '(optional when editing)';
                    this.loadRoleData(roleId);
                } else {
                    this.modalTitle.textContent = 'Add New User';
                    this.passwordInput.required = true;
                    this.passwordInput.placeholder = 'Enter secure password';
                    this.passwordHint.textContent = '*';
                }

                this.modal.classList.remove('hidden');
            }

            closeModal() {
                this.modal.classList.add('hidden');
                this.form.reset();
                this.roleId.value = '';
                this.passwordInput.required = true;
                this.passwordHint.textContent = '*';
                this.passwordInput.placeholder = 'Enter secure password';
            }

            async loadRoles() {
                try {
                    const response = await fetch('roles_handler.php?action=get_all');
                    const data = await response.json();

                    if (data.success) {
                        this.renderRoles(data.roles || []);
                    } else {
                        this.showError(data.message || 'Failed to load roles');
                    }
                } catch (error) {
                    this.showError('Error loading roles: ' + error.message);
                }
            }

            async loadRoleData(roleId) {
                try {
                    const response = await fetch('roles_handler.php?action=get_single&role_id=' + roleId);
                    const data = await response.json();

                    if (data.success) {
                        const role = data.role;
                        document.getElementById('roleFirstName').value = role.first_name || '';
                        document.getElementById('roleLastName').value = role.last_name || '';
                        document.getElementById('roleName').value = role.role_name || '';
                        document.getElementById('roleUsername').value = role.username || '';
                        document.getElementById('roleEmail').value = role.email || '';
                        document.getElementById('roleScope').value = role.access_scope || '';
                        document.getElementById('roleStatus').value = role.status || 'Active';
                        this.roleId.value = role.role_id;
                    } else {
                        this.showError(data.message || 'Failed to load role details');
                    }
                } catch (error) {
                    this.showError('Error loading role: ' + error.message);
                }
            }

            async handleFormSubmit(e) {
                e.preventDefault();

                const formData = new FormData(this.form);
                const isEditing = this.roleId.value !== '';
                const action = isEditing ? 'update' : 'add';

                const data = {
                    action,
                    first_name: (formData.get('first_name') || '').toString().trim(),
                    last_name: (formData.get('last_name') || '').toString().trim(),
                    role_name: (formData.get('role_name') || '').toString().trim(),
                    username: (formData.get('username') || '').toString().trim(),
                    email: (formData.get('email') || '').toString().trim(),
                    password: (formData.get('password') || '').toString(),
                    access_scope: (formData.get('access_scope') || '').toString().trim(),
                    status: (formData.get('status') || 'Active').toString()
                };

                if (isEditing) {
                    data.role_id = this.roleId.value;
                }

                if (!data.first_name || !data.last_name || !data.username || !data.email) {
                    this.showError('First name, last name, username, and email are required');
                    return;
                }

                if (!isEditing && !data.password) {
                    this.showError('Password is required when creating a user role');
                    return;
                }

                try {
                    const response = await fetch('roles_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: new URLSearchParams(data)
                    });

                    const rawResponse = await response.text();
                    let result;
                    try {
                        result = JSON.parse(rawResponse);
                    } catch (parseError) {
                        const preview = rawResponse.slice(0, 180).replace(/\s+/g, ' ').trim();
                        this.showError('Server returned invalid response. ' + preview);
                        return;
                    }

                    if (result.success) {
                        this.showSuccess(result.message || 'User role saved successfully');
                        this.closeModal();
                        this.loadRoles();
                    } else {
                        this.showError(result.message || 'Failed to save role');
                    }
                } catch (error) {
                    this.showError('Error saving role: ' + error.message);
                }
            }

            renderRoles(roles) {
                if (roles.length === 0) {
                    this.tableBody.innerHTML = `
                        <tr>
                            <td class="px-6 py-8 text-center text-sm text-slate-500" colspan="6">
                                No user roles yet. Click "Add New User" to create one.
                            </td>
                        </tr>
                    `;
                    return;
                }

                this.tableBody.innerHTML = roles.map((role) => `
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded bg-slate-100 flex items-center justify-center">
                                    <span class="material-symbols-outlined text-slate-500 text-lg">person</span>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-slate-900">${this.escapeHtml((role.first_name || '') + ' ' + (role.last_name || ''))}</p>
                                    <p class="text-xs text-slate-500">${this.escapeHtml(role.email || 'No email')}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-700 font-medium">${this.escapeHtml(role.username || '-')}</td>
                        <td class="px-6 py-4">
                            <span class="text-sm font-medium text-slate-700">${this.escapeHtml(role.role_name || '-')}</span>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-700">${this.escapeHtml(role.access_scope || 'General')}</td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold ${
                                role.status === 'Active' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600'
                            }">
                                ${this.escapeHtml(role.status || 'Inactive')}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button class="role-edit-btn p-1.5 text-slate-400 hover:text-primary transition-colors" data-id="${role.role_id}" title="Edit">
                                    <span class="material-symbols-outlined text-lg">edit</span>
                                </button>
                                <button class="role-delete-btn p-1.5 text-slate-400 hover:text-error transition-colors" data-id="${role.role_id}" title="Delete">
                                    <span class="material-symbols-outlined text-lg">delete</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                `).join('');

                document.querySelectorAll('.role-edit-btn').forEach((btn) => {
                    btn.addEventListener('click', (e) => {
                        const roleId = Number.parseInt(e.currentTarget.getAttribute('data-id'), 10);
                        this.openModal(roleId);
                    });
                });

                document.querySelectorAll('.role-delete-btn').forEach((btn) => {
                    btn.addEventListener('click', (e) => {
                        const roleId = Number.parseInt(e.currentTarget.getAttribute('data-id'), 10);
                        this.deleteRole(roleId);
                    });
                });
            }

            async deleteRole(roleId) {
                if (!confirm('Are you sure you want to delete this user role?')) {
                    return;
                }

                try {
                    const response = await fetch('roles_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'action=delete&role_id=' + encodeURIComponent(roleId)
                    });

                    const result = await response.json();
                    if (result.success) {
                        this.showSuccess(result.message || 'Role deleted successfully');
                        this.loadRoles();
                    } else {
                        this.showError(result.message || 'Failed to delete role');
                    }
                } catch (error) {
                    this.showError('Error deleting role: ' + error.message);
                }
            }

            showSuccess(message) {
                alert(message);
            }

            showError(message) {
                alert('Error: ' + message);
            }

            escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
        }

        // Service Management JavaScript
        class ServiceManager {
            constructor() {
                this.modal = document.getElementById('serviceModal');
                this.form = document.getElementById('serviceForm');
                this.container = document.getElementById('servicesContainer');
                this.addBtn = document.getElementById('addServiceBtn');
                this.closeBtn = document.getElementById('closeModalBtn');
                this.cancelBtn = document.getElementById('cancelBtn');
                this.modalTitle = document.getElementById('modalTitle');
                this.serviceId = document.getElementById('serviceId');
                this.currentEditingId = null;
                this.phpFormatter = new Intl.NumberFormat('en-PH', {
                    style: 'currency',
                    currency: 'PHP',
                    minimumFractionDigits: 2
                });
                
                this.init();
            }

            init() {
                this.attachEventListeners();
                this.loadServices();
            }

            attachEventListeners() {
                this.addBtn.addEventListener('click', () => this.openModal());
                this.closeBtn.addEventListener('click', () => this.closeModal());
                this.cancelBtn.addEventListener('click', () => this.closeModal());
                this.form.addEventListener('submit', (e) => this.handleFormSubmit(e));
                
                // Category change listener
                const categorySelect = document.getElementById('serviceCategory');
                categorySelect.addEventListener('change', (e) => this.handleCategoryChange(e));
            }

            openModal(serviceId = null) {
                this.currentEditingId = serviceId;
                if (serviceId) {
                    this.modalTitle.textContent = 'Edit Service';
                    this.loadServiceData(serviceId);
                } else {
                    this.modalTitle.textContent = 'Add New Service';
                    this.form.reset();
                    this.serviceId.value = '';
                                    document.getElementById('customCategoryContainer').classList.add('hidden');
                                    document.getElementById('customCategory').value = '';
                }
                this.modal.classList.remove('hidden');
            }

            closeModal() {
                this.modal.classList.add('hidden');
                this.form.reset();
                this.currentEditingId = null;
            }

            handleCategoryChange(e) {
                const categoryValue = e.target.value;
                const customCategoryContainer = document.getElementById('customCategoryContainer');
                const customCategoryInput = document.getElementById('customCategory');

                if (categoryValue === 'Other') {
                    customCategoryContainer.classList.remove('hidden');
                    customCategoryInput.focus();
                } else {
                    customCategoryContainer.classList.add('hidden');
                    customCategoryInput.value = '';
                }
            }

            async loadServices() {
                try {
                    const response = await fetch('services_handler.php?action=get_all');
                    const data = await response.json();
                    
                    if (data.success) {
                        this.renderServices(data.services);
                    } else {
                        this.showError(data.message || 'Failed to load services');
                    }
                } catch (error) {
                    this.showError('Error loading services: ' + error.message);
                }
            }

            async loadServiceData(serviceId) {
                try {
                    const response = await fetch('services_handler.php?action=get_single&service_id=' + serviceId);
                    const data = await response.json();
                    
                    if (data.success) {
                        const service = data.service;
                        document.getElementById('serviceName').value = service.service_name;
                        const predefinedCategories = ['Engine', 'Electrical', 'Maintenance', 'Brakes', 'Suspension', 'Transmission', 'Cooling System', 'Diagnostics', 'Other'];
                        
                        if (service.category && !predefinedCategories.includes(service.category)) {
                            document.getElementById('serviceCategory').value = 'Other';
                            document.getElementById('customCategory').value = service.category;
                            document.getElementById('customCategoryContainer').classList.remove('hidden');
                        } else {
                            document.getElementById('serviceCategory').value = service.category || '';
                            document.getElementById('customCategoryContainer').classList.add('hidden');
                            document.getElementById('customCategory').value = '';
                        }
                        document.getElementById('servicePrice').value = service.price;
                        document.getElementById('serviceDuration').value = service.duration_minutes || '';
                        document.getElementById('serviceDescription').value = service.description || '';
                        document.getElementById('serviceStatus').value = service.status;
                        this.serviceId.value = serviceId;
                    } else {
                        this.showError(data.message || 'Failed to load service details');
                    }
                } catch (error) {
                    this.showError('Error loading service: ' + error.message);
                }
            }

            async handleFormSubmit(e) {
                e.preventDefault();
                
                const formData = new FormData(this.form);
                const action = this.serviceId.value ? 'update' : 'add';
                
                const data = {
                    action: action,
                    service_name: formData.get('service_name'),
                    category: formData.get('category'),
                    price: formData.get('price'),
                    duration_minutes: formData.get('duration_minutes'),
                    description: formData.get('description'),
                    status: formData.get('status')
                };
                
                if (this.serviceId.value) {
                    data.service_id = this.serviceId.value;
                }

                // If "Other" is selected, use the custom category input
                if (data.category === 'Other') {
                    const customCategory = formData.get('custom_category');
                    if (!customCategory || customCategory.trim() === '') {
                        this.showError('Please enter a custom category name');
                        return;
                    }
                    data.category = customCategory.trim();
                }

                try {
                    const response = await fetch('services_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams(data)
                    });
                    
                    const rawResponse = await response.text();
                    let result;
                    try {
                        result = JSON.parse(rawResponse);
                    } catch (parseError) {
                        const preview = rawResponse.slice(0, 180).replace(/\s+/g, ' ').trim();
                        this.showError('Server returned invalid response. ' + preview);
                        return;
                    }
                    
                    if (result.success) {
                        this.showSuccess(result.message);
                        this.closeModal();
                        this.loadServices();
                    } else {
                        this.showError(result.message || 'An error occurred');
                    }
                } catch (error) {
                    this.showError('Error saving service: ' + error.message);
                }
            }

            renderServices(services) {
                if (services.length === 0) {
                    this.container.innerHTML = `
                        <div class="col-span-full flex justify-center items-center py-12">
                            <div class="text-center">
                                <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-4">
                                    <span class="material-symbols-outlined text-slate-400">work_outline</span>
                                </div>
                                <p class="text-slate-500 font-medium mb-3">No services yet</p>
                                <button id="emptyAddBtn" class="text-primary font-bold text-sm hover:underline">
                                    Create your first service
                                </button>
                            </div>
                        </div>
                    `;
                    document.getElementById('emptyAddBtn')?.addEventListener('click', () => this.openModal());
                    return;
                }

                this.container.innerHTML = services.map(service => `
                    <div class="bg-white border border-slate-200 p-5 rounded-lg shadow-sm hover:border-primary/50 transition-all flex flex-col justify-between">
                        <div class="flex justify-between items-start mb-4">
                            <div class="flex-1">
                                <h4 class="font-bold text-slate-900">${this.escapeHtml(service.service_name)}</h4>
                                <span class="inline-block mt-1 px-2 py-0.5 ${service.category ? 'bg-blue-50 text-blue-700' : 'bg-slate-100 text-slate-600'} text-[10px] font-black uppercase tracking-widest rounded">
                                    ${this.escapeHtml(service.category || 'Uncategorized')}
                                </span>
                            </div>
                            <span class="text-lg font-black text-primary">${this.formatPHP(service.price)}</span>
                        </div>
                        <div class="text-xs text-slate-500 mb-4">
                            ${service.description ? this.escapeHtml(service.description) : '<em>No description</em>'}
                        </div>
                        <div class="flex items-center justify-between pt-4 border-t border-slate-100">
                            <div class="flex items-center gap-2 text-slate-500">
                                <span class="material-symbols-outlined text-lg">schedule</span>
                                <span class="text-xs font-medium">${service.duration_minutes ? service.duration_minutes + ' mins' : 'Not set'}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs px-2 py-1 rounded ${service.status === 'Active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'}">
                                    ${service.status}
                                </span>
                                <button class="edit-btn p-1.5 text-slate-400 hover:text-primary transition-colors" data-id="${service.service_id}" title="Edit">
                                    <span class="material-symbols-outlined text-lg">edit</span>
                                </button>
                                <button class="delete-btn p-1.5 text-slate-400 hover:text-error transition-colors" data-id="${service.service_id}" title="Delete">
                                    <span class="material-symbols-outlined text-lg">delete</span>
                                </button>
                            </div>
                        </div>
                    </div>
                `).join('');

                // Attach event listeners to edit and delete buttons
                document.querySelectorAll('.edit-btn').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        const serviceId = e.currentTarget.getAttribute('data-id');
                        this.openModal(parseInt(serviceId));
                    });
                });

                document.querySelectorAll('.delete-btn').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        const serviceId = e.currentTarget.getAttribute('data-id');
                        this.deleteService(parseInt(serviceId));
                    });
                });
            }

            async deleteService(serviceId) {
                if (!confirm('Are you sure you want to delete this service?')) {
                    return;
                }

                try {
                    const response = await fetch('services_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=delete&service_id=' + serviceId
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        this.showSuccess(result.message);
                        this.loadServices();
                    } else {
                        this.showError(result.message || 'Failed to delete service');
                    }
                } catch (error) {
                    this.showError('Error deleting service: ' + error.message);
                }
            }

            showSuccess(message) {
                alert(message); // In production, use a better notification system
            }

            showError(message) {
                alert('Error: ' + message); // In production, use a better notification system
            }

            formatPHP(amount) {
                const numericAmount = Number.parseFloat(amount ?? 0);
                if (Number.isNaN(numericAmount)) {
                    return this.phpFormatter.format(0);
                }
                return this.phpFormatter.format(numericAmount);
            }

            escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
        }

        // Initialize managers when DOM is ready
        document.addEventListener('DOMContentLoaded', () => {
            const roleManager = new RoleManager();
            roleManager.init();
            new ServiceManager();
        });
    </script>
</body>

</html>