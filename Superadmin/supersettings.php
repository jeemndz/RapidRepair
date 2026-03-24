<?php
session_start();
require_once __DIR__ . "/../db.php";

if (isset($_POST['logout_superadmin'])) {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
    header("Location: superaddlogin.php");
    exit();
}

if (!isset($_SESSION['superadmin_id'])) {
    header("Location: superaddlogin.php");
    exit();
}

if (isset($conn) && $conn instanceof mysqli) {
    $columnsToEnsure = [
        'role' => "VARCHAR(100) NOT NULL DEFAULT 'Superadmin' AFTER `password`",
        'access_scope' => "VARCHAR(255) NOT NULL DEFAULT 'Global Root' AFTER `role`",
        'status' => "VARCHAR(50) NOT NULL DEFAULT 'Active' AFTER `access_scope`",
        'last_modified' => "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`"
    ];

    $checkColumnStmt = $conn->prepare(
        "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'superadmin' AND COLUMN_NAME = ? LIMIT 1"
    );

    if ($checkColumnStmt) {
        foreach ($columnsToEnsure as $columnName => $columnDefinition) {
            $checkColumnStmt->bind_param("s", $columnName);
            $checkColumnStmt->execute();
            $columnResult = $checkColumnStmt->get_result();

            if (!$columnResult || $columnResult->num_rows === 0) {
                $conn->query("ALTER TABLE superadmin ADD COLUMN `{$columnName}` {$columnDefinition}");
            }
        }

        $checkColumnStmt->close();
    }
}

$superadminName = "Superadmin";
$superadminStmt = $conn->prepare("SELECT fullName FROM superadmin WHERE superadmin_id = ? LIMIT 1");
if ($superadminStmt) {
    $superadminStmt->bind_param("i", $_SESSION['superadmin_id']);
    $superadminStmt->execute();
    $superadminRes = $superadminStmt->get_result();
    if ($superadminRes && $superadminRes->num_rows > 0) {
        $superadminRow = $superadminRes->fetch_assoc();
        $superadminName = $superadminRow['fullName'] ?: $superadminName;
    }
    $superadminStmt->close();
}

function initials($name)
{
    $name = trim((string)$name);
    if ($name === '') {
        return 'NA';
    }

    $parts = preg_split('/\s+/', $name);
    if (!$parts) {
        return 'NA';
    }

    $first = strtoupper(substr($parts[0], 0, 1));
    $second = count($parts) > 1 ? strtoupper(substr($parts[count($parts) - 1], 0, 1)) : '';
    return $first . ($second ?: '');
}
?>

<!DOCTYPE html>

<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>System Settings | Cobalt Precision</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&amp;display=swap" rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
        rel="stylesheet" />
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "secondary-fixed": "#e5e7eb",
                        "on-secondary": "#ffffff",
                        "on-secondary-container": "#18181b",
                        "tertiary": "#f59e0b",
                        "on-tertiary-fixed-variant": "#9a3412",
                        "inverse-surface": "#18181b",
                        "on-tertiary": "#ffffff",
                        "primary-container": "#fee2e2",
                        "on-error-container": "#991b1b",
                        "tertiary-fixed": "#ffedd5",
                        "tertiary-container": "#fef3c7",
                        "outline": "#e5e7eb",
                        "on-secondary-fixed": "#111827",
                        "on-surface-variant": "#525252",
                        "surface-container-lowest": "#ffffff",
                        "error-container": "#fee2e2",
                        "tertiary-fixed-dim": "#fed7aa",
                        "surface-bright": "#ffffff",
                        "secondary-container": "#f5f5f5",
                        "on-primary-fixed": "#7f1d1d",
                        "surface-tint": "#b91c1c",
                        "surface-dim": "#e5e7eb",
                        "error": "#dc2626",
                        "on-tertiary-container": "#92400e",
                        "primary-fixed": "#fee2e2",
                        "on-secondary-fixed-variant": "#3f3f46",
                        "surface": "#ffffff",
                        "background": "#ffffff",
                        "outline-variant": "#d4d4d8",
                        "on-primary": "#ffffff",
                        "inverse-on-surface": "#f8fafc",
                        "on-tertiary-fixed": "#7c2d12",
                        "surface-container": "#ffffff",
                        "secondary": "#3f3f46",
                        "primary": "#b91c1c",
                        "on-primary-container": "#7f1d1d",
                        "surface-container-highest": "#ffffff",
                        "primary-fixed-dim": "#fecaca",
                        "on-surface": "#111827",
                        "on-background": "#0a0a0a",
                        "inverse-primary": "#fecaca",
                        "surface-container-high": "#ffffff",
                        "surface-variant": "#f5f5f5",
                        "on-primary-fixed-variant": "#991b1b",
                        "on-error": "#ffffff",
                        "surface-container-low": "#ffffff",
                        "secondary-fixed-dim": "#d4d4d8"
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

<body class="bg-background text-on-background antialiased selection:bg-primary-container selection:text-primary">
    <!-- SideNavBar (Shared Component) -->
        <aside
            class="flex flex-col fixed left-0 top-0 h-full z-50 w-64 border-r border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 font-['Inter'] antialiased tracking-tight shadow-sm dark:shadow-none">
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
            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors rounded-lg active:scale-95"
                href="superauditlogs.php">
                <span class="material-symbols-outlined" data-icon="assignment">assignment</span>
                <span class="text-sm">Audit Logs</span>
            </a>

            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors rounded-lg active:scale-95"
                href="superbackup.php">
                <span class="material-symbols-outlined" data-icon="backup"
                    style="font-variation-settings: 'FILL' 1;">backup</span>
                <span class="text-sm">System Backup</span>
            </a>

            <a class="flex items-center gap-3 px-3 py-2.5 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 font-bold border-r-4 border-red-700 dark:border-red-500 rounded-lg active:scale-95"
                href="supersettings.php">
                <span class="material-symbols-outlined" data-icon="settings">settings</span>
                <span class="text-sm">Settings</span>
            </a>
        </nav>
        <!-- Footer Actions (Exactly as Screen 11) -->
        <div class="p-4 border-t border-slate-100 dark:border-slate-800 space-y-2">
            <div class="flex items-center gap-3 p-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                <div class="w-10 h-10 rounded-full bg-primary-container text-primary flex items-center justify-center font-semibold text-sm">
                    <?php echo htmlspecialchars(initials($superadminName)); ?>
                </div>
                <div class="flex flex-col min-w-0">
                    <h3 class="text-sm font-semibold truncate text-slate-900 dark:text-white"><?php echo htmlspecialchars($superadminName); ?></h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 truncate">Superadmin</p>
                </div>
            </div>
            <form method="POST" class="w-full">
                <button type="submit" name="logout_superadmin"
                    class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-900/10 transition-colors cursor-pointer text-left">
                    <span class="material-symbols-outlined">logout</span>
                    <p class="text-sm font-medium">Logout</p>
                </button>
            </form>
        </div>
    </aside>
    <!-- TopAppBar Shell (Exactly as Screen 11) -->
    <header
        class="flex items-center justify-between px-8 sticky top-0 z-30 ml-64 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md h-16 border-b border-slate-200 dark:border-slate-800">
        <div class="flex items-center gap-4">
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-on-surface-variant">
                    <span class="material-symbols-outlined text-lg" data-icon="search">search</span>
                </span>
                <input
                    class="pl-10 pr-4 py-1.5 bg-surface-variant border-none text-sm rounded-lg focus:ring-2 focus:ring-primary w-64 transition-all"
                    placeholder="Search parameters..." type="text" />
            </div>
        </div>
        <div class="flex items-center gap-4">
            <button class="p-2 text-slate-500 hover:text-primary transition-colors">
                <span class="material-symbols-outlined" data-icon="notifications">notifications</span>
            </button>
            <button class="p-2 text-slate-500 hover:text-primary transition-colors">
                <span class="material-symbols-outlined" data-icon="help_outline">help_outline</span>
            </button>
        </div>
    </header>
    <!-- Main Content Canvas -->
    <main class="ml-64 p-8 min-h-screen">
        <div class="w-full">
            <div class="mb-8">
                <h2 class="text-[1.875rem] font-black text-on-background tracking-tight">System Configuration</h2>
                <p class="text-slate-500 text-sm mt-1">Manage global branding, scaling limits, and core architectural
                    permissions.</p>
            </div>
            <div class="grid grid-cols-12 gap-6">
                <!-- Section 1: System Branding (Bento Style) -->
                <section class="col-span-12 lg:col-span-8 bg-white border border-slate-200 rounded-lg shadow-sm p-6">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 bg-primary-container flex items-center justify-center rounded-lg">
                            <span class="material-symbols-outlined text-primary">palette</span>
                        </div>
                        <h3 class="text-xl font-bold text-slate-900">System Branding</h3>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div class="space-y-4">
                            <div>
                                <label
                                    class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">System
                                    Name</label>
                                <input
                                    class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-primary/20 outline-none"
                                    type="text" value="Cobalt Precision" />
                            </div>
                            <div>
                                <label
                                    class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Primary
                                    Branding Color</label>
                                <div class="flex items-center gap-3">
                                    <input class="h-10 w-10 p-0 border-0 rounded cursor-pointer overflow-hidden"
                                        type="color" value="#b91c1c" />
                                    <input class="flex-1 px-3 py-2 border border-slate-200 rounded-lg text-sm font-mono"
                                        type="text" value="#B91C1C" />
                                </div>
                            </div>
                        </div>
                        <div
                            class="bg-slate-50 border border-dashed border-slate-300 rounded-lg p-6 flex flex-col items-center justify-center text-center">
                            <img class="h-12 w-12 mb-3 opacity-80"
                                data-alt="System branding logo placeholder with architectural design"
                                src="https://lh3.googleusercontent.com/aida-public/AB6AXuCl5adYWZB3DQQed5l4hP_RHz1zAoS_cJ5A3u1puaDfwtqve1cj_Mlzs0UfUP663oUN9Fv43SsvhjSsk9Upm1DOGNwZSlfYRskHCL3lEDoty4vXFurSJ3gYm_GMcNDgdAd7DjtzP8lpJuW3oIa12cNn-XcQ2m35EsYAFEs59zWyXTiwwvhCOEqGwIQLo4M5ypa8DVK2_DPz7nTXFxohsVMS0O-AESbCybQquP0sBXvszqDAb20y4rndguddCX-XG07HFFIzR_Xh7st_" />
                            <p class="text-sm font-bold text-slate-700">System Logo</p>
                            <p class="text-xs text-slate-400 mt-1 mb-4">SVG, PNG or JPG. Max 2MB.</p>
                            <button
                                class="px-4 py-2 bg-white border border-slate-200 text-slate-700 text-xs font-bold rounded-lg hover:bg-slate-50 transition-colors shadow-sm">Replace
                                Logo</button>
                        </div>
                    </div>
                </section>
                <!-- Section 2: Tenant Limits (Compact Sidebar Card) -->
                <section class="col-span-12 lg:col-span-4 bg-white border border-slate-200 rounded-lg shadow-sm p-6">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 bg-primary-container flex items-center justify-center rounded-lg">
                            <span class="material-symbols-outlined text-primary">analytics</span>
                        </div>
                        <h3 class="text-xl font-bold text-slate-900">Tenant Limits</h3>
                    </div>
                    <div class="space-y-5">
                        <div class="flex justify-between items-end">
                            <div class="flex-1">
                                <label
                                    class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Max
                                    Tenants</label>
                                <input
                                    class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-primary/20 outline-none"
                                    type="number" value="250" />
                            </div>
                        </div>
                        <div>
                            <label
                                class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Storage
                                Limit (Per Tenant)</label>
                            <div class="flex items-center gap-2">
                                <input
                                    class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-primary/20 outline-none"
                                    type="number" value="50" />
                                <span class="text-xs font-bold text-slate-400">GB</span>
                            </div>
                        </div>
                        <div class="pt-4 border-t border-slate-50">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-bold text-slate-700">Auto-approval</p>
                                    <p class="text-xs text-slate-400">Instant activation for new tenants</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input class="sr-only peer" type="checkbox" value="" />
                                    <div
                                        class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary">
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                </section>
                <!-- Section 3: User Roles & Permissions (Full Width Table) -->
                <section class="col-span-12 bg-white border border-slate-200 rounded-lg shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-primary-container flex items-center justify-center rounded-lg">
                                <span class="material-symbols-outlined text-primary">admin_panel_settings</span>
                            </div>
                            <h3 class="text-xl font-bold text-slate-900">User Roles &amp; Permissions</h3>
                        </div>
                        <button
                            class="px-4 py-2 bg-primary text-white text-xs font-bold rounded-lg hover:bg-opacity-90 active:scale-95 transition-all shadow-md flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm">add</span>
                            Create New Role
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-slate-50 border-b border-slate-200">
                                <tr>
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Role
                                    </th>
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                        Access Scope</th>
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                        Status</th>
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Last
                                        Modified</th>
                                    <th
                                        class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-right">
                                        Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col">
                                            <span class="text-sm font-bold text-slate-900">Superadmin</span>
                                            <span class="text-xs text-slate-500">Total architectural control</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-wrap gap-1.5">
                                            <span
                                                class="px-2 py-0.5 bg-primary-container text-primary text-[10px] font-bold rounded-full">Global
                                                Root</span>
                                            <span
                                                class="px-2 py-0.5 bg-primary-container text-primary text-[10px] font-bold rounded-full">Financials</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="px-2.5 py-1 bg-green-100 text-green-700 text-xs font-bold rounded-full">System
                                            Active</span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-500">Oct 12, 2023</td>
                                    <td class="px-6 py-4 text-right">
                                        <button class="text-slate-400 hover:text-primary transition-colors">
                                            <span class="material-symbols-outlined">edit</span>
                                        </button>
                                    </td>
                                </tr>
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col">
                                            <span class="text-sm font-bold text-slate-900">Support</span>
                                            <span class="text-xs text-slate-500">Tenant assistance &amp;
                                                ticketing</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-wrap gap-1.5">
                                            <span
                                                class="px-2 py-0.5 bg-secondary-container text-secondary text-[10px] font-bold rounded-full">Ticket
                                                Read/Write</span>
                                            <span
                                                class="px-2 py-0.5 bg-secondary-container text-secondary text-[10px] font-bold rounded-full">User
                                                Reset</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="px-2.5 py-1 bg-green-100 text-green-700 text-xs font-bold rounded-full">Active</span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-500">Jan 05, 2024</td>
                                    <td class="px-6 py-4 text-right">
                                        <button class="text-slate-400 hover:text-primary transition-colors">
                                            <span class="material-symbols-outlined">edit</span>
                                        </button>
                                    </td>
                                </tr>
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col">
                                            <span class="text-sm font-bold text-slate-900">Auditor</span>
                                            <span class="text-xs text-slate-500">Compliance &amp; log monitoring</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-wrap gap-1.5">
                                            <span
                                                class="px-2 py-0.5 bg-slate-100 text-slate-500 text-[10px] font-bold rounded-full">Read
                                                Only</span>
                                            <span
                                                class="px-2 py-0.5 bg-slate-100 text-slate-500 text-[10px] font-bold rounded-full">Log
                                                Export</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="px-2.5 py-1 bg-green-100 text-green-700 text-xs font-bold rounded-full">Active</span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-500">Mar 22, 2024</td>
                                    <td class="px-6 py-4 text-right">
                                        <button class="text-slate-400 hover:text-primary transition-colors">
                                            <span class="material-symbols-outlined">edit</span>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
                <div class="col-span-12 flex justify-end gap-3 mt-4 mb-12">
                    <button
                        class="px-6 py-2.5 bg-white border border-slate-300 text-slate-600 text-sm font-bold rounded-lg hover:bg-slate-50 transition-all">Discard
                        Changes</button>
                    <button
                        class="px-8 py-2.5 bg-primary text-white text-sm font-bold rounded-lg hover:shadow-lg active:scale-95 transition-all">Save
                        Global Settings</button>
                </div>
            </div>
        </div>
    </main>
</body>

</html>