<?php
// superadd.php
session_start();
require_once __DIR__ . "/../db.php";

// Redirect if not logged in
if (!isset($_SESSION['superadmin_id'])) {
    header("Location: superaddlogin.php");
    exit();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

// Helper functions
function getPercentChange($current, $previous)
{
    if ($previous == 0)
        return $current > 0 ? 100 : 0;
    return (($current - $previous) / $previous) * 100;
}

// Get superadmin info
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

// ===== METRICS FOR KPI CARDS =====

// Total Tenants - Current Month
$currentMonthStart = date('Y-m-01');
$totalTenantsCurrentResult = $conn->query("SELECT COUNT(*) as total FROM owners WHERE DATE(created_at) >= '$currentMonthStart'");
$totalTenantsCurrentMonth = $totalTenantsCurrentResult ? $totalTenantsCurrentResult->fetch_assoc()['total'] : 0;

// Total Tenants - Previous Month
$lastMonthStart = date('Y-m-01', strtotime('-1 month'));
$lastMonthEnd = date('Y-m-t', strtotime('-1 month'));
$totalTenantsPrevResult = $conn->query("SELECT COUNT(*) as total FROM owners WHERE DATE(created_at) >= '$lastMonthStart' AND DATE(created_at) <= '$lastMonthEnd'");
$totalTenantsPrevMonth = $totalTenantsPrevResult ? $totalTenantsPrevResult->fetch_assoc()['total'] : 0;

// Total Tenants All Time
$totalTenantsResult = $conn->query("SELECT COUNT(*) as total FROM owners");
$totalTenants = $totalTenantsResult ? $totalTenantsResult->fetch_assoc()['total'] : 0;

$tenantChangePercent = getPercentChange($totalTenantsCurrentMonth, $totalTenantsPrevMonth);
$tenantChangeTrend = $tenantChangePercent >= 0 ? "+" : "";

// Active Shops (status = 'active')
$activeShopsResult = $conn->query("SELECT COUNT(*) as total FROM owners WHERE LOWER(status) = 'active'");
$activeShops = $activeShopsResult ? $activeShopsResult->fetch_assoc()['total'] : 0;

// Active Shops Last Month
$lastMonthActiveResult = $conn->query("SELECT COUNT(*) as total FROM owners WHERE LOWER(status) = 'active' AND DATE(created_at) <= '$lastMonthEnd'");
$lastMonthActive = $lastMonthActiveResult ? $lastMonthActiveResult->fetch_assoc()['total'] : 0;

$activeChangePercent = getPercentChange($activeShops, max($lastMonthActive, 1));
$activeChangeTrend = $activeChangePercent >= 0 ? "+" : "";

// Pending Approvals (status = 'trial' or 'pending')
$pendingResult = $conn->query("SELECT COUNT(*) as total FROM owners WHERE LOWER(status) IN ('trial', 'pending')");
$pendingApprovals = $pendingResult ? $pendingResult->fetch_assoc()['total'] : 0;

// Pending Last Month
$lastMonthPendingResult = $conn->query("SELECT COUNT(*) as total FROM owners WHERE LOWER(status) IN ('trial', 'pending') AND DATE(created_at) <= '$lastMonthEnd'");
$lastMonthPending = $lastMonthPendingResult ? $lastMonthPendingResult->fetch_assoc()['total'] : 0;

$pendingChangePercent = getPercentChange($pendingApprovals, max($lastMonthPending, 1));
$pendingChangeTrend = $pendingApprovals < $lastMonthPending ? "-" : "+";

// ===== CHART DATA: TENANT GROWTH (Last 12 Months) =====
$growthLabels = [];
$growthData = [];
$currentMonthIndex = null;
$currentMonthYearString = date('M Y');

for ($i = 11; $i >= 0; $i--) {
    $monthStart = date('Y-m-01', strtotime("-$i months"));
    $monthEnd = date('Y-m-t', strtotime("-$i months"));
    $monthLabel = date('M Y', strtotime("-$i months"));

    // Track current month
    if ($monthLabel === $currentMonthYearString) {
        $currentMonthIndex = 11 - $i; // Index in the array (0-based)
    }

    $monthCountResult = $conn->query("SELECT COUNT(*) as total FROM owners WHERE DATE(created_at) >= '$monthStart' AND DATE(created_at) <= '$monthEnd'");
    $monthCount = $monthCountResult ? $monthCountResult->fetch_assoc()['total'] : 0;

    $growthLabels[] = $monthLabel;
    $growthData[] = $monthCount;
}

// ===== GEOGRAPHIC DISTRIBUTION (Philippine Regions) =====
// Extract regions from Philippine addresses based on shopAddress
$geoQuery = "SELECT 
    CASE 
        WHEN shopAddress LIKE '%Metro Manila%' OR shopAddress LIKE '%Manila%' OR shopAddress LIKE '%Makati%' OR shopAddress LIKE '%Pasig%' OR shopAddress LIKE '%Taguig%' OR shopAddress LIKE '%Quezon%' OR shopAddress LIKE '%Caloocan%' THEN 'Metro Manila'
        WHEN shopAddress LIKE '%Cebu%' THEN 'Cebu'
        WHEN shopAddress LIKE '%Davao%' THEN 'Davao'
        WHEN shopAddress LIKE '%Bulacan%' OR shopAddress LIKE '%Malolos%' OR shopAddress LIKE '%Meycauayan%' OR shopAddress LIKE '%Marilao%' THEN 'Bulacan'
        WHEN shopAddress LIKE '%Laguna%' THEN 'Laguna'
        WHEN shopAddress LIKE '%Cavite%' THEN 'Cavite'
        WHEN shopAddress LIKE '%Batangas%' THEN 'Batangas'
        WHEN shopAddress LIKE '%Rizal%' THEN 'Rizal'
        WHEN shopAddress LIKE '%Pampanga%' THEN 'Pampanga'
        WHEN shopAddress LIKE '%Iloilo%' THEN 'Iloilo'
        WHEN shopAddress LIKE '%Cagayan%' THEN 'Cagayan de Oro'
        WHEN shopAddress LIKE '%Mindanao%' THEN 'Mindanao'
        WHEN shopAddress LIKE '%Visayas%' THEN 'Visayas'
        WHEN shopAddress LIKE '%Luzon%' THEN 'Luzon'
        ELSE 'Other Regions'
    END as region, COUNT(*) as shop_count 
FROM owners 
WHERE shopAddress IS NOT NULL AND shopAddress != ''
GROUP BY region 
ORDER BY shop_count DESC LIMIT 10";
$geoResult = $conn->query($geoQuery);
$geoData = [];
if ($geoResult) {
    while ($row = $geoResult->fetch_assoc()) {
        $geoData[] = $row;
    }
}

// ===== SUBSCRIPTION BREAKDOWN =====
// Count by subscription_plan
$subQuery = "SELECT subscription_plan, COUNT(*) as count, SUM(plan_price) as revenue FROM owners WHERE plan_price > 0 GROUP BY subscription_plan";
$subResult = $conn->query($subQuery);
$subBreakdown = [];
$totalActivePlanRevenue = 0;

if ($subResult) {
    while ($row = $subResult->fetch_assoc()) {
        $subBreakdown[] = $row;
        $totalActivePlanRevenue += $row['revenue'];
    }
}

// Calculate percentages
$totalSubCount = 0;
foreach ($subBreakdown as $sub) {
    $totalSubCount += $sub['count'];
}

// ===== RECENT ACTIVITY (System Logs) =====
$activityQuery = "SELECT user_name, action, entity_type, created_at FROM system_logs ORDER BY created_at DESC LIMIT 10";
$activityResult = $conn->query($activityQuery);
$recentActivity = [];

if ($activityResult) {
    while ($row = $activityResult->fetch_assoc()) {
        $recentActivity[] = $row;
    }
}

// Fallback to recent owners if no system logs
if (empty($recentActivity)) {
    $fallbackQuery = "SELECT ownerName, 'registered' as action, created_at FROM owners ORDER BY created_at DESC LIMIT 10";
    $fallbackResult = $conn->query($fallbackQuery);
    if ($fallbackResult) {
        while ($row = $fallbackResult->fetch_assoc()) {
            $recentActivity[] = [
                'user_name' => $row['ownerName'],
                'action' => 'registered',
                'entity_type' => 'shop',
                'created_at' => $row['created_at']
            ];
        }
    }
}

// ===== ACTIVE AND INACTIVE TENANTS LIST =====
$activeTenantsList = [];
$inactiveTenantsList = [];

// Get active tenants (status = 'active', limit 20)
$activeTenantsQuery = "SELECT tenantID, ownerName, shopName, email, status, created_at FROM owners WHERE LOWER(status) = 'active' ORDER BY created_at DESC LIMIT 20";
$activeTenantsResult = $conn->query($activeTenantsQuery);
if ($activeTenantsResult) {
    while ($row = $activeTenantsResult->fetch_assoc()) {
        $activeTenantsList[] = $row;
    }
}

// Get inactive tenants (status != 'active', limit 20)
$inactiveTenantsQuery = "SELECT tenantID, ownerName, shopName, email, status, created_at FROM owners WHERE LOWER(status) != 'active' ORDER BY created_at DESC LIMIT 20";
$inactiveTenantsResult = $conn->query($inactiveTenantsQuery);
if ($inactiveTenantsResult) {
    while ($row = $inactiveTenantsResult->fetch_assoc()) {
        $inactiveTenantsList[] = $row;
    }
}

// Helper function to format time ago
function timeAgo($datetime)
{
    $timestamp = strtotime($datetime);
    $difference = time() - $timestamp;

    if ($difference < 60)
        return "just now";
    if ($difference < 3600)
        return round($difference / 60) . " minutes ago";
    if ($difference < 86400)
        return round($difference / 3600) . " hours ago";
    if ($difference < 604800)
        return round($difference / 86400) . " days ago";
    return date('M d, Y', $timestamp);
}

// Helper function to get action icon color
function getActionColor($action)
{
    switch (strtolower($action)) {
        case 'registered':
        case 'created':
            return 'green';
        case 'updated':
        case 'modified':
            return 'amber';
        case 'deleted':
            return 'red';
        case 'approved':
            return 'green';
        default:
            return 'slate';
    }
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

<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Dashboard | RepidRepair</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.min.js"></script>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap"
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
                        "primary": "#b91c1c",
                        "background-light": "#ffffff",
                        "background-dark": "#101622",
                    },
                    fontFamily: {
                        "display": ["Inter"]
                    },
                    borderRadius: { "DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px" },
                },
            },
        }
    </script>
</head>

<body class="bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-slate-100">
    <div class="flex h-screen overflow-hidden">
        <!-- Side Navigation -->
        <aside
            class="w-72 flex flex-col bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 shrink-0">
            <!-- Brand Header -->
            <div class="p-6 flex items-center gap-3">
                <div class="size-12 rounded-lg bg-white p-1 shadow-md dark:bg-slate-900">
                    <img src="../pictures/RRlogo3.png" alt="Rapid Repair logo" class="h-full w-full object-contain drop-shadow-sm">
                </div>
                <h2 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white leading-none">
                    RapidRepair <span class="text-primary">SuperAdmin</span>
                </h2>
            </div>
            <!-- Navigation Links -->
            <nav class="flex-1 px-4 space-y-1 mt-4">
                <a class="flex items-center gap-3 px-3 py-2.5 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 font-bold border-r-4 border-red-700 dark:border-red-500 rounded-lg active:scale-95"
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
                <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors rounded-lg active:scale-95"
                    href="supersettings.php">
                    <span class="material-symbols-outlined" data-icon="settings">settings</span>
                    <span class="text-sm">Settings</span>
                </a>
            </nav>
            <div class="p-4 border-t border-slate-100 dark:border-slate-800 space-y-2">
                <!-- Profile -->
                <div
                    class="flex items-center gap-3 p-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                    <div
                        class="w-10 h-10 rounded-full bg-primary/10 text-primary flex items-center justify-center font-semibold text-sm">
                        <?php echo htmlspecialchars(initials($superadminName)); ?>
                    </div>
                    <div class="flex flex-col min-w-0">
                        <h3 class="text-sm font-semibold truncate text-slate-900 dark:text-white">
                            <?php echo htmlspecialchars($superadminName); ?>
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 truncate">Superadmin</p>
                    </div>
                </div>

                <!-- Logout -->
                <button id="logoutBtn" type="button"
                    class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-900/10 transition-colors cursor-pointer text-left">
                    <span class="material-symbols-outlined">logout</span>
                    <p class="text-sm font-medium">Logout</p>
                </button>
                
                <!-- Logout Form (Hidden) -->
                <form id="logoutForm" method="POST" action="../logout/logout.php" class="hidden">
                    <input type="hidden" name="action" value="confirm">
                    <input type="hidden" name="redirect" value="superaddlogin.php">
                </form>
            </div>
        </aside>
        <!-- Main Content Area -->
        <main class="flex-1 flex flex-col overflow-y-auto">
            <!-- Header -->
            <header
                class="sticky top-0 z-10 flex h-16 items-center justify-between border-b border-slate-200 bg-white/80 dark:bg-slate-900/80 px-8 backdrop-blur-md dark:border-slate-800">
                <div class="flex items-center gap-4 text-slate-900 dark:text-white">
                    <span class="material-symbols-outlined text-primary">auto_awesome</span>
                    <h2 class="text-lg font-bold tracking-tight">Superadmin Dashboard</h2>
                </div>
                <div class="flex items-center gap-4">
                    <div class="relative w-64">
                        <span
                            class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xl">search</span>
                        <input
                            class="w-full rounded-lg border-slate-200 bg-slate-50 py-1.5 pl-10 text-sm focus:border-primary focus:ring-1 focus:ring-primary dark:border-slate-800 dark:bg-slate-800 dark:text-slate-200"
                            placeholder="Search insights..." type="text" />
                    </div>
                    <button
                        class="relative rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800">
                        <span class="material-symbols-outlined">notifications</span>
                        <span
                            class="absolute right-2 top-2 h-2 w-2 rounded-full bg-red-500 ring-2 ring-white dark:ring-slate-900"></span>
                    </button>
                    <button
                        class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800">
                        <span class="material-symbols-outlined">chat_bubble</span>
                    </button>
                </div>
            </header>
            <div class="p-8 space-y-8">
                <!-- Metrics Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Total Tenants</p>
                            <span
                                class="material-symbols-outlined text-primary bg-primary/10 rounded-lg p-2">storefront</span>
                        </div>
                        <div class="mt-4">
                            <h3 class="text-3xl font-bold text-slate-900 dark:text-white">
                                <?php echo number_format($totalTenants); ?></h3>
                            <p
                                class="mt-1 text-sm <?php echo $tenantChangePercent >= 0 ? 'text-green-600' : 'text-red-600'; ?> font-medium flex items-center gap-1">
                                <span
                                    class="material-symbols-outlined text-sm"><?php echo $tenantChangePercent >= 0 ? 'trending_up' : 'trending_down'; ?></span>
                                <?php echo $tenantChangeTrend . number_format(abs($tenantChangePercent), 1); ?>% vs last
                                month
                            </p>
                        </div>
                    </div>
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Active Shops</p>
                            <span
                                class="material-symbols-outlined text-primary bg-primary/10 rounded-lg p-2">check_circle</span>
                        </div>
                        <div class="mt-4">
                            <h3 class="text-3xl font-bold text-slate-900 dark:text-white">
                                <?php echo number_format($activeShops); ?></h3>
                            <p
                                class="mt-1 text-sm <?php echo $activeChangePercent >= 0 ? 'text-green-600' : 'text-red-600'; ?> font-medium flex items-center gap-1">
                                <span
                                    class="material-symbols-outlined text-sm"><?php echo $activeChangePercent >= 0 ? 'trending_up' : 'trending_down'; ?></span>
                                <?php echo $activeChangeTrend . number_format(abs($activeChangePercent), 1); ?>% vs last
                                month
                            </p>
                        </div>
                    </div>
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Pending Approvals</p>
                            <span
                                class="material-symbols-outlined text-red-600 bg-red-100 rounded-lg p-2">pending_actions</span>
                        </div>
                        <div class="mt-4">
                            <h3 class="text-3xl font-bold text-slate-900 dark:text-white">
                                <?php echo number_format($pendingApprovals); ?></h3>
                            <p
                                class="mt-1 text-sm <?php echo $pendingChangePercent < 0 ? 'text-green-600' : 'text-red-600'; ?> font-medium flex items-center gap-1">
                                <span
                                    class="material-symbols-outlined text-sm"><?php echo $pendingChangePercent < 0 ? 'trending_down' : 'trending_up'; ?></span>
                                <?php echo $pendingChangeTrend . number_format(abs($pendingChangePercent), 1); ?>%
                                change
                            </p>
                        </div>
                    </div>
                </div>
                <!-- Main Analytics Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Tenant Growth Chart -->
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h4 class="text-base font-bold text-slate-900 dark:text-white">Tenant Growth Trend</h4>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Monthly shop registrations</p>
                            </div>
                            <div class="flex gap-2">
                                <span
                                    class="inline-flex items-center rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/20">+<?php echo $totalTenantsCurrentMonth; ?>
                                    new shops</span>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm growth-table" data-rows-per-page="5">
                                <thead>
                                    <tr class="border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50">
                                        <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Month</th>
                                        <th class="px-4 py-3 text-right font-semibold text-slate-600 dark:text-slate-300">Registrations</th>
                                        <th class="px-4 py-3 text-right font-semibold text-slate-600 dark:text-slate-300">Growth</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $maxValue = max($growthData);
                                    for ($i = 0; $i < count($growthLabels); $i++):
                                        $month = $growthLabels[$i];
                                        $value = $growthData[$i];
                                        $percentage = $maxValue > 0 ? ($value / $maxValue) * 100 : 0;
                                        $isCurrentMonth = ($i === $currentMonthIndex);
                                    ?>
                                        <tr class="growth-row border-b border-slate-100 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors <?php echo $isCurrentMonth ? 'bg-blue-50 dark:bg-blue-900/20' : ''; ?>">
                                            <td class="px-4 py-3 text-slate-900 dark:text-slate-100 font-medium flex items-center gap-2">
                                                <?php echo htmlspecialchars($month); ?>
                                                <?php if ($isCurrentMonth): ?>
                                                    <span class="inline-flex items-center rounded-md bg-blue-100 dark:bg-blue-900 px-2 py-0.5 text-xs font-medium text-blue-700 dark:text-blue-300">Current</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3 text-right text-slate-900 dark:text-slate-100 font-semibold"><?php echo number_format($value); ?></td>
                                            <td class="px-4 py-3 text-right">
                                                <div class="flex items-center justify-end gap-2">
                                                    <div class="w-16 h-6 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                                                        <div class="h-full bg-primary" style="width: <?php echo $percentage; ?>%;"></div>
                                                    </div>
                                                    <span class="text-xs text-slate-500 dark:text-slate-400 w-8 text-right"><?php echo round($percentage); ?>%</span>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                        </div>
                        <!-- Pagination -->
                        <div class="flex items-center justify-between mt-4 pt-4 border-t border-slate-100 dark:border-slate-700">
                            <span class="text-xs text-slate-500 dark:text-slate-400">
                                Showing <span class="growth-page-info">1-5</span> of <?php echo count($growthLabels); ?> months
                            </span>
                            <div class="flex gap-2">
                                <button class="growth-prev-btn px-3 py-1.5 rounded-lg text-sm font-medium text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                    ← Previous
                                </button>
                                <button class="growth-next-btn px-3 py-1.5 rounded-lg text-sm font-medium text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                    Next →
                                </button>
                            </div>
                        </div>
                    </div>
                    <!-- Geographic Distribution -->
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h4 class="text-base font-bold text-slate-900 dark:text-white">Geographic Distribution
                                </h4>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Shop distribution across Bulacan cities</p>
                            </div>
                            <button class="text-xs text-primary font-medium hover:underline">View Map</button>
                        </div>
                        <div class="space-y-4">
                            <?php
                            $maxShops = !empty($geoData) ? $geoData[0]['shop_count'] : 1;
                            foreach ($geoData as $idx => $region):
                                $percentage = ($region['shop_count'] / $maxShops) * 100;
                                $displayName = htmlspecialchars($region['region']);
                                ?>
                                <div>
                                    <div class="flex justify-between text-sm mb-1.5">
                                        <span class="text-slate-600 dark:text-slate-300"><?php echo $displayName; ?></span>
                                        <span class="font-semibold"><?php echo number_format($region['shop_count']); ?>
                                            Shops</span>
                                    </div>
                                    <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-2">
                                        <div class="bg-primary h-2 rounded-full" style="width: <?php echo $percentage; ?>%">
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($geoData)): ?>
                                <p class="text-sm text-slate-400">No Bulacan shop data available</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Subscription Breakdown -->
                    <div
                        class="lg:col-span-2 rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h4 class="text-base font-bold text-slate-900 dark:text-white">Service & Tier Breakdown</h4>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Revenue distribution by membership tier</p>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50">
                                        <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Plan</th>
                                        <th class="px-4 py-3 text-right font-semibold text-slate-600 dark:text-slate-300">Count</th>
                                        <th class="px-4 py-3 text-right font-semibold text-slate-600 dark:text-slate-300">Distribution</th>
                                        <th class="px-4 py-3 text-right font-semibold text-slate-600 dark:text-slate-300">Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    if (!empty($subBreakdown)) {
                                        foreach ($subBreakdown as $idx => $sub):
                                            $percentage = $totalSubCount > 0 ? ($sub['count'] / $totalSubCount) * 100 : 0;
                                            $revenue = $sub['revenue'] ?: 0;
                                        ?>
                                            <tr class="border-b border-slate-100 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                                                <td class="px-4 py-3 text-slate-900 dark:text-slate-100 font-medium">
                                                    <?php echo htmlspecialchars(ucfirst($sub['subscription_plan']) ?: 'Standard Plan'); ?>
                                                </td>
                                                <td class="px-4 py-3 text-right text-slate-900 dark:text-slate-100 font-semibold">
                                                    <?php echo number_format($sub['count']); ?>
                                                </td>
                                                <td class="px-4 py-3 text-right">
                                                    <div class="flex items-center justify-end gap-2">
                                                        <div class="w-16 h-6 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                                                            <div class="h-full bg-primary" style="width: <?php echo $percentage; ?>%;"></div>
                                                        </div>
                                                        <span class="text-xs text-slate-500 dark:text-slate-400 w-8 text-right"><?php echo round($percentage); ?>%</span>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 text-right font-semibold text-primary">
                                                    ₱<?php echo number_format($revenue, 0); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach;
                                    } else { ?>
                                        <tr>
                                            <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-400">No subscription data available</td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                                <tfoot>
                                    <tr class="border-t-2 border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 font-bold">
                                        <td class="px-4 py-3 text-slate-900 dark:text-slate-100">Total</td>
                                        <td class="px-4 py-3 text-right text-slate-900 dark:text-slate-100">
                                            <?php echo number_format($totalSubCount); ?>
                                        </td>
                                        <td class="px-4 py-3 text-right text-slate-900 dark:text-slate-100">100%</td>
                                        <td class="px-4 py-3 text-right text-primary">
                                            ₱<?php echo number_format($totalActivePlanRevenue, 0); ?>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    <!-- Recent Activity Feed -->
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-base font-bold text-slate-900 dark:text-white">Recent Activity</h4>
                            <span class="material-symbols-outlined text-slate-400">bolt</span>
                        </div>
                        <div class="overflow-y-auto pr-1" style="max-height: 420px; scrollbar-width: thin;">
                            <div class="flex flex-col gap-3">
                                <?php foreach ($recentActivity as $activity):
                                    $color = getActionColor($activity['action']);
                                    $colorMap = [
                                        'green' => 'border-l-4 border-l-red-700 bg-red-50/50 dark:bg-red-900/10',
                                        'amber' => 'border-l-4 border-l-red-600 bg-red-50/40 dark:bg-red-900/10',
                                        'red' => 'border-l-4 border-l-red-500 bg-red-50/50 dark:bg-red-900/10',
                                        'slate' => 'border-l-4 border-l-slate-900 bg-slate-50/50 dark:bg-slate-800/30'
                                    ];
                                    $colorClass = $colorMap[$color] ?? $colorMap['slate'];
                                    ?>
                                    <div class="w-full min-h-[72px] p-4 rounded-lg border border-slate-200 dark:border-slate-700 <?php echo $colorClass; ?>">
                                        <div class="flex items-start justify-between mb-2">
                                            <div class="flex-1">
                                                <p class="text-sm font-medium text-slate-900 dark:text-white truncate">
                                                    <?php echo htmlspecialchars(substr($activity['user_name'], 0, 20)); ?>
                                                </p>
                                            </div>
                                            <span class="text-xs px-2 py-1 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 ml-1 flex-shrink-0">
                                                <?php echo htmlspecialchars(ucfirst($activity['entity_type'] ?? 'system')); ?>
                                            </span>
                                        </div>
                                        <p class="text-xs text-slate-600 dark:text-slate-400 mb-3 capitalize">
                                            <?php echo htmlspecialchars($activity['action']); ?>
                                        </p>
                                        <p class="text-xs text-slate-500 dark:text-slate-500">
                                            <?php echo timeAgo($activity['created_at']); ?>
                                        </p>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (empty($recentActivity)): ?>
                                    <p class="text-sm text-slate-400">No recent activity</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Active and Inactive Tenants Lists -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Active Tenants -->
                    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h4 class="text-base font-bold text-slate-900 dark:text-white">Active Tenants</h4>
                                <p class="text-xs text-slate-500 dark:text-slate-400 active-count"><?php echo count($activeTenantsList); ?> active shops</p>
                            </div>
                            <span class="material-symbols-outlined text-green-600 bg-green-100/50 rounded-lg p-2">verified</span>
                        </div>
                        <div class="mb-4">
                            <input 
                                type="text" 
                                id="activeTenantsSearch"
                                class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2 px-3 text-sm focus:border-primary focus:ring-1 focus:ring-primary dark:border-slate-800 dark:bg-slate-800 dark:text-slate-200"
                                placeholder="Search by name or email..."
                            />
                        </div>
                        <div class="space-y-3 max-h-96 overflow-y-auto active-tenants-list">
                            <?php if (!empty($activeTenantsList)): ?>
                                <?php foreach ($activeTenantsList as $tenant): ?>
                                    <div class="tenant-item" data-name="<?php echo htmlspecialchars($tenant['ownerName']); ?>" data-email="<?php echo htmlspecialchars($tenant['email']); ?>" data-shop="<?php echo htmlspecialchars($tenant['shopName']); ?>" data-initials="<?php echo htmlspecialchars(initials($tenant['ownerName'])); ?>">
                                        <div class="flex items-start gap-4 p-3 border border-slate-100 dark:border-slate-700 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                            <div class="w-10 h-10 rounded-full bg-green-100 text-green-700 flex items-center justify-center font-semibold text-sm flex-shrink-0">
                                                <?php echo htmlspecialchars(initials($tenant['ownerName'])); ?>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-slate-900 dark:text-white truncate">
                                                    <?php echo htmlspecialchars($tenant['ownerName']); ?>
                                                </p>
                                                <p class="text-xs text-slate-500 dark:text-slate-400 truncate">
                                                    <?php echo htmlspecialchars($tenant['shopName']); ?>
                                                </p>
                                                <p class="text-xs text-slate-400 dark:text-slate-500 truncate">
                                                    <?php echo htmlspecialchars($tenant['email']); ?>
                                                </p>
                                                <p class="text-xs text-green-600 dark:text-green-400 mt-1 font-medium">
                                                    Joined <?php echo date('M d, Y', strtotime($tenant['created_at'])); ?>
                                                </p>
                                            </div>
                                            <div class="flex-shrink-0">
                                                <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                                    Active
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-sm text-slate-400 text-center py-6">No active tenants</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Inactive Tenants -->
                    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h4 class="text-base font-bold text-slate-900 dark:text-white">Inactive Tenants</h4>
                                <p class="text-xs text-slate-500 dark:text-slate-400 inactive-count"><?php echo count($inactiveTenantsList); ?> inactive shops</p>
                            </div>
                            <span class="material-symbols-outlined text-amber-600 bg-amber-100/50 rounded-lg p-2">pause_circle</span>
                        </div>
                        <div class="mb-4">
                            <input 
                                type="text" 
                                id="inactiveTenantsSearch"
                                class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2 px-3 text-sm focus:border-primary focus:ring-1 focus:ring-primary dark:border-slate-800 dark:bg-slate-800 dark:text-slate-200"
                                placeholder="Search by name or email..."
                            />
                        </div>
                        <div class="space-y-3 max-h-96 overflow-y-auto inactive-tenants-list">
                            <?php if (!empty($inactiveTenantsList)): ?>
                                <?php foreach ($inactiveTenantsList as $tenant): ?>
                                    <div class="tenant-item" data-name="<?php echo htmlspecialchars($tenant['ownerName']); ?>" data-email="<?php echo htmlspecialchars($tenant['email']); ?>" data-shop="<?php echo htmlspecialchars($tenant['shopName']); ?>" data-initials="<?php echo htmlspecialchars(initials($tenant['ownerName'])); ?>" data-status="<?php echo htmlspecialchars($tenant['status']); ?>">
                                        <div class="flex items-start gap-4 p-3 border border-slate-100 dark:border-slate-700 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                            <div class="w-10 h-10 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center font-semibold text-sm flex-shrink-0">
                                                <?php echo htmlspecialchars(initials($tenant['ownerName'])); ?>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-slate-900 dark:text-white truncate">
                                                    <?php echo htmlspecialchars($tenant['ownerName']); ?>
                                                </p>
                                                <p class="text-xs text-slate-500 dark:text-slate-400 truncate">
                                                    <?php echo htmlspecialchars($tenant['shopName']); ?>
                                                </p>
                                                <p class="text-xs text-slate-400 dark:text-slate-500 truncate">
                                                    <?php echo htmlspecialchars($tenant['email']); ?>
                                                </p>
                                                <p class="text-xs text-amber-600 dark:text-amber-400 mt-1 font-medium">
                                                    Joined <?php echo date('M d, Y', strtotime($tenant['created_at'])); ?>
                                                </p>
                                            </div>
                                            <div class="flex-shrink-0">
                                                <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">
                                                    <?php echo htmlspecialchars(ucfirst($tenant['status'])); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-sm text-slate-400 text-center py-6">No inactive tenants</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Prevent back button access - push a state onto history
            history.pushState(null, null, window.location.href);
            
            window.addEventListener('popstate', function(event) {
                // User clicked back button - redirect to logout
                window.location.href = '../logout/logout.php?action=do_logout&redirect=superaddlogin.php';
            });

            // Growth Table Pagination
            const growthTable = document.querySelector('.growth-table');
            if (growthTable) {
                const rows = Array.from(growthTable.querySelectorAll('.growth-row'));
                const rowsPerPage = parseInt(growthTable.getAttribute('data-rows-per-page')) || 5;
                let currentPage = 1;
                const totalPages = Math.ceil(rows.length / rowsPerPage);

                function showPage(pageNum) {
                    const start = (pageNum - 1) * rowsPerPage;
                    const end = start + rowsPerPage;

                    rows.forEach((row, idx) => {
                        row.style.display = (idx >= start && idx < end) ? 'table-row' : 'none';
                    });

                    // Update pagination info
                    const pageInfo = document.querySelector('.growth-page-info');
                    if (pageInfo) {
                        const displayStart = start + 1;
                        const displayEnd = Math.min(end, rows.length);
                        pageInfo.textContent = `${displayStart}-${displayEnd}`;
                    }

                    // Update button states
                    const prevBtn = document.querySelector('.growth-prev-btn');
                    const nextBtn = document.querySelector('.growth-next-btn');
                    if (prevBtn) prevBtn.disabled = pageNum === 1;
                    if (nextBtn) nextBtn.disabled = pageNum === totalPages;
                }

                // Pagination button handlers
                const prevBtn = document.querySelector('.growth-prev-btn');
                const nextBtn = document.querySelector('.growth-next-btn');

                if (prevBtn) {
                    prevBtn.addEventListener('click', () => {
                        if (currentPage > 1) {
                            currentPage--;
                            showPage(currentPage);
                        }
                    });
                }

                if (nextBtn) {
                    nextBtn.addEventListener('click', () => {
                        if (currentPage < totalPages) {
                            currentPage++;
                            showPage(currentPage);
                        }
                    });
                }

                // Initialize first page
                showPage(1);
            }

            // Tenant Search Functionality
            const activeSearchInput = document.getElementById('activeTenantsSearch');
            const inactiveSearchInput = document.getElementById('inactiveTenantsSearch');

            function filterTenants(searchInput, tenantList, countElement) {
                const searchTerm = searchInput.value.toLowerCase();
                const tenantItems = tenantList.querySelectorAll('.tenant-item');
                let visibleCount = 0;

                tenantItems.forEach(item => {
                    const name = item.dataset.name.toLowerCase();
                    const email = item.dataset.email.toLowerCase();
                    const shop = item.dataset.shop.toLowerCase();

                    const matches = name.includes(searchTerm) || email.includes(searchTerm) || shop.includes(searchTerm);
                    item.style.display = matches ? 'block' : 'none';
                    if (matches) visibleCount++;
                });

                // Update count
                if (countElement) {
                    countElement.textContent = visibleCount + ' ' + (visibleCount === 1 ? 'shop' : 'shops');
                }

                // Show no results message if needed
                if (visibleCount === 0) {
                    if (!tenantList.querySelector('.no-results')) {
                        const noResultsMsg = document.createElement('p');
                        noResultsMsg.className = 'no-results text-sm text-slate-400 text-center py-6';
                        noResultsMsg.textContent = 'No tenants found';
                        tenantList.appendChild(noResultsMsg);
                    }
                } else {
                    const noResultsMsg = tenantList.querySelector('.no-results');
                    if (noResultsMsg) {
                        noResultsMsg.remove();
                    }
                }
            }

            if (activeSearchInput) {
                const activeTenantsList = activeSearchInput.closest('.rounded-xl').querySelector('.active-tenants-list');
                const activeCount = activeSearchInput.closest('.rounded-xl').querySelector('.active-count');
                activeSearchInput.addEventListener('input', () => filterTenants(activeSearchInput, activeTenantsList, activeCount));
            }

            if (inactiveSearchInput) {
                const inactiveTenantsList = inactiveSearchInput.closest('.rounded-xl').querySelector('.inactive-tenants-list');
                const inactiveCount = inactiveSearchInput.closest('.rounded-xl').querySelector('.inactive-count');
                inactiveSearchInput.addEventListener('input', () => filterTenants(inactiveSearchInput, inactiveTenantsList, inactiveCount));
            }

            // Logout handler - submit to logout.php
            const logoutBtn = document.getElementById('logoutBtn');
            const logoutForm = document.getElementById('logoutForm');

            if (logoutBtn && logoutForm) {
                logoutBtn.addEventListener('click', () => {
                    logoutForm.submit();
                });
            }
        });
    </script>
</body>

</html>-