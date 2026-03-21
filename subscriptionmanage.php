<?php
session_start();

if (!isset($_SESSION['superadmin_id'])) {
    header("Location: superaddlogin.php");
    exit();
}

include "db.php";

function subscriptionsColumnExists($conn, $columnName)
{
    $safeColumn = mysqli_real_escape_string($conn, $columnName);
    $checkSql = "SHOW COLUMNS FROM subscription_plans LIKE '$safeColumn'";
    $check = mysqli_query($conn, $checkSql);
    return $check && mysqli_num_rows($check) > 0;
}

function generatePlanCode($conn, $planName)
{
    $code = strtolower(trim($planName));
    $code = preg_replace('/[^a-z0-9]+/', '-', $code);
    $code = trim($code, '-');
    if ($code === '') {
        $code = 'plan';
    }

    $originalCode = $code;
    $counter = 1;

    while (true) {
        $safeCode = mysqli_real_escape_string($conn, $code);
        $exists = mysqli_query($conn, "SELECT plan_id FROM subscription_plans WHERE plan_code='$safeCode' LIMIT 1");
        if (!$exists || mysqli_num_rows($exists) === 0) {
            break;
        }
        $code = $originalCode . '-' . $counter;
        $counter++;
    }

    return $code;
}

function getPlanFeaturesJsonFromPost()
{
    $planFeaturesRaw = $_POST['plan_features'] ?? '[]';
    $decodedFeatures = json_decode($planFeaturesRaw, true);
    if (!is_array($decodedFeatures)) {
        $decodedFeatures = [];
    }

    $cleanFeatures = [];
    foreach ($decodedFeatures as $feature) {
        $featureText = trim((string) $feature);
        if ($featureText !== '') {
            $cleanFeatures[] = $featureText;
        }
    }

    return json_encode(array_values($cleanFeatures));
}

$hasPlanIdColumn = subscriptionsColumnExists($conn, 'plan_id');
$hasPlanCodeColumn = subscriptionsColumnExists($conn, 'plan_code');
$hasPlanNameColumn = subscriptionsColumnExists($conn, 'plan_name');
$hasMonthlyPriceColumn = subscriptionsColumnExists($conn, 'monthly_price');
$hasPlanFeaturesColumn = subscriptionsColumnExists($conn, 'plan_features');
$hasIsActiveColumn = subscriptionsColumnExists($conn, 'is_active');
$hasCreatedAtColumn = subscriptionsColumnExists($conn, 'created_at');

if (isset($_POST['togglePlanStatus'])) {
    $statusValue = (int) ($_POST['status_value'] ?? 0);
    $statusValue = $statusValue === 1 ? 1 : 0;

    if (!$hasIsActiveColumn) {
        header("Location: subscriptionmanage.php?plan_notice=schema_error");
        exit();
    }

    $whereClause = '';
    if ($hasPlanIdColumn && isset($_POST['plan_id']) && $_POST['plan_id'] !== '') {
        $planId = (int) $_POST['plan_id'];
        $whereClause = "plan_id = '$planId'";
    } elseif ($hasPlanCodeColumn && isset($_POST['plan_code']) && $_POST['plan_code'] !== '') {
        $planCode = mysqli_real_escape_string($conn, trim((string) $_POST['plan_code']));
        $whereClause = "plan_code = '$planCode'";
    }

    if ($whereClause === '') {
        header("Location: subscriptionmanage.php?plan_notice=failed");
        exit();
    }

    $toggleSql = "UPDATE subscription_plans SET is_active = '$statusValue' WHERE $whereClause LIMIT 1";
    $toggleResult = mysqli_query($conn, $toggleSql);

    if ($toggleResult) {
        $notice = $statusValue === 1 ? 'activated' : 'deactivated';
        header("Location: subscriptionmanage.php?plan_notice=$notice");
    } else {
        header("Location: subscriptionmanage.php?plan_notice=failed");
    }
    exit();
}

if (isset($_POST['updatePlan'])) {
    $planName = trim($_POST['plan_name'] ?? '');
    $monthlyPriceRaw = $_POST['monthly_price'] ?? '';
    $monthlyPrice = is_numeric($monthlyPriceRaw) ? (float) $monthlyPriceRaw : 0.0;
    $planFeaturesJson = getPlanFeaturesJsonFromPost();

    if ($planName === '' || $monthlyPrice <= 0 || !$hasPlanNameColumn || !$hasMonthlyPriceColumn) {
        header("Location: subscriptionmanage.php?plan_notice=invalid");
        exit();
    }

    $whereClause = '';
    if ($hasPlanIdColumn && isset($_POST['plan_id']) && $_POST['plan_id'] !== '') {
        $planId = (int) $_POST['plan_id'];
        $whereClause = "plan_id = '$planId'";
    } elseif ($hasPlanCodeColumn && isset($_POST['plan_code']) && $_POST['plan_code'] !== '') {
        $planCode = mysqli_real_escape_string($conn, trim((string) $_POST['plan_code']));
        $whereClause = "plan_code = '$planCode'";
    }

    if ($whereClause === '') {
        header("Location: subscriptionmanage.php?plan_notice=failed");
        exit();
    }

    $updateFields = [];
    $updateFields[] = "plan_name='" . mysqli_real_escape_string($conn, $planName) . "'";
    $updateFields[] = "monthly_price='" . mysqli_real_escape_string($conn, number_format($monthlyPrice, 2, '.', '')) . "'";

    if ($hasPlanFeaturesColumn) {
        $updateFields[] = "plan_features='" . mysqli_real_escape_string($conn, $planFeaturesJson) . "'";
    }

    $updateSql = "UPDATE subscription_plans SET " . implode(', ', $updateFields) . " WHERE $whereClause LIMIT 1";
    $updateResult = mysqli_query($conn, $updateSql);

    if ($updateResult) {
        header("Location: subscriptionmanage.php?plan_notice=updated");
    } else {
        header("Location: subscriptionmanage.php?plan_notice=failed");
    }
    exit();
}

if (isset($_POST['publishPlan'])) {
    $planName = trim($_POST['plan_name'] ?? '');
    $monthlyPriceRaw = $_POST['monthly_price'] ?? '';
    $monthlyPrice = is_numeric($monthlyPriceRaw) ? (float) $monthlyPriceRaw : 0.0;
    $planFeaturesJson = getPlanFeaturesJsonFromPost();

    if ($planName === '' || $monthlyPrice <= 0) {
        header("Location: subscriptionmanage.php?plan_notice=invalid");
        exit();
    }

    if (!$hasPlanNameColumn || !$hasMonthlyPriceColumn) {
        header("Location: subscriptionmanage.php?plan_notice=schema_error");
        exit();
    }

    $columns = [];
    $values = [];

    if ($hasPlanCodeColumn) {
        $planCode = generatePlanCode($conn, $planName);
        $columns[] = 'plan_code';
        $values[] = "'" . mysqli_real_escape_string($conn, $planCode) . "'";
    }

    $columns[] = 'plan_name';
    $values[] = "'" . mysqli_real_escape_string($conn, $planName) . "'";

    $columns[] = 'monthly_price';
    $values[] = "'" . mysqli_real_escape_string($conn, number_format($monthlyPrice, 2, '.', '')) . "'";

    if ($hasPlanFeaturesColumn) {
        $columns[] = 'plan_features';
        $values[] = "'" . mysqli_real_escape_string($conn, $planFeaturesJson) . "'";
    }

    if ($hasIsActiveColumn) {
        $columns[] = 'is_active';
        $values[] = '1';
    }

    $insertPlanSql = "INSERT INTO subscription_plans (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ")";
    $insertPlan = mysqli_query($conn, $insertPlanSql);

    if ($insertPlan) {
        header("Location: subscriptionmanage.php?plan_notice=created");
    } else {
        header("Location: subscriptionmanage.php?plan_notice=failed");
    }
    exit();
}

$planNotice = $_GET['plan_notice'] ?? '';
$planFilter = strtolower(trim((string) ($_GET['plan_filter'] ?? 'all')));
$allowedPlanFilters = ['all', 'active', 'inactive'];
if (!in_array($planFilter, $allowedPlanFilters, true)) {
    $planFilter = 'all';
}

if (!$hasIsActiveColumn) {
    $planFilter = 'all';
}

$savedPlans = [];
if ($hasPlanNameColumn && $hasMonthlyPriceColumn) {
    $selectColumns = ['plan_name', 'monthly_price'];
    if ($hasPlanIdColumn) {
        $selectColumns[] = 'plan_id';
    }
    if ($hasPlanCodeColumn) {
        $selectColumns[] = 'plan_code';
    }
    if ($hasPlanFeaturesColumn) {
        $selectColumns[] = 'plan_features';
    }
    if ($hasIsActiveColumn) {
        $selectColumns[] = 'is_active';
    }
    if ($hasCreatedAtColumn) {
        $selectColumns[] = 'created_at';
    }

    $plansQuery = "SELECT " . implode(', ', $selectColumns) . " FROM subscription_plans";
    if ($hasIsActiveColumn && $planFilter === 'active') {
        $plansQuery .= " WHERE is_active = 1";
    } elseif ($hasIsActiveColumn && $planFilter === 'inactive') {
        $plansQuery .= " WHERE is_active = 0";
    }
    $plansQuery .= " ORDER BY " . ($hasIsActiveColumn ? "is_active DESC, " : "") . "monthly_price ASC, plan_name ASC";

    $plansResult = mysqli_query($conn, $plansQuery);
    if ($plansResult) {
        while ($planRow = mysqli_fetch_assoc($plansResult)) {
            $savedPlans[] = $planRow;
        }
    }
}

// Subscription plans pricing config
$pricingConfig = [
    'basic' => ['monthly' => 999, 'name' => 'Basic', 'emoji' => '📦'],
    'standard' => ['monthly' => 1999, 'name' => 'Standard', 'emoji' => '🚀'],
    'premium' => ['monthly' => 3499, 'name' => 'Premium', 'emoji' => '💎']
];

// Get subscription statistics
$stats = [
    'total_mrr' => 0,
    'active_subscriptions' => 0,
    'avg_arpu' => 0,
    'churn_rate' => 0,
    'plans' => []
];

// Count active tenants by subscription plan
$activeTenantsQuery = "SELECT subscription_plan, COUNT(*) as count, SUM(plan_price) as total_revenue 
                       FROM owners 
                       WHERE status = 'Active' AND subscription_plan IS NOT NULL 
                       GROUP BY subscription_plan";
$result = mysqli_query($conn, $activeTenantsQuery);

while ($row = mysqli_fetch_assoc($result)) {
    $plan = strtolower($row['subscription_plan']);
    if (!isset($stats['plans'][$plan])) {
        $stats['plans'][$plan] = [
            'count' => 0,
            'revenue' => 0,
            'name' => $pricingConfig[$plan]['name'] ?? 'Unknown'
        ];
    }
    $stats['plans'][$plan]['count'] = $row['count'];
    $stats['plans'][$plan]['revenue'] = $row['total_revenue'] ?? 0;
    $stats['total_mrr'] += $row['total_revenue'] ?? 0;
}

// Get total active subscriptions
$totalActiveQuery = "SELECT COUNT(*) as total FROM owners WHERE status = 'Active' AND subscription_plan IS NOT NULL";
$totalResult = mysqli_query($conn, $totalActiveQuery);
$totalActiveRow = mysqli_fetch_assoc($totalResult);
$stats['active_subscriptions'] = $totalActiveRow['total'];

// Calculate ARPU
if ($stats['active_subscriptions'] > 0) {
    $stats['avg_arpu'] = round($stats['total_mrr'] / $stats['active_subscriptions'], 2);
}

// Get churn rate (inactive tenants this month vs total)
$currentMonth = date('Y-m-01');
$churnQuery = "SELECT COUNT(*) as inactive_count FROM owners 
               WHERE status = 'Inactive' AND subscription_end >= '$currentMonth' AND subscription_end < NOW()";
$churnResult = mysqli_query($conn, $churnQuery);
$churnRow = mysqli_fetch_assoc($churnResult);
$inactiveCount = $churnRow['inactive_count'] ?? 0;

if ($stats['active_subscriptions'] > 0) {
    $stats['churn_rate'] = round(($inactiveCount / ($stats['active_subscriptions'] + $inactiveCount)) * 100, 1);
}

?>
<!DOCTYPE html>

<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Subscription Management | Cobalt Admin</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&amp;family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
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
                        "on-secondary": "#ffffff",
                        "surface-container-high": "#ffffff",
                        "outline-variant": "#cbd5e1",
                        "on-tertiary": "#ffffff",
                        "on-tertiary-container": "#92400e",
                        "error": "#ef4444",
                        "on-secondary-fixed-variant": "#334155",
                        "background": "#f6f6f8",
                        "on-secondary-container": "#1e293b",
                        "inverse-primary": "#b4c5ff",
                        "surface-container-low": "#ffffff",
                        "surface-dim": "#d9d9e4",
                        "tertiary-container": "#fef3c7",
                        "outline": "#e2e8f0",
                        "on-surface-variant": "#64748b",
                        "on-surface": "#0f172a",
                        "secondary": "#475569",
                        "inverse-on-surface": "#f8fafc",
                        "on-primary": "#ffffff",
                        "on-tertiary-fixed": "#7c2d12",
                        "on-primary-fixed": "#1e3a8a",
                        "surface-container-highest": "#ffffff",
                        "inverse-surface": "#1e293b",
                        "primary-fixed": "#dbeafe",
                        "on-background": "#0f172a",
                        "tertiary-fixed-dim": "#fed7aa",
                        "surface-tint": "#1152d4",
                        "surface-container": "#ffffff",
                        "error-container": "#fee2e2",
                        "surface-variant": "#f1f5f9",
                        "surface-container-lowest": "#ffffff",
                        "tertiary-fixed": "#ffedd5",
                        "surface": "#f6f6f8",
                        "on-error": "#ffffff",
                        "secondary-fixed": "#e2e8f0",
                        "on-secondary-fixed": "#0f172a",
                        "primary": "#1152d4",
                        "on-error-container": "#991b1b",
                        "on-primary-fixed-variant": "#1d4ed8",
                        "on-primary-container": "#1152d4",
                        "primary-container": "#eef2ff",
                        "tertiary": "#f59e0b",
                        "surface-bright": "#ffffff",
                        "primary-fixed-dim": "#bfdbfe",
                        "secondary-container": "#f1f5f9",
                        "secondary-fixed-dim": "#cbd5e1",
                        "on-tertiary-fixed-variant": "#9a3412"
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

<body class="bg-background text-on-background antialiased selection:bg-primary-fixed selection:text-primary">
    <!-- SideNavBar Shell -->
    <aside
        class="flex flex-col fixed left-0 top-0 h-full z-40 h-screen w-64 border-r border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 font-['Inter'] antialiased tracking-tight shadow-sm dark:shadow-none">
        <!-- Brand Header -->
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
                href="superhealth.php">
                <span class="material-symbols-outlined" data-icon="health_and_safety">health_and_safety</span>
                <span class="text-sm">System Health</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400 font-bold border-r-4 border-blue-700 dark:border-blue-500 rounded-lg active:scale-95"
                href="subscriptionmanage.php">
                <span class="material-symbols-outlined" data-icon="subscriptions">subscriptions</span>
                <span class="text-sm">Subscriptions</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors rounded-lg active:scale-95"
                href="supersalesreport.php">
                <span class="material-symbols-outlined" data-icon="bar_chart">bar_chart</span>
                <span class="text-sm">Sales Reports</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors rounded-lg active:scale-95"
                href="superauditlogs.php">
                <span class="material-symbols-outlined" data-icon="assignment">assignment</span>
                <span class="text-sm">Audit Logs</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors rounded-lg active:scale-95"
                href="supersettings.php">
                <span class="material-symbols-outlined" data-icon="settings">settings</span>
                <span class="text-sm">Settings</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors rounded-lg active:scale-95"
                href="backup_restore.php">
                <span class="material-symbols-outlined" data-icon="backup"
                    style="font-variation-settings: 'FILL' 1;">backup</span>
                <span class="text-sm">System Backup</span>
            </a>
        </nav>
        <!-- Footer Actions -->
        <div class="p-4 border-t border-slate-100 dark:border-slate-800 space-y-2">
            <div
                class="flex items-center gap-3 p-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer transition-colors">
                <div class="w-10 h-10 rounded-full bg-slate-200 dark:bg-slate-700 bg-cover bg-center"
                    data-alt="Alex Rivera headshot professional portrait"
                    style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuAA7ZvS0RT24pYl7zsQUKsnC9inrzmoUQVQC8PvdcW5_q4FtMWEC8ZD9Ke8mBa8iRwi4vfG0NbuLhEY9U_mYTQt3gBMRoNS0jNV_aJYQ-QCLtauVwWdyP53SHmFLjb5bQvwjbvvF24yHFp3moy4K6rJ0tVvtMIzdIUNohESEbLUilTPScnQYQQutAW0bzWhFZkGsX1GwwAl_2_9yXjauFnRNg0uTHfeR3lnfDRxLlk9Jo_hIr7N64rr5SWZq57QEfMdbFLkygzUgb-A')">
                </div>
                <div class="flex flex-col min-w-0">
                    <h3 class="text-sm font-semibold truncate text-slate-900 dark:text-white">Alex Rivera</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 truncate">Superadmin</p>
                </div>
            </div>
            <div
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-900/10 transition-colors cursor-pointer">
                <span class="material-symbols-outlined">logout</span>
                <a href="logout.php" class="text-sm font-medium">Logout</a>
            </div>
        </div>
    </aside>
    <!-- TopAppBar Shell -->
    <header
        class="flex items-center justify-between px-8 sticky top-0 z-30 ml-64 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md w-full h-16 border-b border-slate-200 dark:border-slate-800">
        <div class="flex items-center gap-4">
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-on-surface-variant">
                    <span class="material-symbols-outlined text-lg" data-icon="search">search</span>
                </span>
                <input
                    class="pl-10 pr-4 py-1.5 bg-surface-variant border-none text-sm rounded-lg focus:ring-2 focus:ring-primary w-64 transition-all"
                    placeholder="Search tenants or plans..." type="text" />
            </div>
        </div>
        <div class="flex items-center gap-6">
            <div class="flex items-center gap-4">
                <button class="text-slate-500 hover:text-blue-700 transition-all duration-200">
                    <span class="material-symbols-outlined" data-icon="notifications">notifications</span>
                </button>
                <button class="text-slate-500 hover:text-blue-700 transition-all duration-200">
                    <span class="material-symbols-outlined" data-icon="help_outline">help_outline</span>
                </button>
            </div>
            <div class="h-8 w-px bg-slate-200 dark:bg-slate-800"></div>
            <div class="flex items-center gap-3">
                <div class="text-right">
                    <div class="text-xs font-bold text-slate-900">Admin User</div>
                    <div class="text-[10px] text-slate-500">Superadmin</div>
                </div>
                <img class="h-8 w-8 rounded-lg object-cover" data-alt="Superadmin Profile Avatar"
                    src="https://lh3.googleusercontent.com/aida-public/AB6AXuBbWq7K39o59qxyf1N9jitSWRpffXaH70Rd7A4awqGKNS42DdPIknKcFsuc5McGZO9lpBxiAgAO3AhVelUg0OU0Kr0vATRrLtmyI4EIBHttmX3_sLKPXVw9tZJ8ka56x1iHPvfysI-uwt4l2henmEBYZXtrGBQVAIqV1pM_hHHBJXpPQ_BLbVFAeZl2ldXQYHQhIcwoDpgGiQGwiY5J0XvDuuEd38Uu1713IJQVMQ6on9guu5Bo_cgZm0v6yQW7PGnbdqfOzzIgw9Ou" />
            </div>
        </div>
    </header>
    <!-- Main Content Area -->
    <main class="ml-64 p-8">
        <?php if ($planNotice !== ''): ?>
            <?php $isSuccessNotice = in_array($planNotice, ['created', 'updated', 'activated', 'deactivated'], true); ?>
            <div class="mb-6 rounded-lg px-4 py-3 text-sm font-medium <?php echo $isSuccessNotice ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'; ?>">
                <?php if ($planNotice === 'created'): ?>
                    Subscription plan created successfully.
                <?php elseif ($planNotice === 'updated'): ?>
                    Subscription plan updated successfully.
                <?php elseif ($planNotice === 'activated'): ?>
                    Subscription plan activated successfully.
                <?php elseif ($planNotice === 'deactivated'): ?>
                    Subscription plan deactivated successfully.
                <?php elseif ($planNotice === 'invalid'): ?>
                    Please enter a valid plan name and monthly price.
                <?php elseif ($planNotice === 'schema_error'): ?>
                    subscription_plans table is missing required columns (plan_name/monthly_price).
                <?php else: ?>
                    Failed to create subscription plan. Please try again.
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Header Section -->
        <div class="flex items-end justify-between mb-8">
            <div>
                <nav class="flex text-xs font-bold text-primary mb-2 uppercase tracking-widest gap-2">
                    <span>Console</span>
                    <span class="text-slate-300">/</span>
                    <span class="text-slate-400">Subscription Management</span>
                </nav>
                <h1 class="text-[30px] font-black tracking-tight text-on-surface leading-none">Subscription Plans</h1>
                <p class="text-on-surface-variant mt-2 text-sm max-w-lg">Manage multi-tenant service tiers, pricing
                    structures, and feature entitlements across the enterprise ecosystem.</p>
            </div>
            <button type="button" onclick="openCreatePlanModal()"
                class="flex items-center gap-2 px-5 py-2.5 bg-primary text-white text-sm font-bold rounded-lg shadow-sm active:scale-95 transition-transform">
                <span class="material-symbols-outlined text-[20px]" data-icon="add">add</span>
                Create New Plan
            </button>
        </div>

        <?php if ($hasIsActiveColumn): ?>
            <div class="mb-6 flex items-center gap-2">
                <a href="subscriptionmanage.php?plan_filter=all" class="px-3 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wide <?php echo $planFilter === 'all' ? 'bg-primary text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>">All</a>
                <a href="subscriptionmanage.php?plan_filter=active" class="px-3 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wide <?php echo $planFilter === 'active' ? 'bg-emerald-600 text-white' : 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200'; ?>">Active</a>
                <a href="subscriptionmanage.php?plan_filter=inactive" class="px-3 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wide <?php echo $planFilter === 'inactive' ? 'bg-amber-600 text-white' : 'bg-amber-100 text-amber-700 hover:bg-amber-200'; ?>">Inactive</a>
            </div>
        <?php endif; ?>

        <?php if (count($savedPlans) === 0): ?>
            <!-- Blank Create Plan Box -->
            <div class="rounded-xl border-2 border-dashed border-slate-300 bg-white/70 p-10 text-center">
                <div class="mx-auto mb-4 h-14 w-14 rounded-full bg-primary/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-primary text-3xl">add</span>
                </div>
                <h3 class="text-xl font-black text-on-surface">No Custom Plans Yet</h3>
                <p class="text-sm text-on-surface-variant mt-2 max-w-md mx-auto">Start by creating your first subscription plan. Configure monthly price and included features in the modal.</p>
                <button type="button" onclick="openCreatePlanModal()" class="mt-6 inline-flex items-center gap-2 px-5 py-2.5 bg-primary text-white text-sm font-bold rounded-lg shadow-sm active:scale-95 transition-transform">
                    <span class="material-symbols-outlined text-[20px]">add</span>
                    Create New Plan
                </button>
            </div>
        <?php else: ?>
            <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                <?php foreach ($savedPlans as $plan): ?>
                    <?php
                    $features = [];
                    $planRecordId = isset($plan['plan_id']) ? (int) $plan['plan_id'] : 0;
                    $planCodeValue = isset($plan['plan_code']) ? (string) $plan['plan_code'] : '';
                    $isActive = !isset($plan['is_active']) || (int) $plan['is_active'] === 1;
                    if (isset($plan['plan_features']) && $plan['plan_features'] !== '') {
                        $decoded = json_decode($plan['plan_features'], true);
                        if (is_array($decoded)) {
                            foreach ($decoded as $featureText) {
                                $featureText = trim((string) $featureText);
                                if ($featureText !== '') {
                                    $features[] = $featureText;
                                }
                            }
                        }
                    }
                    ?>
                    <article class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-lg font-black text-on-surface"><?php echo htmlspecialchars($plan['plan_name']); ?></h3>
                                <p class="text-xs text-slate-500 uppercase tracking-wide mt-1">Monthly Price</p>
                            </div>
                            <?php if (isset($plan['plan_code']) && $plan['plan_code'] !== ''): ?>
                                <span class="px-2.5 py-1 rounded bg-primary/10 text-primary text-[10px] font-bold uppercase tracking-wide"><?php echo htmlspecialchars($plan['plan_code']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="mt-4 text-3xl font-black text-on-surface">₱<?php echo number_format((float) $plan['monthly_price'], 2); ?><span class="text-sm font-semibold text-slate-500"> / month</span></div>

                        <div class="mt-3 flex items-center justify-between gap-2">
                            <span class="px-2.5 py-1 rounded text-[10px] font-bold uppercase tracking-wide <?php echo $isActive ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'; ?>">
                                <?php echo $isActive ? 'Active' : 'Inactive'; ?>
                            </span>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    class="edit-plan-btn px-3 py-1.5 rounded border border-slate-200 text-slate-700 text-xs font-bold hover:bg-slate-50"
                                    data-plan-id="<?php echo $planRecordId; ?>"
                                    data-plan-code="<?php echo htmlspecialchars($planCodeValue, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-plan-name="<?php echo htmlspecialchars((string) $plan['plan_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-plan-price="<?php echo htmlspecialchars((string) $plan['monthly_price'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-plan-features="<?php echo htmlspecialchars(json_encode($features), ENT_QUOTES, 'UTF-8'); ?>"
                                >
                                    Edit
                                </button>
                                <?php if ($hasIsActiveColumn): ?>
                                    <form method="POST" class="inline">
                                        <?php if ($hasPlanIdColumn): ?>
                                            <input type="hidden" name="plan_id" value="<?php echo $planRecordId; ?>" />
                                        <?php endif; ?>
                                        <?php if ($hasPlanCodeColumn): ?>
                                            <input type="hidden" name="plan_code" value="<?php echo htmlspecialchars($planCodeValue, ENT_QUOTES, 'UTF-8'); ?>" />
                                        <?php endif; ?>
                                        <input type="hidden" name="status_value" value="<?php echo $isActive ? '0' : '1'; ?>" />
                                        <button type="submit" name="togglePlanStatus" value="1" class="px-3 py-1.5 rounded text-xs font-bold <?php echo $isActive ? 'bg-amber-100 text-amber-700 hover:bg-amber-200' : 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200'; ?>">
                                            <?php echo $isActive ? 'Set Inactive' : 'Set Active'; ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (count($features) > 0): ?>
                            <ul class="mt-5 space-y-2">
                                <?php foreach ($features as $feature): ?>
                                    <li class="flex items-start gap-2 text-sm text-slate-700">
                                        <span class="material-symbols-outlined text-emerald-500 text-[18px] mt-[1px]">check_circle</span>
                                        <span><?php echo htmlspecialchars($feature); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="mt-5 text-sm text-slate-500">No feature list saved for this plan yet.</p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
        <!-- Recent Activity / Comparative Chart Placeholder -->
        <section class="mt-8">
            <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
                    <h2 class="text-sm font-bold text-on-surface uppercase tracking-tight">Revenue Stream Analysis</h2>
                    <div class="flex gap-2">
                        <button
                            class="px-3 py-1 text-[10px] font-bold text-slate-500 bg-slate-100 rounded uppercase">Last
                            30 Days</button>
                        <button
                            class="px-3 py-1 text-[10px] font-bold text-primary bg-primary-container rounded uppercase">Custom</button>
                    </div>
                </div>
                <div class="p-6">
                    <!-- Technical Pattern Placeholder for Chart -->
                    <div
                        class="h-48 w-full bg-slate-50 rounded-lg border border-dashed border-slate-200 relative overflow-hidden">
                        <div class="absolute inset-0 opacity-10"
                            style="background-image: radial-gradient(#1152d4 1px, transparent 0); background-size: 20px 20px;">
                        </div>
                        <div
                            class="absolute bottom-0 left-0 w-full h-32 bg-gradient-to-t from-primary/10 to-transparent">
                        </div>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <div class="text-center">
                                <span class="material-symbols-outlined text-slate-300 text-4xl mb-2"
                                    data-icon="monitoring">monitoring</span>
                                <p class="text-xs font-medium text-slate-400">Aggregated Subscription Revenue
                                    Visualization</p>
                            </div>
                        </div>
                        <!-- Decorative SVG Line -->
                        <svg class="absolute bottom-10 left-0 w-full h-24" preserveaspectratio="none">
                            <path d="M0 60 Q 150 10, 300 50 T 600 20 T 900 70 T 1200 40" fill="transparent"
                                stroke="#1152d4" stroke-width="2"></path>
                        </svg>
                    </div>
                    <div class="grid grid-cols-4 gap-6 mt-6">
                        <div class="p-4 rounded-lg bg-slate-50">
                            <div class="text-[10px] font-bold text-slate-400 uppercase mb-1">Total MRR</div>
                            <div class="text-xl font-black text-on-surface">
                                ₱<?php echo number_format($stats['total_mrr'], 0); ?></div>
                            <div class="text-[10px] text-slate-500 font-bold flex items-center gap-1">
                                <span class="material-symbols-outlined text-[12px]" data-icon="info">info</span> Monthly
                                recurring
                            </div>
                        </div>
                        <div class="p-4 rounded-lg bg-slate-50">
                            <div class="text-[10px] font-bold text-slate-400 uppercase mb-1">Active Subs</div>
                            <div class="text-xl font-black text-on-surface">
                                <?php echo $stats['active_subscriptions']; ?>
                            </div>
                            <div class="text-[10px] text-slate-500 font-bold flex items-center gap-1">
                                <span class="material-symbols-outlined text-[12px]"
                                    data-icon="check_circle">check_circle</span> Active tenants
                            </div>
                        </div>
                        <div class="p-4 rounded-lg bg-slate-50">
                            <div class="text-[10px] font-bold text-slate-400 uppercase mb-1">Avg. ARPU</div>
                            <div class="text-xl font-black text-on-surface">
                                ₱<?php echo number_format($stats['avg_arpu'], 0); ?></div>
                            <div class="text-[10px] text-slate-500 font-bold flex items-center gap-1">
                                <span class="material-symbols-outlined text-[12px]"
                                    data-icon="account_balance">account_balance</span> Per user monthly
                            </div>
                        </div>
                        <div class="p-4 rounded-lg bg-slate-50">
                            <div class="text-[10px] font-bold text-slate-400 uppercase mb-1">Churn Rate</div>
                            <div class="text-xl font-black text-on-surface"><?php echo $stats['churn_rate']; ?>%</div>
                            <div class="text-[10px] text-slate-500 font-bold flex items-center gap-1">
                                <span class="material-symbols-outlined text-[12px]"
                                    data-icon="trending_down">trending_down</span> This month
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <!-- Active Subscriptions Table -->
        <section class="mt-8">
            <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
                    <h2 class="text-sm font-bold text-on-surface uppercase tracking-tight">Active Subscriptions</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50">
                                <th class="px-6 py-3 text-left font-bold text-slate-600 uppercase text-[10px]">Tenant
                                </th>
                                <th class="px-6 py-3 text-left font-bold text-slate-600 uppercase text-[10px]">Plan</th>
                                <th class="px-6 py-3 text-left font-bold text-slate-600 uppercase text-[10px]">Billing
                                    Cycle</th>
                                <th class="px-6 py-3 text-left font-bold text-slate-600 uppercase text-[10px]">Monthly
                                    Rate</th>
                                <th class="px-6 py-3 text-left font-bold text-slate-600 uppercase text-[10px]">Start
                                    Date</th>
                                <th class="px-6 py-3 text-left font-bold text-slate-600 uppercase text-[10px]">Next
                                    Billing</th>
                                <th class="px-6 py-3 text-left font-bold text-slate-600 uppercase text-[10px]">Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $subscriptionsQuery = "SELECT tenantID, shopName, subscription_plan, billing_cycle, plan_price, 
                                                 subscription_start, next_billing_date, status
                                                 FROM owners 
                                                 WHERE status = 'Active' AND subscription_plan IS NOT NULL 
                                                 ORDER BY next_billing_date ASC";
                            $subResult = mysqli_query($conn, $subscriptionsQuery);

                            if (mysqli_num_rows($subResult) > 0) {
                                while ($sub = mysqli_fetch_assoc($subResult)) {
                                    $planKey = strtolower($sub['subscription_plan']);
                                    $planName = $pricingConfig[$planKey]['name'] ?? ucfirst($sub['subscription_plan']);
                                    $monthlyRate = $pricingConfig[$planKey]['monthly'] ?? 0;
                                    $startDate = date('M d, Y', strtotime($sub['subscription_start']));
                                    $nextBilling = date('M d, Y', strtotime($sub['next_billing_date']));
                                    $billingCycle = ucfirst($sub['billing_cycle']);
                                    $totalBillingAmount = $sub['plan_price'];
                                    ?>
                                    <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                                        <td class="px-6 py-4 font-medium text-slate-900">
                                            <?php echo htmlspecialchars($sub['shopName']); ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span
                                                class="px-2.5 py-1 bg-primary/10 text-primary text-[10px] font-bold rounded uppercase">
                                                <?php echo $planName; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-slate-600"><?php echo $billingCycle; ?></td>
                                        <td class="px-6 py-4">
                                            <div class="font-bold text-slate-900">
                                                ₱<?php echo number_format($monthlyRate, 0); ?>/mo</div>
                                            <div class="text-[10px] text-slate-500">
                                                ₱<?php echo number_format($totalBillingAmount, 0); ?> total</div>
                                        </td>
                                        <td class="px-6 py-4 text-slate-600"><?php echo $startDate; ?></td>
                                        <td class="px-6 py-4">
                                            <span class="px-2.5 py-1 bg-amber-100 text-amber-800 text-[10px] font-bold rounded">
                                                <?php echo $nextBilling; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <button class="p-1 hover:bg-slate-200 rounded transition-colors" title="Edit">
                                                <span class="material-symbols-outlined text-[18px] text-slate-600">edit</span>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php
                                }
                            } else {
                                ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-8 text-center text-slate-500">
                                        <div class="flex flex-col items-center gap-2">
                                            <span class="material-symbols-outlined text-4xl text-slate-300">inbox</span>
                                            <p class="font-medium">No active subscriptions yet</p>
                                        </div>
                                    </td>
                                </tr>
                                <?php
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Edit Plan Modal -->
        <div id="editPlanModal" class="hidden fixed inset-0 z-[110] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
            <div class="bg-white w-full max-w-xl rounded-lg shadow-2xl border border-slate-200 overflow-hidden">
                <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between bg-white">
                    <div>
                        <h2 class="text-xl font-black text-on-surface tracking-tight">Edit Plan</h2>
                        <p class="text-xs text-on-surface-variant font-medium mt-1">Update pricing and plan features.</p>
                    </div>
                    <button type="button" onclick="closeEditPlanModal()" class="text-slate-400 hover:text-on-surface transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <form id="editPlanForm" method="POST" class="px-8 py-8 space-y-6">
                    <?php if ($hasPlanIdColumn): ?>
                        <input type="hidden" id="editPlanIdInput" name="plan_id" />
                    <?php endif; ?>
                    <?php if ($hasPlanCodeColumn): ?>
                        <input type="hidden" id="editPlanCodeInput" name="plan_code" />
                    <?php endif; ?>
                    <input type="hidden" id="editPlanFeaturesInput" name="plan_features" value="[]" />

                    <div class="grid grid-cols-2 gap-6">
                        <div class="col-span-2 md:col-span-1">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Plan Name</label>
                            <input id="editPlanNameInput" name="plan_name" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all" type="text" />
                        </div>
                        <div class="col-span-2 md:col-span-1">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Monthly Price</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 font-bold text-sm">₱</span>
                                <input id="editPlanPriceInput" name="monthly_price" class="w-full bg-slate-50 border border-slate-200 rounded-lg pl-8 pr-4 py-2.5 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all" type="number" min="0" step="0.01" />
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-4 flex justify-between">
                            <span>Included Features</span>
                            <button id="editAddFeatureBtn" type="button" class="text-primary cursor-pointer hover:underline">+ Add Feature</button>
                        </label>
                        <div id="editFeatureList" class="space-y-3"></div>
                    </div>

                    <div class="pt-2 flex gap-4">
                        <button type="button" onclick="closeEditPlanModal()" class="flex-1 py-3 border border-slate-200 text-slate-600 font-bold text-sm rounded-lg hover:bg-slate-50">Cancel</button>
                        <button type="submit" name="updatePlan" value="1" class="flex-1 py-3 bg-primary text-white font-bold text-sm rounded-lg hover:opacity-90">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Create Plan Modal -->
        <div id="createPlanModal" class="hidden fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
            <div class="bg-white w-full max-w-xl rounded-lg shadow-2xl border border-slate-200 overflow-hidden">
                <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between bg-white">
                    <div>
                        <h2 class="text-xl font-black text-on-surface tracking-tight">Create New Plan</h2>
                        <p class="text-xs text-on-surface-variant font-medium mt-1">Define pricing and features for a new subscription tier.</p>
                    </div>
                    <button type="button" onclick="closeCreatePlanModal()" class="text-slate-400 hover:text-on-surface transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <form id="createPlanForm" method="POST" class="px-8 py-8 space-y-6">
                    <div class="grid grid-cols-2 gap-6">
                        <div class="col-span-2 md:col-span-1">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Plan Name</label>
                            <input id="planNameInput" name="plan_name" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all" placeholder="e.g. Enterprise" type="text" />
                        </div>
                        <div class="col-span-2 md:col-span-1">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Monthly Price (USD)</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 font-bold text-sm">$</span>
                                <input id="planPriceInput" name="monthly_price" class="w-full bg-slate-50 border border-slate-200 rounded-lg pl-8 pr-4 py-2.5 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all" placeholder="0.00" type="number" min="0" step="0.01" />
                            </div>
                        </div>
                    </div>
                    <input type="hidden" id="planFeaturesInput" name="plan_features" value="[]" />

                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-4 flex justify-between">
                            <span>Included Features</span>
                            <button id="addFeatureBtn" type="button" class="text-primary cursor-pointer hover:underline">+ Add Feature</button>
                        </label>
                        <div id="featureList" class="space-y-3">
                            <div class="flex items-center gap-3 feature-row">
                                <div class="flex-1 relative">
                                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-emerald-500 text-lg">check_circle</span>
                                    <input class="w-full bg-white border border-slate-200 rounded-lg pl-10 pr-4 py-2 text-sm" type="text" value="Unlimited user accounts" />
                                </div>
                                <button type="button" class="remove-feature-btn text-slate-300 hover:text-error transition-colors">
                                    <span class="material-symbols-outlined">delete</span>
                                </button>
                            </div>

                            <div class="flex items-center gap-3 feature-row">
                                <div class="flex-1 relative">
                                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-emerald-500 text-lg">check_circle</span>
                                    <input class="w-full bg-white border border-slate-200 rounded-lg pl-10 pr-4 py-2 text-sm" type="text" value="24/7 technical support" />
                                </div>
                                <button type="button" class="remove-feature-btn text-slate-300 hover:text-error transition-colors">
                                    <span class="material-symbols-outlined">delete</span>
                                </button>
                            </div>

                            <div class="flex items-center gap-3 feature-row">
                                <div class="flex-1 relative">
                                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-emerald-500 text-lg">check_circle</span>
                                    <input class="w-full bg-white border border-slate-100 rounded-lg pl-10 pr-4 py-2 text-sm" placeholder="Add a feature description..." type="text" />
                                </div>
                                <button type="button" class="remove-feature-btn text-slate-300 hover:text-error transition-colors">
                                    <span class="material-symbols-outlined">delete</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 flex gap-4">
                        <button id="saveDraftBtn" class="flex-1 py-3 border border-slate-200 text-slate-600 font-bold text-sm rounded-lg hover:bg-slate-50 active:scale-[0.99] transition-all" type="button">
                            Save as Draft
                        </button>
                        <button id="publishPlanBtn" name="publishPlan" value="1" class="flex-1 py-3 bg-primary text-white font-bold text-sm rounded-lg hover:opacity-90 active:scale-[0.99] transition-all" type="submit">
                            Publish Plan
                        </button>
                    </div>
                </form>

                <div class="px-8 py-4 bg-slate-50 border-t border-slate-100 flex justify-center">
                    <p class="text-[10px] text-slate-400 font-medium uppercase tracking-[0.1em]">Changes will be applied to all new sign-ups immediately</p>
                </div>
            </div>
        </div>
    </main>

    <script>
        function createFeatureRowMarkup(value) {
            const safeValue = String(value || '').replace(/"/g, '&quot;');
            return "<div class=\"flex items-center gap-3 feature-row\"><div class=\"flex-1 relative\"><span class=\"material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-emerald-500 text-lg\">check_circle</span><input class=\"w-full bg-white border border-slate-100 rounded-lg pl-10 pr-4 py-2 text-sm\" placeholder=\"Add a feature description...\" type=\"text\" value=\"" + safeValue + "\" /></div><button type=\"button\" class=\"remove-feature-btn text-slate-300 hover:text-error transition-colors\"><span class=\"material-symbols-outlined\">delete</span></button></div>";
        }

        function openCreatePlanModal() {
            document.getElementById('createPlanModal').classList.remove('hidden');
        }

        function closeCreatePlanModal() {
            document.getElementById('createPlanModal').classList.add('hidden');
        }

        (function setupCreatePlanModal() {
            const modal = document.getElementById('createPlanModal');
            const form = document.getElementById('createPlanForm');
            const addFeatureBtn = document.getElementById('addFeatureBtn');
            const featureList = document.getElementById('featureList');
            const planNameInput = document.getElementById('planNameInput');
            const planPriceInput = document.getElementById('planPriceInput');
            const planFeaturesInput = document.getElementById('planFeaturesInput');
            const saveDraftBtn = document.getElementById('saveDraftBtn');

            if (!modal || !form || !addFeatureBtn || !featureList || !planNameInput || !planPriceInput || !planFeaturesInput || !saveDraftBtn) {
                return;
            }

            function getFeatureValues() {
                return Array.from(featureList.querySelectorAll('input')).map((el) => el.value.trim()).filter(Boolean);
            }

            function createFeatureRow(value = '') {
                const row = document.createElement('div');
                row.className = 'flex items-center gap-3 feature-row';
                row.innerHTML = createFeatureRowMarkup(value);
                return row;
            }

            addFeatureBtn.addEventListener('click', function () {
                featureList.appendChild(createFeatureRow(''));
            });

            featureList.addEventListener('click', function (event) {
                const button = event.target.closest('.remove-feature-btn');
                if (!button) {
                    return;
                }

                const rows = featureList.querySelectorAll('.feature-row');
                if (rows.length <= 1) {
                    alert('At least one feature row is required.');
                    return;
                }

                const row = button.closest('.feature-row');
                if (row) {
                    row.remove();
                }
            });

            saveDraftBtn.addEventListener('click', function () {
                const payload = {
                    planName: planNameInput.value.trim(),
                    monthlyPrice: planPriceInput.value,
                    features: getFeatureValues(),
                    savedAt: new Date().toISOString()
                };
                localStorage.setItem('subscription_plan_draft', JSON.stringify(payload));
                alert('Draft saved.');
            });

            form.addEventListener('submit', function (event) {

                const planName = planNameInput.value.trim();
                const monthlyPrice = parseFloat(planPriceInput.value);
                const features = getFeatureValues();

                if (!planName) {
                    event.preventDefault();
                    alert('Please enter a plan name.');
                    planNameInput.focus();
                    return;
                }

                if (Number.isNaN(monthlyPrice) || monthlyPrice <= 0) {
                    event.preventDefault();
                    alert('Please enter a valid monthly price greater than 0.');
                    planPriceInput.focus();
                    return;
                }

                if (features.length === 0) {
                    event.preventDefault();
                    alert('Please add at least one feature.');
                    return;
                }

                planFeaturesInput.value = JSON.stringify(features);
            });

            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    closeCreatePlanModal();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                    closeCreatePlanModal();
                }
            });
        })();

        (function setupEditPlanModal() {
            const modal = document.getElementById('editPlanModal');
            const form = document.getElementById('editPlanForm');
            const planIdInput = document.getElementById('editPlanIdInput');
            const planCodeInput = document.getElementById('editPlanCodeInput');
            const nameInput = document.getElementById('editPlanNameInput');
            const priceInput = document.getElementById('editPlanPriceInput');
            const featureList = document.getElementById('editFeatureList');
            const addFeatureBtn = document.getElementById('editAddFeatureBtn');
            const featuresInput = document.getElementById('editPlanFeaturesInput');

            if (!modal || !form || !nameInput || !priceInput || !featureList || !addFeatureBtn || !featuresInput) {
                return;
            }

            function addFeatureRow(value) {
                const row = document.createElement('div');
                row.className = 'flex items-center gap-3 feature-row';
                row.innerHTML = createFeatureRowMarkup(value || '');
                featureList.appendChild(row);
            }

            function setFeatureRows(features) {
                featureList.innerHTML = '';
                if (!Array.isArray(features) || features.length === 0) {
                    addFeatureRow('');
                    return;
                }
                features.forEach(function (feature) {
                    addFeatureRow(String(feature));
                });
            }

            function getFeatureValues() {
                return Array.from(featureList.querySelectorAll('input')).map((el) => el.value.trim()).filter(Boolean);
            }

            window.closeEditPlanModal = function () {
                modal.classList.add('hidden');
            };

            document.querySelectorAll('.edit-plan-btn').forEach(function (button) {
                button.addEventListener('click', function () {
                    if (planIdInput) {
                        planIdInput.value = button.dataset.planId || '';
                    }
                    if (planCodeInput) {
                        planCodeInput.value = button.dataset.planCode || '';
                    }
                    nameInput.value = button.dataset.planName || '';
                    priceInput.value = button.dataset.planPrice || '';

                    let features = [];
                    try {
                        const parsed = JSON.parse(button.dataset.planFeatures || '[]');
                        if (Array.isArray(parsed)) {
                            features = parsed;
                        }
                    } catch (e) {
                        features = [];
                    }

                    setFeatureRows(features);
                    modal.classList.remove('hidden');
                });
            });

            addFeatureBtn.addEventListener('click', function () {
                addFeatureRow('');
            });

            featureList.addEventListener('click', function (event) {
                const button = event.target.closest('.remove-feature-btn');
                if (!button) {
                    return;
                }

                const rows = featureList.querySelectorAll('.feature-row');
                if (rows.length <= 1) {
                    alert('At least one feature row is required.');
                    return;
                }

                const row = button.closest('.feature-row');
                if (row) {
                    row.remove();
                }
            });

            form.addEventListener('submit', function (event) {
                const name = nameInput.value.trim();
                const price = parseFloat(priceInput.value);
                const features = getFeatureValues();

                if (!name) {
                    event.preventDefault();
                    alert('Please enter a plan name.');
                    nameInput.focus();
                    return;
                }

                if (Number.isNaN(price) || price <= 0) {
                    event.preventDefault();
                    alert('Please enter a valid monthly price greater than 0.');
                    priceInput.focus();
                    return;
                }

                if (features.length === 0) {
                    event.preventDefault();
                    alert('Please add at least one feature.');
                    return;
                }

                featuresInput.value = JSON.stringify(features);
            });

            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    closeEditPlanModal();
                }
            });
        })();
    </script>
</body>

</html>