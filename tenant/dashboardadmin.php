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

// Try session slug first, then URL slug
$login_slug = '';
if (isset($_SESSION['login_slug']) && trim((string) $_SESSION['login_slug']) !== '') {
    $login_slug = trim((string) $_SESSION['login_slug']);
} elseif (isset($_GET['shop']) && trim((string) $_GET['shop']) !== '') {
    $login_slug = trim((string) $_GET['shop']);
    $_SESSION['login_slug'] = $login_slug; // restore into session
}

// If still no slug, force login
if ($login_slug === '') {
    session_unset();
    session_destroy();
    header('Location: tenantlogin.php');
    exit;
}

// Validate tenant + slug
$stmt = mysqli_prepare($conn, "SELECT * FROM owners WHERE tenantID = ? AND login_slug = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "is", $tenantID, $login_slug);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$owner = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$owner) {
    session_unset();
    session_destroy();
    header('Location: tenantlogin.php');
    exit;
}

// Re-store correct slug in session to keep it persistent
$_SESSION['login_slug'] = $login_slug;

$shopName = isset($owner['shopName']) && $owner['shopName'] !== '' ? $owner['shopName'] : 'AutoFix Pro';
$shopSlug = $login_slug;
$shopQuery = urlencode($shopSlug);

// Keep URL consistent
$currentScript = basename($_SERVER['PHP_SELF']);
if (!isset($_GET['shop']) || trim((string) $_GET['shop']) !== $shopSlug) {
    header('Location: ' . $currentScript . '?shop=' . $shopQuery);
    exit;
}

// Enforce access control for this module
enforceModuleAccess($tenantID, basename(__FILE__));

// Get accessible modules for navigation
$accessibleModules = getAccessibleModules($tenantID);
$isStaffUser = isset($_SESSION['userType']) && $_SESSION['userType'] === 'staff';

// Helper function to check if a module should be accessible
function canAccessModule($moduleFile, $accessibleModules) {
    return in_array($moduleFile, $accessibleModules);
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

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function format_money($amount): string
{
    return '₱' . number_format((float) $amount, 2);
}

function percent_change(float $current, float $previous): float
{
    if ($previous <= 0.0) {
        return $current > 0.0 ? 100.0 : 0.0;
    }

    return (($current - $previous) / $previous) * 100;
}

function time_ago(string $datetime): string
{
    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return 'Unknown time';
    }

    $diff = time() - $timestamp;
    if ($diff < 60) {
        return 'Just now';
    }
    if ($diff < 3600) {
        $minutes = (int) floor($diff / 60);
        return $minutes . ' minute' . ($minutes === 1 ? '' : 's') . ' ago';
    }
    if ($diff < 86400) {
        $hours = (int) floor($diff / 3600);
        return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago';
    }

    $days = (int) floor($diff / 86400);
    if ($days < 7) {
        return $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
    }

    return date('M d, Y', $timestamp);
}

function repair_progress_percent(string $status): int
{
    $map = [
        'Queued' => 10,
        'Diagnostics' => 25,
        'In Progress' => 60,
        'Waiting for Parts' => 50,
        'Quality Check' => 85,
        'Ready for Pickup' => 95,
        'Completed' => 100,
        'Cancelled' => 0,
    ];

    return $map[$status] ?? 15;
}

function repair_status_badge(string $status): string
{
    if ($status === 'In Progress' || $status === 'Waiting for Parts') {
        return 'bg-orange-100 dark:bg-orange-900/30 text-orange-600';
    }
    if ($status === 'Diagnostics' || $status === 'Quality Check') {
        return 'bg-blue-100 dark:bg-blue-900/30 text-blue-600';
    }
    if ($status === 'Ready for Pickup') {
        return 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600';
    }

    return 'bg-slate-100 dark:bg-slate-800 text-slate-600';
}

function activity_color_class(string $action): string
{
    if ($action === 'CREATE') {
        return 'bg-green-500 ring-green-100 dark:ring-green-900/20';
    }
    if ($action === 'UPDATE') {
        return 'bg-blue-500 ring-blue-100 dark:ring-blue-900/20';
    }
    if ($action === 'DELETE') {
        return 'bg-red-500 ring-red-100 dark:ring-red-900/20';
    }
    if ($action === 'LOGIN' || $action === 'LOGOUT') {
        return 'bg-indigo-500 ring-indigo-100 dark:ring-indigo-900/20';
    }

    return 'bg-slate-400 ring-slate-100 dark:ring-slate-800/20';
}

$monthlyRevenue = 0.0;
$previousMonthlyRevenue = 0.0;
$activeRepairJobs = 0;
$inProgressRepairJobs = 0;
$upcomingAppointments = 0;
$inventoryAlerts = 0;
$weekChart = [];
$recentActivities = [];
$activeRepairs = [];

$managerName = isset($owner['ownerName']) && trim((string) $owner['ownerName']) !== ''
    ? (string) $owner['ownerName']
    : $shopName;

$monthlyStmt = mysqli_prepare(
    $conn,
    "SELECT
        COALESCE(SUM(CASE
            WHEN paymentDate >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
             AND paymentDate < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)
            THEN amountPaid
            ELSE 0
        END), 0) AS current_month,
        COALESCE(SUM(CASE
            WHEN paymentDate >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)
             AND paymentDate < DATE_FORMAT(CURDATE(), '%Y-%m-01')
            THEN amountPaid
            ELSE 0
        END), 0) AS previous_month
     FROM payments
     WHERE tenantID = ? AND paymentStatus IN ('Paid', 'Partial')"
);
if ($monthlyStmt) {
    mysqli_stmt_bind_param($monthlyStmt, 'i', $tenantID);
    mysqli_stmt_execute($monthlyStmt);
    $monthlyResult = mysqli_stmt_get_result($monthlyStmt);
    if ($monthlyResult && $monthlyRow = mysqli_fetch_assoc($monthlyResult)) {
        $monthlyRevenue = (float) ($monthlyRow['current_month'] ?? 0);
        $previousMonthlyRevenue = (float) ($monthlyRow['previous_month'] ?? 0);
    }
    mysqli_stmt_close($monthlyStmt);
}

$revenueChange = percent_change($monthlyRevenue, $previousMonthlyRevenue);

$jobsStmt = mysqli_prepare(
    $conn,
    "SELECT
        COALESCE(SUM(CASE WHEN job_status NOT IN ('Completed', 'Cancelled') THEN 1 ELSE 0 END), 0) AS active_jobs,
        COALESCE(SUM(CASE WHEN job_status = 'In Progress' THEN 1 ELSE 0 END), 0) AS in_progress
     FROM repair_jobs
     WHERE tenantID = ?"
);
if ($jobsStmt) {
    mysqli_stmt_bind_param($jobsStmt, 'i', $tenantID);
    mysqli_stmt_execute($jobsStmt);
    $jobsResult = mysqli_stmt_get_result($jobsStmt);
    if ($jobsResult && $jobsRow = mysqli_fetch_assoc($jobsResult)) {
        $activeRepairJobs = (int) ($jobsRow['active_jobs'] ?? 0);
        $inProgressRepairJobs = (int) ($jobsRow['in_progress'] ?? 0);
    }
    mysqli_stmt_close($jobsStmt);
}

$appointmentsStmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total
     FROM appointments
     WHERE tenantID = ?
       AND appointment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 2 DAY)
       AND status NOT IN ('Cancelled', 'Completed')"
);
if ($appointmentsStmt) {
    mysqli_stmt_bind_param($appointmentsStmt, 'i', $tenantID);
    mysqli_stmt_execute($appointmentsStmt);
    $appointmentsResult = mysqli_stmt_get_result($appointmentsStmt);
    if ($appointmentsResult && $appointmentsRow = mysqli_fetch_assoc($appointmentsResult)) {
        $upcomingAppointments = (int) ($appointmentsRow['total'] ?? 0);
    }
    mysqli_stmt_close($appointmentsStmt);
}

$inventoryStmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total
     FROM inventory_items
     WHERE tenantID = ?
       AND status = 'Active'
       AND stock_quantity <= reorder_level"
);
if ($inventoryStmt) {
    mysqli_stmt_bind_param($inventoryStmt, 'i', $tenantID);
    mysqli_stmt_execute($inventoryStmt);
    $inventoryResult = mysqli_stmt_get_result($inventoryStmt);
    if ($inventoryResult && $inventoryRow = mysqli_fetch_assoc($inventoryResult)) {
        $inventoryAlerts = (int) ($inventoryRow['total'] ?? 0);
    }
    mysqli_stmt_close($inventoryStmt);
}

$weekSums = [];
$weekStmt = mysqli_prepare(
    $conn,
    "SELECT DATE(paymentDate) AS payment_day, COALESCE(SUM(amountPaid), 0) AS total
     FROM payments
     WHERE tenantID = ?
       AND paymentStatus IN ('Paid', 'Partial')
       AND DATE(paymentDate) BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()
     GROUP BY DATE(paymentDate)"
);
if ($weekStmt) {
    mysqli_stmt_bind_param($weekStmt, 'i', $tenantID);
    mysqli_stmt_execute($weekStmt);
    $weekResult = mysqli_stmt_get_result($weekStmt);
    while ($weekResult && $weekRow = mysqli_fetch_assoc($weekResult)) {
        $weekSums[(string) $weekRow['payment_day']] = (float) $weekRow['total'];
    }
    mysqli_stmt_close($weekStmt);
}

$weekTotal = 0.0;
for ($i = 6; $i >= 0; $i--) {
    $dayKey = date('Y-m-d', strtotime('-' . $i . ' day'));
    $dayAmount = $weekSums[$dayKey] ?? 0.0;
    $weekTotal += $dayAmount;
    $weekChart[] = [
        'label' => date('D', strtotime($dayKey)),
        'amount' => $dayAmount,
    ];
}

$weekMax = 1.0;
foreach ($weekChart as $chartDay) {
    if ($chartDay['amount'] > $weekMax) {
        $weekMax = (float) $chartDay['amount'];
    }
}

$activityStmt = mysqli_prepare(
    $conn,
    "SELECT action, entity_type, details, user_name, created_at
     FROM system_logs
     WHERE tenantID = ?
     ORDER BY created_at DESC
     LIMIT 6"
);
if ($activityStmt) {
    mysqli_stmt_bind_param($activityStmt, 'i', $tenantID);
    mysqli_stmt_execute($activityStmt);
    $activityResult = mysqli_stmt_get_result($activityStmt);
    while ($activityResult && $activityRow = mysqli_fetch_assoc($activityResult)) {
        $recentActivities[] = $activityRow;
    }
    mysqli_stmt_close($activityStmt);
}

$repairTableStmt = mysqli_prepare(
    $conn,
    "SELECT
        rj.repair_job_id,
        rj.job_order_no,
        rj.job_status,
        rj.assigned_technician,
        rj.updated_at,
        u.fullName AS customer_name,
        vi.year_model,
        vi.brand,
        vi.model
     FROM repair_jobs rj
     LEFT JOIN users u
       ON u.user_id = rj.user_id AND u.tenantID = rj.tenantID
     LEFT JOIN vehicleinformation vi
       ON vi.vehicle_id = rj.vehicle_id AND vi.tenantID = rj.tenantID
     WHERE rj.tenantID = ?
       AND rj.job_status NOT IN ('Completed', 'Cancelled')
     ORDER BY rj.updated_at DESC
     LIMIT 8"
);
if ($repairTableStmt) {
    mysqli_stmt_bind_param($repairTableStmt, 'i', $tenantID);
    mysqli_stmt_execute($repairTableStmt);
    $repairTableResult = mysqli_stmt_get_result($repairTableStmt);
    while ($repairTableResult && $repairRow = mysqli_fetch_assoc($repairTableResult)) {
        $activeRepairs[] = $repairRow;
    }
    mysqli_stmt_close($repairTableStmt);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="0" />
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap"
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
    </style>
</head>

<body class="bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display">
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
                        <h1 class="text-lg font-bold leading-none"><?php echo htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8'); ?></h1>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Repair Management</p>
                    </div>
                </div>
                <nav class="space-y-1">
                    <?php if (canAccessModule('dashboardadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-primary/10 text-primary font-medium"
                        href="dashboardadmin.php?shop=<?php echo $shopQuery; ?>">
                        <span class="material-symbols-outlined text-[22px]">dashboard</span>
                        Dashboard
                    </a>
                    <?php endif; ?>
                    
                    <?php if (canAccessModule('repairjobsadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors font-medium"
                        href="repairjobsadmin.php?shop=<?php echo $shopQuery; ?>">
                        <span class="material-symbols-outlined text-[22px]">build</span>
                        Repair Jobs
                    </a>
                    <?php endif; ?>
                    
                    <?php if (canAccessModule('vehicleadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                        href="vehicleadmin.php?shop=<?php echo $shopQuery; ?>">
                        <span class="material-symbols-outlined text-[22px]">directions_car</span>
                        Vehicles
                    </a>
                    <?php endif; ?>
                    
                    <?php if (canAccessModule('appointmentadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                        href="appointmentadmin.php?shop=<?php echo $shopQuery; ?>">
                        <span class="material-symbols-outlined text-[22px]">event</span>
                        Appointments
                    </a>
                    <?php endif; ?>
                    
                    <?php if (canAccessModule('reportsadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                        href="reportsadmin.php?shop=<?php echo $shopQuery; ?>">
                        <span class="material-symbols-outlined text-[22px]">description</span>
                        Reports
                    </a>
                    <?php endif; ?>
                    
                    <?php if (canAccessModule('inventoryadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                        href="inventoryadmin.php?shop=<?php echo $shopQuery; ?>">
                        <span class="material-symbols-outlined text-[22px]">inventory_2</span>
                        Inventory
                    </a>
                    <?php endif; ?>
                    
                    <?php if (canAccessModule('customeradmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                        href="customeradmin.php?shop=<?php echo $shopQuery; ?>">
                        <span class="material-symbols-outlined text-[22px]">group</span>
                        Customers
                    </a>
                    <?php endif; ?>
                    
                    <?php if (canAccessModule('paymentsadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                        href="paymentsadmin.php?shop=<?php echo $shopQuery; ?>">
                        <span class="material-symbols-outlined text-[22px]">payments</span>
                        Payments
                    </a>
                    <?php endif; ?>
                    
                    <div class="pt-4 mt-4 border-t border-slate-100">
                        <div class="relative group">
                            <button class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors w-full text-left settings-dropdown-btn" data-dropdown="settings">
                                <span class="material-symbols-outlined text-[22px]">settings</span>
                                <span>Settings</span>
                                <span class="material-symbols-outlined text-[16px] ml-auto">expand_more</span>
                            </button>
                            <div class="absolute left-0 top-full mt-1 w-full bg-white border border-slate-200 rounded-lg shadow-lg hidden z-50 settings-dropdown" data-dropdown="settings">
                                <?php if (canAccessModule('settingsadmin.php', $accessibleModules)): ?>
                                <a class="flex items-center gap-3 px-3 py-2.5 rounded-t-lg text-slate-600 hover:bg-blue-50 transition-colors text-sm"
                                    href="settingsadmin.php?shop=<?php echo $shopQuery; ?>">
                                    <span class="material-symbols-outlined text-[18px]">settings</span>
                                    Settings
                                </a>
                                <?php endif; ?>
                                <?php if (canAccessModule('accountbillingadmin.php', $accessibleModules)): ?>
                                <a class="flex items-center gap-3 px-3 py-2.5 rounded-b-lg text-slate-600 hover:bg-blue-50 transition-colors text-sm border-t border-slate-100"
                                    href="accountbillingadmin.php?shop=<?php echo $shopQuery; ?>">
                                    <span class="material-symbols-outlined text-[18px]">receipt_long</span>
                                    Account Billing
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </nav>
            </div>
            <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                <div class="flex items-center gap-3">
                    <div class="size-10 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center overflow-hidden">
                        <span class="material-symbols-outlined text-slate-500 dark:text-slate-400">person</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold truncate text-slate-900 dark:text-white"><?php echo h($loggedInUserName); ?></p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 truncate"><?php echo h($loggedInUserRole); ?></p>
                    </div>
                    <form id="logoutForm" method="post" action="../logout/logout.php" class="inline">
                        <input type="hidden" name="action" value="confirm" />
                        <input type="hidden" name="shop" value="<?php echo htmlspecialchars($shopSlug, ENT_QUOTES, 'UTF-8'); ?>" />
                        <button type="submit" class="text-slate-400 hover:text-error transition-colors" title="Logout">
                            <span class="material-symbols-outlined text-xl">logout</span>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto">
            <header
                class="sticky top-0 z-40 w-full border-b border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md flex items-center justify-between px-8 h-16">
                <h2 class="text-lg font-black text-slate-900 dark:text-white tracking-tight">Dashboard Overview</h2>
                <div class="flex items-center gap-4">
                    <button class="p-2 text-slate-500 hover:text-primary transition-all">
                        <span class="material-symbols-outlined">notifications</span>
                    </button>
                    <button class="p-2 text-slate-500 hover:text-primary transition-all">
                        <span class="material-symbols-outlined">help_outline</span>
                    </button>
                </div>
            </header>

            <div class="p-8">
                <!-- Stats Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-2 bg-primary/10 rounded-lg text-primary">
                                <span class="material-symbols-outlined">payments</span>
                            </div>
                            <span class="text-xs font-semibold px-2 py-1 rounded <?php echo $revenueChange >= 0 ? 'text-green-600 bg-green-100 dark:bg-green-900/30' : 'text-red-600 bg-red-100 dark:bg-red-900/30'; ?>">
                                <?php echo ($revenueChange >= 0 ? '+' : '') . number_format($revenueChange, 1); ?>%
                            </span>
                        </div>
                        <p class="text-sm text-slate-500 dark:text-slate-400 font-medium">Monthly Revenue</p>
                        <p class="text-2xl font-bold mt-1"><?php echo h(format_money($monthlyRevenue)); ?></p>
                    </div>
                    <div class="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-2 bg-orange-100 dark:bg-orange-900/20 rounded-lg text-orange-600">
                                <span class="material-symbols-outlined">car_repair</span>
                            </div>
                            <span class="text-xs font-semibold text-slate-500 bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded"><?php echo h((string) $inProgressRepairJobs); ?> In Progress</span>
                        </div>
                        <p class="text-sm text-slate-500 dark:text-slate-400 font-medium">Active Repair Jobs</p>
                        <p class="text-2xl font-bold mt-1"><?php echo h((string) $activeRepairJobs); ?></p>
                    </div>
                    <div class="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-2 bg-purple-100 dark:bg-purple-900/20 rounded-lg text-purple-600">
                                <span class="material-symbols-outlined">calendar_month</span>
                            </div>
                            <span class="text-xs font-semibold text-slate-500 bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded">Next 48h</span>
                        </div>
                        <p class="text-sm text-slate-500 dark:text-slate-400 font-medium">Upcoming Appts</p>
                        <p class="text-2xl font-bold mt-1"><?php echo h((string) $upcomingAppointments); ?></p>
                    </div>
                    <div class="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-2 bg-red-100 dark:bg-red-900/20 rounded-lg text-red-600">
                                <span class="material-symbols-outlined">warning</span>
                            </div>
                            <span class="text-xs font-semibold text-red-600 bg-red-100 dark:bg-red-900/30 px-2 py-1 rounded"><?php echo $inventoryAlerts > 0 ? 'Urgent' : 'Normal'; ?></span>
                        </div>
                        <p class="text-sm text-slate-500 dark:text-slate-400 font-medium">Inventory Alerts</p>
                        <p class="text-2xl font-bold mt-1 <?php echo $inventoryAlerts > 0 ? 'text-red-600' : ''; ?>"><?php echo h((string) $inventoryAlerts); ?> Low Stock</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800">
                        <div class="flex items-center justify-between mb-8">
                            <div>
                                <h3 class="text-lg font-bold">Weekly Performance</h3>
                                <p class="text-sm text-slate-500">Revenue tracking for the last 7 days (Total: <?php echo h(format_money($weekTotal)); ?>)</p>
                            </div>
                            <select class="bg-slate-100 dark:bg-slate-800 border-none rounded-lg text-xs font-semibold focus:ring-primary">
                                <option>This Week</option>
                                <option disabled>Last Week</option>
                            </select>
                        </div>
                        <div class="h-64 flex items-end gap-3 px-2">
                            <?php foreach ($weekChart as $chartDay): ?>
                                <?php
                                $heightPx = 24;
                                if ($weekMax > 0) {
                                    $heightPx = (int) max(24, round(($chartDay['amount'] / $weekMax) * 220));
                                }
                                $fillPercent = $weekMax > 0 ? (int) max(10, round(($chartDay['amount'] / $weekMax) * 100)) : 10;
                                ?>
                                <div class="flex-1 flex flex-col items-center gap-2 group" title="<?php echo h(format_money($chartDay['amount'])); ?>">
                                    <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-t-lg relative overflow-hidden" style="height: <?php echo $heightPx; ?>px;">
                                        <div class="absolute bottom-0 w-full bg-primary/40 group-hover:bg-primary/60 transition-all" style="height: <?php echo $fillPercent; ?>%;"></div>
                                    </div>
                                    <span class="text-xs font-medium text-slate-500"><?php echo h($chartDay['label']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 flex flex-col">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-bold">Recent Activity</h3>
                            <a href="reportsadmin.php?shop=<?php echo $shopQuery; ?>" class="text-xs text-primary font-semibold hover:underline">View All</a>
                        </div>
                        <div class="space-y-6 flex-1">
                            <?php if (!empty($recentActivities)): ?>
                                <?php foreach ($recentActivities as $activity): ?>
                                    <div class="flex gap-4">
                                        <div class="size-2 mt-2 rounded-full ring-4 shrink-0 <?php echo h(activity_color_class((string) ($activity['action'] ?? ''))); ?>"></div>
                                        <div>
                                            <p class="text-sm font-semibold"><?php echo h((string) ($activity['action'] ?? 'Activity')); ?><?php echo !empty($activity['entity_type']) ? ' - ' . h((string) $activity['entity_type']) : ''; ?></p>
                                            <p class="text-xs text-slate-500"><?php echo h((string) (($activity['details'] ?? '') !== '' ? $activity['details'] : (($activity['user_name'] ?? 'System') . ' performed an action'))); ?></p>
                                            <p class="text-[10px] text-slate-400 mt-1 uppercase font-bold"><?php echo h(time_ago((string) ($activity['created_at'] ?? ''))); ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-sm text-slate-500">No recent activity found for this tenant yet.</div>
                            <?php endif; ?>
                        </div>
                        <a href="reportsadmin.php?shop=<?php echo $shopQuery; ?>" class="w-full py-2.5 mt-6 border border-slate-200 dark:border-slate-800 rounded-lg text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors text-center block">
                            Open Reports
                        </a>
                    </div>
                </div>

                <div class="mt-8 grid grid-cols-1 gap-6">
                    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                            <h3 class="text-lg font-bold">Active Repair Status</h3>
                            <div class="flex gap-2">
                                <span class="size-3 rounded-full bg-blue-500"></span>
                                <span class="text-xs text-slate-500 font-medium">Diagnostic</span>
                                <span class="size-3 rounded-full bg-orange-500 ml-2"></span>
                                <span class="text-xs text-slate-500 font-medium">Repairing</span>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 text-xs font-bold uppercase tracking-wider">
                                    <tr>
                                        <th class="px-6 py-4">Customer</th>
                                        <th class="px-6 py-4">Vehicle</th>
                                        <th class="px-6 py-4">Technician</th>
                                        <th class="px-6 py-4">Status</th>
                                        <th class="px-6 py-4">Progress</th>
                                        <th class="px-6 py-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    <?php if (!empty($activeRepairs)): ?>
                                        <?php foreach ($activeRepairs as $repair): ?>
                                            <?php
                                            $progress = repair_progress_percent((string) ($repair['job_status'] ?? 'Queued'));
                                            $vehicleLabel = trim((string) ($repair['year_model'] ?? '') . ' ' . (string) ($repair['brand'] ?? '') . ' ' . (string) ($repair['model'] ?? ''));
                                            if ($vehicleLabel === '') {
                                                $vehicleLabel = 'Vehicle not set';
                                            }
                                            ?>
                                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition-colors">
                                                <td class="px-6 py-4 font-semibold text-sm"><?php echo h((string) ($repair['customer_name'] ?? 'Unknown Customer')); ?></td>
                                                <td class="px-6 py-4 text-sm"><?php echo h($vehicleLabel); ?></td>
                                                <td class="px-6 py-4 text-sm"><?php echo h((string) (($repair['assigned_technician'] ?? '') !== '' ? $repair['assigned_technician'] : 'Unassigned')); ?></td>
                                                <td class="px-6 py-4">
                                                    <span class="px-2 py-1 rounded text-[10px] font-bold <?php echo h(repair_status_badge((string) ($repair['job_status'] ?? 'Queued'))); ?>"><?php echo h(strtoupper((string) ($repair['job_status'] ?? 'Queued'))); ?></span>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="w-32 h-1.5 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                                                        <div class="bg-orange-500 h-full" style="width: <?php echo $progress; ?>%;"></div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <a href="repairjobsadmin.php?shop=<?php echo $shopQuery; ?>" class="text-slate-400 hover:text-primary transition-colors" title="Open repair jobs">
                                                        <span class="material-symbols-outlined">open_in_new</span>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="px-6 py-8 text-center text-sm text-slate-500">No active repair jobs yet.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <?php echo getBackButtonDetectionScript(); ?>
    <script>
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