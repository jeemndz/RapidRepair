<?php
session_start();

include __DIR__ . '/../db.php';

if (file_exists(__DIR__ . '/../session_security.php')) {
    include __DIR__ . '/../session_security.php';
}

if (file_exists(__DIR__ . '/access_control.php')) {
    include __DIR__ . '/access_control.php';
}

if (!isset($_SESSION['tenantID'])) {
    header("Location: tenantlogin.php");
    exit;
}

$tenantID = (int) $_SESSION['tenantID'];

if (function_exists('enforceModuleAccess')) {
    enforceModuleAccess($tenantID, basename(__FILE__));
}

$accessibleModules = [];

if (function_exists('getAccessibleModules')) {
    $accessibleModules = getAccessibleModules($tenantID);
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function canAccessStorageModule($moduleFile, $accessibleModules)
{
    if (empty($accessibleModules)) {
        return true;
    }

    return in_array($moduleFile, $accessibleModules, true);
}

$shopName = "RapidRepair";

$stmt = $conn->prepare("SELECT shopName FROM owners WHERE tenantID = ? LIMIT 1");
$stmt->bind_param("i", $tenantID);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $shopName = $row['shopName'] ?: "RapidRepair";
}

if (($_SESSION['userType'] ?? '') === 'owner') {
    $loggedInUserName = $_SESSION['shopName'] ?? $shopName;
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
    <meta charset="UTF-8">
    <title>Storage Management | <?= h($shopName) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet">

    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        primary: "#1152d4",
                        surface: "#f6f6f8",
                        "surface-container": "#ffffff",
                        "surface-variant": "#f1f5f9",
                        "on-surface": "#0f172a",
                        "on-surface-variant": "#64748b",
                        outline: "#e2e8f0",
                        "outline-variant": "#cbd5e1",
                        error: "#ef4444",
                        tertiary: "#f59e0b"
                    },
                    fontFamily: {
                        body: ["Inter"],
                        headline: ["Inter"]
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

    <div
        class="md:hidden fixed top-0 left-0 right-0 bg-white border-b border-slate-200 px-4 py-3 z-50 flex items-center justify-between">
        <button id="sidebarToggle" type="button"
            class="inline-flex items-center justify-center w-10 h-10 rounded-lg hover:bg-slate-100 transition-colors">
            <span class="material-symbols-outlined">menu</span>
        </button>

        <h2 class="text-lg font-bold truncate flex-1 ml-3"><?= h($shopName) ?></h2>
    </div>

    <div id="sidebarOverlay" class="hidden fixed inset-0 bg-black/50 z-30 md:hidden"></div>

    <div class="flex h-screen overflow-hidden pt-16 md:pt-0">

        <aside id="sidebar"
            class="fixed md:static left-0 top-0 h-screen w-64 flex-shrink-0 border-r border-slate-200 bg-white flex flex-col overflow-y-auto z-40 -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out pt-16 md:pt-0">
            <div class="p-6">
                <div class="flex items-center gap-3 mb-8">
                    <div class="bg-primary rounded-lg p-2 text-white">
                        <span class="material-symbols-outlined">directions_car</span>
                    </div>

                    <div>
                        <h1 class="text-lg font-bold leading-none"><?= h($shopName) ?></h1>
                        <p class="text-xs text-slate-500 mt-1">Your Repair Shop</p>
                    </div>
                </div>

                <nav class="space-y-1">
                    <?php if (canAccessStorageModule('dashboardadmin.php', $accessibleModules)): ?>
                        <a href="dashboardadmin.php"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors">
                            <span class="material-symbols-outlined text-[22px]">dashboard</span> Dashboard
                        </a>
                    <?php endif; ?>

                    <?php if (canAccessStorageModule('repairjobsadmin.php', $accessibleModules)): ?>
                        <a href="repairjobsadmin.php"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors">
                            <span class="material-symbols-outlined text-[22px]">build</span> Repair Jobs
                        </a>
                    <?php endif; ?>

                    <?php if (canAccessStorageModule('vehicleadmin.php', $accessibleModules)): ?>
                        <a href="vehicleadmin.php"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors">
                            <span class="material-symbols-outlined text-[22px]">directions_car</span> Vehicles
                        </a>
                    <?php endif; ?>

                    <?php if (canAccessStorageModule('appointmentadmin.php', $accessibleModules)): ?>
                        <a href="appointmentadmin.php"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors">
                            <span class="material-symbols-outlined text-[22px]">event</span> Appointments
                        </a>
                    <?php endif; ?>

                    <?php if (canAccessStorageModule('inventoryadmin.php', $accessibleModules)): ?>
                        <a href="inventoryadmin.php"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors">
                            <span class="material-symbols-outlined text-[22px]">inventory_2</span> Inventory
                        </a>
                    <?php endif; ?>

                    <?php if (canAccessStorageModule('customeradmin.php', $accessibleModules)): ?>
                        <a href="customeradmin.php"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors">
                            <span class="material-symbols-outlined text-[22px]">group</span> Customers
                        </a>
                    <?php endif; ?>

                    <?php if (canAccessStorageModule('paymentsadmin.php', $accessibleModules)): ?>
                        <a href="paymentsadmin.php"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors">
                            <span class="material-symbols-outlined text-[22px]">payments</span> Payments
                        </a>
                    <?php endif; ?>

                    <?php if (canAccessStorageModule('reportsadmin.php', $accessibleModules)): ?>
                        <a href="reportsadmin.php"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors">
                            <span class="material-symbols-outlined text-[22px]">description</span> Reports
                        </a>
                    <?php endif; ?>

                    <div class="pt-4 mt-4 border-t border-slate-100">
                        <button type="button" id="settingsDropdownBtn"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-blue-50 text-primary font-bold w-full transition-colors">
                            <span class="material-symbols-outlined text-[22px]">settings</span>
                            <span class="flex-1 text-left">Settings</span>
                            <span id="settingsDropdownIcon"
                                class="material-symbols-outlined text-[18px]">expand_less</span>
                        </button>

                        <div id="settingsDropdownMenu" class="mt-1 ml-8 space-y-1">
                            <?php if (canAccessStorageModule('storage_managementadmin.php', $accessibleModules)): ?>
                                <a href="storage_managementadmin.php"
                                    class="flex items-center gap-2 px-3 py-2 rounded-lg bg-blue-50 text-primary font-bold text-sm">
                                    <span class="material-symbols-outlined text-[18px]">cloud</span> Storage Management
                                </a>
                            <?php endif; ?>

                            <?php if (canAccessStorageModule('accountbillingadmin.php', $accessibleModules)): ?>
                                <a href="accountbillingadmin.php"
                                    class="flex items-center gap-2 px-3 py-2 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors text-sm">
                                    <span class="material-symbols-outlined text-[18px]">receipt_long</span> Account Billing
                                </a>
                            <?php endif; ?>

                            <?php if (canAccessStorageModule('websitecustomeadmin.php', $accessibleModules)): ?>
                                <a href="websitecustomeadmin.php"
                                    class="flex items-center gap-2 px-3 py-2 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors text-sm">
                                    <span class="material-symbols-outlined text-[18px]">palette</span> Website Customizer
                                </a>
                            <?php endif; ?>

                            <?php if (canAccessStorageModule('settingsadmin.php', $accessibleModules)): ?>
                                <a href="settingsadmin.php"
                                    class="flex items-center gap-2 px-3 py-2 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors text-sm">
                                    <span class="material-symbols-outlined text-[18px]">tune</span> Settings
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </nav>
            </div>

            <div class="mt-auto w-full p-4 border-t border-slate-200">
                <div class="flex items-center gap-3">
                    <div class="size-10 rounded-full bg-slate-200 flex items-center justify-center overflow-hidden">
                        <span class="material-symbols-outlined text-slate-500">person</span>
                    </div>

                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold truncate"><?= h($loggedInUserName) ?></p>
                        <p class="text-xs text-slate-500 truncate"><?= h($loggedInUserRole) ?></p>
                    </div>

                    <form method="post" action="../logout/logout.php" class="inline">
                        <input type="hidden" name="action" value="confirm">
                        <button type="submit" class="text-slate-400 hover:text-red-500 transition-colors"
                            title="Logout">
                            <span class="material-symbols-outlined text-xl">logout</span>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <main class="flex-1 overflow-y-auto flex flex-col">
            <header
                class="sticky top-0 z-40 w-full border-b border-slate-200 bg-white/80 backdrop-blur-md flex items-center justify-between px-8 h-16">
                <div>
                    <h2 class="text-lg font-black text-slate-900 tracking-tight">Storage Management</h2>
                </div>

                <div class="flex items-center gap-4">
                    <button class="p-2 text-slate-500 hover:text-primary transition-all"><span
                            class="material-symbols-outlined">notifications</span></button>
                    <button class="p-2 text-slate-500 hover:text-primary transition-all"><span
                            class="material-symbols-outlined">help_outline</span></button>
                </div>
            </header>

            <div class="p-8 space-y-8 flex-1">
                <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
                    <div>
                        <h1 class="text-3xl font-black text-slate-900 tracking-tight">Storage Management</h1>
                        <p class="text-sm text-slate-500 mt-1">View storage usage and database records used by this
                            repair shop.</p>
                    </div>

                    <div class="flex gap-3">
                        <a href="accountbillingadmin.php"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white rounded-lg text-sm font-bold hover:bg-blue-800 transition-colors">
                            <span class="material-symbols-outlined text-[18px]">upgrade</span> Upgrade Plan
                        </a>

                        <button onclick="loadStorage()"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 rounded-lg text-sm font-bold text-slate-700 hover:bg-slate-50 transition-colors">
                            <span class="material-symbols-outlined text-[18px]">refresh</span> Refresh
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 bg-white border border-slate-200 rounded-lg p-6 shadow-sm">
                        <div class="flex justify-between items-start mb-6">
                            <div class="flex items-center gap-2">
                                <div class="p-2 bg-blue-50 rounded-lg"><span
                                        class="material-symbols-outlined text-primary">cloud</span></div>
                                <div>
                                    <h3 class="text-lg font-black text-slate-900">Storage Usage</h3>
                                    <p id="planName" class="text-sm text-slate-500">Loading subscription plan...</p>
                                </div>
                            </div>

                            <span id="percentageText"
                                class="text-xl font-black text-primary bg-blue-50 px-3 py-1 rounded-full">0%</span>
                        </div>

                        <div class="mb-5">
                            <p class="text-4xl font-black text-slate-900">
                                <span id="usedStorage">0 GB</span>
                                <span class="text-base font-medium text-slate-500">of <span id="limitStorage">0
                                        GB</span> used</span>
                            </p>
                        </div>

                        <div class="w-full h-5 bg-slate-100 rounded-full overflow-hidden">
                            <div id="storageBar" class="h-full bg-primary rounded-full transition-all duration-500"
                                style="width: 0%;"></div>
                        </div>

                        <p id="storageMessage" class="mt-4 text-sm font-semibold text-slate-500">Checking storage
                            status...</p>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
                            <div class="bg-slate-50 rounded-lg p-4 border border-slate-200">
                                <p class="text-xs text-slate-500 font-bold uppercase tracking-wide">Plan</p>
                                <p id="planBox" class="text-lg font-black text-slate-800 mt-1">—</p>
                            </div>

                            <div class="bg-slate-50 rounded-lg p-4 border border-slate-200">
                                <p class="text-xs text-slate-500 font-bold uppercase tracking-wide">Billing Cycle</p>
                                <p id="billingCycle" class="text-lg font-black text-slate-800 mt-1">—</p>
                            </div>

                            <div class="bg-slate-50 rounded-lg p-4 border border-slate-200">
                                <p class="text-xs text-slate-500 font-bold uppercase tracking-wide">Next Billing Date
                                </p>
                                <p id="nextBilling" class="text-lg font-black text-slate-800 mt-1">—</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-900 text-white rounded-lg p-6 shadow-sm relative overflow-hidden">
                        <div class="relative z-10 flex flex-col h-full justify-between">
                            <div>
                                <div class="p-2 bg-white/10 rounded-lg inline-flex mb-4"><span
                                        class="material-symbols-outlined text-white">workspace_premium</span></div>
                                <h3 class="text-2xl font-black mb-3">Need more storage?</h3>
                                <p class="text-slate-300 text-sm leading-relaxed">Upgrade your subscription plan to
                                    increase file storage for documents, vehicle images, repair photos, receipts, and
                                    reports.</p>
                            </div>
                            <a href="accountbillingadmin.php"
                                class="mt-6 block text-center bg-white text-slate-900 font-black px-5 py-3 rounded-lg hover:bg-blue-50 transition-colors">View
                                Billing</a>
                        </div>
                        <div class="absolute -right-10 -bottom-10 opacity-10"><span
                                class="material-symbols-outlined text-[160px]">cloud_upload</span></div>
                    </div>
                </div>

                <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-black text-slate-800">Storage Information</h3>
                            <p class="text-sm text-slate-500">These files are counted from your tenant upload folder.
                            </p>
                        </div>
                        <span class="material-symbols-outlined text-slate-400">info</span>
                    </div>

                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
                        <div class="border border-slate-200 rounded-lg p-4 bg-white">
                            <div class="p-2 bg-blue-50 rounded-lg inline-flex mb-3"><span
                                    class="material-symbols-outlined text-primary">folder</span></div>
                            <p class="text-sm text-slate-500">Used Storage (MB)</p>
                            <p id="usedMB" class="text-2xl font-black text-slate-800">0 MB</p>
                        </div>

                        <div class="border border-slate-200 rounded-lg p-4 bg-white">
                            <div class="p-2 bg-cyan-50 rounded-lg inline-flex mb-3"><span
                                    class="material-symbols-outlined text-cyan-600">data_usage</span></div>
                            <p class="text-sm text-slate-500">Used Storage (KB)</p>
                            <p id="usedKB" class="text-2xl font-black text-slate-800">0 KB</p>
                        </div>

                        <div class="border border-slate-200 rounded-lg p-4 bg-white">
                            <div class="p-2 bg-blue-50 rounded-lg inline-flex mb-3"><span
                                    class="material-symbols-outlined text-primary">database</span></div>
                            <p class="text-sm text-slate-500">Storage Limit</p>
                            <p id="limitGB" class="text-2xl font-black text-slate-800">0 GB</p>
                        </div>

                        <div class="border border-slate-200 rounded-lg p-4 bg-white">
                            <div class="p-2 bg-emerald-50 rounded-lg inline-flex mb-3"><span
                                    class="material-symbols-outlined text-emerald-600">verified</span></div>
                            <p class="text-sm text-slate-500">Status</p>
                            <p id="storageStatus" class="text-2xl font-black text-emerald-600">Normal</p>
                        </div>

                        <div class="border border-slate-200 rounded-lg p-4 bg-white">
                            <div class="p-2 bg-purple-50 rounded-lg inline-flex mb-3"><span
                                    class="material-symbols-outlined text-purple-600">table_rows</span></div>
                            <p class="text-sm text-slate-500">Total Records</p>
                            <p id="totalRecords" class="text-2xl font-black text-slate-800">0</p>
                        </div>

                        <div class="border border-slate-200 rounded-lg p-4 bg-white">
                            <div class="p-2 bg-slate-100 rounded-lg inline-flex mb-3"><span
                                    class="material-symbols-outlined text-slate-600">folder_open</span></div>
                            <p class="text-sm text-slate-500">Your Uploaded Files</p>
                            <p class="text-sm font-bold text-slate-800 break-all mt-1">uploads/files/<?= $tenantID ?>/
                            </p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
                        <h3 class="text-lg font-black text-slate-800">Record Usage</h3>
                        <p class="text-sm text-slate-500">Total saved database records used by this tenant. Services are
                            not included.</p>
                    </div>

                    <div class="p-6 grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
                        <div class="border border-slate-200 rounded-lg p-4 bg-white">
                            <p class="text-sm text-slate-500">Customers</p>
                            <p id="recordsCustomers" class="text-2xl font-black text-slate-900 mt-1">0</p>
                        </div>

                        <div class="border border-slate-200 rounded-lg p-4 bg-white">
                            <p class="text-sm text-slate-500">Vehicles</p>
                            <p id="recordsVehicles" class="text-2xl font-black text-slate-900 mt-1">0</p>
                        </div>

                        <div class="border border-slate-200 rounded-lg p-4 bg-white">
                            <p class="text-sm text-slate-500">Appointments</p>
                            <p id="recordsAppointments" class="text-2xl font-black text-slate-900 mt-1">0</p>
                        </div>

                        <div class="border border-slate-200 rounded-lg p-4 bg-white">
                            <p class="text-sm text-slate-500">Repair Jobs</p>
                            <p id="recordsRepairJobs" class="text-2xl font-black text-slate-900 mt-1">0</p>
                        </div>

                        <div class="border border-slate-200 rounded-lg p-4 bg-white">
                            <p class="text-sm text-slate-500">Diagnostics</p>
                            <p id="recordsDiagnostics" class="text-2xl font-black text-slate-900 mt-1">0</p>
                        </div>

                        <div class="border border-slate-200 rounded-lg p-4 bg-white">
                            <p class="text-sm text-slate-500">Inventory Items</p>
                            <p id="recordsInventory" class="text-2xl font-black text-slate-900 mt-1">0</p>
                        </div>

                        <div class="border border-slate-200 rounded-lg p-4 bg-white">
                            <p class="text-sm text-slate-500">Payments</p>
                            <p id="recordsPayments" class="text-2xl font-black text-slate-900 mt-1">0</p>
                        </div>

                        <div class="border border-slate-200 rounded-lg p-4 bg-slate-900 text-white">
                            <p class="text-sm text-slate-300">Total Records</p>
                            <p id="recordsTotalBox" class="text-2xl font-black mt-1">0</p>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-6">
                    <div class="flex items-start gap-4">
                        <div class="p-2 bg-blue-100 rounded-lg"><span
                                class="material-symbols-outlined text-primary">tips_and_updates</span></div>
                        <div>
                            <h3 class="text-lg font-black text-slate-900">Storage Reminder</h3>
                            <p class="text-sm text-slate-600 mt-1">When storage reaches 80%, the system will show a
                                warning. When it reaches 100%, upgrade the plan or remove unused uploaded files.</p>
                        </div>
                    </div>
                </div>
            </div>

            <footer
                class="bg-white border-t border-slate-200 px-8 py-4 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex gap-8">
                    <div class="flex items-center gap-3">
                        <div class="p-1.5 bg-blue-50 rounded"><span
                                class="material-symbols-outlined text-primary text-base">cloud</span></div>
                        <div>
                            <p class="text-[10px] text-slate-400 font-black uppercase tracking-widest">Storage
                                Monitoring</p>
                            <p class="text-sm font-bold text-slate-900">Active</p>
                        </div>
                    </div>

                    <div class="hidden md:flex items-center gap-3">
                        <div class="p-1.5 bg-blue-50 rounded"><span
                                class="material-symbols-outlined text-primary text-base">business</span></div>
                        <div>
                            <p class="text-[10px] text-slate-400 font-black uppercase tracking-widest">Shop</p>
                            <p class="text-sm font-bold text-slate-900"><?= h($shopName) ?></p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-4 text-xs font-medium text-slate-400">
                    <span>© 2026 RapidRepair</span>
                    <span class="w-1 h-1 rounded-full bg-slate-300"></span>
                    <span>Storage Management Module</span>
                </div>
            </footer>
        </main>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            loadStorage();
            setupSidebar();
        });

        function setupSidebar() {
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebarOverlay = document.getElementById('sidebarOverlay');

            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function () {
                    sidebar.classList.toggle('-translate-x-full');
                    sidebarOverlay.classList.toggle('hidden');
                });
            }

            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', function () {
                    sidebar.classList.add('-translate-x-full');
                    sidebarOverlay.classList.add('hidden');
                });
            }

            const settingsDropdownBtn = document.getElementById('settingsDropdownBtn');
            const settingsDropdownMenu = document.getElementById('settingsDropdownMenu');
            const settingsDropdownIcon = document.getElementById('settingsDropdownIcon');

            if (settingsDropdownBtn && settingsDropdownMenu && settingsDropdownIcon) {
                settingsDropdownBtn.addEventListener('click', function () {
                    settingsDropdownMenu.classList.toggle('hidden');
                    settingsDropdownIcon.innerText = settingsDropdownMenu.classList.contains('hidden') ? 'expand_more' : 'expand_less';
                });
            }
        }

        function setText(id, value) {
            const el = document.getElementById(id);
            if (el) {
                el.innerText = value;
            }
        }

        function loadStorage() {
            fetch("storage_api.php?refresh=" + Date.now())
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        setText("planName", data.message);
                        setText("storageMessage", data.message);
                        const msg = document.getElementById("storageMessage");
                        if (msg) msg.className = "mt-4 text-sm font-semibold text-red-600";
                        return;
                    }

                    const percentage = data.percentage ?? 0;

                    setText("planName", (data.plan_name ?? "Subscription") + " Plan");
                    setText("percentageText", percentage + "%");

                    const usedKB = parseFloat(data.used_kb || 0).toFixed(2);
                    const usedMB = parseFloat(data.used_mb || 0).toFixed(2);
                    const usedGB = parseFloat(data.used_gb || 0).toFixed(2);

                    setText("usedKB", usedKB + " KB");
                    setText("usedMB", usedMB + " MB");
                    setText("usedStorage", usedGB + " GB");

                    setText("limitStorage", (data.storage_limit_gb ?? 0) + " GB");
                    setText("planBox", data.plan_name ?? "—");
                    setText("billingCycle", formatText(data.billing_cycle));
                    setText("nextBilling", data.next_billing_date ?? "—");
                    setText("limitGB", (data.storage_limit_gb ?? 0) + " GB");

                    if (data.record_usage) {
                        setText("totalRecords", data.total_records ?? 0);
                        setText("recordsTotalBox", data.total_records ?? 0);
                        setText("recordsCustomers", data.record_usage.customers ?? 0);
                        setText("recordsVehicles", data.record_usage.vehicles ?? 0);
                        setText("recordsAppointments", data.record_usage.appointments ?? 0);
                        setText("recordsRepairJobs", data.record_usage.repair_jobs ?? 0);
                        setText("recordsDiagnostics", data.record_usage.diagnostics ?? 0);
                        setText("recordsInventory", data.record_usage.inventory_items ?? 0);
                        setText("recordsPayments", data.record_usage.payments ?? 0);
                    }

                    const bar = document.getElementById("storageBar");
                    const message = document.getElementById("storageMessage");
                    const status = document.getElementById("storageStatus");
                    const percentText = document.getElementById("percentageText");

                    if (bar) bar.style.width = percentage + "%";

                    if (data.is_full) {
                        if (bar) bar.className = "h-full bg-red-600 rounded-full transition-all duration-500";
                        if (message) {
                            message.innerText = "Storage is full. Please upgrade your plan or delete unused files.";
                            message.className = "mt-4 text-sm font-semibold text-red-600";
                        }
                        if (status) {
                            status.innerText = "Full";
                            status.className = "text-2xl font-black text-red-600";
                        }
                        if (percentText) percentText.className = "text-xl font-black text-red-600 bg-red-50 px-3 py-1 rounded-full";
                    } else if (data.is_warning) {
                        if (bar) bar.className = "h-full bg-orange-500 rounded-full transition-all duration-500";
                        if (message) {
                            message.innerText = "Storage almost full. Consider upgrading your plan.";
                            message.className = "mt-4 text-sm font-semibold text-orange-600";
                        }
                        if (status) {
                            status.innerText = "Warning";
                            status.className = "text-2xl font-black text-orange-600";
                        }
                        if (percentText) percentText.className = "text-xl font-black text-orange-600 bg-orange-50 px-3 py-1 rounded-full";
                    } else {
                        if (bar) bar.className = "h-full bg-primary rounded-full transition-all duration-500";
                        if (message) {
                            message.innerText = "Storage usage is normal.";
                            message.className = "mt-4 text-sm font-semibold text-emerald-600";
                        }
                        if (status) {
                            status.innerText = "Normal";
                            status.className = "text-2xl font-black text-emerald-600";
                        }
                        if (percentText) percentText.className = "text-xl font-black text-primary bg-blue-50 px-3 py-1 rounded-full";
                    }
                })
                .catch(error => {
                    console.error(error);
                    setText("storageMessage", "Failed to load storage data.");
                    const msg = document.getElementById("storageMessage");
                    if (msg) msg.className = "mt-4 text-sm font-semibold text-red-600";
                });
        }

        function formatText(text) {
            if (!text) return "—";
            return text.charAt(0).toUpperCase() + text.slice(1);
        }
    </script>

</body>

</html>