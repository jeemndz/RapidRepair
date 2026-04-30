<?php
session_start();
include __DIR__ . '/../db.php';
include __DIR__ . '/../session_security.php';
include __DIR__ . '/access_control.php';

if (!isset($_SESSION['tenantID'])) {
    header('Location: tenantlogin.php');
    exit;
}

$tenantID = (int) $_SESSION['tenantID'];

enforceModuleAccess($tenantID, basename(__FILE__));

$accessibleModules = getAccessibleModules($tenantID);

function canAccessModule($moduleFile, $accessibleModules) {
    return in_array($moduleFile, $accessibleModules, true);
}

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if (($_SESSION['userType'] ?? '') === 'owner') {
    $loggedInUserName = $_SESSION['shopName'] ?? 'Shop Owner';
    $loggedInUserRole = 'Administrator';
} else {
    $loggedInUserName = trim(($_SESSION['firstName'] ?? '') . ' ' . ($_SESSION['lastName'] ?? ''));
    $loggedInUserName = $loggedInUserName ?: 'User';
    $loggedInUserRole = $_SESSION['userRole'] ?? 'Staff Member';
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Shop Settings | Cobalt Precision Admin</title>

    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />

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
                    borderRadius: {
                        "DEFAULT": "0.125rem",
                        "lg": "0.25rem",
                        "xl": "0.5rem",
                        "full": "0.75rem"
                    }
                }
            }
        };
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
    <aside class="w-64 flex-shrink-0 border-r border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 h-screen sticky top-0 flex flex-col overflow-y-auto">
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
                <?php if (canAccessModule('dashboardadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" href="dashboardadmin.php">
                        <span class="material-symbols-outlined text-[22px]">dashboard</span>
                        Dashboard
                    </a>
                <?php endif; ?>

                <?php if (canAccessModule('repairjobsadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" href="repairjobsadmin.php">
                        <span class="material-symbols-outlined text-[22px]">build</span>
                        Repair Jobs
                    </a>
                <?php endif; ?>

                <?php if (canAccessModule('vehicleadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" href="vehicleadmin.php">
                        <span class="material-symbols-outlined text-[22px]">directions_car</span>
                        Vehicles
                    </a>
                <?php endif; ?>

                <?php if (canAccessModule('appointmentadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" href="appointmentadmin.php">
                        <span class="material-symbols-outlined text-[22px]">event</span>
                        Appointments
                    </a>
                <?php endif; ?>

                <?php if (canAccessModule('reportsadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" href="reportsadmin.php">
                        <span class="material-symbols-outlined text-[22px]">description</span>
                        Reports
                    </a>
                <?php endif; ?>

                <?php if (canAccessModule('inventoryadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" href="inventoryadmin.php">
                        <span class="material-symbols-outlined text-[22px]">inventory_2</span>
                        Inventory
                    </a>
                <?php endif; ?>

                <?php if (canAccessModule('customeradmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" href="customeradmin.php">
                        <span class="material-symbols-outlined text-[22px]">group</span>
                        Customers
                    </a>
                <?php endif; ?>

                <?php if (canAccessModule('paymentsadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" href="paymentsadmin.php">
                        <span class="material-symbols-outlined text-[22px]">payments</span>
                        Payments
                    </a>
                <?php endif; ?>

                <div class="pt-4 mt-4 border-t border-slate-100 dark:border-slate-800">
                    <div class="relative group">
                        <button class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors w-full text-left settings-dropdown-btn" data-dropdown="settings">
                            <span class="material-symbols-outlined text-[22px]">settings</span>
                            <span>Settings</span>
                            <span class="material-symbols-outlined text-[16px] ml-auto">expand_more</span>
                        </button>

                        <div class="absolute left-0 top-full mt-1 w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg shadow-lg hidden z-50 settings-dropdown" data-dropdown="settings">
                            <?php if (canAccessModule('settingsadmin.php', $accessibleModules)): ?>
                                <a class="flex items-center gap-3 px-3 py-2.5 rounded-t-lg text-slate-600 dark:text-slate-400 hover:bg-blue-50 dark:hover:bg-slate-800 transition-colors text-sm" href="settingsadmin.php">
                                    <span class="material-symbols-outlined text-[18px]">settings</span>
                                    Settings
                                </a>
                            <?php endif; ?>

                            <?php if (canAccessModule('accountbillingadmin.php', $accessibleModules)): ?>
                                <a class="flex items-center gap-3 px-3 py-2.5 rounded-b-lg text-slate-600 dark:text-slate-400 hover:bg-blue-50 dark:hover:bg-slate-800 transition-colors text-sm border-t border-slate-100 dark:border-slate-700" href="accountbillingadmin.php">
                                    <span class="material-symbols-outlined text-[18px]">receipt_long</span>
                                    Account Billing
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </nav>
        </div>

        <div class="mt-auto w-full p-4 border-t border-slate-200 dark:border-slate-800">
            <div class="flex items-center gap-3">
                <div class="size-10 rounded-full bg-slate-200 flex items-center justify-center overflow-hidden">
                    <span class="material-symbols-outlined text-slate-500">person</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold truncate"><?php echo h($loggedInUserName); ?></p>
                    <p class="text-xs text-slate-500 truncate"><?php echo h($loggedInUserRole); ?></p>
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

    <main class="flex-1 overflow-y-auto flex flex-col">
        <header class="sticky top-0 z-40 w-full border-b border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md flex items-center justify-between px-8 h-16">
            <h2 class="text-lg font-black text-slate-900 dark:text-white tracking-tight">Settings Management</h2>
            <div class="flex items-center gap-4">
                <button class="p-2 text-slate-500 hover:text-primary transition-all">
                    <span class="material-symbols-outlined">notifications</span>
                </button>
                <button class="p-2 text-slate-500 hover:text-primary transition-all">
                    <span class="material-symbols-outlined">help_outline</span>
                </button>
            </div>
        </header>

        <div class="p-8 space-y-8 flex-1">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="bg-white border border-slate-200 p-6 rounded-lg shadow-sm flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-2 bg-primary-container rounded-lg">
                            <span class="material-symbols-outlined text-primary text-xl">group</span>
                        </div>
                        <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2 py-1 rounded-full" id="staffTrendBadge">Loading...</span>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-500 mb-1">Total Staff</p>
                        <p class="text-2xl font-black text-slate-900" id="totalStaffCount">Loading...</p>
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
                        <p class="text-2xl font-black text-slate-900" id="totalServicesCount">Loading...</p>
                    </div>
                </div>

                <div class="md:col-span-2 bg-slate-900 text-white p-6 rounded-lg shadow-sm relative overflow-hidden">
                    <div class="relative z-10 flex flex-col h-full justify-between">
                        <div>
                            <h3 class="text-lg font-bold mb-1">System Integrity</h3>
                            <p class="text-slate-400 text-sm">All operational modules are running at peak efficiency.</p>
                        </div>
                        <div class="flex gap-4 mt-4">
                            <div class="flex items-center gap-2">
                                <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                                <span class="text-xs font-mono uppercase tracking-widest text-slate-300">Live Server</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                                <span class="text-xs font-mono uppercase tracking-widest text-slate-300">Sync Active</span>
                            </div>
                        </div>
                    </div>
                    <div class="absolute -right-10 -bottom-10 opacity-10">
                        <span class="material-symbols-outlined text-[160px]" style="font-variation-settings: 'FILL' 1;">precision_manufacturing</span>
                    </div>
                </div>
            </div>

            <section class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-xl font-bold text-slate-900 tracking-tight">User Role Management</h3>
                        <p class="text-sm text-slate-500">Manage permissions and access levels for shop technicians and office staff.</p>
                    </div>
                    <button id="addRoleBtn" class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-bold flex items-center gap-2 hover:bg-blue-800 transition-colors shadow-sm">
                        <span class="material-symbols-outlined text-sm">add</span>
                        Add New User
                    </button>
                </div>

                <div class="bg-white border border-slate-200 rounded-lg shadow-sm overflow-hidden">
                    <table class="w-full text-left border-collapse">
                        <thead>
                        <tr class="bg-slate-50 border-b border-slate-200">
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Username</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Role</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Access Scope</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-right">Actions</th>
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

            <section class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-xl font-bold text-slate-900 tracking-tight">Service Management</h3>
                        <p class="text-sm text-slate-500">Configure main services, sub-services, pricing, durations, and category tags.</p>
                    </div>
                    <button id="addServiceBtn" class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-bold flex items-center gap-2 hover:bg-blue-800 transition-colors shadow-sm">
                        <span class="material-symbols-outlined text-sm">add_circle</span>
                        Add New Service
                    </button>
                </div>

                <div id="servicesContainer" class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4">
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
                        <a href="tenantslogs.php<?= isset($_GET['shop']) ? '?shop=' . urlencode($_GET['shop']) : '' ?>" class="bg-primary text-white px-6 py-3 rounded-lg font-bold flex items-center gap-2 hover:bg-blue-800 transition-colors shadow-sm whitespace-nowrap">
                            <span class="material-symbols-outlined text-sm">arrow_forward</span>
                            View Activity Logs
                        </a>
                    </div>
                </div>
            </section>
        </div>

        <footer class="bg-white border-t border-slate-200 px-8 py-4 flex items-center justify-between">
            <div class="flex gap-12">
                <div class="flex items-center gap-3">
                    <div class="p-1.5 bg-blue-50 rounded">
                        <span class="material-symbols-outlined text-primary text-base">analytics</span>
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-400 font-black uppercase tracking-widest">Shop Utilization</p>
                        <p class="text-sm font-bold text-slate-900">84.2% <span class="text-emerald-500 text-[10px] font-bold">↑ 2.4%</span></p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="p-1.5 bg-blue-50 rounded">
                        <span class="material-symbols-outlined text-primary text-base">monetization_on</span>
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-400 font-black uppercase tracking-widest">Revenue Per Hour</p>
                        <p class="text-sm font-bold text-slate-900">PHP 218.40 <span class="text-slate-400 text-[10px] font-bold">(Target PHP 200)</span></p>
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

<div id="serviceModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-lg max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between p-8 border-b border-slate-200 sticky top-0 bg-white z-10">
            <div>
                <h3 id="modalTitle" class="text-2xl font-bold text-slate-900">Add New Service</h3>
                <p class="text-sm text-slate-500 mt-1">Add or edit service details including hierarchy, pricing and duration.</p>
            </div>
            <button id="closeModalBtn" class="text-slate-400 hover:text-slate-600 p-2">
                <span class="material-symbols-outlined text-2xl">close</span>
            </button>
        </div>

        <form id="serviceForm" class="p-8">
            <input type="hidden" id="serviceId" name="service_id">

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div class="space-y-5">
                    <div class="bg-slate-50 p-6 rounded-lg border border-slate-200">
                        <h4 class="font-bold text-slate-900 mb-4 text-sm uppercase tracking-wider text-slate-600">Basic Information</h4>

                        <div class="space-y-4">
                            <div>
                                <label for="serviceType" class="block text-sm font-semibold text-slate-700 mb-2">Service Type *</label>
                                <select id="serviceType" name="service_type" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary" required>
                                    <option value="Main">Main Service</option>
                                    <option value="Sub">Sub Service</option>
                                </select>
                                <p class="text-xs text-slate-500 mt-1">Main services can have their own price. You can also add multiple sub-services below.</p>
                            </div>

                            <div id="parentServiceContainer" class="hidden">
                                <label for="parentServiceId" class="block text-sm font-semibold text-slate-700 mb-2">Parent Main Service *</label>
                                <select id="parentServiceId" name="parent_service_id" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                                    <option value="">Select main service</option>
                                </select>
                            </div>

                            <div>
                                <label for="serviceName" class="block text-sm font-semibold text-slate-700 mb-2">Service Name *</label>
                                <input type="text" id="serviceName" name="service_name" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary" placeholder="e.g., Brake Services or Brake Pad Replacement" required>
                            </div>

                            <div>
                                <label for="serviceCategory" class="block text-sm font-semibold text-slate-700 mb-2">Category</label>
                                <select id="serviceCategory" name="category" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
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

                            <div id="customCategoryContainer" class="hidden">
                                <label for="customCategory" class="block text-sm font-semibold text-slate-700 mb-2">Custom Category Name</label>
                                <input type="text" id="customCategory" name="custom_category" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary" placeholder="Enter custom category name">
                                <p class="text-xs text-slate-500 mt-1">Your current database category is ENUM, so custom category will be saved as Other unless you change it to VARCHAR.</p>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label for="servicePrice" class="block text-sm font-semibold text-slate-700 mb-2">Price (PHP) *</label>
                                    <input type="number" id="servicePrice" name="price" step="0.01" min="0" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary" placeholder="0.00" required>
                                </div>

                                <div>
                                    <label for="serviceDuration" class="block text-sm font-semibold text-slate-700 mb-2">Duration (min)</label>
                                    <input type="number" id="serviceDuration" name="duration_minutes" min="0" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary" placeholder="60">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-5">
                    <div class="bg-slate-50 p-6 rounded-lg border border-slate-200">
                        <h4 class="font-bold text-slate-900 mb-4 text-sm uppercase tracking-wider text-slate-600">Additional Details</h4>

                        <div class="space-y-4">
                            <div>
                                <label for="serviceDescription" class="block text-sm font-semibold text-slate-700 mb-2">Description</label>
                                <textarea id="serviceDescription" name="description" rows="4" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary resize-none" placeholder="Detailed description of the service..."></textarea>
                            </div>

                            <div>
                                <label for="serviceStatus" class="block text-sm font-semibold text-slate-700 mb-2">Status</label>
                                <select id="serviceStatus" name="status" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="subServicesContainer" class="bg-slate-50 p-6 rounded-lg border border-slate-200 mt-8 hidden">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h4 class="font-bold text-slate-900 text-sm uppercase tracking-wider text-slate-600">Sub-Services</h4>
                        <p class="text-xs text-slate-500 mt-1">Add optional sub-services under this main service. Each sub-service has its own price and duration.</p>
                    </div>
                    <button type="button" id="addSubServiceBtn" class="bg-blue-100 text-blue-700 px-3 py-2 rounded-lg text-xs font-bold hover:bg-blue-200 transition-colors flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">add</span>
                        Add Sub-Service
                    </button>
                </div>

                <div id="subServicesList" class="space-y-3"></div>

                <div id="emptySubServicesHint" class="text-center py-6 border border-dashed border-slate-300 rounded-lg bg-white">
                    <span class="material-symbols-outlined text-slate-400 text-3xl">playlist_add</span>
                    <p class="text-sm text-slate-500 mt-2">No sub-services added yet.</p>
                    <p class="text-xs text-slate-400">Example: Brake Pad Replacement, Brake Fluid Change, Rotor Resurfacing</p>
                </div>
            </div>

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

<div id="roleModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between p-6 border-b border-slate-200 sticky top-0 bg-white z-10">
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

            <div class="bg-slate-50 p-4 rounded-lg border border-slate-200">
                <h4 class="text-sm font-bold text-slate-700 mb-4">Personal Information</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="roleFirstName" class="block text-sm font-semibold text-slate-700 mb-2">First Name *</label>
                        <input type="text" id="roleFirstName" name="first_name" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary" placeholder="e.g., James" required>
                    </div>
                    <div>
                        <label for="roleLastName" class="block text-sm font-semibold text-slate-700 mb-2">Last Name *</label>
                        <input type="text" id="roleLastName" name="last_name" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary" placeholder="e.g., Davis" required>
                    </div>
                </div>
            </div>

            <div class="bg-slate-50 p-4 rounded-lg border border-slate-200">
                <h4 class="text-sm font-bold text-slate-700 mb-4">Account Information</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="roleUsername" class="block text-sm font-semibold text-slate-700 mb-2">Username *</label>
                        <input type="text" id="roleUsername" name="username" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary" placeholder="e.g., jamesd" required>
                    </div>
                    <div>
                        <label for="roleEmail" class="block text-sm font-semibold text-slate-700 mb-2">Email *</label>
                        <input type="email" id="roleEmail" name="email" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary" placeholder="name@shop.com" required>
                    </div>
                </div>

                <div class="mt-4">
                    <label for="rolePassword" class="block text-sm font-semibold text-slate-700 mb-2">
                        Password <span id="passwordHint" class="text-xs font-normal text-slate-400">*</span>
                    </label>
                    <div class="space-y-2">
                        <div class="relative">
                            <input type="password" id="rolePassword" name="password" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary pr-12" placeholder="Enter secure password">
                            <button type="button" id="togglePasswordVisibility" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors">
                                <span class="material-symbols-outlined text-xl" id="toggleIcon">visibility_off</span>
                            </button>
                        </div>

                        <div class="flex gap-2">
                            <button type="button" id="generatePasswordBtn" class="flex-1 px-3 py-2 bg-blue-100 hover:bg-blue-200 text-blue-700 font-semibold rounded-lg text-sm transition-colors flex items-center justify-center gap-2">
                                <span class="material-symbols-outlined text-base">refresh</span>
                                Generate Password
                            </button>
                            <button type="button" id="copyPasswordBtn" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-lg text-sm transition-colors flex items-center justify-center gap-2">
                                <span class="material-symbols-outlined text-base">content_copy</span>
                            </button>
                        </div>

                        <div id="passwordStrengthContainer" class="hidden space-y-1">
                            <div class="flex items-center gap-2">
                                <div class="flex-1 h-2 bg-slate-200 rounded-full overflow-hidden">
                                    <div id="passwordStrengthBar" class="h-full w-0 bg-red-500 transition-all duration-300"></div>
                                </div>
                                <span id="passwordStrengthText" class="text-xs font-semibold text-red-600">Weak</span>
                            </div>
                            <p class="text-xs text-slate-600">
                                • At least 8 characters<br>
                                • Mix of uppercase and lowercase<br>
                                • At least one number or special character
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-4 border border-slate-300 rounded-lg bg-slate-50">
                <label class="block text-sm font-semibold text-slate-700 mb-3">Module Access <span class="text-red-500">*</span></label>
                <p class="text-xs text-slate-500 mb-3">Select which modules this user can access.</p>
                <div id="modulesCheckboxContainer" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <?php
                    $modules = [
                        'Dashboard' => 'Dashboard',
                        'Appointments' => 'Appointments',
                        'Repair Jobs' => 'Repair Jobs',
                        'Vehicles' => 'Vehicles',
                        'Inventory' => 'Inventory',
                        'Customers' => 'Customers',
                        'Payments' => 'Payments',
                        'Billing' => 'Billing & Accounts',
                        'Reports' => 'Reports',
                        'Settings' => 'Settings',
                        'Logs' => 'Activity Logs'
                    ];

                    foreach ($modules as $value => $label):
                        $id = 'module_' . preg_replace('/[^A-Za-z0-9]/', '', $value);
                    ?>
                        <div class="flex items-center">
                            <input type="checkbox" id="<?php echo h($id); ?>" name="modules" value="<?php echo h($value); ?>" class="w-4 h-4 rounded border-slate-300">
                            <label for="<?php echo h($id); ?>" class="ml-2 text-sm text-slate-700 cursor-pointer"><?php echo h($label); ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="flex gap-2 mt-3">
                    <button type="button" id="selectAllModules" class="text-xs font-semibold text-primary hover:underline">Select All</button>
                    <button type="button" id="clearAllModules" class="text-xs font-semibold text-slate-500 hover:underline">Clear All</button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="roleStatus" class="block text-sm font-semibold text-slate-700 mb-2">Status</label>
                    <select id="roleStatus" name="status" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
                <div>
                    <label for="roleName" class="block text-sm font-semibold text-slate-700 mb-2">Role Title</label>
                    <input type="text" id="roleName" name="role_name" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary" placeholder="e.g., Senior Technician">
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
class StatsManager {
    async loadStats() {
        try {
            const [rolesResponse, servicesResponse] = await Promise.all([
                fetch('roles_handler.php?action=get_count'),
                fetch('services_handler.php?action=get_count')
            ]);

            const rolesData = await rolesResponse.json();
            const servicesData = await servicesResponse.json();

            if (rolesData.success) {
                const staffCount = rolesData.count || 0;
                document.getElementById('totalStaffCount').textContent = staffCount + ' Members';

                const badge = document.getElementById('staffTrendBadge');
                if (staffCount > 0) {
                    badge.textContent = '✓ Active';
                    badge.classList.add('text-emerald-600', 'bg-emerald-50');
                    badge.classList.remove('text-slate-500', 'bg-slate-100');
                } else {
                    badge.textContent = 'No staff';
                    badge.classList.remove('text-emerald-600', 'bg-emerald-50');
                    badge.classList.add('text-slate-500', 'bg-slate-100');
                }
            }

            if (servicesData.success) {
                const serviceCount = servicesData.count || 0;
                document.getElementById('totalServicesCount').textContent = serviceCount + ' Catalog Items';
            }
        } catch (error) {
            console.error('Error loading stats:', error);
        }
    }
}

class RoleManager {
    constructor(statsManager) {
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
        this.statsManager = statsManager;
    }

    init() {
        this.attachEventListeners();
        this.setupModuleButtons();
        this.setupPasswordHandlers();
        this.loadRoles();
    }

    attachEventListeners() {
        this.addBtn.addEventListener('click', () => this.openModal());
        this.closeBtn.addEventListener('click', () => this.closeModal());
        this.cancelBtn.addEventListener('click', () => this.closeModal());
        this.form.addEventListener('submit', (e) => this.handleFormSubmit(e));
        this.passwordInput.addEventListener('input', () => this.updatePasswordStrength());
    }

    setupModuleButtons() {
        document.getElementById('selectAllModules')?.addEventListener('click', (e) => {
            e.preventDefault();
            document.querySelectorAll('input[name="modules"]').forEach(checkbox => checkbox.checked = true);
        });

        document.getElementById('clearAllModules')?.addEventListener('click', (e) => {
            e.preventDefault();
            document.querySelectorAll('input[name="modules"]').forEach(checkbox => checkbox.checked = false);
        });
    }

    setupPasswordHandlers() {
        document.getElementById('togglePasswordVisibility')?.addEventListener('click', (e) => {
            e.preventDefault();
            this.togglePasswordVisibility();
        });

        document.getElementById('generatePasswordBtn')?.addEventListener('click', (e) => {
            e.preventDefault();
            this.generatePassword();
        });

        document.getElementById('copyPasswordBtn')?.addEventListener('click', (e) => {
            e.preventDefault();
            this.copyPassword();
        });
    }

    togglePasswordVisibility() {
        const icon = document.getElementById('toggleIcon');

        if (this.passwordInput.type === 'password') {
            this.passwordInput.type = 'text';
            icon.textContent = 'visibility';
        } else {
            this.passwordInput.type = 'password';
            icon.textContent = 'visibility_off';
        }
    }

    generatePassword() {
        const length = 12;
        const uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        const lowercase = 'abcdefghijklmnopqrstuvwxyz';
        const numbers = '0123456789';
        const special = '!@#$%^&*';
        const allChars = uppercase + lowercase + numbers + special;

        let password = '';
        password += uppercase[Math.floor(Math.random() * uppercase.length)];
        password += lowercase[Math.floor(Math.random() * lowercase.length)];
        password += numbers[Math.floor(Math.random() * numbers.length)];
        password += special[Math.floor(Math.random() * special.length)];

        for (let i = password.length; i < length; i++) {
            password += allChars[Math.floor(Math.random() * allChars.length)];
        }

        password = password.split('').sort(() => Math.random() - 0.5).join('');

        this.passwordInput.value = password;
        this.passwordInput.type = 'text';
        document.getElementById('toggleIcon').textContent = 'visibility';
        this.updatePasswordStrength();
    }

    copyPassword() {
        const password = this.passwordInput.value;

        if (!password) {
            alert('No password to copy');
            return;
        }

        navigator.clipboard.writeText(password).then(() => {
            alert('Password copied');
        }).catch(() => {
            alert('Failed to copy password');
        });
    }

    updatePasswordStrength() {
        const password = this.passwordInput.value;
        const strengthContainer = document.getElementById('passwordStrengthContainer');
        const strengthBar = document.getElementById('passwordStrengthBar');
        const strengthText = document.getElementById('passwordStrengthText');

        if (!password) {
            strengthContainer.classList.add('hidden');
            return;
        }

        strengthContainer.classList.remove('hidden');

        let strength = 0;
        if (password.length >= 8) strength += 20;
        if (/[A-Z]/.test(password)) strength += 20;
        if (/[a-z]/.test(password)) strength += 20;
        if (/\d/.test(password)) strength += 20;
        if (/[!@#$%^&*]/.test(password)) strength += 20;

        let color = '#ef4444';
        let text = 'Weak';

        if (strength > 80) {
            color = '#22c55e';
            text = 'Strong';
        } else if (strength > 60) {
            color = '#eab308';
            text = 'Good';
        } else if (strength > 40) {
            color = '#f59e0b';
            text = 'Fair';
        }

        strengthBar.style.width = strength + '%';
        strengthBar.style.backgroundColor = color;
        strengthText.textContent = text;
        strengthText.style.color = color;
    }

    openModal(roleId = null) {
        this.form.reset();
        this.roleId.value = '';
        document.getElementById('passwordStrengthContainer').classList.add('hidden');
        this.passwordInput.type = 'password';
        document.getElementById('toggleIcon').textContent = 'visibility_off';

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
            document.querySelectorAll('input[name="modules"]').forEach(checkbox => checkbox.checked = false);
        }

        this.modal.classList.remove('hidden');
    }

    closeModal() {
        this.modal.classList.add('hidden');
        this.form.reset();
        this.roleId.value = '';
        this.passwordInput.required = true;
        this.passwordHint.textContent = '*';
    }

    async loadRoles() {
        try {
            const response = await fetch('roles_handler.php?action=get_all');
            const data = await response.json();

            if (data.success) {
                this.renderRoles(data.roles || []);
                this.statsManager.loadStats();
            } else {
                this.showError(data.message || 'Failed to load roles');
            }
        } catch (error) {
            this.showError('Error loading roles: ' + error.message);
        }
    }

    async loadRoleData(roleId) {
        try {
            const response = await fetch('roles_handler.php?action=get_single&role_id=' + encodeURIComponent(roleId));
            const data = await response.json();

            if (data.success) {
                const role = data.role;

                document.getElementById('roleFirstName').value = role.first_name || '';
                document.getElementById('roleLastName').value = role.last_name || '';
                document.getElementById('roleName').value = role.role_name || '';
                document.getElementById('roleUsername').value = role.username || '';
                document.getElementById('roleEmail').value = role.email || '';
                document.getElementById('roleStatus').value = role.status || 'Active';
                this.roleId.value = role.role_id;
                this.passwordInput.value = '';

                document.querySelectorAll('input[name="modules"]').forEach(checkbox => {
                    checkbox.checked = false;
                });

                if (role.access_scope && role.access_scope.trim() !== '' && role.access_scope !== '0') {
                    const modules = role.access_scope.split(',').map(m => m.trim());
                    modules.forEach(module => {
                        const checkbox = Array.from(document.querySelectorAll('input[name="modules"]'))
                            .find(item => item.value === module);

                        if (checkbox) {
                            checkbox.checked = true;
                        }
                    });
                }
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

        const checkedModules = [];
        document.querySelectorAll('input[name="modules"]').forEach(checkbox => {
            if (checkbox.checked) {
                checkedModules.push(checkbox.value);
            }
        });

        const data = {
            action,
            first_name: (formData.get('first_name') || '').toString().trim(),
            last_name: (formData.get('last_name') || '').toString().trim(),
            role_name: (formData.get('role_name') || '').toString().trim(),
            username: (formData.get('username') || '').toString().trim(),
            email: (formData.get('email') || '').toString().trim(),
            password: (formData.get('password') || '').toString(),
            access_scope: checkedModules.join(','),
            status: (formData.get('status') || 'Active').toString()
        };

        if (isEditing) {
            data.role_id = this.roleId.value;
        }

        if (!data.first_name || !data.last_name || !data.username || !data.email) {
            this.showError('First name, last name, username, and email are required');
            return;
        }

        if (checkedModules.length === 0) {
            this.showError('Please select at least one module');
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
                this.showError('Server returned invalid response. ' + rawResponse.slice(0, 180).replace(/\s+/g, ' ').trim());
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

        this.tableBody.innerHTML = roles.map(role => `
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
                <td class="px-6 py-4 text-sm">
                    <div class="flex flex-wrap gap-1">
                        ${(role.access_scope || '').split(',').filter(Boolean).map(module => `
                            <span class="inline-block px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs font-medium">${this.escapeHtml(module.trim())}</span>
                        `).join('')}
                    </div>
                </td>
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

        document.querySelectorAll('.role-edit-btn').forEach(btn => {
            btn.addEventListener('click', e => {
                const roleId = Number.parseInt(e.currentTarget.getAttribute('data-id'), 10);
                this.openModal(roleId);
            });
        });

        document.querySelectorAll('.role-delete-btn').forEach(btn => {
            btn.addEventListener('click', e => {
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

    escapeAttribute(text) {
        return this.escapeHtml(text).replace(/"/g, '&quot;');
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

class ServiceManager {
    constructor(statsManager) {
        this.statsManager = statsManager;
        this.modal = document.getElementById('serviceModal');
        this.form = document.getElementById('serviceForm');
        this.container = document.getElementById('servicesContainer');
        this.addBtn = document.getElementById('addServiceBtn');
        this.closeBtn = document.getElementById('closeModalBtn');
        this.cancelBtn = document.getElementById('cancelBtn');
        this.modalTitle = document.getElementById('modalTitle');
        this.serviceId = document.getElementById('serviceId');
        this.currentEditingId = null;
        this.services = [];

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
        this.form.addEventListener('submit', e => this.handleFormSubmit(e));

        document.getElementById('serviceCategory').addEventListener('change', e => this.handleCategoryChange(e));
        document.getElementById('serviceType').addEventListener('change', () => this.handleServiceTypeChange());

        document.getElementById('addSubServiceBtn')?.addEventListener('click', () => {
            this.addSubServiceRow();
        });
    }

    handleServiceTypeChange() {
        const serviceType = document.getElementById('serviceType').value;
        const parentContainer = document.getElementById('parentServiceContainer');
        const parentSelect = document.getElementById('parentServiceId');
        const subServicesContainer = document.getElementById('subServicesContainer');

        if (serviceType === 'Sub') {
            parentContainer.classList.remove('hidden');
            parentSelect.required = true;
            subServicesContainer.classList.add('hidden');
            this.clearSubServiceRows();
        } else {
            parentContainer.classList.add('hidden');
            parentSelect.required = false;
            parentSelect.value = '';
            subServicesContainer.classList.remove('hidden');
            this.updateSubServicesHint();
        }
    }

    createSubServiceRow(data = {}) {
        const row = document.createElement('div');
        row.className = 'sub-service-row bg-white border border-slate-200 rounded-lg p-4';

        row.innerHTML = `
            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                <div class="md:col-span-4">
                    <label class="block text-xs font-bold text-slate-600 mb-1">Sub-Service Name *</label>
                    <input type="text"
                        class="sub-service-name w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
                        placeholder="e.g., Brake Pad Replacement"
                        value="${this.escapeAttribute(data.service_name || data.name || '')}">
                </div>

                <div class="md:col-span-3">
                    <label class="block text-xs font-bold text-slate-600 mb-1">Price (PHP) *</label>
                    <input type="number"
                        class="sub-service-price w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
                        step="0.01"
                        min="0"
                        placeholder="0.00"
                        value="${this.escapeAttribute(data.price || '')}">
                </div>

                <div class="md:col-span-3">
                    <label class="block text-xs font-bold text-slate-600 mb-1">Duration (min)</label>
                    <input type="number"
                        class="sub-service-duration w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
                        min="0"
                        placeholder="60"
                        value="${this.escapeAttribute(data.duration_minutes || data.duration || '')}">
                </div>

                <div class="md:col-span-2">
                    <button type="button" class="remove-sub-service w-full px-3 py-2 bg-red-50 text-red-600 rounded-lg font-bold hover:bg-red-100 transition-colors">
                        Remove
                    </button>
                </div>
            </div>

            <div class="mt-3">
                <label class="block text-xs font-bold text-slate-600 mb-1">Description</label>
                <textarea class="sub-service-description w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary resize-none"
                    rows="2"
                    placeholder="Optional description...">${this.escapeHtml(data.description || '')}</textarea>
            </div>
        `;

        row.querySelector('.remove-sub-service').addEventListener('click', () => {
            row.remove();
            this.updateSubServicesHint();
        });

        return row;
    }

    addSubServiceRow(data = {}) {
        const list = document.getElementById('subServicesList');
        list.appendChild(this.createSubServiceRow(data));
        this.updateSubServicesHint();
    }

    clearSubServiceRows() {
        const list = document.getElementById('subServicesList');
        if (list) {
            list.innerHTML = '';
        }
        this.updateSubServicesHint();
    }

    updateSubServicesHint() {
        const hint = document.getElementById('emptySubServicesHint');
        const rows = document.querySelectorAll('#subServicesList .sub-service-row');

        if (!hint) {
            return;
        }

        if (rows.length === 0) {
            hint.classList.remove('hidden');
        } else {
            hint.classList.add('hidden');
        }
    }

    collectSubServices() {
        const subServices = [];
        const rows = document.querySelectorAll('#subServicesList .sub-service-row');

        rows.forEach(row => {
            const name = row.querySelector('.sub-service-name').value.trim();
            const price = row.querySelector('.sub-service-price').value;
            const duration = row.querySelector('.sub-service-duration').value || 0;
            const description = row.querySelector('.sub-service-description').value.trim();

            if (name || price || description) {
                subServices.push({
                    service_name: name,
                    price: price,
                    duration_minutes: duration,
                    description: description
                });
            }
        });

        return subServices;
    }

    populateParentServices(selectedParentId = '') {
        const parentSelect = document.getElementById('parentServiceId');

        const mainServices = this.services.filter(service =>
            service.service_type === 'Main' &&
            String(service.service_id) !== String(this.currentEditingId)
        );

        parentSelect.innerHTML = '<option value="">Select main service</option>';

        mainServices.forEach(service => {
            const option = document.createElement('option');
            option.value = service.service_id;
            option.textContent = service.service_name + ' - ' + this.formatPHP(service.price);

            if (String(selectedParentId) === String(service.service_id)) {
                option.selected = true;
            }

            parentSelect.appendChild(option);
        });
    }

    openModal(serviceId = null) {
        this.currentEditingId = serviceId;
        this.form.reset();
        this.serviceId.value = '';
        document.getElementById('customCategoryContainer').classList.add('hidden');
        document.getElementById('customCategory').value = '';
        this.clearSubServiceRows();

        if (serviceId) {
            this.modalTitle.textContent = 'Edit Service';
            this.loadServiceData(serviceId);
        } else {
            this.modalTitle.textContent = 'Add New Service';
            document.getElementById('serviceType').value = 'Main';
            document.getElementById('parentServiceId').value = '';
            document.getElementById('serviceStatus').value = 'Active';
            this.populateParentServices();
            this.handleServiceTypeChange();
        }

        this.modal.classList.remove('hidden');
    }

    closeModal() {
        this.modal.classList.add('hidden');
        this.form.reset();
        this.currentEditingId = null;
        this.serviceId.value = '';
        document.getElementById('customCategoryContainer').classList.add('hidden');
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
                this.services = data.services || [];
                this.renderServices(this.services);
                this.populateParentServices();
                this.statsManager.loadStats();
            } else {
                this.showError(data.message || 'Failed to load services');
            }
        } catch (error) {
            this.showError('Error loading services: ' + error.message);
        }
    }

    async loadServiceData(serviceId) {
        try {
            const response = await fetch('services_handler.php?action=get_single&service_id=' + encodeURIComponent(serviceId));
            const data = await response.json();

            if (data.success) {
                const service = data.service;

                document.getElementById('serviceType').value = service.service_type || 'Main';
                this.populateParentServices(service.parent_service_id || '');
                this.handleServiceTypeChange();
                this.clearSubServiceRows();

                document.getElementById('serviceName').value = service.service_name || '';

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

                document.getElementById('servicePrice').value = service.price || '0.00';
                document.getElementById('serviceDuration').value = service.duration_minutes || '';
                document.getElementById('serviceDescription').value = service.description || '';
                document.getElementById('serviceStatus').value = service.status || 'Active';
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
        const serviceType = formData.get('service_type') || 'Main';
        const action = this.serviceId.value ? 'update' : 'add';

        const data = {
            action,
            service_type: serviceType,
            parent_service_id: serviceType === 'Sub' ? (formData.get('parent_service_id') || '') : '',
            service_name: (formData.get('service_name') || '').toString().trim(),
            category: formData.get('category') || 'Other',
            price: formData.get('price'),
            duration_minutes: formData.get('duration_minutes') || 0,
            description: formData.get('description') || '',
            status: formData.get('status') || 'Active'
        };

        if (this.serviceId.value) {
            data.service_id = this.serviceId.value;
        }

        if (!data.service_name) {
            this.showError('Service name is required');
            return;
        }

        if (data.price === '' || Number.parseFloat(data.price) < 0) {
            this.showError('Please enter a valid service price');
            return;
        }

        if (data.service_type === 'Sub' && !data.parent_service_id) {
            this.showError('Please select a parent main service for this sub-service');
            return;
        }

        if (data.category === 'Other') {
            const customCategory = (formData.get('custom_category') || '').toString().trim();

            if (customCategory) {
                data.category = customCategory;
            }
        }

        if (data.service_type === 'Main') {
            const subServices = this.collectSubServices();

            for (const subService of subServices) {
                if (!subService.service_name) {
                    this.showError('Every sub-service must have a name');
                    return;
                }

                if (subService.price === '' || Number.parseFloat(subService.price) < 0) {
                    this.showError('Every sub-service must have a valid price');
                    return;
                }
            }

            data.sub_services = JSON.stringify(subServices);
        }

        try {
            const response = await fetch('services_handler.php', {
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
                this.showError('Server returned invalid response. ' + rawResponse.slice(0, 180).replace(/\s+/g, ' ').trim());
                return;
            }

            if (result.success) {
                this.showSuccess(result.message || 'Service saved successfully');
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

        const mainServices = services.filter(service => service.service_type === 'Main');
        const subServices = services.filter(service => service.service_type === 'Sub');

        const orphanSubServices = subServices.filter(sub => {
            return !mainServices.some(main => String(main.service_id) === String(sub.parent_service_id));
        });

        let html = '';

        if (mainServices.length === 0 && orphanSubServices.length > 0) {
            html += `
                <div class="col-span-full bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-sm text-yellow-800">
                    No main services found. Sub-services without valid parent services are listed below.
                </div>
            `;
        }

        html += mainServices.map(main => {
            const children = subServices.filter(sub => String(sub.parent_service_id) === String(main.service_id));

            const subServicesHtml = children.length > 0
                ? children.map(sub => `
                    <div class="bg-slate-50 border border-slate-200 rounded-lg p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="inline-flex items-center px-2 py-0.5 bg-orange-100 text-orange-700 text-[10px] font-black uppercase tracking-widest rounded">
                                        Sub
                                    </span>
                                    <h5 class="text-sm font-bold text-slate-900">${this.escapeHtml(sub.service_name)}</h5>
                                </div>

                                <p class="text-xs text-slate-500 mt-1">
                                    ${sub.description ? this.escapeHtml(sub.description) : '<em>No description</em>'}
                                </p>

                                <div class="flex flex-wrap items-center gap-3 mt-2 text-xs text-slate-500">
                                    <span class="flex items-center gap-1">
                                        <span class="material-symbols-outlined text-sm">schedule</span>
                                        ${sub.duration_minutes ? sub.duration_minutes + ' mins' : 'Not set'}
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <span class="material-symbols-outlined text-sm">sell</span>
                                        ${this.escapeHtml(sub.category || 'Uncategorized')}
                                    </span>
                                    <span class="px-2 py-0.5 rounded ${sub.status === 'Active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'}">
                                        ${this.escapeHtml(sub.status || 'Inactive')}
                                    </span>
                                </div>
                            </div>

                            <div class="text-right">
                                <p class="text-sm font-black text-primary whitespace-nowrap">${this.formatPHP(sub.price)}</p>
                                <div class="flex items-center justify-end gap-1 mt-2">
                                    <button class="edit-btn p-1.5 text-slate-400 hover:text-primary transition-colors" data-id="${sub.service_id}" title="Edit Sub-Service">
                                        <span class="material-symbols-outlined text-lg">edit</span>
                                    </button>
                                    <button class="delete-btn p-1.5 text-slate-400 hover:text-error transition-colors" data-id="${sub.service_id}" title="Delete Sub-Service">
                                        <span class="material-symbols-outlined text-lg">delete</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `).join('')
                : `
                    <div class="border border-dashed border-slate-300 rounded-lg p-4 text-center bg-slate-50">
                        <p class="text-xs text-slate-500">No sub-services under this main service yet.</p>
                    </div>
                `;

            return `
                <div class="bg-white border border-blue-200 p-5 rounded-lg shadow-sm hover:border-primary/50 transition-all flex flex-col">
                    <div class="flex justify-between items-start gap-4 mb-4">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="inline-flex items-center px-2 py-0.5 bg-indigo-100 text-indigo-700 text-[10px] font-black uppercase tracking-widest rounded">
                                    Main
                                </span>
                                <span class="inline-flex items-center px-2 py-0.5 ${main.category ? 'bg-blue-50 text-blue-700' : 'bg-slate-100 text-slate-600'} text-[10px] font-black uppercase tracking-widest rounded">
                                    ${this.escapeHtml(main.category || 'Uncategorized')}
                                </span>
                            </div>

                            <h4 class="font-black text-slate-900 text-lg mt-2">${this.escapeHtml(main.service_name)}</h4>

                            <p class="text-xs text-slate-500 mt-1 min-h-[32px]">
                                ${main.description ? this.escapeHtml(main.description) : '<em>No description</em>'}
                            </p>
                        </div>

                        <div class="text-right">
                            <p class="text-lg font-black text-primary whitespace-nowrap">${this.formatPHP(main.price)}</p>
                            <p class="text-xs text-slate-500 mt-1">
                                ${main.duration_minutes ? main.duration_minutes + ' mins' : 'Duration not set'}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center justify-between border-y border-slate-100 py-3 mb-4">
                        <div class="flex items-center gap-3 text-xs text-slate-500">
                            <span class="flex items-center gap-1">
                                <span class="material-symbols-outlined text-sm">format_list_bulleted</span>
                                ${children.length} Sub-Service${children.length === 1 ? '' : 's'}
                            </span>
                            <span class="px-2 py-1 rounded ${main.status === 'Active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'}">
                                ${this.escapeHtml(main.status || 'Inactive')}
                            </span>
                        </div>

                        <div class="flex items-center gap-2">
                            <button class="edit-btn p-1.5 text-slate-400 hover:text-primary transition-colors" data-id="${main.service_id}" title="Edit Main Service">
                                <span class="material-symbols-outlined text-lg">edit</span>
                            </button>
                            <button class="delete-btn p-1.5 text-slate-400 hover:text-error transition-colors" data-id="${main.service_id}" title="Delete Main Service">
                                <span class="material-symbols-outlined text-lg">delete</span>
                            </button>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <h5 class="text-xs font-black text-slate-500 uppercase tracking-widest">Sub-Services inside this Main Service</h5>
                            <button type="button"
                                class="add-sub-under-main-btn text-xs font-bold text-primary hover:underline"
                                data-parent-id="${main.service_id}">
                                + Add Sub-Service
                            </button>
                        </div>

                        <div class="space-y-3">
                            ${subServicesHtml}
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        if (orphanSubServices.length > 0) {
            html += `
                <div class="col-span-full mt-4">
                    <h4 class="text-sm font-black text-slate-600 uppercase tracking-widest mb-3">Sub-Services without Parent</h4>
                    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4">
                        ${orphanSubServices.map(sub => `
                            <div class="bg-white border border-red-200 p-4 rounded-lg shadow-sm">
                                <div class="flex justify-between items-start gap-3">
                                    <div>
                                        <span class="inline-block px-2 py-0.5 bg-red-100 text-red-700 text-[10px] font-black uppercase tracking-widest rounded">
                                            Missing Parent
                                        </span>
                                        <h5 class="font-bold text-slate-900 mt-2">${this.escapeHtml(sub.service_name)}</h5>
                                        <p class="text-xs text-slate-500 mt-1">${sub.description ? this.escapeHtml(sub.description) : '<em>No description</em>'}</p>
                                    </div>
                                    <p class="font-black text-primary">${this.formatPHP(sub.price)}</p>
                                </div>
                                <div class="flex justify-end gap-2 mt-3">
                                    <button class="edit-btn p-1.5 text-slate-400 hover:text-primary transition-colors" data-id="${sub.service_id}" title="Edit">
                                        <span class="material-symbols-outlined text-lg">edit</span>
                                    </button>
                                    <button class="delete-btn p-1.5 text-slate-400 hover:text-error transition-colors" data-id="${sub.service_id}" title="Delete">
                                        <span class="material-symbols-outlined text-lg">delete</span>
                                    </button>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        }

        this.container.innerHTML = html;

        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', e => {
                const serviceId = e.currentTarget.getAttribute('data-id');
                this.openModal(Number.parseInt(serviceId, 10));
            });
        });

        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', e => {
                const serviceId = e.currentTarget.getAttribute('data-id');
                this.deleteService(Number.parseInt(serviceId, 10));
            });
        });

        document.querySelectorAll('.add-sub-under-main-btn').forEach(btn => {
            btn.addEventListener('click', e => {
                const parentId = e.currentTarget.getAttribute('data-parent-id');
                this.openAddSubServiceModal(parentId);
            });
        });
    }

    openAddSubServiceModal(parentId) {
        this.currentEditingId = null;
        this.form.reset();
        this.serviceId.value = '';
        this.clearSubServiceRows();

        document.getElementById('customCategoryContainer').classList.add('hidden');
        document.getElementById('customCategory').value = '';

        const parentService = this.services.find(service => String(service.service_id) === String(parentId));

        this.modalTitle.textContent = parentService
            ? 'Add Sub-Service under ' + parentService.service_name
            : 'Add Sub-Service';

        document.getElementById('serviceType').value = 'Sub';
        document.getElementById('serviceStatus').value = 'Active';

        if (parentService) {
            document.getElementById('serviceCategory').value = parentService.category || 'Other';
        }

        this.populateParentServices(parentId);
        document.getElementById('parentServiceId').value = String(parentId);
        this.handleServiceTypeChange();

        document.getElementById('serviceName').placeholder = 'e.g., Brake Pad Replacement';
        document.getElementById('servicePrice').value = '';
        document.getElementById('serviceDuration').value = '';
        document.getElementById('serviceDescription').value = '';

        this.modal.classList.remove('hidden');
        document.getElementById('serviceName').focus();
    }

    async deleteService(serviceId) {
        if (!confirm('Are you sure you want to delete this service? If this is a main service with sub-services, delete or move the sub-services first.')) {
            return;
        }

        try {
            const response = await fetch('services_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'action=delete&service_id=' + encodeURIComponent(serviceId)
            });

            const result = await response.json();

            if (result.success) {
                this.showSuccess(result.message || 'Service deleted successfully');
                this.loadServices();
            } else {
                this.showError(result.message || 'Failed to delete service');
            }
        } catch (error) {
            this.showError('Error deleting service: ' + error.message);
        }
    }

    showSuccess(message) {
        alert(message);
    }

    showError(message) {
        alert('Error: ' + message);
    }

    formatPHP(amount) {
        const numericAmount = Number.parseFloat(amount ?? 0);

        if (Number.isNaN(numericAmount)) {
            return this.phpFormatter.format(0);
        }

        return this.phpFormatter.format(numericAmount);
    }

    escapeAttribute(text) {
        return this.escapeHtml(text).replace(/"/g, '&quot;');
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const statsManager = new StatsManager();
    statsManager.loadStats();

    const roleManager = new RoleManager(statsManager);
    roleManager.init();

    new ServiceManager(statsManager);
});

document.querySelectorAll('.settings-dropdown-btn').forEach(button => {
    button.addEventListener('click', function(e) {
        e.preventDefault();

        const dropdown = document.querySelector('[data-dropdown="settings"].settings-dropdown');

        if (dropdown) {
            dropdown.classList.toggle('hidden');
        }
    });
});

document.addEventListener('click', function(e) {
    const dropdownBtn = document.querySelector('.settings-dropdown-btn');
    const dropdown = document.querySelector('[data-dropdown="settings"].settings-dropdown');

    if (dropdown && dropdownBtn && !dropdownBtn.contains(e.target) && !dropdown.contains(e.target)) {
        dropdown.classList.add('hidden');
    }
});
</script>
</body>
</html>
