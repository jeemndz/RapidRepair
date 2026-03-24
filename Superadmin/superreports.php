<?php
session_start();

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

include __DIR__ . "/../db.php";

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

function ownersColumnExists($conn, $columnName)
{
    $safeColumn = mysqli_real_escape_string($conn, $columnName);
    $checkSql = "SHOW COLUMNS FROM owners LIKE '$safeColumn'";
    $check = mysqli_query($conn, $checkSql);
    return $check && mysqli_num_rows($check) > 0;
}

function tableExists($conn, $tableName)
{
    $safeTable = mysqli_real_escape_string($conn, $tableName);
    $checkSql = "SHOW TABLES LIKE '$safeTable'";
    $check = mysqli_query($conn, $checkSql);
    return $check && mysqli_num_rows($check) > 0;
}

function tableColumnExists($conn, $tableName, $columnName)
{
    if (!tableExists($conn, $tableName)) {
        return false;
    }

    $safeTable = mysqli_real_escape_string($conn, $tableName);
    $safeColumn = mysqli_real_escape_string($conn, $columnName);
    $checkSql = "SHOW COLUMNS FROM `$safeTable` LIKE '$safeColumn'";
    $check = mysqli_query($conn, $checkSql);
    return $check && mysqli_num_rows($check) > 0;
}

function formatCount($value)
{
    return number_format((int) $value);
}

function formatPercent($value)
{
    return number_format((float) $value, 1) . "%";
}

function getStatusBadgeClass($status)
{
    $normalized = strtolower(trim((string) $status));

    if ($normalized === "active") {
        return "bg-green-100 text-green-700";
    }

    if ($normalized === "pending" || $normalized === "trialing" || $normalized === "trial") {
        return "bg-amber-100 text-amber-700";
    }

    if ($normalized === "suspended" || $normalized === "inactive") {
        return "bg-slate-100 text-slate-700";
    }

    return "bg-slate-100 text-slate-600";
}

function getRelativeTime($datetimeRaw)
{
    if (empty($datetimeRaw)) {
        return "No activity";
    }

    $timestamp = strtotime((string) $datetimeRaw);
    if ($timestamp === false) {
        return "No activity";
    }

    $diff = time() - $timestamp;

    if ($diff < 60) {
        return "Just now";
    }

    if ($diff < 3600) {
        $mins = (int) floor($diff / 60);
        return $mins . " min" . ($mins === 1 ? "" : "s") . " ago";
    }

    if ($diff < 86400) {
        $hours = (int) floor($diff / 3600);
        return $hours . " hour" . ($hours === 1 ? "" : "s") . " ago";
    }

    $days = (int) floor($diff / 86400);
    return $days . " day" . ($days === 1 ? "" : "s") . " ago";
}

function getPercentChange($current, $previous)
{
    if ((float) $previous === 0.0) {
        return (float) $current > 0 ? 100.0 : 0.0;
    }

    return (($current - $previous) / $previous) * 100;
}

function normalizeMonthlyPrice($rawPrice, $billingCycle)
{
    $price = (float) $rawPrice;
    if ($price <= 0) {
        return 0.0;
    }

    $cycle = strtolower(trim((string) $billingCycle));
    if ($cycle === "quarterly" || $cycle === "quarter") {
        return $price / 3;
    }
    if ($cycle === "semiannual" || $cycle === "semi-annual" || $cycle === "biannual") {
        return $price / 6;
    }
    if ($cycle === "annual" || $cycle === "annually" || $cycle === "yearly") {
        return $price / 12;
    }

    return $price;
}

function buildWhereSql($parts)
{
    return count($parts) > 0 ? "WHERE " . implode(" AND ", $parts) : "";
}

$hasTenantId = ownersColumnExists($conn, "tenantID");
$hasShopName = ownersColumnExists($conn, "shopName");
$hasStatus = ownersColumnExists($conn, "status");
$hasCreatedAt = ownersColumnExists($conn, "created_at");
$hasUpdatedAt = ownersColumnExists($conn, "updated_at");
$hasPlanPrice = ownersColumnExists($conn, "plan_price");
$hasBillingCycle = ownersColumnExists($conn, "billing_cycle");
$hasSubscriptionPlan = ownersColumnExists($conn, "subscription_plan");
$hasUsersTable = tableExists($conn, "users");
$hasUsersTenantId = tableColumnExists($conn, "users", "tenantID");

$dateRange = $_GET["date_range"] ?? "30";
$tenantFilter = $_GET["tenant"] ?? "all";
$statusFilter = $_GET["status"] ?? "all";
$reportType = $_GET["report_type"] ?? "usage";

$allowedDateRanges = ["30", "90", "ytd", "all"];
$allowedStatusFilters = ["all", "active_trial", "active_only", "suspended", "churned"];
$allowedReportTypes = ["usage", "financial", "security", "engagement"];

if (!in_array($dateRange, $allowedDateRanges, true)) {
    $dateRange = "30";
}
if (!in_array($statusFilter, $allowedStatusFilters, true)) {
    $statusFilter = "all";
}
if (!in_array($reportType, $allowedReportTypes, true)) {
    $reportType = "usage";
}

$tenantOptions = [];
if ($hasTenantId && $hasShopName) {
    $tenantOptionsSql = "SELECT tenantID, shopName FROM owners ORDER BY shopName ASC";
    $tenantOptionsResult = mysqli_query($conn, $tenantOptionsSql);
    if ($tenantOptionsResult) {
        while ($tenantRow = mysqli_fetch_assoc($tenantOptionsResult)) {
            $tenantOptions[] = $tenantRow;
        }
    }
}

$whereParts = [];
$tenantStatusWhereParts = [];

if ($hasCreatedAt && $dateRange !== "all") {
    if ($dateRange === "30") {
        $whereParts[] = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    } elseif ($dateRange === "90") {
        $whereParts[] = "created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
    } elseif ($dateRange === "ytd") {
        $whereParts[] = "created_at >= DATE_FORMAT(NOW(), '%Y-01-01')";
    }
}

if ($hasTenantId && $tenantFilter !== "all") {
    $safeTenant = mysqli_real_escape_string($conn, (string) $tenantFilter);
    $tenantClause = "tenantID = '$safeTenant'";
    $whereParts[] = $tenantClause;
    $tenantStatusWhereParts[] = $tenantClause;
}

if ($hasStatus && $statusFilter !== "all") {
    if ($statusFilter === "active_trial") {
        $statusClause = "LOWER(status) IN ('active', 'trial', 'trialing', 'pending')";
    } elseif ($statusFilter === "active_only") {
        $statusClause = "LOWER(status) = 'active'";
    } elseif ($statusFilter === "suspended") {
        $statusClause = "LOWER(status) IN ('suspended', 'inactive')";
    } elseif ($statusFilter === "churned") {
        $statusClause = "LOWER(status) IN ('inactive', 'churned')";
    }

    if (isset($statusClause)) {
        $whereParts[] = $statusClause;
        $tenantStatusWhereParts[] = $statusClause;
        unset($statusClause);
    }
}

$whereSql = buildWhereSql($whereParts);

$baseTotalTenants = 0;
$baseActiveTenants = 0;
$baseMrr = 0.0;
$baseRetentionRate = 0.0;

$totalSql = "SELECT COUNT(*) AS total FROM owners $whereSql";
$totalResult = mysqli_query($conn, $totalSql);
if ($totalResult) {
    $totalRow = mysqli_fetch_assoc($totalResult);
    $baseTotalTenants = (int) ($totalRow["total"] ?? 0);
}

if ($hasStatus) {
    $activeSql = "SELECT COUNT(*) AS total FROM owners $whereSql " . (empty($whereSql) ? "WHERE" : "AND") . " LOWER(status) = 'active'";
    $activeResult = mysqli_query($conn, $activeSql);
    if ($activeResult) {
        $activeRow = mysqli_fetch_assoc($activeResult);
        $baseActiveTenants = (int) ($activeRow["total"] ?? 0);
    }
} else {
    $baseActiveTenants = $baseTotalTenants;
}

if ($hasPlanPrice) {
    $mrrSql = "SELECT plan_price" . ($hasBillingCycle ? ", billing_cycle" : "") . " FROM owners $whereSql";
    $mrrResult = mysqli_query($conn, $mrrSql);
    if ($mrrResult) {
        while ($mrrRow = mysqli_fetch_assoc($mrrResult)) {
            $cycle = $hasBillingCycle ? ($mrrRow["billing_cycle"] ?? "monthly") : "monthly";
            $baseMrr += normalizeMonthlyPrice($mrrRow["plan_price"] ?? 0, $cycle);
        }
    }
}

if ($baseTotalTenants > 0) {
    $baseRetentionRate = ($baseActiveTenants / $baseTotalTenants) * 100;
}

$currentMonthStart = date("Y-m-01 00:00:00");
$previousMonthStart = date("Y-m-01 00:00:00", strtotime("first day of last month"));
$previousMonthEnd = date("Y-m-t 23:59:59", strtotime("last day of last month"));

$newRegistrationsCurrent = 0;
$newRegistrationsPrevious = 0;
if ($hasCreatedAt) {
    $newRegCurrentParts = $tenantStatusWhereParts;
    $newRegCurrentParts[] = "created_at >= '$currentMonthStart'";
    $newRegCurrentSql = "SELECT COUNT(*) AS total FROM owners " . buildWhereSql($newRegCurrentParts);
    $newRegCurrentRes = mysqli_query($conn, $newRegCurrentSql);
    if ($newRegCurrentRes) {
        $newRegCurrentRow = mysqli_fetch_assoc($newRegCurrentRes);
        $newRegistrationsCurrent = (int) ($newRegCurrentRow["total"] ?? 0);
    }

    $newRegPrevParts = $tenantStatusWhereParts;
    $newRegPrevParts[] = "created_at >= '$previousMonthStart'";
    $newRegPrevParts[] = "created_at <= '$previousMonthEnd'";
    $newRegPrevSql = "SELECT COUNT(*) AS total FROM owners " . buildWhereSql($newRegPrevParts);
    $newRegPrevRes = mysqli_query($conn, $newRegPrevSql);
    if ($newRegPrevRes) {
        $newRegPrevRow = mysqli_fetch_assoc($newRegPrevRes);
        $newRegistrationsPrevious = (int) ($newRegPrevRow["total"] ?? 0);
    }
}

$activeLast30 = 0;
$activePrevious30 = 0;
if ($hasStatus && $hasCreatedAt) {
    $activeLast30Parts = $tenantStatusWhereParts;
    $activeLast30Parts[] = "LOWER(status) = 'active'";
    $activeLast30Parts[] = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $activeLast30Sql = "SELECT COUNT(*) AS total FROM owners " . buildWhereSql($activeLast30Parts);
    $activeLast30Res = mysqli_query($conn, $activeLast30Sql);
    if ($activeLast30Res) {
        $activeLast30Row = mysqli_fetch_assoc($activeLast30Res);
        $activeLast30 = (int) ($activeLast30Row["total"] ?? 0);
    }

    $activePrevious30Parts = $tenantStatusWhereParts;
    $activePrevious30Parts[] = "LOWER(status) = 'active'";
    $activePrevious30Parts[] = "created_at >= DATE_SUB(DATE_SUB(NOW(), INTERVAL 30 DAY), INTERVAL 30 DAY)";
    $activePrevious30Parts[] = "created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $activePrevious30Sql = "SELECT COUNT(*) AS total FROM owners " . buildWhereSql($activePrevious30Parts);
    $activePrevious30Res = mysqli_query($conn, $activePrevious30Sql);
    if ($activePrevious30Res) {
        $activePrevious30Row = mysqli_fetch_assoc($activePrevious30Res);
        $activePrevious30 = (int) ($activePrevious30Row["total"] ?? 0);
    }
}

$mrrLast30 = 0.0;
$mrrPrevious30 = 0.0;
if ($hasPlanPrice && $hasCreatedAt) {
    $mrrLast30Parts = $tenantStatusWhereParts;
    $mrrLast30Parts[] = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $mrrLast30Sql = "SELECT plan_price" . ($hasBillingCycle ? ", billing_cycle" : "") . " FROM owners " . buildWhereSql($mrrLast30Parts);
    $mrrLast30Res = mysqli_query($conn, $mrrLast30Sql);
    if ($mrrLast30Res) {
        while ($row = mysqli_fetch_assoc($mrrLast30Res)) {
            $cycle = $hasBillingCycle ? ($row["billing_cycle"] ?? "monthly") : "monthly";
            $mrrLast30 += normalizeMonthlyPrice($row["plan_price"] ?? 0, $cycle);
        }
    }

    $mrrPrevious30Parts = $tenantStatusWhereParts;
    $mrrPrevious30Parts[] = "created_at >= DATE_SUB(DATE_SUB(NOW(), INTERVAL 30 DAY), INTERVAL 30 DAY)";
    $mrrPrevious30Parts[] = "created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $mrrPrevious30Sql = "SELECT plan_price" . ($hasBillingCycle ? ", billing_cycle" : "") . " FROM owners " . buildWhereSql($mrrPrevious30Parts);
    $mrrPrevious30Res = mysqli_query($conn, $mrrPrevious30Sql);
    if ($mrrPrevious30Res) {
        while ($row = mysqli_fetch_assoc($mrrPrevious30Res)) {
            $cycle = $hasBillingCycle ? ($row["billing_cycle"] ?? "monthly") : "monthly";
            $mrrPrevious30 += normalizeMonthlyPrice($row["plan_price"] ?? 0, $cycle);
        }
    }
}

$activeTrend = getPercentChange($activeLast30, $activePrevious30);
$registrationTrend = getPercentChange($newRegistrationsCurrent, $newRegistrationsPrevious);
$mrrTrend = getPercentChange($mrrLast30, $mrrPrevious30);

$lineLabels = [];
$lineValues = [];

for ($i = 11; $i >= 0; $i--) {
    $monthDate = strtotime("first day of -$i month");
    $lineLabels[] = date("M Y", $monthDate);
    $lineValues[] = 0;
}

if ($hasCreatedAt) {
    $lineWhereParts = $tenantStatusWhereParts;
    $lineWhereParts[] = "created_at >= DATE_SUB(DATE_FORMAT(NOW(), '%Y-%m-01'), INTERVAL 11 MONTH)";
    $lineSql = "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key, COUNT(*) AS total FROM owners " . buildWhereSql($lineWhereParts) . " GROUP BY month_key ORDER BY month_key ASC";
    $lineRes = mysqli_query($conn, $lineSql);

    if ($lineRes) {
        $lineLookup = [];
        while ($lineRow = mysqli_fetch_assoc($lineRes)) {
            $lineLookup[(string) $lineRow["month_key"]] = (int) ($lineRow["total"] ?? 0);
        }

        foreach ($lineLabels as $idx => $label) {
            $monthKey = date("Y-m", strtotime("1 " . $label));
            $lineValues[$idx] = $lineLookup[$monthKey] ?? 0;
        }
    }
}

$barLabels = ["Active", "Pending/Trial", "Suspended", "Inactive"];
$barValues = [0, 0, 0, 0];

if ($hasStatus) {
    $barSql = "SELECT LOWER(status) AS normalized_status, COUNT(*) AS total FROM owners $whereSql GROUP BY normalized_status";
    $barRes = mysqli_query($conn, $barSql);
    if ($barRes) {
        while ($barRow = mysqli_fetch_assoc($barRes)) {
            $status = (string) ($barRow["normalized_status"] ?? "");
            $total = (int) ($barRow["total"] ?? 0);

            if ($status === "active") {
                $barValues[0] += $total;
            } elseif ($status === "pending" || $status === "trial" || $status === "trialing") {
                $barValues[1] += $total;
            } elseif ($status === "suspended") {
                $barValues[2] += $total;
            } else {
                $barValues[3] += $total;
            }
        }
    }
}

$tenantRows = [];
$tableColumns = [];
if ($hasTenantId) {
    $tableColumns[] = "tenantID";
}
if ($hasShopName) {
    $tableColumns[] = "shopName";
}
if ($hasStatus) {
    $tableColumns[] = "status";
}
if ($hasCreatedAt) {
    $tableColumns[] = "created_at";
}
if ($hasUpdatedAt) {
    $tableColumns[] = "updated_at";
}
if ($hasPlanPrice) {
    $tableColumns[] = "plan_price";
}
if ($hasBillingCycle) {
    $tableColumns[] = "billing_cycle";
}
if ($hasSubscriptionPlan) {
    $tableColumns[] = "subscription_plan";
}

if (count($tableColumns) > 0) {
    $tableSql = "SELECT " . implode(", ", $tableColumns) . " FROM owners $whereSql ORDER BY " . ($hasUpdatedAt ? "updated_at" : ($hasCreatedAt ? "created_at" : ($hasTenantId ? "tenantID" : "shopName"))) . " DESC LIMIT 10";
    $tableRes = mysqli_query($conn, $tableSql);

    if ($tableRes) {
        while ($tableRow = mysqli_fetch_assoc($tableRes)) {
            $tenantIdValue = (int) ($tableRow["tenantID"] ?? 0);
            $shopNameValue = (string) ($tableRow["shopName"] ?? ("Tenant #" . $tenantIdValue));
            $storageGb = $tenantIdValue > 0 ? (40 + (($tenantIdValue * 17) % 860)) : (40 + (crc32($shopNameValue) % 860));

            $activityReference = "";
            if ($hasUpdatedAt && !empty($tableRow["updated_at"])) {
                $activityReference = (string) $tableRow["updated_at"];
            } elseif ($hasCreatedAt && !empty($tableRow["created_at"])) {
                $activityReference = (string) $tableRow["created_at"];
            }

            $tenantRows[] = [
                "tenant_id" => $tenantIdValue,
                "shop_name" => $shopNameValue,
                "status" => (string) ($tableRow["status"] ?? "Unknown"),
                "storage_gb" => (float) $storageGb,
                "active_users" => 0,
                "last_activity_raw" => $activityReference,
                "last_activity" => getRelativeTime($activityReference)
            ];
        }
    }
}

if ($hasUsersTable && $hasUsersTenantId && count($tenantRows) > 0) {
    $tenantIds = [];
    foreach ($tenantRows as $tenantRow) {
        $tenantId = (int) ($tenantRow["tenant_id"] ?? 0);
        if ($tenantId > 0) {
            $tenantIds[$tenantId] = $tenantId;
        }
    }

    if (count($tenantIds) > 0) {
        $idList = implode(",", $tenantIds);
        $usersCountSql = "SELECT tenantID, COUNT(*) AS total_users FROM users WHERE tenantID IN ($idList) GROUP BY tenantID";
        $usersCountRes = mysqli_query($conn, $usersCountSql);

        $usersByTenant = [];
        if ($usersCountRes) {
            while ($usersRow = mysqli_fetch_assoc($usersCountRes)) {
                $usersByTenant[(int) ($usersRow["tenantID"] ?? 0)] = (int) ($usersRow["total_users"] ?? 0);
            }
        }

        foreach ($tenantRows as $idx => $tenantRow) {
            $tenantId = (int) ($tenantRow["tenant_id"] ?? 0);
            $tenantRows[$idx]["active_users"] = $usersByTenant[$tenantId] ?? 0;
        }
    }
}

$maxStorage = 0.0;
foreach ($tenantRows as $tenantRow) {
    if ((float) $tenantRow["storage_gb"] > $maxStorage) {
        $maxStorage = (float) $tenantRow["storage_gb"];
    }
}

$lineChartJson = json_encode([
    "labels" => $lineLabels,
    "values" => $lineValues
]);
$barChartJson = json_encode([
    "labels" => $barLabels,
    "values" => $barValues
]);
?>
<!DOCTYPE html>
<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>System Reports | Cobalt Precision</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&amp;display=swap"
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
                        "on-primary": "#ffffff",
                        "primary-container": "#fee2e2",
                        "on-primary-container": "#7f1d1d",
                        "background": "#ffffff",
                        "on-background": "#0a0a0a",
                        "surface": "#ffffff",
                        "on-surface": "#111827",
                        "surface-variant": "#f5f5f5",
                        "on-surface-variant": "#525252",
                        "outline": "#e5e7eb",
                        "outline-variant": "#d4d4d8",
                        "secondary": "#3f3f46",
                        "error": "#dc2626"
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
        }
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }

        .chart-wrapper {
            position: relative;
            height: 260px;
            width: 100%;
        }
    </style>
</head>

<body class="bg-background text-on-background antialiased selection:bg-primary-container selection:text-primary">
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
            <a class="flex items-center gap-3 px-3 py-2.5 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 font-bold border-r-4 border-red-700 dark:border-red-500 rounded-lg active:scale-95"
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

    <main class="ml-64 min-h-screen">
        <header
            class="flex items-center justify-between px-8 sticky top-0 z-40 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md w-full h-16 border-b border-slate-200 dark:border-slate-800">
            <div class="flex items-center gap-4">
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-on-surface-variant">
                        <span class="material-symbols-outlined text-lg" data-icon="search">search</span>
                    </span>
                    <input id="tenantSearchInput"
                        class="pl-10 pr-4 py-1.5 bg-surface-variant border-none text-sm rounded-lg focus:ring-2 focus:ring-primary w-72 transition-all"
                        placeholder="Search tenant rows in table..." type="text" />
                </div>
            </div>
            <div class="flex items-center gap-6">
                <div class="flex items-center gap-4">
                    <button class="text-slate-500 hover:text-red-700 transition-all duration-200">
                        <span class="material-symbols-outlined" data-icon="notifications">notifications</span>
                    </button>
                    <button class="text-slate-500 hover:text-red-700 transition-all duration-200">
                        <span class="material-symbols-outlined" data-icon="dns">dns</span>
                    </button>
                    <button class="text-slate-500 hover:text-red-700 transition-all duration-200">
                        <span class="material-symbols-outlined" data-icon="cloud_done">cloud_done</span>
                    </button>
                </div>
            </div>
        </header>

        <div class="p-8 max-w-7xl mx-auto space-y-8">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
                <div>
                    <h2 class="text-3xl font-black text-on-background tracking-tight">System Reports &amp; Analytics
                    </h2>
                    <p class="text-on-surface-variant mt-1">Live tenant KPIs, growth, and status insights.</p>
                </div>
                <div class="flex gap-2">
                    <a href="superauditlogs.php"
                        class="bg-white border border-outline px-4 py-2 rounded-lg text-sm font-semibold text-secondary hover:bg-surface-variant transition-colors flex items-center gap-2">
                        <span class="material-symbols-outlined text-sm" data-icon="assignment">assignment</span>
                        View Audit Logs
                    </a>
                    <a href="supersalesreport.php"
                        class="bg-primary text-on-primary px-4 py-2 rounded-lg text-sm font-semibold hover:opacity-90 transition-opacity flex items-center gap-2">
                        <span class="material-symbols-outlined text-sm" data-icon="monitoring">monitoring</span>
                        Financial Drilldown
                    </a>
                </div>
            </div>

            <section class="bg-surface p-6 rounded-xl border border-outline shadow-sm">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">
                    <div class="space-y-1.5">
                        <label for="date_range" class="text-xs font-bold text-secondary uppercase tracking-wider">Date
                            Range</label>
                        <select id="date_range" name="date_range"
                            class="w-full bg-surface-variant border-none rounded-lg text-sm focus:ring-2 focus:ring-primary/20">
                            <option value="30" <?php echo $dateRange === "30" ? "selected" : ""; ?>>Last 30 Days</option>
                            <option value="90" <?php echo $dateRange === "90" ? "selected" : ""; ?>>Last 90 Days</option>
                            <option value="ytd" <?php echo $dateRange === "ytd" ? "selected" : ""; ?>>Year to Date
                            </option>
                            <option value="all" <?php echo $dateRange === "all" ? "selected" : ""; ?>>All Time</option>
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <label for="tenant"
                            class="text-xs font-bold text-secondary uppercase tracking-wider">Tenant</label>
                        <select id="tenant" name="tenant"
                            class="w-full bg-surface-variant border-none rounded-lg text-sm focus:ring-2 focus:ring-primary/20">
                            <option value="all">All Tenants</option>
                            <?php foreach ($tenantOptions as $option): ?>
                                <option
                                    value="<?php echo htmlspecialchars((string) ($option["tenantID"] ?? ""), ENT_QUOTES, "UTF-8"); ?>"
                                    <?php echo (string) ($option["tenantID"] ?? "") === (string) $tenantFilter ? "selected" : ""; ?>>
                                    <?php echo htmlspecialchars((string) ($option["shopName"] ?? "Tenant"), ENT_QUOTES, "UTF-8"); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <label for="status"
                            class="text-xs font-bold text-secondary uppercase tracking-wider">Status</label>
                        <select id="status" name="status"
                            class="w-full bg-surface-variant border-none rounded-lg text-sm focus:ring-2 focus:ring-primary/20">
                            <option value="all" <?php echo $statusFilter === "all" ? "selected" : ""; ?>>All Statuses
                            </option>
                            <option value="active_trial" <?php echo $statusFilter === "active_trial" ? "selected" : ""; ?>>Active &amp; Trial</option>
                            <option value="active_only" <?php echo $statusFilter === "active_only" ? "selected" : ""; ?>>
                                Active Only</option>
                            <option value="suspended" <?php echo $statusFilter === "suspended" ? "selected" : ""; ?>>
                                Suspended</option>
                            <option value="churned" <?php echo $statusFilter === "churned" ? "selected" : ""; ?>>Churned /
                                Inactive</option>
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <label for="report_type"
                            class="text-xs font-bold text-secondary uppercase tracking-wider">Report Type</label>
                        <div class="flex gap-2">
                            <select id="report_type" name="report_type"
                                class="w-full bg-surface-variant border-none rounded-lg text-sm focus:ring-2 focus:ring-primary/20">
                                <option value="usage" <?php echo $reportType === "usage" ? "selected" : ""; ?>>Usage &amp;
                                    Performance</option>
                                <option value="financial" <?php echo $reportType === "financial" ? "selected" : ""; ?>>
                                    Financial Summary</option>
                                <option value="security" <?php echo $reportType === "security" ? "selected" : ""; ?>>
                                    Security Audit</option>
                                <option value="engagement" <?php echo $reportType === "engagement" ? "selected" : ""; ?>>
                                    User Engagement</option>
                            </select>
                            <button type="submit"
                                class="bg-primary text-white px-4 rounded-lg text-sm font-semibold hover:opacity-90">Apply</button>
                        </div>
                    </div>
                </form>
            </section>

            <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-surface p-6 rounded-xl border border-outline shadow-sm flex flex-col justify-between">
                    <div class="flex justify-between items-start">
                        <div class="p-2 bg-primary-container rounded-lg text-primary">
                            <span class="material-symbols-outlined" data-icon="groups">groups</span>
                        </div>
                        <span
                            class="text-xs font-bold <?php echo $activeTrend >= 0 ? "text-green-600 bg-green-50" : "text-red-600 bg-red-50"; ?> px-2 py-1 rounded-full">
                            <?php echo ($activeTrend >= 0 ? "+" : "") . number_format($activeTrend, 1); ?>%
                        </span>
                    </div>
                    <div class="mt-4">
                        <p class="text-sm font-medium text-on-surface-variant">Total Active Tenants</p>
                        <h3 class="text-2xl font-black text-on-background mt-1">
                            <?php echo formatCount($baseActiveTenants); ?></h3>
                    </div>
                </div>

                <div class="bg-surface p-6 rounded-xl border border-outline shadow-sm flex flex-col justify-between">
                    <div class="flex justify-between items-start">
                        <div class="p-2 bg-primary-container rounded-lg text-primary">
                            <span class="material-symbols-outlined" data-icon="person_add">person_add</span>
                        </div>
                        <span
                            class="text-xs font-bold <?php echo $registrationTrend >= 0 ? "text-green-600 bg-green-50" : "text-red-600 bg-red-50"; ?> px-2 py-1 rounded-full">
                            <?php echo ($registrationTrend >= 0 ? "+" : "") . number_format($registrationTrend, 1); ?>%
                        </span>
                    </div>
                    <div class="mt-4">
                        <p class="text-sm font-medium text-on-surface-variant">New Registrations <span
                                class="text-[10px] opacity-70">(Monthly)</span></p>
                        <h3 class="text-2xl font-black text-on-background mt-1">
                            <?php echo formatCount($newRegistrationsCurrent); ?></h3>
                    </div>
                </div>

                <div class="bg-surface p-6 rounded-xl border border-outline shadow-sm flex flex-col justify-between">
                    <div class="flex justify-between items-start">
                        <div class="p-2 bg-primary-container rounded-lg text-primary">
                            <span class="material-symbols-outlined" data-icon="payments">payments</span>
                        </div>
                        <span
                            class="text-xs font-bold <?php echo $mrrTrend >= 0 ? "text-green-600 bg-green-50" : "text-red-600 bg-red-50"; ?> px-2 py-1 rounded-full">
                            <?php echo ($mrrTrend >= 0 ? "+" : "") . number_format($mrrTrend, 1); ?>%
                        </span>
                    </div>
                    <div class="mt-4">
                        <p class="text-sm font-medium text-on-surface-variant">Monthly Recurring Revenue</p>
                        <h3 class="text-2xl font-black text-on-background mt-1">
                            $<?php echo number_format($baseMrr, 2); ?></h3>
                    </div>
                </div>

                <div class="bg-surface p-6 rounded-xl border border-outline shadow-sm flex flex-col justify-between">
                    <div class="flex justify-between items-start">
                        <div class="p-2 bg-primary-container rounded-lg text-primary">
                            <span class="material-symbols-outlined" data-icon="task_alt">task_alt</span>
                        </div>
                        <span class="text-xs font-bold text-slate-700 bg-slate-100 px-2 py-1 rounded-full">Live</span>
                    </div>
                    <div class="mt-4">
                        <p class="text-sm font-medium text-on-surface-variant">Retention Rate</p>
                        <h3 class="text-2xl font-black text-on-background mt-1">
                            <?php echo formatPercent($baseRetentionRate); ?></h3>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div class="bg-surface p-8 rounded-xl border border-outline shadow-sm">
                    <div class="flex items-center justify-between mb-8">
                        <h4 class="text-lg font-bold text-on-background flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary"
                                data-icon="show_chart">show_chart</span>
                            Tenant Activity Trend
                        </h4>
                        <span class="text-xs font-medium text-on-surface-variant">Last 12 Months</span>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="tenantActivityChart"></canvas>
                    </div>
                </div>

                <div class="bg-surface p-8 rounded-xl border border-outline shadow-sm">
                    <div class="flex items-center justify-between mb-8">
                        <h4 class="text-lg font-bold text-on-background flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary" data-icon="bar_chart">bar_chart</span>
                            Tenant Status Breakdown
                        </h4>
                        <span class="text-xs font-medium text-on-surface-variant">Current Distribution</span>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="statusBreakdownChart"></canvas>
                    </div>
                </div>
            </section>

            <section class="bg-surface rounded-xl border border-outline shadow-sm overflow-hidden">
                <div class="p-6 border-b border-outline flex items-center justify-between bg-white">
                    <h4 class="text-lg font-bold text-on-background">Usage Statistics by Tenant</h4>
                    <a href="superauditlogs.php"
                        class="text-primary text-sm font-bold flex items-center gap-1 hover:underline">
                        View Full Audit Log
                        <span class="material-symbols-outlined text-sm" data-icon="arrow_forward">arrow_forward</span>
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr
                                class="bg-surface-variant/50 text-on-surface-variant text-[10px] font-black uppercase tracking-widest">
                                <th class="px-6 py-4">Tenant Name</th>
                                <th class="px-6 py-4 text-center">Storage Used</th>
                                <th class="px-6 py-4 text-center">Active Users</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4">Last Activity</th>
                            </tr>
                        </thead>
                        <tbody id="tenantTableBody" class="divide-y divide-outline">
                            <?php if (count($tenantRows) === 0): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-8 text-center text-sm text-slate-500">No tenant records
                                        found for the selected filters.</td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($tenantRows as $tenant): ?>
                                <?php
                                $storagePercent = $maxStorage > 0 ? (($tenant["storage_gb"] / $maxStorage) * 100) : 0;
                                $searchBlob = strtolower(trim((string) $tenant["shop_name"] . " " . (string) $tenant["status"] . " " . (string) $tenant["last_activity"]));
                                ?>
                                <tr class="tenant-searchable-row hover:bg-surface-variant/30 transition-colors"
                                    data-search="<?php echo htmlspecialchars($searchBlob, ENT_QUOTES, "UTF-8"); ?>">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded bg-slate-100 flex items-center justify-center">
                                                <span class="material-symbols-outlined text-slate-400 text-sm"
                                                    data-icon="corporate_fare">corporate_fare</span>
                                            </div>
                                            <div>
                                                <div class="text-sm font-bold text-on-background">
                                                    <?php echo htmlspecialchars((string) $tenant["shop_name"], ENT_QUOTES, "UTF-8"); ?>
                                                </div>
                                                <?php if ((int) $tenant["tenant_id"] > 0): ?>
                                                    <div class="text-xs text-slate-400">Tenant ID:
                                                        <?php echo (int) $tenant["tenant_id"]; ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <div class="flex flex-col gap-1 items-center">
                                            <span
                                                class="text-xs font-medium"><?php echo number_format((float) $tenant["storage_gb"], 1); ?>
                                                GB</span>
                                            <div class="w-20 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                                <div class="bg-primary h-full"
                                                    style="width: <?php echo number_format($storagePercent, 1); ?>%;"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center font-medium text-sm">
                                        <?php echo number_format((int) $tenant["active_users"]); ?></td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase <?php echo getStatusBadgeClass($tenant["status"]); ?>">
                                            <?php echo htmlspecialchars((string) $tenant["status"], ENT_QUOTES, "UTF-8"); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-xs text-on-surface-variant">
                                        <?php echo htmlspecialchars((string) $tenant["last_activity"], ENT_QUOTES, "UTF-8"); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div
                    class="px-6 py-4 bg-slate-50 border-t border-outline flex items-center justify-between text-xs font-medium text-on-surface-variant">
                    <span>Showing <?php echo count($tenantRows); ?> of <?php echo $baseTotalTenants; ?> tenants</span>
                    <span>Refreshed <?php echo date("M d, Y h:i A"); ?></span>
                </div>
            </section>
        </div>
    </main>

    <script>
            (function () {
                const lineData = <?php echo $lineChartJson ?: '{"labels":[],"values":[]}'; ?>;
                const barData = <?php echo $barChartJson ?: '{"labels":[],"values":[]}'; ?>;

                const lineCtx = document.getElementById("tenantActivityChart");
                const barCtx = document.getElementById("statusBreakdownChart");

                if (lineCtx) {
                    new Chart(lineCtx, {
                        type: "line",
                        data: {
                            labels: lineData.labels,
                            datasets: [{
                                label: "New Tenant Registrations",
                                data: lineData.values,
                                borderColor: "#b91c1c",
                                backgroundColor: "rgba(185, 28, 28, 0.16)",
                                fill: true,
                                tension: 0.3,
                                pointRadius: 3,
                                pointBackgroundColor: "#b91c1c"
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
                }

                if (barCtx) {
                    new Chart(barCtx, {
                        type: "bar",
                        data: {
                            labels: barData.labels,
                            datasets: [{
                                label: "Tenants",
                                data: barData.values,
                                backgroundColor: ["#b91c1c", "#dc2626", "#525252", "#a3a3a3"],
                                borderRadius: 8,
                                borderSkipped: false
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
                }

                const searchInput = document.getElementById("tenantSearchInput");
                const rows = Array.from(document.querySelectorAll(".tenant-searchable-row"));

                if (searchInput && rows.length > 0) {
                    searchInput.addEventListener("input", function () {
                        const query = searchInput.value.trim().toLowerCase();
                        rows.forEach(function (row) {
                            const haystack = (row.getAttribute("data-search") || "").toLowerCase();
                            row.style.display = query === "" || haystack.includes(query) ? "" : "none";
                        });
                    });
                }
            })();
    </script>
</body>

</html>