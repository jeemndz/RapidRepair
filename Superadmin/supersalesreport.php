<?php
session_start();
require_once '../db.php';

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

// Check if user is logged in as superadmin
if (!isset($_SESSION['superadmin_id'])) {
    header("Location: superaddlogin.php");
    exit;
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

// Load current branding settings
$brandingSettings = [
    'system_name' => 'RapidRepair',
    'primary_color' => '#531cb9',
    'logo_path' => ''
];

$brandingStmt = $conn->prepare("SELECT system_name, primary_color, logo_path FROM branding_settings WHERE id = 1");
if ($brandingStmt) {
    $brandingStmt->execute();
    $brandingRes = $brandingStmt->get_result();
    if ($brandingRes && $brandingRes->num_rows > 0) {
        $brandingSettings = $brandingRes->fetch_assoc();
    }
    $brandingStmt->close();
}

// Helper functions
function tableExists($conn, $tableName)
{
    $result = $conn->query("SHOW TABLES LIKE '$tableName'");
    return $result && $result->num_rows > 0;
}

function tableColumnExists($conn, $tableName, $columnName)
{
    $result = $conn->query("SHOW COLUMNS FROM $tableName LIKE '$columnName'");
    return $result && $result->num_rows > 0;
}

function buildWhereSql($parts)
{
    return count($parts) > 0 ? "WHERE " . implode(" AND ", $parts) : "";
}

function formatCurrency($value)
{
    return "₱" . number_format($value, 2);
}

function formatCount($value)
{
    if ($value >= 1000000)
        return number_format($value / 1000000, 1) . "M";
    if ($value >= 1000)
        return number_format($value / 1000, 1) . "k";
    return number_format($value);
}

function getPercentChange($current, $previous)
{
    if ($previous == 0)
        return $current > 0 ? 100 : 0;
    return (($current - $previous) / $previous) * 100;
}

function initials($name)
{
    $name = trim((string) $name);
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

// Get filter parameters
$dateRange = isset($_GET['dateRange']) ? $_GET['dateRange'] : '30';
$tenantFilter = isset($_GET['tenantFilter']) ? $_GET['tenantFilter'] : 'all';
$statusFilter = isset($_GET['statusFilter']) ? $_GET['statusFilter'] : 'all';
$granularity = isset($_GET['granularity']) ? $_GET['granularity'] : 'monthly';

// Fetch KPI metrics

// Total Revenue (all time or filtered)
$revenueWhere = [];
if ($tenantFilter !== 'all' && !empty($tenantFilter)) {
    $revenueWhere[] = "tenantID = " . intval($tenantFilter);
}
$revenueWhere[] = "paymentStatus IN ('Paid', 'Partial')";
$revenueWhere[] = "amountPaid > 0";
$revenueWhereDate = [];
if ($dateRange !== 'all') {
    if (in_array($dateRange, ['7', '30', '90'], true)) {
        $revenueWhere[] = "paymentDate >= DATE_SUB(NOW(), INTERVAL " . intval($dateRange) . " DAY)";
    } elseif ($dateRange === 'ytd') {
        $revenueWhere[] = "YEAR(paymentDate) = YEAR(CURDATE())";
    }
}

$revenueSql = "SELECT SUM(amountPaid) AS total_revenue FROM payments " . buildWhereSql($revenueWhere);
$revenueResult = $conn->query($revenueSql);
$totalRevenue = $revenueResult ? ($revenueResult->fetch_assoc()['total_revenue'] ?? 0) : 0;

// Revenue for previous period
$prevRevenueWhere = [];
if ($tenantFilter !== 'all')
    $prevRevenueWhere[] = "tenantID = " . intval($tenantFilter);
$prevRevenueWhere[] = "paymentStatus IN ('Paid', 'Partial')";
$prevRevenueWhere[] = "amountPaid > 0";

switch ($dateRange) {
    case '7':
        $prevRevenueWhere[] = "paymentDate >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND paymentDate < DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case '30':
        $prevRevenueWhere[] = "paymentDate >= DATE_SUB(NOW(), INTERVAL 60 DAY) AND paymentDate < DATE_SUB(NOW(), INTERVAL 30 DAY)";
        break;
    case '90':
        $prevRevenueWhere[] = "paymentDate >= DATE_SUB(NOW(), INTERVAL 180 DAY) AND paymentDate < DATE_SUB(NOW(), INTERVAL 90 DAY)";
        break;
}

$prevRevenueSql = "SELECT SUM(amountPaid) AS total_revenue FROM payments " . buildWhereSql($prevRevenueWhere);
$prevRevenueResult = $conn->query($prevRevenueSql);
$prevRevenue = $prevRevenueResult ? ($prevRevenueResult->fetch_assoc()['total_revenue'] ?? 0) : 0;

$revenueChange = getPercentChange($totalRevenue, $prevRevenue);
$revenueChangeStr = ($revenueChange >= 0 ? "+" : "") . number_format($revenueChange, 1);

// Total Transactions Count
$transactionWhere = [];
if ($tenantFilter !== 'all' && !empty($tenantFilter)) {
    $transactionWhere[] = "tenantID = " . intval($tenantFilter);
}
$transactionWhere[] = "paymentStatus IN ('Paid', 'Partial')";
if ($dateRange !== 'all') {
    $transactionWhere[] = "paymentDate >= DATE_SUB(NOW(), INTERVAL " . intval($dateRange) . " DAY)";
}
$txnSql = "SELECT COUNT(*) AS total_txns FROM payments " . buildWhereSql($transactionWhere);
$txnResult = $conn->query($txnSql);
$totalTransactions = $txnResult ? ($txnResult->fetch_assoc()['total_txns'] ?? 0) : 0;

// Previous period transactions
$prevTxnWhere = [];
if ($tenantFilter !== 'all')
    $prevTxnWhere[] = "tenantID = " . intval($tenantFilter);
$prevTxnWhere[] = "paymentStatus IN ('Paid', 'Partial')";
switch ($dateRange) {
    case '7':
        $prevTxnWhere[] = "paymentDate >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND paymentDate < DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case '30':
        $prevTxnWhere[] = "paymentDate >= DATE_SUB(NOW(), INTERVAL 60 DAY) AND paymentDate < DATE_SUB(NOW(), INTERVAL 30 DAY)";
        break;
    case '90':
        $prevTxnWhere[] = "paymentDate >= DATE_SUB(NOW(), INTERVAL 180 DAY) AND paymentDate < DATE_SUB(NOW(), INTERVAL 90 DAY)";
        break;
}
$prevTxnSql = "SELECT COUNT(*) AS total_txns FROM payments " . buildWhereSql($prevTxnWhere);
$prevTxnResult = $conn->query($prevTxnSql);
$prevTxns = $prevTxnResult ? ($prevTxnResult->fetch_assoc()['total_txns'] ?? 0) : 0;

$txnChange = getPercentChange($totalTransactions, $prevTxns);
$txnChangeStr = ($txnChange >= 0 ? "+" : "") . number_format($txnChange, 1);

// Average Transaction Value
$avgTxnValue = $totalTransactions > 0 ? ($totalRevenue / $totalTransactions) : 0;
$prevAvgTxnValue = $prevTxns > 0 ? ($prevRevenue / $prevTxns) : 0;
$avgTxnChange = getPercentChange($avgTxnValue, $prevAvgTxnValue);
$avgTxnChangeStr = ($avgTxnChange >= 0 ? "+" : "") . number_format($avgTxnChange, 1);

// Fetch revenue trend data (dynamic granularity: daily, weekly, monthly)
// Initialize arrays for trend data
$trendLabels = [];
$trendData = [];

// Determine date range for trend based on granularity
$trendDays = 30;
if ($granularity === 'daily') {
    $trendDays = intval($dateRange);
    $dateFormat = '%Y-%m-%d';
    $dateFormatLabel = 'M d';
} elseif ($granularity === 'weekly') {
    $trendDays = intval($dateRange) * 2;
    $dateFormat = '%Y-%u';
    $dateFormatLabel = 'W';
} else { // monthly
    $trendDays = 365;
    $dateFormat = '%Y-%m';
    $dateFormatLabel = 'M Y';
}

// Initialize trend labels based on granularity
if ($granularity === 'daily') {
    for ($i = intval($dateRange) - 1; $i >= 0; $i--) {
        $dayDate = strtotime("-$i days");
        $trendLabels[] = date('M d', $dayDate);
        $trendData[] = 0;
    }
} elseif ($granularity === 'weekly') {
    for ($i = 11; $i >= 0; $i--) {
        $weekDate = strtotime("-$i weeks");
        $weekNum = date('W', $weekDate);
        $yearNum = date('Y', $weekDate);
        $trendLabels[] = "W" . $weekNum . " '" . substr(date('y', $weekDate), 1);
        $trendData[] = 0;
    }
} else { // monthly
    for ($i = 11; $i >= 0; $i--) {
        $monthDate = strtotime("first day of -$i month");
        $trendLabels[] = date("M Y", $monthDate);
        $trendData[] = 0;
    }
}

// Query revenue data with appropriate granularity
$trendWhere = [];
if ($tenantFilter !== 'all')
    $trendWhere[] = "tenantID = " . intval($tenantFilter);
$trendWhere[] = "paymentStatus IN ('Paid', 'Partial')";
$trendWhere[] = "amountPaid > 0";
$trendWhere[] = "paymentDate >= DATE_SUB(NOW(), INTERVAL $trendDays DAY)";

$trendSql = "SELECT DATE_FORMAT(paymentDate, '$dateFormat') AS time_key, SUM(amountPaid) AS revenue 
             FROM payments " . buildWhereSql($trendWhere) . " GROUP BY time_key ORDER BY time_key ASC";
$trendResult = $conn->query($trendSql);

if ($trendResult) {
    $trendLookup = [];
    while ($row = $trendResult->fetch_assoc()) {
        $trendLookup[(string) $row['time_key']] = round((float) $row['revenue'], 2);
    }

    // Map data to the trend periods
    if ($granularity === 'daily') {
        foreach ($trendLabels as $idx => $label) {
            $dayKey = date('Y-m-d', strtotime($label));
            // Parse label back to get the date
            $offset = intval($dateRange) - 1 - $idx;
            $dayKey = date('Y-m-d', strtotime("-$offset days"));
            $trendData[$idx] = $trendLookup[$dayKey] ?? 0;
        }
    } elseif ($granularity === 'weekly') {
        foreach ($trendLabels as $idx => $label) {
            $offset = 11 - $idx;
            $weekDate = strtotime("-$offset weeks");
            $weekKey = date('Y-W', $weekDate);
            $trendData[$idx] = $trendLookup[$weekKey] ?? 0;
        }
    } else { // monthly
        foreach ($trendLabels as $idx => $label) {
            $monthKey = date("Y-m", strtotime("1 " . $label));
            $trendData[$idx] = $trendLookup[$monthKey] ?? 0;
        }
    }
}

// Fetch top performing tenants
$topTenantsWhere = [];
if ($tenantFilter !== 'all' && !empty($tenantFilter)) {
    $topTenantsWhere[] = "tenantID = " . intval($tenantFilter);
}
$topTenantsWhere[] = "paymentStatus IN ('Paid', 'Partial')";
$topTenantsWhere[] = "amountPaid > 0";
$topTenantsSql = "SELECT p.tenantID, 
                         (SELECT shopName FROM owners WHERE tenantID = p.tenantID LIMIT 1) AS shopName,
                         SUM(p.amountPaid) AS total_revenue, 
                         COUNT(*) AS tenant_count 
                  FROM payments p " . buildWhereSql($topTenantsWhere) . " GROUP BY p.tenantID ORDER BY total_revenue DESC LIMIT 10";
$topTenantsResult = $conn->query($topTenantsSql);
$topTenants = [];
if ($topTenantsResult) {
    while ($row = $topTenantsResult->fetch_assoc()) {
        $topTenants[] = $row;
    }
}

// Fetch most availed services per shop
$mostAvailedSql = "SELECT 
                    p.tenantID,
                    (SELECT shopName FROM owners WHERE tenantID = p.tenantID LIMIT 1) AS shopName,
                    s.service_id,
                    s.service_name,
                    COUNT(*) AS serviceCount,
                    SUM(p.amountPaid) AS serviceRevenue
                FROM payments p
                INNER JOIN appointments a ON p.appointment_id = a.appointment_id
                INNER JOIN appointment_services ast ON a.appointment_id = ast.appointment_id
                INNER JOIN services s ON ast.service_id = s.service_id
                WHERE p.paymentStatus IN ('Paid', 'Partial') AND p.amountPaid > 0";

$mostAvailedWhere = [];
if ($tenantFilter !== 'all' && !empty($tenantFilter)) {
    $mostAvailedWhere[] = "p.tenantID = " . intval($tenantFilter);
}
if (count($mostAvailedWhere) > 0) {
    $mostAvailedSql .= " AND " . implode(" AND ", $mostAvailedWhere);
}

$mostAvailedSql .= " GROUP BY p.tenantID, s.service_id, s.service_name 
                ORDER BY p.tenantID, serviceCount DESC";

$mostAvailedResult = $conn->query($mostAvailedSql);
$mostAvailedServicesByShop = [];
if ($mostAvailedResult) {
    while ($row = $mostAvailedResult->fetch_assoc()) {
        $tenantID = $row['tenantID'];
        if (!isset($mostAvailedServicesByShop[$tenantID])) {
            $mostAvailedServicesByShop[$tenantID] = [
                'shopName' => $row['shopName'],
                'services' => []
            ];
        }
        if (count($mostAvailedServicesByShop[$tenantID]['services']) < 5) {
            $mostAvailedServicesByShop[$tenantID]['services'][] = $row;
        }
    }
}

// Fetch available tenants for dropdown
$tenantsDropdownSql = "SELECT tenantID, shopName FROM owners ORDER BY shopName ASC";
$tenantsDropdownResult = $conn->query($tenantsDropdownSql);
$tenantsList = [];
if ($tenantsDropdownResult) {
    while ($row = $tenantsDropdownResult->fetch_assoc()) {
        $tenantsList[] = $row;
    }
}

// Get date range label
$dateLabel = match ($dateRange) {
    '7' => 'Last 7 Days',
    '30' => 'Last 30 Days',
    '90' => 'Last 90 Days',
    'ytd' => 'Year to Date',
    default => 'Last 30 Days'
};

// Get current date for display
$dateEnd = date('M d, Y');
$dateStart = match ($dateRange) {
    '7' => date('M d, Y', strtotime('-7 days')),
    '30' => date('M d, Y', strtotime('-30 days')),
    '90' => date('M d, Y', strtotime('-90 days')),
    'ytd' => date('M d, Y', strtotime('January 1, ' . date('Y'))),
    default => date('M d, Y', strtotime('-30 days'))
};
?>

<!DOCTYPE html>

<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Sales Reports &amp; Financial Analytics | RapidRepair</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <!-- jsPDF for PDF export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <!-- jsPDF AutoTable plugin for table formatting -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
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
                        "primary-fixed-dim": "#fecaca",
                        "surface-tint": "#b91c1c",
                        "background": "#ffffff",
                        "secondary-fixed": "#e5e7eb",
                        "surface-container-high": "#ffffff",
                        "surface-container-lowest": "#ffffff",
                        "tertiary": "#f59e0b",
                        "surface-container-low": "#ffffff",
                        "secondary-fixed-dim": "#d4d4d8",
                        "error": "#dc2626",
                        "tertiary-fixed-dim": "#fed7aa",
                        "on-primary-fixed-variant": "#991b1b",
                        "on-primary-fixed": "#7f1d1d",
                        "surface-dim": "#e5e7eb",
                        "outline-variant": "#d4d4d8",
                        "on-tertiary": "#ffffff",
                        "error-container": "#fee2e2",
                        "on-primary-container": "#7f1d1d",
                        "primary-container": "#fee2e2",
                        "on-secondary-container": "#18181b",
                        "surface": "#ffffff",
                        "outline": "#e5e7eb",
                        "tertiary-container": "#fef3c7",
                        "surface-variant": "#f5f5f5",
                        "on-background": "#0a0a0a",
                        "inverse-primary": "#fecaca",
                        "on-secondary-fixed-variant": "#3f3f46",
                        "surface-container-highest": "#ffffff",
                        "inverse-surface": "#18181b",
                        "on-surface": "#111827",
                        "surface-bright": "#ffffff",
                        "on-primary": "#ffffff",
                        "secondary": "#3f3f46",
                        "on-surface-variant": "#525252",
                        "on-tertiary-fixed-variant": "#9a3412",
                        "inverse-on-surface": "#f8fafc",
                        "primary-fixed": "#fee2e2",
                        "on-tertiary-fixed": "#7c2d12",
                        "on-error-container": "#991b1b",
                        "secondary-container": "#f5f5f5",
                        "on-secondary-fixed": "#111827",
                        "surface-container": "#ffffff",
                        "on-error": "#ffffff",
                        "tertiary-fixed": "#ffedd5",
                        "primary": "#b91c1c",
                        "on-tertiary-container": "#92400e",
                        "on-secondary": "#ffffff"
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
                    },
                },
            },
        }
    </script>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #ffffff;
        }

        .chart-gradient {
            background: linear-gradient(to bottom, rgba(185, 28, 28, 0.1), rgba(185, 28, 28, 0));
        }

        .chart-wrapper {
            position: relative;
            height: 300px;
            width: 100%;
            display: block;
        }
    </style>
</head>

<body class="bg-background text-on-background antialiased selection:bg-primary-container selection:text-primary">
    <div class="flex h-screen overflow-hidden">
        <!-- SideNavBar (Shared Component) -->
        <aside
            class="flex flex-col fixed left-0 top-0 h-full z-50 w-64 border-r border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 font-['Inter'] antialiased tracking-tight shadow-sm dark:shadow-none">
            <div class="p-6 flex items-center gap-3">
                <div class="h-10 w-10 rounded-lg overflow-hidden">
                    <img src="../pictures/RRlogo3.png" alt="Rapid Repair logo" class="h-full w-full object-contain">
                </div>
                <h2 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white leading-none">
                    <?= htmlspecialchars($brandingSettings['system_name']) ?> <span class="text-primary">SuperAdmin</span>
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
                <a class="flex items-center gap-3 px-3 py-2.5 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 font-bold border-r-4 border-red-700 dark:border-red-500 rounded-lg active:scale-95"
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

            <div class="p-4 border-t border-slate-100 space-y-2">
                <div class="flex items-center gap-3 p-2 rounded-lg hover:bg-slate-50 transition-colors">
                    <div
                        class="w-10 h-10 rounded-full bg-primary-container text-primary flex items-center justify-center font-semibold text-sm">
                        <?php echo htmlspecialchars(initials($superadminName)); ?>
                    </div>
                    <div class="flex flex-col min-w-0">
                        <h3 class="text-sm font-semibold truncate"><?php echo htmlspecialchars($superadminName); ?></h3>
                        <p class="text-xs text-slate-500 truncate">Superadmin</p>
                    </div>
                </div>
                <form method="POST" class="w-full">
                    <button type="submit" name="logout_superadmin"
                        class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-red-500 hover:bg-red-50 transition-colors cursor-pointer text-left mt-2">
                        <span class="material-symbols-outlined">logout</span>
                        <p class="text-sm font-medium">Logout</p>
                    </button>
                </form>
            </div>
        </aside>
        <!-- Main Content Canvas -->
        <main class="flex-1 flex flex-col overflow-y-auto ml-64">
            <!-- TopAppBar (Shared Component) -->
            <header
                class="flex items-center justify-between px-8 w-full h-16 border-b border-slate-200 bg-[#f6f6f8] sticky top-0 z-10">
                <div class="flex items-center gap-6">
                    <div class="relative">
                        <input
                            class="pl-4 pr-4 py-1.5 my-2 bg-white border border-slate-200 rounded-lg text-sm focus:ring-1 focus:ring-primary focus:border-primary w-64 transition-all"
                            placeholder="Search analytics..." type="text" />
                    </div>
                </div>
                <div class="flex items-center gap-4"></div>
            </header>
            <!-- Scrollable Content Section -->
            <div class="p-8 space-y-8 max-w-7xl mx-auto w-full">
                <!-- Page Title -->
                <div class="flex items-end justify-between">
                    <div>
                        <h2 class="text-3xl font-black tracking-tight text-on-background">Sales &amp; Financials</h2>
                        <p class="text-sm text-slate-500 mt-1">Real-time revenue aggregation across all tenant shops.
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="exportReport()"
                            class="px-4 py-2 border border-slate-200 bg-white text-xs font-bold rounded-lg hover:bg-slate-50 transition-colors flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm">download</span>
                            Export CSV
                        </button>
                        <button onclick="exportReportPDF()"
                            class="px-4 py-2 bg-tertiary text-white text-xs font-bold rounded-lg hover:bg-yellow-600 transition-all flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm">picture_as_pdf</span>
                            Export PDF
                        </button>
                        <button
                            class="px-4 py-2 bg-primary text-white text-xs font-bold rounded-lg hover:bg-primary/90 transition-all flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm">calendar_today</span>
                            <?php echo $dateStart; ?> - <?php echo $dateEnd; ?>
                        </button>
                    </div>
                </div>

                <!-- Filter Controls -->
                <div class="bg-white border border-slate-200 p-6 rounded-lg shadow-sm">
                    <form method="GET" class="flex flex-col gap-4">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="flex-1">
                                <label class="text-xs font-bold text-slate-600 uppercase tracking-wider block mb-2">Date
                                    Range</label>
                                <select name="dateRange"
                                    class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-1 focus:ring-primary">
                                    <option value="7" <?php echo $dateRange === '7' ? 'selected' : ''; ?>>Last 7 Days</option>
                                    <option value="30" <?php echo $dateRange === '30' ? 'selected' : ''; ?>>Last 30 Days
                                    </option>
                                    <option value="90" <?php echo $dateRange === '90' ? 'selected' : ''; ?>>Last 90 Days
                                    </option>
                                    <option value="ytd" <?php echo $dateRange === 'ytd' ? 'selected' : ''; ?>>Year to Date
                                    </option>
                                    <option value="all" <?php echo $dateRange === 'all' ? 'selected' : ''; ?>>All Time
                                    </option>
                                </select>
                            </div>
                            <div class="flex-1">
                                <label
                                    class="text-xs font-bold text-slate-600 uppercase tracking-wider block mb-2">Tenant</label>
                                <select name="tenantFilter"
                                    class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-1 focus:ring-primary">
                                    <option value="all">All Tenants</option>
                                    <?php foreach ($tenantsList as $tenant): ?>
                                        <option value="<?php echo $tenant['tenantID']; ?>" <?php echo $tenantFilter == $tenant['tenantID'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($tenant['shopName']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="flex-1">
                                <label
                                    class="text-xs font-bold text-slate-600 uppercase tracking-wider block mb-2">Status</label>
                                <select name="statusFilter"
                                    class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-1 focus:ring-primary">
                                    <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Status
                                    </option>
                                    <option value="active_only" <?php echo $statusFilter === 'active_only' ? 'selected' : ''; ?>>Active Only</option>
                                    <option value="trial" <?php echo $statusFilter === 'trial' ? 'selected' : ''; ?>>Trial
                                    </option>
                                    <option value="suspended" <?php echo $statusFilter === 'suspended' ? 'selected' : ''; ?>>
                                        Suspended</option>
                                </select>
                            </div>
                            <div class="flex-1">
                                <label
                                    class="text-xs font-bold text-slate-600 uppercase tracking-wider block mb-2">Granularity</label>
                                <select name="granularity"
                                    class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-1 focus:ring-primary">
                                    <option value="daily" <?php echo $granularity === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                    <option value="weekly" <?php echo $granularity === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                    <option value="monthly" <?php echo $granularity === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                </select>
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit"
                                class="px-6 py-2 bg-primary text-white text-xs font-bold rounded-lg hover:bg-primary/90 transition-colors flex items-center gap-2">
                                <span class="material-symbols-outlined text-sm">filter_list</span>
                                Apply Filters
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Top Section: Metric Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Total Revenue -->
                    <div class="bg-white border border-slate-200 p-6 rounded-lg shadow-sm">
                        <div class="flex justify-between items-start mb-4">
                            <div
                                class="w-10 h-10 rounded-lg bg-primary-container flex items-center justify-center text-primary">
                                <span class="material-symbols-outlined">payments</span>
                            </div>
                            <span
                                class="text-[10px] font-bold px-2 py-1 <?php echo $revenueChange >= 0 ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-600'; ?> rounded-full flex items-center gap-1">
                                <span
                                    class="material-symbols-outlined text-[12px]"><?php echo $revenueChange >= 0 ? 'trending_up' : 'trending_down'; ?></span>
                                <?php echo $revenueChangeStr; ?>%
                            </span>
                        </div>
                        <p class="text-slate-500 text-xs font-semibold uppercase tracking-wider">Total Sales
                        </p>
                        <h3 class="text-2xl font-black mt-1"><?php echo formatCurrency($totalRevenue); ?></h3>
                        <p class="text-[10px] text-slate-400 mt-2 font-medium">vs.
                            <?php echo formatCurrency($prevRevenue); ?> previous period</p>
                    </div>
                    <!-- Total Transactions -->
                    <div class="bg-white border border-slate-200 p-6 rounded-lg shadow-sm">
                        <div class="flex justify-between items-start mb-4">
                            <div
                                class="w-10 h-10 rounded-lg bg-primary-container flex items-center justify-center text-primary">
                                <span class="material-symbols-outlined">receipt_long</span>
                            </div>
                            <span
                                class="text-[10px] font-bold px-2 py-1 <?php echo $txnChange >= 0 ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-600'; ?> rounded-full flex items-center gap-1">
                                <span
                                    class="material-symbols-outlined text-[12px]"><?php echo $txnChange >= 0 ? 'trending_up' : 'trending_down'; ?></span>
                                <?php echo $txnChangeStr; ?>%
                            </span>
                        </div>
                        <p class="text-slate-500 text-xs font-semibold uppercase tracking-wider">Total Transactions</p>
                        <h3 class="text-2xl font-black mt-1"><?php echo formatCount($totalTransactions); ?></h3>
                        <p class="text-[10px] text-slate-400 mt-2 font-medium">Avg.
                            <?php echo round($totalTransactions / ($dateRange == 'all' ? 365 : intval($dateRange))); ?>
                            per day</p>
                    </div>
                    <!-- Average Transaction Value -->
                    <div class="bg-white border border-slate-200 p-6 rounded-lg shadow-sm">
                        <div class="flex justify-between items-start mb-4">
                            <div
                                class="w-10 h-10 rounded-lg bg-primary-container flex items-center justify-center text-primary">
                                <span class="material-symbols-outlined">analytics</span>
                            </div>
                            <span
                                class="text-[10px] font-bold px-2 py-1 <?php echo $avgTxnChange >= 0 ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-600'; ?> rounded-full flex items-center gap-1">
                                <span
                                    class="material-symbols-outlined text-[12px]"><?php echo $avgTxnChange >= 0 ? 'trending_up' : 'trending_down'; ?></span>
                                <?php echo $avgTxnChangeStr; ?>%
                            </span>
                        </div>
                        <p class="text-slate-500 text-xs font-semibold uppercase tracking-wider">Avg. Transaction Value
                        </p>
                        <h3 class="text-2xl font-black mt-1"><?php echo formatCurrency($avgTxnValue); ?></h3>
                        <p class="text-[10px] text-slate-400 mt-2 font-medium">vs.
                            <?php echo formatCurrency($prevAvgTxnValue); ?> previous period</p>
                    </div>
                </div>
                <!-- Middle Section: Analytics Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Revenue Trends Chart -->
                    <div
                        class="lg:col-span-2 bg-white border border-slate-200 rounded-lg shadow-sm flex flex-col">
                        <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                            <h4 class="font-bold text-sm">Sales Trends</h4>
                            <div class="flex bg-slate-100 p-1 rounded-lg gap-1">
                                <a href="?dateRange=<?php echo $dateRange; ?>&tenantFilter=<?php echo $tenantFilter; ?>&statusFilter=<?php echo $statusFilter; ?>&granularity=daily"
                                    class="px-3 py-1 text-[10px] font-bold rounded <?php echo $granularity === 'daily' ? 'bg-white text-primary shadow-sm' : 'text-slate-600 hover:text-primary'; ?> transition-colors">Daily</a>
                                <a href="?dateRange=<?php echo $dateRange; ?>&tenantFilter=<?php echo $tenantFilter; ?>&statusFilter=<?php echo $statusFilter; ?>&granularity=weekly"
                                    class="px-3 py-1 text-[10px] font-bold rounded <?php echo $granularity === 'weekly' ? 'bg-white text-primary shadow-sm' : 'text-slate-600 hover:text-primary'; ?> transition-colors">Weekly</a>
                                <a href="?dateRange=<?php echo $dateRange; ?>&tenantFilter=<?php echo $tenantFilter; ?>&statusFilter=<?php echo $statusFilter; ?>&granularity=monthly"
                                    class="px-3 py-1 text-[10px] font-bold rounded <?php echo $granularity === 'monthly' ? 'bg-white text-primary shadow-sm' : 'text-slate-600 hover:text-primary'; ?> transition-colors">Monthly</a>
                            </div>
                        </div>
                        <div class="p-6 flex-1">
                            <div class="chart-wrapper">
                                <canvas id="revenueChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <!-- Top-Performing Tenants -->
                    <div class="bg-white border border-slate-200 rounded-lg shadow-sm flex flex-col">
                        <div class="p-6 border-b border-slate-100">
                            <h4 class="font-bold text-sm">Top Performers</h4>
                        </div>
                        <div class="p-6 space-y-6 flex-1 overflow-y-auto max-h-[400px]">
                            <div>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">Top
                                    Tenant Shops</p>
                                <div class="space-y-3">
                                    <?php
                                    foreach ($topTenants as $idx => $tenant):
                                        $totalRev = $tenant['total_revenue'];
                                        ?>
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <div
                                                    class="w-6 h-6 rounded bg-slate-100 text-[10px] font-bold flex items-center justify-center">
                                                    <?php echo $idx + 1; ?>
                                                </div>
                                                <span
                                                    class="text-xs font-bold"><?php echo htmlspecialchars(substr($tenant['shopName'], 0, 25)); ?></span>
                                            </div>
                                            <span
                                                class="text-xs font-bold text-primary"><?php echo formatCurrency($totalRev); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (count($topTenants) === 0): ?>
                                        <p class="text-xs text-slate-400">No data available</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- New Section: Most Availed Services -->
                <div class="bg-white border border-slate-200 rounded-lg shadow-sm">
                    <div class="p-6 border-b border-slate-100">
                        <h4 class="font-bold text-sm">Most Availed Services per Shop</h4>
                        <p class="text-[11px] text-slate-500 mt-1">Top 5 services used by each tenant</p>
                    </div>
                    <div class="p-6">
                        <?php if (count($mostAvailedServicesByShop) === 0): ?>
                            <p class="text-xs text-slate-400 text-center py-4">No service data available</p>
                        <?php else: ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <?php foreach ($mostAvailedServicesByShop as $tenantID => $shopData): ?>
                                    <div class="border border-slate-200 rounded-lg p-4">
                                        <h5 class="font-bold text-sm text-slate-900 mb-4"><?php echo htmlspecialchars($shopData['shopName']); ?></h5>
                                        <div class="space-y-3">
                                            <?php if (empty($shopData['services'])): ?>
                                                <p class="text-xs text-slate-400">No services</p>
                                            <?php else: ?>
                                                <?php foreach ($shopData['services'] as $service): ?>
                                                    <div class="flex items-center justify-between border-b border-slate-100 pb-3 last:border-b-0">
                                                        <div class="flex-1">
                                                            <p class="text-xs font-semibold text-slate-900"><?php echo htmlspecialchars($service['service_name']); ?></p>
                                                            <p class="text-[10px] text-slate-500 mt-1">
                                                                <?php echo $service['serviceCount']; ?> times
                                                            </p>
                                                        </div>
                                                        <div class="text-right">
                                                            <p class="text-xs font-bold text-primary"><?php echo formatCurrency($service['serviceRevenue']); ?></p>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Bottom Section: Transaction History -->
                <div class="bg-white border border-slate-200 rounded-lg shadow-sm">
                    <div
                        class="p-6 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div>
                            <h4 class="font-bold text-sm">Tenant Sales Breakdown</h4>
                            <p class="text-[11px] text-slate-500">Overview of revenue by tenant during selected period
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <div class="relative">
                                <span
                                    class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">search</span>
                                <input id="tenantSearch"
                                    class="pl-8 pr-4 py-1.5 bg-slate-50 border border-slate-200 rounded text-xs focus:ring-1 focus:ring-primary w-40"
                                    placeholder="Search tenants..." type="text" />
                            </div>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200">
                                    <th
                                        class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                        Rank</th>
                                    <th
                                        class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                        Tenant Name</th>
                                    <th
                                        class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                        Total Sales</th>
                                    <th
                                        class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                        Subscriptions</th>
                                    <th
                                        class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                        Avg Value</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php
                                $rank = 1;
                                foreach ($topTenants as $tenant):
                                    $avgValue = $tenant['tenant_count'] > 0 ? $tenant['total_revenue'] / $tenant['tenant_count'] : 0;
                                    ?>
                                    <tr class="hover:bg-slate-50 transition-colors tenant-row"
                                        data-search="<?php echo strtolower(htmlspecialchars($tenant['shopName'])); ?>">
                                        <td class="px-6 py-4 text-xs font-bold text-slate-900"><?php echo $rank; ?></td>
                                        <td class="px-6 py-4 text-xs font-bold">
                                            <?php echo htmlspecialchars($tenant['shopName']); ?></td>
                                        <td class="px-6 py-4 text-xs font-bold text-primary">
                                            <?php echo formatCurrency($tenant['total_revenue']); ?></td>
                                        <td class="px-6 py-4 text-xs">
                                            <span class="px-2 py-0.5 bg-red-50 text-red-600 rounded text-[10px] font-bold">
                                                <?php echo $tenant['tenant_count']; ?> active
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-xs text-slate-600">
                                            <?php echo formatCurrency($avgValue); ?></td>
                                    </tr>
                                    <?php
                                    $rank++;
                                endforeach;
                                ?>
                                <?php if (count($topTenants) === 0): ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-8 text-center text-xs text-slate-400">
                                            No data available for the selected filters
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4 border-t border-slate-100 flex items-center justify-between">
                        <span class="text-[11px] font-bold text-slate-400">Showing
                            <?php echo min(10, count($topTenants)); ?> of <?php echo count($topTenants); ?>
                            tenants</span>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize revenue chart
            const ctx = document.getElementById('revenueChart');
            if (ctx) {
                const labels = <?php echo json_encode($trendLabels); ?>;
                const data = <?php echo json_encode($trendData); ?>;
                
                // Convert all values to numbers
                const numericData = data.map(function(val) {
                    return parseFloat(val) || 0;
                });
                
                try {
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Monthly Revenue',
                                data: numericData,
                                borderColor: '#b91c1c',
                                backgroundColor: 'rgba(185, 28, 28, 0.16)',
                                fill: true,
                                tension: 0.3,
                                pointRadius: 4,
                                pointBackgroundColor: '#b91c1c',
                                pointBorderColor: '#ffffff',
                                pointBorderWidth: 2,
                                pointHoverRadius: 6
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
                                x: {
                                    grid: {
                                        display: false
                                    },
                                    ticks: {
                                        maxRotation: 0,
                                        autoSkip: true
                                    }
                                },
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        precision: 0
                                    }
                                }
                            }
                        }
                    });
                } catch(e) {
                    console.error('Chart initialization error:', e);
                }
            }
        });

        // Table search functionality
        const searchInput = document.getElementById('tenantSearch');
        if (searchInput) {
            searchInput.addEventListener('keyup', function () {
                const searchTerm = this.value.toLowerCase();
                const rows = document.querySelectorAll('.tenant-row');

                rows.forEach(row => {
                    const searchData = row.getAttribute('data-search') || '';
                    if (searchData.includes(searchTerm) || searchTerm === '') {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }

        // Export report as CSV
        function exportReport() {
            const table = document.querySelector('table');
            let csv = 'Tenant Revenue Report\n';
            csv += 'Rank,Tenant Name,Total Revenue,Subscriptions,Avg Value\n';
            const rows = document.querySelectorAll('.tenant-row');
            let rank = 1;
            rows.forEach(row => {
                if (row.style.display !== 'none') {
                    const cells = row.querySelectorAll('td');
                    csv += rank + ',';
                    csv += cells[1].textContent + ',';
                    csv += cells[2].textContent + ',';
                    csv += cells[3].textContent + ',';
                    csv += cells[4].textContent + '\n';
                    rank++;
                }
            });
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'sales-report-' + new Date().toISOString().split('T')[0] + '.csv';
            a.click();
        }

        // Export report as PDF (with table formatting)
        function exportReportPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            // Get filter values from URL parameters
            const params = new URLSearchParams(window.location.search);
            const dateRange = params.get('dateRange') || '30';
            const tenantFilter = params.get('tenantFilter') || 'all';
            const statusFilter = params.get('statusFilter') || 'all';
            const granularity = params.get('granularity') || 'monthly';
            
            // Page settings
            let yPosition = 15;
            const pageWidth = doc.internal.pageSize.getWidth();
            const pageHeight = doc.internal.pageSize.getHeight();
            const margin = 10;
            
            // Title
            doc.setFontSize(18);
            doc.setTextColor(185, 28, 28);
            doc.text('Sales & Financial Report', margin, yPosition);
            yPosition += 8;
            
            // Filter Summary
            doc.setFontSize(10);
            doc.setTextColor(100, 100, 100);
            const dateRangeLabel = getDateRangeLabel(dateRange);
            const tenantLabel = tenantFilter === 'all' ? 'All Tenants' : getTenantName(tenantFilter);
            const statusLabel = statusFilter === 'all' ? 'All Status' : statusFilter.replace('_', ' ').toUpperCase();
            
            doc.text(`Period: ${dateRangeLabel} | Tenant: ${tenantLabel} | Status: ${statusLabel} | Granularity: ${granularity.toUpperCase()}`, margin, yPosition);
            yPosition += 6;
            doc.text(`Generated: ${new Date().toLocaleString()}`, margin, yPosition);
            yPosition += 10;
            
            // KPI Metrics Section
            doc.setFontSize(12);
            doc.setTextColor(0, 0, 0);
            doc.text('Key Performance Indicators', margin, yPosition);
            yPosition += 8;
            
            // Get metric values from the page
            const metricsData = [
                ['Metric', 'Current', 'Previous', 'Change'],
                ['Total Revenue', getMetricValue('revenue'), getMetricValue('revenuePrev'), getMetricValue('revenueChange')],
                ['Total Transactions', getMetricValue('transactions'), getMetricValue('transactionsPrev'), getMetricValue('transactionsChange')],
                ['Avg Transaction Value', getMetricValue('avgValue'), getMetricValue('avgValuePrev'), getMetricValue('avgValueChange')]
            ];
            
            doc.autoTable({
                head: metricsData.slice(0, 1),
                body: metricsData.slice(1),
                startY: yPosition,
                margin: margin,
                styles: { fontSize: 9, cellPadding: 3 },
                headStyles: { fillColor: [185, 28, 28], textColor: 255, fontStyle: 'bold' },
                alternateRowStyles: { fillColor: [245, 245, 245] },
                columnStyles: {
                    0: { cellWidth: 50 },
                    1: { halign: 'right', cellWidth: 40 },
                    2: { halign: 'right', cellWidth: 40 },
                    3: { halign: 'right', cellWidth: 40 }
                }
            });
            
            yPosition = doc.lastAutoTable.finalY + 10;
            
            // Check if we need a new page
            if (yPosition > pageHeight - 40) {
                doc.addPage();
                yPosition = 15;
            }
            
            // Revenue Trends Chart (Canvas to Image)
            doc.setFontSize(12);
            doc.setTextColor(0, 0, 0);
            doc.text('Revenue Trends', margin, yPosition);
            yPosition += 8;
            
            const canvas = document.getElementById('revenueChart');
            if (canvas) {
                try {
                    const imgData = canvas.toDataURL('image/png');
                    const chartWidth = pageWidth - (2 * margin);
                    const chartHeight = 60;
                    doc.addImage(imgData, 'PNG', margin, yPosition, chartWidth, chartHeight);
                    yPosition += chartHeight + 10;
                } catch(e) {
                    console.error('Error capturing chart:', e);
                }
            }
            
            // Check if we need a new page
            if (yPosition > pageHeight - 60) {
                doc.addPage();
                yPosition = 15;
            }
            
            // Tenant Revenue Breakdown Table
            doc.setFontSize(12);
            doc.setTextColor(0, 0, 0);
            doc.text('Tenant Revenue Breakdown', margin, yPosition);
            yPosition += 8;
            
            // Prepare table data
            const rows = document.querySelectorAll('.tenant-row');
            let data = [];
            let rank = 1;
            rows.forEach(row => {
                if (row.style.display !== 'none') {
                    const cells = row.querySelectorAll('td');
                    data.push([
                        String(rank),
                        cells[1].textContent,
                        cells[2].textContent,
                        cells[3].textContent,
                        cells[4].textContent
                    ]);
                    rank++;
                }
            });
            
            if (data.length === 0) {
                doc.setFontSize(10);
                doc.setTextColor(150, 150, 150);
                doc.text('No data available for the selected filters', margin, yPosition);
            } else {
                doc.autoTable({
                    head: [["Rank", "Tenant Name", "Total Revenue", "Subscriptions", "Avg Value"]],
                    body: data,
                    startY: yPosition,
                    margin: margin,
                    styles: { fontSize: 9, cellPadding: 2 },
                    headStyles: { fillColor: [185, 28, 28], textColor: 255, fontStyle: 'bold' },
                    alternateRowStyles: { fillColor: [245, 245, 245] },
                    columnStyles: {
                        0: { halign: 'center', cellWidth: 18 },
                        1: { cellWidth: 60 },
                        2: { halign: 'right', cellWidth: 32 },
                        3: { halign: 'center', cellWidth: 32 },
                        4: { halign: 'right', cellWidth: 32 }
                    }
                });
            }
            
            // Footer
            const totalPages = doc.getNumberOfPages();
            for (let i = 1; i <= totalPages; i++) {
                doc.setPage(i);
                doc.setFontSize(8);
                doc.setTextColor(150, 150, 150);
                doc.text(`Page ${i} of ${totalPages}`, pageWidth - margin - 20, pageHeight - 5);
                doc.text('RapidRepair Sales Report', margin, pageHeight - 5);
            }
            
            doc.save('sales-report-' + new Date().toISOString().split('T')[0] + '.pdf');
        }
        
        // Helper function to get date range label
        function getDateRangeLabel(dateRange) {
            const labels = {
                '7': 'Last 7 Days',
                '30': 'Last 30 Days',
                '90': 'Last 90 Days',
                'ytd': 'Year to Date',
                'all': 'All Time'
            };
            return labels[dateRange] || 'Last 30 Days';
        }
        
        // Helper function to get tenant name
        function getTenantName(tenantID) {
            const select = document.querySelector('select[name="tenantFilter"]');
            if (select) {
                const option = select.querySelector(`option[value="${tenantID}"]`);
                return option ? option.textContent : 'Unknown';
            }
            return 'Unknown';
        }
        
        // Helper function to extract metric values from page
        function getMetricValue(metricType) {
            const metricsMap = {
                'revenue': 0,
                'revenuePrev': 1,
                'revenueChange': 2,
                'transactions': 3,
                'transactionsPrev': 4,
                'transactionsChange': 5,
                'avgValue': 6,
                'avgValuePrev': 7,
                'avgValueChange': 8
            };
            
            const cards = document.querySelectorAll('.bg-white.border.border-slate-200.p-6.rounded-lg.shadow-sm');
            let valueIndex = metricsMap[metricType];
            let cardIndex = Math.floor(valueIndex / 3);
            let valueType = valueIndex % 3;
            
            if (cards.length > cardIndex) {
                const card = cards[cardIndex];
                const heading = card.querySelector('h3');
                const description = card.querySelector('p:last-of-type');
                
                if (valueType === 0 && heading) return heading.textContent;
                if (valueType === 2 && description) return description.textContent;
            }
            return '—';
        }

    </script>
</body>

</html>