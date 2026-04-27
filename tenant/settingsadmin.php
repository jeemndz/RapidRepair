<?php
session_start();
include __DIR__ . '/../db.php';
include __DIR__ . '/../session_security.php';
include __DIR__ . '/access_control.php';

// Check if tenant is logged in
if (!isset($_SESSION['tenantID'])) {
    header('Location: tenantlogin.php');
    exit;
}

$tenantID = (int) $_SESSION['tenantID'];

// Enforce access control for this module
enforceModuleAccess($tenantID, basename(__FILE__));

// Get accessible modules for navigation
$accessibleModules = getAccessibleModules($tenantID);
$isStaffUser = isset($_SESSION['userType']) && $_SESSION['userType'] === 'staff';

// Helper function to check if a module should be accessible
function canAccessModule($moduleFile, $accessibleModules) {
    return in_array($moduleFile, $accessibleModules);
}

// HTML escape helper function
function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Get logged-in user information
$loggedInUserName = '';
$loggedInUserRole = '';
if ($_SESSION['userType'] === 'owner') {
    $loggedInUserName = isset($_SESSION['shopName']) ? $_SESSION['shopName'] : 'Shop Owner';
    $loggedInUserRole = 'Administrator';
} else {
    $loggedInUserName = (isset($_SESSION['firstName']) ? $_SESSION['firstName'] : '') . ' ' . (isset($_SESSION['lastName']) ? $_SESSION['lastName'] : '');
    $loggedInUserName = trim($loggedInUserName) ?: 'User';
    $loggedInUserRole = isset($_SESSION['userRole']) ? $_SESSION['userRole'] : 'Staff Member';
}
?>
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
                <?php if (canAccessModule('dashboardadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    href="dashboardadmin.php">
                    <span class="material-symbols-outlined text-[22px]">dashboard</span>
                    Dashboard
                </a>
                <?php endif; ?>
                
                <?php if (canAccessModule('repairjobsadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    href="repairjobsadmin.php">
                    <span class="material-symbols-outlined text-[22px]">build</span>
                    Repair Jobs
                </a>
                <?php endif; ?>
                
                <?php if (canAccessModule('vehicleadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    href="vehicleadmin.php">
                    <span class="material-symbols-outlined text-[22px]">directions_car</span>
                    Vehicles
                </a>
                <?php endif; ?>
                
                <?php if (canAccessModule('appointmentadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    href="appointmentadmin.php">
                    <span class="material-symbols-outlined text-[22px]">event</span>
                    Appointments
                </a>
                <?php endif; ?>
                
                <?php if (canAccessModule('reportsadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    href="reportsadmin.php">
                    <span class="material-symbols-outlined text-[22px]">description</span>
                    Reports
                </a>
                <?php endif; ?>
                
                <?php if (canAccessModule('inventoryadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    href="inventoryadmin.php">
                    <span class="material-symbols-outlined text-[22px]">inventory_2</span>
                    Inventory
                </a>
                <?php endif; ?>
                
                <?php if (canAccessModule('customeradmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    href="customeradmin.php">
                    <span class="material-symbols-outlined text-[22px]">group</span>
                    Customers
                </a>
                <?php endif; ?>
                
                <?php if (canAccessModule('paymentsadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    href="paymentsadmin.php">
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
                            <a class="flex items-center gap-3 px-3 py-2.5 rounded-t-lg text-slate-600 dark:text-slate-400 hover:bg-blue-50 dark:hover:bg-slate-800 transition-colors text-sm"
                                href="settingsadmin.php">
                                <span class="material-symbols-outlined text-[18px]">settings</span>
                                Settings
                            </a>
                            <?php endif; ?>
                            <?php if (canAccessModule('accountbillingadmin.php', $accessibleModules)): ?>
                            <a class="flex items-center gap-3 px-3 py-2.5 rounded-b-lg text-slate-600 dark:text-slate-400 hover:bg-blue-50 dark:hover:bg-slate-800 transition-colors text-sm border-t border-slate-100 dark:border-slate-700"
                                href="accountbillingadmin.php">
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
                <div
                    class="size-10 rounded-full bg-slate-200 flex items-center justify-center overflow-hidden">
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
        <!-- Main Content Canvas -->
        <main class="flex-1 overflow-y-auto flex flex-col">
        <!-- Top Nav Bar -->
        <header
            class="sticky top-0 z-40 w-full border-b border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md flex items-center justify-between px-8 h-16">
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
        <!-- Content Area -->
        <div class="p-8 space-y-8 flex-1">
            <!-- Bento Grid Section: Stats Overview -->
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
                        <div class="md:col-span-2">
                            <label for="rolePassword" class="block text-sm font-semibold text-slate-700 mb-2">Password <span id="passwordHint" class="text-xs font-normal text-slate-400">*</span></label>
                            <div class="space-y-2">
                                <div class="relative">
                                    <input type="password" id="rolePassword" name="password"
                                        class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary pr-12"
                                        placeholder="Enter secure password">
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
                                
                                <!-- Password Strength Indicator -->
                                <div id="passwordStrengthContainer" class="hidden space-y-1">
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 h-2 bg-slate-200 rounded-full overflow-hidden">
                                            <div id="passwordStrengthBar" class="h-full w-0 bg-red-500 transition-all duration-300"></div>
                                        </div>
                                        <span id="passwordStrengthText" class="text-xs font-semibold text-red-600">Weak</span>
                                    </div>
                                    <p id="passwordRequirements" class="text-xs text-slate-600">
                                        • At least 8 characters
                                        <br>• Mix of uppercase and lowercase
                                        <br>• At least one number or special character
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 p-4 border border-slate-300 rounded-lg bg-slate-50">
                        <label class="block text-sm font-semibold text-slate-700 mb-3">Module Access <span class="text-red-500">*</span></label>
                        <p class="text-xs text-slate-500 mb-3">Select which modules this user can access</p>
                        <div id="modulesCheckboxContainer" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div class="flex items-center">
                                <input type="checkbox" id="module_Dashboard" name="modules" value="Dashboard" class="w-4 h-4 rounded border-slate-300">
                                <label for="module_Dashboard" class="ml-2 text-sm text-slate-700 cursor-pointer">Dashboard</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="module_Appointments" name="modules" value="Appointments" class="w-4 h-4 rounded border-slate-300">
                                <label for="module_Appointments" class="ml-2 text-sm text-slate-700 cursor-pointer">Appointments</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="module_RepairJobs" name="modules" value="Repair Jobs" class="w-4 h-4 rounded border-slate-300">
                                <label for="module_RepairJobs" class="ml-2 text-sm text-slate-700 cursor-pointer">Repair Jobs</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="module_Vehicles" name="modules" value="Vehicles" class="w-4 h-4 rounded border-slate-300">
                                <label for="module_Vehicles" class="ml-2 text-sm text-slate-700 cursor-pointer">Vehicles</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="module_Inventory" name="modules" value="Inventory" class="w-4 h-4 rounded border-slate-300">
                                <label for="module_Inventory" class="ml-2 text-sm text-slate-700 cursor-pointer">Inventory</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="module_Customers" name="modules" value="Customers" class="w-4 h-4 rounded border-slate-300">
                                <label for="module_Customers" class="ml-2 text-sm text-slate-700 cursor-pointer">Customers</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="module_Payments" name="modules" value="Payments" class="w-4 h-4 rounded border-slate-300">
                                <label for="module_Payments" class="ml-2 text-sm text-slate-700 cursor-pointer">Payments</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="module_Billing" name="modules" value="Billing" class="w-4 h-4 rounded border-slate-300">
                                <label for="module_Billing" class="ml-2 text-sm text-slate-700 cursor-pointer">Billing & Accounts</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="module_Reports" name="modules" value="Reports" class="w-4 h-4 rounded border-slate-300">
                                <label for="module_Reports" class="ml-2 text-sm text-slate-700 cursor-pointer">Reports</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="module_Settings" name="modules" value="Settings" class="w-4 h-4 rounded border-slate-300">
                                <label for="module_Settings" class="ml-2 text-sm text-slate-700 cursor-pointer">Settings</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="module_Logs" name="modules" value="Logs" class="w-4 h-4 rounded border-slate-300">
                                <label for="module_Logs" class="ml-2 text-sm text-slate-700 cursor-pointer">Activity Logs</label>
                            </div>
                        </div>
                        <div class="flex gap-2 mt-3">
                            <button type="button" id="selectAllModules" class="text-xs font-semibold text-primary hover:underline">Select All</button>
                            <button type="button" id="clearAllModules" class="text-xs font-semibold text-slate-500 hover:underline">Clear All</button>
                        </div>
                        <input type="hidden" id="roleScope" name="access_scope" value="">
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
        // Stats Management
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
                        
                        // Update badge
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

        // Role Management JavaScript
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
                const selectAllBtn = document.getElementById('selectAllModules');
                const clearAllBtn = document.getElementById('clearAllModules');
                
                if (selectAllBtn) {
                    selectAllBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        document.querySelectorAll('input[name="modules"]').forEach(checkbox => {
                            checkbox.checked = true;
                        });
                    });
                }
                
                if (clearAllBtn) {
                    clearAllBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        document.querySelectorAll('input[name="modules"]').forEach(checkbox => {
                            checkbox.checked = false;
                        });
                    });
                }
            }

            setupPasswordHandlers() {
                const toggleBtn = document.getElementById('togglePasswordVisibility');
                const generateBtn = document.getElementById('generatePasswordBtn');
                const copyBtn = document.getElementById('copyPasswordBtn');
                
                if (toggleBtn) {
                    toggleBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        this.togglePasswordVisibility();
                    });
                }
                
                if (generateBtn) {
                    generateBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        this.generatePassword();
                    });
                }
                
                if (copyBtn) {
                    copyBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        this.copyPassword();
                    });
                }
            }

            togglePasswordVisibility() {
                const input = this.passwordInput;
                const icon = document.getElementById('toggleIcon');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.textContent = 'visibility';
                } else {
                    input.type = 'password';
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
                
                // Ensure at least one of each type
                password += uppercase[Math.floor(Math.random() * uppercase.length)];
                password += lowercase[Math.floor(Math.random() * lowercase.length)];
                password += numbers[Math.floor(Math.random() * numbers.length)];
                password += special[Math.floor(Math.random() * special.length)];
                
                // Fill the rest randomly
                for (let i = password.length; i < length; i++) {
                    password += allChars[Math.floor(Math.random() * allChars.length)];
                }
                
                // Shuffle the password
                password = password.split('').sort(() => Math.random() - 0.5).join('');
                
                this.passwordInput.value = password;
                this.passwordInput.type = 'text';
                document.getElementById('toggleIcon').textContent = 'visibility';
                
                this.updatePasswordStrength();
                
                // Show success message
                const generateBtn = document.getElementById('generatePasswordBtn');
                const originalText = generateBtn.innerHTML;
                generateBtn.innerHTML = '<span class="material-symbols-outlined text-base">check_circle</span> Generated!';
                setTimeout(() => {
                    generateBtn.innerHTML = originalText;
                }, 2000);
            }

            copyPassword() {
                const password = this.passwordInput.value;
                if (!password) {
                    alert('No password to copy');
                    return;
                }
                
                navigator.clipboard.writeText(password).then(() => {
                    const copyBtn = document.getElementById('copyPasswordBtn');
                    const originalText = copyBtn.innerHTML;
                    copyBtn.innerHTML = '<span class="material-symbols-outlined text-base">check_circle</span>';
                    setTimeout(() => {
                        copyBtn.innerHTML = originalText;
                    }, 2000);
                }).catch(err => {
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
                const checks = {
                    length: password.length >= 8,
                    uppercase: /[A-Z]/.test(password),
                    lowercase: /[a-z]/.test(password),
                    numbers: /\d/.test(password),
                    special: /[!@#$%^&*]/.test(password)
                };
                
                Object.values(checks).forEach(check => {
                    if (check) strength += 20;
                });
                
                let color, text;
                if (strength <= 40) {
                    color = '#ef4444'; // red
                    text = 'Weak';
                } else if (strength <= 60) {
                    color = '#f59e0b'; // amber
                    text = 'Fair';
                } else if (strength <= 80) {
                    color = '#eab308'; // yellow
                    text = 'Good';
                } else {
                    color = '#22c55e'; // green
                    text = 'Strong';
                }
                
                strengthBar.style.width = strength + '%';
                strengthBar.style.backgroundColor = color;
                strengthText.textContent = text;
                strengthText.style.color = color;
            }

            openModal(roleId = null) {
                // Don't use form.reset() for edit mode since it would clear checkboxes
                // Instead, only clear text fields
                if (!roleId) {
                    this.form.reset();
                } else {
                    // For edit mode, clear text inputs but preserve checkboxes temporarily
                    document.getElementById('roleFirstName').value = '';
                    document.getElementById('roleLastName').value = '';
                    document.getElementById('roleName').value = '';
                    document.getElementById('roleUsername').value = '';
                    document.getElementById('roleEmail').value = '';
                    document.getElementById('rolePassword').value = '';
                }
                
                this.roleId.value = '';
                this.setupModuleButtons();
                this.setupPasswordHandlers();
                
                // Reset password field appearance
                this.passwordInput.type = 'password';
                document.getElementById('toggleIcon').textContent = 'visibility_off';
                document.getElementById('passwordStrengthContainer').classList.add('hidden');
                document.getElementById('passwordStrengthBar').style.width = '0%';

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
                    // For new roles, uncheck all modules
                    document.querySelectorAll('input[name="modules"]').forEach(checkbox => {
                        checkbox.checked = false;
                    });
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
                    const response = await fetch('roles_handler.php?action=get_single&role_id=' + roleId);
                    const data = await response.json();

                    console.log('Loaded role data:', data);

                    if (data.success) {
                        const role = data.role;
                        console.log('Role access_scope from DB:', role.access_scope, 'Type:', typeof role.access_scope);
                        
                        document.getElementById('roleFirstName').value = role.first_name || '';
                        document.getElementById('roleLastName').value = role.last_name || '';
                        document.getElementById('roleName').value = role.role_name || '';
                        document.getElementById('roleUsername').value = role.username || '';
                        document.getElementById('roleEmail').value = role.email || '';
                        document.getElementById('roleStatus').value = role.status || 'Active';
                        this.roleId.value = role.role_id;
                        
                        // Clear password field for editing
                        this.passwordInput.value = '';
                        
                        // First, uncheck ALL module checkboxes
                        const allCheckboxes = document.querySelectorAll('input[name="modules"]');
                        console.log('Total checkboxes in modal:', allCheckboxes.length);
                        allCheckboxes.forEach(checkbox => {
                            checkbox.checked = false;
                        });
                        
                        // Then check modules based on access_scope (comma-separated)
                        if (role.access_scope && role.access_scope.trim() !== '' && role.access_scope !== '0') {
                            const modules = role.access_scope.split(',').map(m => m.trim());
                            console.log('Parsed modules from access_scope:', modules);
                            modules.forEach(module => {
                                // Find the checkbox with matching value and check it
                                const checkbox = document.querySelector(`input[name="modules"][value="${module}"]`);
                                console.log(`Looking for module "${module}": ${checkbox ? 'FOUND' : 'NOT FOUND'}`);
                                if (checkbox) {
                                    checkbox.checked = true;
                                    console.log(`Checked box for module: ${module}`);
                                } else {
                                    console.warn('Checkbox not found for module:', module);
                                }
                            });
                        } else {
                            console.log('No valid access_scope to parse. Value was:', role.access_scope);
                        }
                        
                        // Verify checkbox states after loading
                        console.log('Final checkbox states after loading:');
                        allCheckboxes.forEach((checkbox, index) => {
                            console.log(`  Checkbox ${index} (${checkbox.value}): ${checkbox.checked}`);
                        });
                    } else {
                        this.showError(data.message || 'Failed to load role details');
                    }
                } catch (error) {
                    this.showError('Error loading role: ' + error.message);
                    console.error('Error loading role data:', error);
                }
            }

            async handleFormSubmit(e) {
                e.preventDefault();

                const formData = new FormData(this.form);
                const isEditing = this.roleId.value !== '';
                const action = isEditing ? 'update' : 'add';

                // Collect checked modules - use querySelectorAll to ensure we get all checkboxes
                const checkedModules = [];
                const allCheckboxes = document.querySelectorAll('input[name="modules"]');
                
                console.log('Total checkboxes found:', allCheckboxes.length);
                allCheckboxes.forEach((checkbox, index) => {
                    console.log(`Checkbox ${index}: value="${checkbox.value}", checked=${checkbox.checked}`);
                    if (checkbox.checked) {
                        checkedModules.push(checkbox.value);
                    }
                });

                console.log('Collected modules:', checkedModules);
                console.log('Joined access_scope:', checkedModules.join(','));

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

                console.log('Final data object:', data);

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
                    console.log('Sending data:', new URLSearchParams(data).toString());
                    
                    const response = await fetch('roles_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: new URLSearchParams(data)
                    });

                    const rawResponse = await response.text();
                    console.log('Raw response:', rawResponse);
                    
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
                        <td class="px-6 py-4 text-sm">
                            <div class="flex flex-wrap gap-1">
                                ${(role.access_scope || '').split(',').map(module => `
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
            const statsManager = new StatsManager();
            statsManager.loadStats();
            
            const roleManager = new RoleManager(statsManager);
            roleManager.init();
            new ServiceManager(statsManager);
        });

        // Dropdown menu click handler
        document.querySelectorAll('.settings-dropdown-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const dropdown = document.querySelector('[data-dropdown="settings"].settings-dropdown');
                if (dropdown) {
                    dropdown.classList.toggle('hidden');
                }
            });
        });

        // Close dropdown when clicking outside
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