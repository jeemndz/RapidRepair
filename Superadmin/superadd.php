<?php
// superadd.php
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
    header("Location: /superadmin/superaddlogin.php");
    exit();
}

// Redirect if not logged in
if (!isset($_SESSION['superadmin_id'])) {
    header("Location: /superadmin/superaddlogin.php");
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

for ($i = 11; $i >= 0; $i--) {
    $monthStart = date('Y-m-01', strtotime("-$i months"));
    $monthEnd = date('Y-m-t', strtotime("-$i months"));
    $monthLabel = date('M', strtotime("-$i months"));

    $monthCountResult = $conn->query("SELECT COUNT(*) as total FROM owners WHERE DATE(created_at) >= '$monthStart' AND DATE(created_at) <= '$monthEnd'");
    $monthCount = $monthCountResult ? $monthCountResult->fetch_assoc()['total'] : 0;

    $growthLabels[] = $monthLabel;
    $growthData[] = $monthCount;
}

// ===== GEOGRAPHIC DISTRIBUTION (Top Regions - use a placeholder field or estimated by tenantID mod) =====
// Since there's no explicit region field, we'll aggregate by first letter of shopName as pseudo-regions
$geoQuery = "SELECT LEFT(shopName, 1) as region_code, COUNT(*) as shop_count FROM owners GROUP BY region_code ORDER BY shop_count DESC LIMIT 5";
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
    <title>Superadmin Dashboard - Car Repair Platform</title>
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
                        "background-light": "#f6f6f8",
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
                <form method="POST" class="w-full">
                    <button type="submit" name="logout_superadmin"
                        class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-900/10 transition-colors cursor-pointer text-left">
                        <span class="material-symbols-outlined">logout</span>
                        <p class="text-sm font-medium">Logout</p>
                    </button>
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
                    <div
                        class="h-10 w-10 rounded-full border-2 border-primary/20 bg-primary/10 text-primary flex items-center justify-center font-semibold text-sm">
                        <?php echo htmlspecialchars(initials($superadminName)); ?>
                    </div>
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
                                <p class="text-xs text-slate-500 dark:text-slate-400">Monthly shop registrations (12
                                    Months)</p>
                            </div>
                            <div class="flex gap-2">
                                <span
                                    class="inline-flex items-center rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/20">+<?php echo $totalTenantsCurrentMonth; ?>
                                    new shops</span>
                            </div>
                        </div>
                        <div class="relative w-full" style="height: 300px; position: relative;">
                            <canvas id="growthChart" style="max-height: 300px;"></canvas>
                        </div>
                    </div>
                    <!-- Geographic Distribution -->
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h4 class="text-base font-bold text-slate-900 dark:text-white">Geographic Distribution
                                </h4>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Top performance regions by shop
                                    volume</p>
                            </div>
                            <button class="text-xs text-primary font-medium hover:underline">View Map</button>
                        </div>
                        <div class="space-y-4">
                            <?php
                            $maxShops = !empty($geoData) ? $geoData[0]['shop_count'] : 1;
                            foreach ($geoData as $idx => $region):
                                $percentage = ($region['shop_count'] / $maxShops) * 100;
                                $regionNames = ['A' => 'Aurora', 'B' => 'Brooklyn', 'C' => 'Chicago', 'D' => 'Denver', 'E' => 'Edison', 'F' => 'Fresco', 'G' => 'Georgia', 'H' => 'Houston'];
                                $displayName = isset($regionNames[$region['region_code']]) ? $regionNames[$region['region_code']] : $region['region_code'] . ' Region';
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
                                <p class="text-sm text-slate-400">No shop data available</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Subscription Breakdown -->
                    <div
                        class="lg:col-span-2 rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-center justify-between mb-8">
                            <div>
                                <h4 class="text-base font-bold text-slate-900 dark:text-white">Service &amp; Tier
                                    Breakdown</h4>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Revenue distribution by membership
                                    tier</p>
                            </div>
                        </div>
                        <div class="flex flex-col md:flex-row items-center gap-12">
                            <div style="width: 250px; height: 250px; position: relative; flex-shrink: 0;">
                                <canvas id="subscriptionChart" style="display: block;"></canvas>
                            </div>
                            <div class="flex-1 space-y-4 w-full">
                                <?php
                                $colors = ['#b91c1c', '#dc2626', '#ef4444'];
                                foreach ($subBreakdown as $idx => $sub):
                                    $percentage = $totalSubCount > 0 ? ($sub['count'] / $totalSubCount) * 100 : 0;
                                    ?>
                                    <div class="flex items-center gap-4">
                                        <div class="h-3 w-3 rounded-full"
                                            style="background-color: <?php echo $colors[$idx % count($colors)]; ?>"></div>
                                        <span class="flex-1 text-sm text-slate-600 dark:text-slate-400">
                                            <?php echo htmlspecialchars($sub['subscription_plan'] ?: 'Standard Plan'); ?>
                                        </span>
                                        <span class="text-sm font-bold"><?php echo number_format($percentage, 0); ?>%</span>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (empty($subBreakdown)): ?>
                                    <p class="text-sm text-slate-400">No subscription data available</p>
                                <?php endif; ?>
                                <div class="pt-4 border-t border-slate-100 dark:border-slate-800">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-medium">Monthly Recurring Revenue</span>
                                        <span
                                            class="text-primary font-bold">$<?php echo number_format($totalActivePlanRevenue, 0); ?></span>
                                    </div>
                                </div>
                            </div>
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
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Growth Chart
            const growthCtx = document.getElementById('growthChart');
            if (growthCtx && typeof Chart !== 'undefined') {
                const growthCtxContext = growthCtx.getContext('2d');
                new Chart(growthCtxContext, {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode($growthLabels); ?>,
                        datasets: [{
                            label: 'Registrations',
                            data: <?php echo json_encode($growthData); ?>,
                            borderColor: '#b91c1c',
                            backgroundColor: 'rgba(185, 28, 28, 0.15)',
                            borderWidth: 2,
                            fill: false,
                            tension: 0,
                            pointRadius: 3
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { precision: 0 }
                            },
                            x: { grid: { display: false } }
                        }
                    }
                });
            }

            // Subscription Chart (Pie)
            const subCtx = document.getElementById('subscriptionChart');
            if (subCtx && typeof Chart !== 'undefined') {
                const subCtxContext = subCtx.getContext('2d');
                const subLabels = <?php echo json_encode(array_map(fn($s) => $s['subscription_plan'] ?: 'Standard', $subBreakdown)); ?>;
                const subData = <?php echo json_encode(array_map(fn($s) => $s['count'], $subBreakdown)); ?>;

                new Chart(subCtxContext, {
                    type: 'pie',
                    data: {
                        labels: subLabels,
                        datasets: [{
                            data: subData,
                            backgroundColor: ['#7f1d1d', '#b91c1c', '#dc2626', '#ef4444'],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'bottom'
                            }
                        }
                    }
                });
            }
        });
    </script>
</body>

</html>-