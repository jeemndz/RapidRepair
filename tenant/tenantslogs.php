<?php
session_start();
require_once __DIR__ . '/../db.php';
include __DIR__ . '/../session_security.php';
include __DIR__ . '/access_control.php';

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

$loginSlug = '';
if (isset($_SESSION['login_slug']) && trim((string) $_SESSION['login_slug']) !== '') {
    $loginSlug = trim((string) $_SESSION['login_slug']);
} elseif (isset($_GET['shop']) && trim((string) $_GET['shop']) !== '') {
    $loginSlug = trim((string) $_GET['shop']);
    $_SESSION['login_slug'] = $loginSlug;
}

if ($loginSlug === '') {
    session_unset();
    session_destroy();
    header('Location: tenantlogin.php');
    exit;
}

$ownerStmt = mysqli_prepare($conn, 'SELECT shopName FROM owners WHERE tenantID = ? AND login_slug = ? LIMIT 1');
if (!$ownerStmt) {
    die('Unable to validate tenant.');
}
mysqli_stmt_bind_param($ownerStmt, 'is', $tenantID, $loginSlug);
mysqli_stmt_execute($ownerStmt);
$ownerResult = mysqli_stmt_get_result($ownerStmt);
$owner = $ownerResult ? mysqli_fetch_assoc($ownerResult) : null;
mysqli_stmt_close($ownerStmt);

if (!$owner) {
    session_unset();
    session_destroy();
    header('Location: tenantlogin.php');
    exit;
}

$_SESSION['login_slug'] = $loginSlug;
$shopName = !empty($owner['shopName']) ? $owner['shopName'] : 'RapidRepair';
$shopQuery = urlencode($loginSlug);

// Get system logs for this tenant
$page = max(1, (int) ($_GET['page'] ?? 1));
$recordsPerPage = 5;
$offset = ($page - 1) * $recordsPerPage;

$logsCountStmt = mysqli_prepare(
    $conn,
    'SELECT COUNT(*) as total FROM system_logs WHERE tenantID = ?'
);
if ($logsCountStmt) {
    mysqli_stmt_bind_param($logsCountStmt, 'i', $tenantID);
    mysqli_stmt_execute($logsCountStmt);
    $countResult = mysqli_stmt_get_result($logsCountStmt);
    $countRow = mysqli_fetch_assoc($countResult);
    $totalLogs = (int) ($countRow['total'] ?? 0);
    mysqli_stmt_close($logsCountStmt);
}

$totalPages = max(1, (int) ceil($totalLogs / $recordsPerPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $recordsPerPage;

$logsStmt = mysqli_prepare(
    $conn,
    'SELECT log_id, user_id, user_name, user_role, action, entity_type, entity_id, details, created_at, ip_address, user_agent 
     FROM system_logs 
     WHERE tenantID = ? 
     ORDER BY created_at DESC 
     LIMIT ? OFFSET ?'
);

$systemLogs = [];
if ($logsStmt) {
    mysqli_stmt_bind_param($logsStmt, 'iii', $tenantID, $recordsPerPage, $offset);
    mysqli_stmt_execute($logsStmt);
    $logsResult = mysqli_stmt_get_result($logsStmt);
    while ($logsResult && $row = mysqli_fetch_assoc($logsResult)) {
        $systemLogs[] = $row;
    }
    mysqli_stmt_close($logsStmt);
}

// Get stats
$statsStmt = mysqli_prepare($conn, 'SELECT COUNT(*) as total FROM system_logs WHERE tenantID = ? AND DATE(created_at) = CURDATE()');
$todayLogs = 0;
if ($statsStmt) {
    mysqli_stmt_bind_param($statsStmt, 'i', $tenantID);
    mysqli_stmt_execute($statsStmt);
    $statsResult = mysqli_stmt_get_result($statsStmt);
    $statsRow = mysqli_fetch_assoc($statsResult);
    $todayLogs = (int) ($statsRow['total'] ?? 0);
    mysqli_stmt_close($statsStmt);
}

function h($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function getActionBadgeClass($action): string {
    return match ($action) {
        'CREATE', 'INSERT' => 'bg-emerald-50 text-emerald-700',
        'UPDATE', 'MODIFY' => 'bg-blue-50 text-blue-700',
        'DELETE' => 'bg-red-50 text-error',
        'LOGIN' => 'bg-slate-100 text-slate-700',
        default => 'bg-slate-100 text-slate-700',
    };
}

function getStatusIcon($action): string {
    return match ($action) {
        'CREATE', 'INSERT', 'UPDATE', 'MODIFY' => 'check_circle',
        'DELETE' => 'delete',
        'LOGIN' => 'login',
        default => 'info',
    };
}
?>
<!DOCTYPE html>

<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title><?php echo h($shopName); ?> | System Logs</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&amp;display=swap"
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
                    "colors": {
                        "on-secondary-container": "#1e293b",
                        "tertiary-fixed": "#ffedd5",
                        "tertiary": "#f59e0b",
                        "secondary-fixed-dim": "#cbd5e1",
                        "on-primary-container": "#1152d4",
                        "on-error-container": "#991b1b",
                        "surface": "#f6f6f8",
                        "background": "#f6f6f8",
                        "on-primary-fixed-variant": "#1d4ed8",
                        "on-tertiary": "#ffffff",
                        "surface-container-high": "#ffffff",
                        "surface-container": "#ffffff",
                        "surface-container-lowest": "#ffffff",
                        "on-secondary": "#ffffff",
                        "on-primary": "#ffffff",
                        "inverse-primary": "#b4c5ff",
                        "error-container": "#fee2e2",
                        "surface-dim": "#d9d9e4",
                        "surface-container-low": "#ffffff",
                        "secondary-fixed": "#e2e8f0",
                        "outline-variant": "#cbd5e1",
                        "on-secondary-fixed-variant": "#334155",
                        "tertiary-fixed-dim": "#fed7aa",
                        "error": "#ef4444",
                        "on-background": "#0f172a",
                        "primary-fixed-dim": "#bfdbfe",
                        "inverse-on-surface": "#f8fafc",
                        "on-error": "#ffffff",
                        "surface-variant": "#f1f5f9",
                        "on-tertiary-fixed": "#7c2d12",
                        "primary-container": "#eef2ff",
                        "primary": "#1152d4",
                        "surface-tint": "#1152d4",
                        "outline": "#e2e8f0",
                        "surface-bright": "#ffffff",
                        "secondary": "#475569",
                        "primary-fixed": "#dbeafe",
                        "tertiary-container": "#fef3c7",
                        "on-tertiary-fixed-variant": "#9a3412",
                        "inverse-surface": "#1e293b",
                        "on-tertiary-container": "#92400e",
                        "on-primary-fixed": "#1e3a8a",
                        "on-surface-variant": "#64748b",
                        "surface-container-highest": "#ffffff",
                        "secondary-container": "#f1f5f9",
                        "on-surface": "#0f172a",
                        "on-secondary-fixed": "#0f172a"
                    },
                    "borderRadius": {
                        "DEFAULT": "0.125rem",
                        "lg": "0.25rem",
                        "xl": "0.5rem",
                        "full": "0.75rem"
                    },
                    "fontFamily": {
                        "headline": ["Inter"],
                        "body": ["Inter"],
                        "label": ["Inter"]
                    }
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

<body class="bg-background text-on-background antialiased">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <aside
            class="w-64 flex-shrink-0 border-r border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 h-screen sticky top-0 flex flex-col overflow-y-auto">
        <div class="p-6 flex-1">
            <div class="flex items-center gap-3 mb-8">
                <div class="bg-primary rounded-lg p-2 text-white">
                    <span class="material-symbols-outlined">directions_car</span>
                </div>
                <div>
                    <h1 class="text-lg font-bold leading-none"><?php echo h($shopName); ?></h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Repair Management</p>
                </div>
            </div>
            <nav class="space-y-1">
                <?php if (canAccessModule('dashboardadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors font-medium"
                    href="dashboardadmin.php?shop=<?php echo h($shopQuery); ?>">
                    <span class="material-symbols-outlined text-[22px]">dashboard</span>
                    Dashboard
                </a>
                <?php endif; ?>
                <?php if (canAccessModule('repairjobsadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors font-medium"
                    href="repairjobsadmin.php?shop=<?php echo h($shopQuery); ?>">
                    <span class="material-symbols-outlined text-[22px]">build</span>
                    Repair Jobs
                </a>
                <?php endif; ?>
                <?php if (canAccessModule('vehicleadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="vehicleadmin.php?shop=<?php echo h($shopQuery); ?>">
                    <span class="material-symbols-outlined text-[22px]">directions_car</span>
                    Vehicles
                </a>
                <?php endif; ?>
                <?php if (canAccessModule('appointmentadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="appointmentadmin.php?shop=<?php echo h($shopQuery); ?>">
                    <span class="material-symbols-outlined text-[22px]">event</span>
                    Appointments
                </a>
                <?php endif; ?>
                <?php if (canAccessModule('reportsadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="reportsadmin.php?shop=<?php echo h($shopQuery); ?>">
                    <span class="material-symbols-outlined text-[22px]">description</span>
                    Reports
                </a>
                <?php endif; ?>
                <?php if (canAccessModule('inventoryadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="inventoryadmin.php?shop=<?php echo h($shopQuery); ?>">
                    <span class="material-symbols-outlined text-[22px]">inventory_2</span>
                    Inventory
                </a>
                <?php endif; ?>
                <?php if (canAccessModule('customeradmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="customeradmin.php?shop=<?php echo h($shopQuery); ?>">
                    <span class="material-symbols-outlined text-[22px]">group</span>
                    Customers
                </a>
                <?php endif; ?>
                <?php if (canAccessModule('paymentsadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="paymentsadmin.php?shop=<?php echo h($shopQuery); ?>">
                    <span class="material-symbols-outlined text-[22px]">payments</span>
                    Payments
                </a>
                <?php endif; ?>
                <div class="pt-4 mt-4 border-t border-slate-100">
                    <?php if (canAccessModule('settingsadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                        href="settingsadmin.php?shop=<?php echo h($shopQuery); ?>">
                        <span class="material-symbols-outlined text-[22px]">settings</span>
                        Settings
                    </a>
                    <?php endif; ?>
                </div>
            </nav>
        </div>
        <div class="p-4 border-t border-slate-200 dark:border-slate-800">
            <div class="flex items-center gap-3">
                <div
                    class="size-10 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center overflow-hidden">
                    <span class="material-symbols-outlined">person</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold truncate"><?php echo h($shopName); ?></p>
                    <p class="text-xs text-slate-500 truncate">Admin Panel</p>
                </div>
                <form method="post" action="../logout/logout.php" class="inline">
                    <input type="hidden" name="action" value="confirm" />
                    <input type="hidden" name="shop" value="<?php echo h($loginSlug); ?>" />
                    <button type="submit" class="text-slate-400 hover:text-red-600 transition-colors" title="Logout">
                        <span class="material-symbols-outlined text-xl">logout</span>
                    </button>
                </form>
            </div>
        </div>
    </aside>
    <!-- Main Content Area -->
    <main class="flex-1 overflow-y-auto flex flex-col">
        <!-- TopNavBar -->
        <header
            class="sticky top-0 z-40 w-full border-b border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md flex items-center justify-between px-8 h-16">
        <div class="flex items-center gap-6">
            <h2 class="text-lg font-black text-slate-900 dark:white tracking-tight">System Logs</h2>
            <span class="hidden xl:inline-flex items-center px-2.5 py-1 rounded-full bg-blue-50 text-blue-700 text-[11px] font-bold uppercase tracking-wide"><?php echo h($loginSlug); ?></span>
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
                    <p class="text-xs font-bold text-slate-900 dark:text-slate-100">System Admin</p>
                    <p class="text-[10px] text-slate-500 uppercase font-semibold">Audit Logs</p>
                </div>
                <div
                    class="h-10 w-10 rounded-full border-2 border-primary/20 bg-slate-200 dark:bg-slate-700 flex items-center justify-center overflow-hidden">
                    <span class="material-symbols-outlined text-slate-600 dark:text-slate-400">person</span>
                </div>
            </div>
        </div>
    </header>
    <!-- Content Area -->
    <div class="flex-1 overflow-y-auto bg-surface p-8">
        <div class="max-w-[1400px] mx-auto space-y-8">
            <!-- Page Header -->
            <div class="flex items-end justify-between">
                <div>
                    <h2 class="text-[30px] font-black text-on-background tracking-tight">System Audit Logs</h2>
                    <p class="text-sm text-on-surface-variant font-medium mt-1">Real-time monitoring of all system activity and administrative changes.</p>
                </div>
                <div class="flex gap-3">
                    <a href="tenantslogs.php?shop=<?php echo h($shopQuery); ?>" class="px-4 py-2 bg-white border border-slate-200 text-slate-700 text-sm font-bold rounded-lg shadow-sm hover:bg-slate-50 transition-all flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">refresh</span>
                        Refresh
                    </a>
                </div>
            </div>
            <!-- Metric Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                    <div class="flex justify-between items-start">
                        <div class="p-2 bg-blue-50 text-blue-700 rounded-lg">
                            <span class="material-symbols-outlined" data-icon="analytics">analytics</span>
                        </div>
                        <span class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">Total</span>
                    </div>
                    <div class="mt-4">
                        <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Total Events</p>
                        <h3 class="text-2xl font-black text-slate-900 mt-1"><?php echo number_format($totalLogs); ?></h3>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                    <div class="flex justify-between items-start">
                        <div class="p-2 bg-slate-100 text-slate-700 rounded-lg">
                            <span class="material-symbols-outlined" data-icon="today">today</span>
                        </div>
                        <span class="text-[10px] font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full">Today</span>
                    </div>
                    <div class="mt-4">
                        <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Today's Events</p>
                        <h3 class="text-2xl font-black text-slate-900 mt-1"><?php echo number_format($todayLogs); ?></h3>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                    <div class="flex justify-between items-start">
                        <div class="p-2 bg-slate-100 text-slate-700 rounded-lg">
                            <span class="material-symbols-outlined" data-icon="schedule">schedule</span>
                        </div>
                        <span class="text-[10px] font-bold text-slate-400 px-2 py-0.5">Live</span>
                    </div>
                    <div class="mt-4">
                        <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Latest Log Entry</p>
                        <h3 class="text-sm font-semibold text-slate-900 mt-1"><?php echo count($systemLogs) > 0 ? date('M d, Y h:i A', strtotime($systemLogs[0]['created_at'])) : 'No logs'; ?></h3>
                    </div>
                </div>
            </div>
            <!-- Layout Grid: Main Table -->
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                        <h4 class="text-sm font-bold text-slate-900">Recent System Activity</h4>
                        <div class="flex gap-2">
                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">Live Stream
                                Active</span>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50/50">
                                    <th
                                        class="px-6 py-3 text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                                        Timestamp</th>
                                    <th
                                        class="px-6 py-3 text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                                        User</th>
                                    <th
                                        class="px-6 py-3 text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                                        Action</th>
                                    <th
                                        class="px-6 py-3 text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                                        Target Entity</th>
                                    <th
                                        class="px-6 py-3 text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                                        Status</th>
                                    <th
                                        class="px-6 py-3 text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                                        Description</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (count($systemLogs) === 0): ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-10 text-center text-sm text-slate-500">No system logs found for this tenant.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($systemLogs as $log): ?>
                                        <?php 
                                        $userDisplay = h($log['user_name'] ?? 'System');
                                        $userRole = h($log['user_role'] ?? 'Unknown');
                                        $ipAddress = h($log['ip_address'] ?? 'N/A');
                                        $userAgent = h($log['user_agent'] ?? 'N/A');
                                        ?>
                                        <tr class="hover:bg-slate-50 transition-colors group">
                                            <td class="px-6 py-4 text-xs font-medium text-slate-500 whitespace-nowrap" title="<?php echo h($log['created_at']); ?>">
                                                <?php echo h(date('Y-m-d H:i:s', strtotime($log['created_at']))); ?></td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-2">
                                                    <div class="w-6 h-6 bg-slate-200 rounded-full flex items-center justify-center text-[10px] font-bold" title="User ID: <?php echo (int)($log['user_id'] ?? 0); ?>">
                                                        <?php 
                                                        $initials = substr(strtoupper($userDisplay), 0, 2);
                                                        echo $initials;
                                                        ?>
                                                    </div>
                                                    <div class="flex flex-col min-w-0">
                                                        <span class="text-xs font-bold text-slate-900 truncate"><?php echo $userDisplay; ?></span>
                                                        <span class="text-[10px] text-slate-500 truncate"><?php echo $userRole; ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4"><span class="px-2 py-0.5 rounded text-[10px] font-bold <?php echo getActionBadgeClass($log['action']); ?>"><?php echo h(strtoupper($log['action'])); ?></span></td>
                                            <td class="px-6 py-4 text-xs font-semibold text-slate-700">
                                                <div class="flex flex-col">
                                                    <span><?php echo h($log['entity_type'] ?? 'System'); ?></span>
                                                    <?php if (!empty($log['entity_id'])): ?>
                                                        <span class="text-[10px] text-slate-500">ID: <?php echo (int)$log['entity_id']; ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="flex items-center gap-1.5 text-emerald-600 text-[10px] font-bold" title="IP: <?php echo $ipAddress; ?>">
                                                    <span class="material-symbols-outlined text-[14px]" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                                                    SUCCESS
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-xs text-slate-500 truncate max-w-xs" title="<?php echo h($log['details'] ?? 'No details'); ?>"><?php echo h($log['details'] ?? 'No details'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="px-6 py-4 bg-slate-50/50 border-t border-slate-100 flex items-center justify-between">
                        <span class="text-[10px] font-bold text-slate-500 uppercase">Showing <?php echo count($systemLogs); ?> of <?php echo number_format($totalLogs); ?> entries</span>
                        <div class="flex gap-2">
                            <?php if ($page > 1): ?>
                                <a href="tenantslogs.php?shop=<?php echo h($shopQuery); ?>&page=<?php echo $page - 1; ?>" class="w-8 h-8 flex items-center justify-center border border-slate-200 rounded text-slate-500 hover:bg-white transition-all"><span class="material-symbols-outlined text-sm">chevron_left</span></a>
                            <?php else: ?>
                                <button disabled class="w-8 h-8 flex items-center justify-center border border-slate-200 rounded text-slate-300 cursor-not-allowed"><span class="material-symbols-outlined text-sm">chevron_left</span></button>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= min(3, $totalPages); $i++): ?>
                                <?php if ($i === $page): ?>
                                    <button class="w-8 h-8 flex items-center justify-center border border-blue-700 bg-blue-700 rounded text-white text-[10px] font-bold transition-all"><?php echo $i; ?></button>
                                <?php else: ?>
                                    <a href="tenantslogs.php?shop=<?php echo h($shopQuery); ?>&page=<?php echo $i; ?>" class="w-8 h-8 flex items-center justify-center border border-slate-200 rounded text-slate-500 hover:bg-white transition-all text-[10px] font-bold"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($totalPages > 3): ?>
                                <span class="w-8 h-8 flex items-center justify-center text-slate-400 text-[10px]">...</span>
                            <?php endif; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="tenantslogs.php?shop=<?php echo h($shopQuery); ?>&page=<?php echo $page + 1; ?>" class="w-8 h-8 flex items-center justify-center border border-slate-200 rounded text-slate-500 hover:bg-white transition-all"><span class="material-symbols-outlined text-sm">chevron_right</span></a>
                            <?php else: ?>
                                <button disabled class="w-8 h-8 flex items-center justify-center border border-slate-200 rounded text-slate-300 cursor-not-allowed"><span class="material-symbols-outlined text-sm">chevron_right</span></button>
                            <?php endif; ?>
                        </div>
                    </div>
            </div>
        </div>
    </div>
    </div>
</body>

</html>