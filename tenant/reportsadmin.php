<?php
session_start();
require_once __DIR__ . "/../db.php";
include __DIR__ . '/../session_security.php';
include __DIR__ . '/access_control.php';

// Get tenant ID from session
$tenantID = isset($_SESSION['tenantID']) ? (int)$_SESSION['tenantID'] : 0;

if ($tenantID === 0) {
    header("Location: tenantlogin.php");
    exit();
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

// Get shop name
$shopName = 'Repair Shop';
if ($_SESSION['userType'] === 'owner') {
    $shopName = isset($_SESSION['shopName']) ? $_SESSION['shopName'] : 'Repair Shop';
} else {
    // For staff, try to get from session or database
    $ownerStmt = $conn->prepare('SELECT shopName FROM owners WHERE tenantID = ? LIMIT 1');
    if ($ownerStmt) {
        $ownerStmt->bind_param('i', $tenantID);
        $ownerStmt->execute();
        $ownerResult = $ownerStmt->get_result();
        if ($ownerResult && $ownerRow = $ownerResult->fetch_assoc()) {
            $shopName = $ownerRow['shopName'] ?: 'Repair Shop';
        }
        $ownerStmt->close();
    }
}

// Get date range filter
$dateRange = $_GET['date_range'] ?? 'last_30_days';
$startDate = new DateTime();
$endDate = new DateTime();

switch($dateRange) {
    case 'last_7_days':
        $startDate->modify('-7 days');
        break;
    case 'last_90_days':
        $startDate->modify('-90 days');
        break;
    case 'year_to_date':
        $startDate->setDate($endDate->format('Y'), 1, 1);
        break;
    case 'last_30_days':
    default:
        $startDate->modify('-30 days');
}

$startDateStr = $startDate->format('Y-m-d');
$endDateStr = $endDate->format('Y-m-d');

// Calculate metrics for date range
$metricsQuery = "SELECT 
    COUNT(*) as total_jobs,
    SUM(grand_total) as total_revenue,
    AVG(grand_total) as avg_repair_cost,
    SUM(CASE WHEN job_status = 'Completed' THEN 1 ELSE 0 END) as completed_jobs,
    SUM(CASE WHEN job_status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_jobs
FROM repair_jobs
WHERE tenantID = ? AND DATE(created_at) BETWEEN ? AND ?";

$metricsStmt = $conn->prepare($metricsQuery);
$metricsStmt->bind_param("iss", $tenantID, $startDateStr, $endDateStr);
$metricsStmt->execute();
$metricsResult = $metricsStmt->get_result();
$metrics = $metricsResult->fetch_assoc();
$metricsStmt->close();

// Calculate productivity percentage
$totalJobs = $metrics['total_jobs'] ?? 0;
$completedJobs = $metrics['completed_jobs'] ?? 0;
$productivity = $totalJobs > 0 ? round(($completedJobs / $totalJobs) * 100, 1) : 0;

$totalRevenue = (float)($metrics['total_revenue'] ?? 0);
$avgRepairCost = (float)($metrics['avg_repair_cost'] ?? 0);

// Get revenue trends (daily revenue for last 30 days)
$trendsQuery = "SELECT 
    DATE(created_at) as date,
    SUM(grand_total) as daily_revenue,
    COUNT(*) as jobs_count
FROM repair_jobs
WHERE tenantID = ? AND DATE(created_at) BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND CURDATE()
GROUP BY DATE(created_at)
ORDER BY DATE(created_at) ASC";

$trendsStmt = $conn->prepare($trendsQuery);
$trendsStmt->bind_param("i", $tenantID);
$trendsStmt->execute();
$trendsResult = $trendsStmt->get_result();
$trendData = [];
while ($row = $trendsResult->fetch_assoc()) {
    $trendData[] = $row;
}
$trendsStmt->close();

// Get service volume by job status
$statusQuery = "SELECT 
    job_status,
    COUNT(*) as count
FROM repair_jobs
WHERE tenantID = ? AND DATE(created_at) BETWEEN ? AND ?
GROUP BY job_status
ORDER BY count DESC";

$statusStmt = $conn->prepare($statusQuery);
$statusStmt->bind_param("iss", $tenantID, $startDateStr, $endDateStr);
$statusStmt->execute();
$statusResult = $statusStmt->get_result();
$statusData = [];
$maxJobs = 0;
while ($row = $statusResult->fetch_assoc()) {
    $statusData[] = $row;
    $maxJobs = max($maxJobs, $row['count']);
}
$statusStmt->close();

// Get technician performance
$techQuery = "SELECT 
    assigned_technician,
    COUNT(*) as completed_jobs,
    SUM(CASE WHEN job_status = 'Completed' THEN 1 ELSE 0 END) as successful_jobs,
    SUM(grand_total) as revenue_generated,
    AVG(TIMESTAMPDIFF(HOUR, work_started_at, completed_at)) as avg_hours_per_job
FROM repair_jobs
WHERE tenantID = ? AND DATE(created_at) BETWEEN ? AND ? AND assigned_technician IS NOT NULL
GROUP BY assigned_technician
ORDER BY revenue_generated DESC
LIMIT 10";

$techStmt = $conn->prepare($techQuery);
$techStmt->bind_param("iss", $tenantID, $startDateStr, $endDateStr);
$techStmt->execute();
$techResult = $techStmt->get_result();
$techData = [];
while ($row = $techResult->fetch_assoc()) {
    $techData[] = $row;
}
$techStmt->close();

// Generate chart data for revenue trends
$chartLabels = [];
$chartData = [];
foreach ($trendData as $data) {
    $chartLabels[] = date('M d', strtotime($data['date']));
    $chartData[] = $data['daily_revenue'];
}

?>
<!DOCTYPE html>

<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&amp;display=swap"
        rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
        rel="stylesheet" />
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            display: inline-block;
            line-height: 1;
            text-transform: none;
            letter-spacing: normal;
            word-wrap: normal;
            white-space: nowrap;
            direction: ltr;
        }

        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "on-secondary-fixed": "#0f172a",
                        "on-primary": "#ffffff",
                        "background": "#f6f6f8",
                        "on-primary-fixed-variant": "#1d4ed8",
                        "inverse-surface": "#1e293b",
                        "primary": "#1152d4",
                        "on-surface-variant": "#64748b",
                        "primary-fixed": "#dbeafe",
                        "secondary": "#475569",
                        "inverse-on-surface": "#f8fafc",
                        "on-error": "#ffffff",
                        "tertiary": "#f59e0b",
                        "on-secondary-container": "#1e293b",
                        "on-error-container": "#991b1b",
                        "tertiary-fixed-dim": "#fed7aa",
                        "surface-container-lowest": "#ffffff",
                        "on-tertiary-container": "#92400e",
                        "surface-tint": "#1152d4",
                        "on-secondary-fixed-variant": "#334155",
                        "surface-container": "#ffffff",
                        "secondary-fixed-dim": "#cbd5e1",
                        "primary-container": "#eef2ff",
                        "surface": "#f6f6f8",
                        "surface-dim": "#d9d9e4",
                        "on-primary-fixed": "#1e3a8a",
                        "error-container": "#fee2e2",
                        "surface-container-high": "#ffffff",
                        "primary-fixed-dim": "#bfdbfe",
                        "outline": "#e2e8f0",
                        "surface-variant": "#f1f5f9",
                        "on-background": "#0f172a",
                        "inverse-primary": "#b4c5ff",
                        "outline-variant": "#cbd5e1",
                        "tertiary-container": "#fef3c7",
                        "on-secondary": "#ffffff",
                        "secondary-fixed": "#e2e8f0",
                        "surface-container-low": "#ffffff",
                        "secondary-container": "#f1f5f9",
                        "on-primary-container": "#1152d4",
                        "error": "#ef4444",
                        "on-tertiary": "#ffffff",
                        "on-tertiary-fixed": "#7c2d12",
                        "surface-bright": "#ffffff",
                        "surface-container-highest": "#ffffff",
                        "on-tertiary-fixed-variant": "#9a3412",
                        "tertiary-fixed": "#ffedd5",
                        "on-surface": "#0f172a"
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
</head>

<body class="bg-surface text-on-surface">
    <!-- SideNavBar -->
    <aside
        class="fixed left-0 top-0 h-screen w-64 flex flex-col bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 z-[60]">
        <div class="p-6">
            <div class="flex items-center gap-3 mb-8">
                <div class="bg-primary rounded-lg p-2 text-white">
                    <span class="material-symbols-outlined">directions_car</span>
                </div>
                <div>
                    <h1 class="text-lg font-bold leading-none"><?php echo h($shopName); ?></h1>
                    <p class="text-xs text-slate-500 mt-1">Repair Management</p>
                </div>
            </div>
            <nav class="space-y-1">
                <?php if (canAccessModule('dashboardadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    href="dashboardadmin.php">
                    <span class="material-symbols-outlined text-[22px]">dashboard</span>
                    <span class="font-medium">Dashboard</span>
                </a>
                <?php endif; ?>
                <?php if (canAccessModule('repairjobsadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    href="repairjobsadmin.php">
                    <span class="material-symbols-outlined text-[22px]">build</span>
                    <span class="font-medium">Repair Jobs</span>
                </a>
                <?php endif; ?>
                <?php if (canAccessModule('vehicleadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    href="vehicleadmin.php">
                    <span class="material-symbols-outlined text-[22px]">directions_car</span>
                    <span class="font-medium">Vehicles</span>
                </a>
                <?php endif; ?>
                <?php if (canAccessModule('appointmentadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    href="appointmentadmin.php">
                    <span class="material-symbols-outlined text-[22px]">event</span>
                    <span class="font-medium">Appointments</span>
                </a>
                <?php endif; ?>
                <?php if (canAccessModule('reportsadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-primary/10 text-primary font-bold" 
                    href="reportsadmin.php">
                    <span class="material-symbols-outlined text-[22px]">description</span>
                    <span class="font-medium">Reports</span>
                </a>
                <?php endif; ?>
                <?php if (canAccessModule('inventoryadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    href="inventoryadmin.php">
                    <span class="material-symbols-outlined text-[22px]">inventory_2</span>
                    <span class="font-medium">Inventory</span>
                </a>
                <?php endif; ?>
                <?php if (canAccessModule('customeradmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    href="customeradmin.php">
                    <span class="material-symbols-outlined text-[22px]">group</span>
                    <span class="font-medium">Customers</span>
                </a>
                <?php endif; ?>
                <?php if (canAccessModule('paymentsadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    href="paymentsadmin.php">
                    <span class="material-symbols-outlined text-[22px]">payments</span>
                    <span class="font-medium">Payments</span>
                </a>
                <?php endif; ?>
                <div class="pt-4 mt-4 border-t border-slate-100 dark:border-slate-800">
                    <?php if (canAccessModule('settingsadmin.php', $accessibleModules)): ?>
                    <div class="relative group">
                        <button class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors w-full text-left settings-dropdown-btn" data-dropdown="settings">
                            <span class="material-symbols-outlined text-[22px]">settings</span>
                            <span>Settings</span>
                            <span class="material-symbols-outlined text-[16px] ml-auto">expand_more</span>
                        </button>
                        <div class="absolute left-0 top-full mt-1 w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg shadow-lg hidden z-50 settings-dropdown" data-dropdown="settings">
                            <?php if (canAccessModule('accountbillingadmin.php', $accessibleModules)): ?>
                            <a href="accountbillingadmin.php" class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors border-b border-slate-100 dark:border-slate-800 last:border-b-0">
                                <span class="material-symbols-outlined text-[18px]">receipt_long</span>
                                <span>Account Billing</span>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </nav>
        </div>
        <div class="mt-auto w-full p-4 border-t border-slate-200">
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
    <main class="ml-64 min-h-screen bg-background">
        <!-- Top Nav Bar -->
        <header
            class="sticky top-0 z-40 w-full border-b border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md flex items-center justify-between px-8 h-16">
            <h2 class="text-lg font-black text-slate-900 dark:text-white tracking-tight">Reports Management</h2>
            <div class="flex items-center gap-4">
                <button class="p-2 text-slate-500 hover:text-primary transition-all">
                    <span class="material-symbols-outlined">notifications</span>
                </button>
                <button class="p-2 text-slate-500 hover:text-primary transition-all">
                    <span class="material-symbols-outlined">help_outline</span>
                </button>
            </div>
        </header>
        <!-- Changed max-w-7xl mx-auto to max-w-none and removed centering -->
        <div class="p-8 max-w-none">
            <!-- Header Section -->
            <div class="flex justify-between items-end mb-8">
                <div>
                    <h1 class="text-[30px] font-black text-on-background tracking-tight">Performance Reports</h1>
                    <p class="text-on-surface-variant font-medium mt-1">Detailed operational and financial analytics for
                        the current period.</p>
                </div>
                <button
                    class="bg-primary hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg font-bold text-sm flex items-center gap-2 transition-all shadow-sm">
                    <span class="material-symbols-outlined text-[18px]" data-icon="download">download</span>
                    Export Report
                </button>
            </div>
            <!-- Filter Bar: Ensuring it spans but the elements are naturally left-aligned -->
            <div class="bg-white border border-slate-200 rounded-xl p-4 mb-8 flex flex-wrap gap-4 items-center">
                <div class="w-64">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 px-1">Date
                        Range</label>
                    <div class="relative">
                        <form method="GET" id="filterForm">
                            <select name="date_range" onchange="document.getElementById('filterForm').submit()"
                                class="w-full bg-slate-50 border-slate-200 text-sm rounded-lg py-2 pl-3 pr-10 appearance-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="last_30_days" <?php echo $dateRange === 'last_30_days' ? 'selected' : ''; ?>>Last 30 Days</option>
                                <option value="last_7_days" <?php echo $dateRange === 'last_7_days' ? 'selected' : ''; ?>>Last 7 Days</option>
                                <option value="last_90_days" <?php echo $dateRange === 'last_90_days' ? 'selected' : ''; ?>>Last 90 Days</option>
                                <option value="year_to_date" <?php echo $dateRange === 'year_to_date' ? 'selected' : ''; ?>>Year to Date</option>
                            </select>
                            <span
                                class="material-symbols-outlined absolute right-3 top-2.5 text-slate-400 pointer-events-none">calendar_month</span>
                        </form>
                    </div>
                </div>
            </div>
            <!-- Metrics Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Revenue -->
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-2 bg-blue-50 rounded-lg text-primary">
                            <span class="material-symbols-outlined" data-icon="payments">payments</span>
                        </div>
                        <span
                            class="text-[10px] font-bold <?php echo $totalRevenue > 100000 ? 'text-green-600 bg-green-50' : 'text-slate-400 bg-slate-50'; ?> px-2 py-0.5 rounded-full"><?php echo $totalRevenue > 100000 ? '+12.4%' : 'Stable'; ?></span>
                    </div>
                    <p class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1 text-left">Total Revenue
                        (Period)</p>
                    <h3 class="text-2xl font-black text-slate-900 text-left">₱<?php echo number_format($totalRevenue, 2); ?></h3>
                </div>
                <!-- Avg Repair Cost -->
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-2 bg-blue-50 rounded-lg text-primary">
                            <span class="material-symbols-outlined" data-icon="average_pace">avg_pace</span>
                        </div>
                        <span
                            class="text-[10px] font-bold text-slate-400 bg-slate-50 px-2 py-0.5 rounded-full">-2.1%</span>
                    </div>
                    <p class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1 text-left">Average Repair
                        Cost</p>
                    <h3 class="text-2xl font-black text-slate-900 text-left">₱<?php echo number_format($avgRepairCost, 2); ?></h3>
                </div>
                <!-- Tech Productivity -->
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-2 bg-blue-50 rounded-lg text-primary">
                            <span class="material-symbols-outlined" data-icon="bolt">bolt</span>
                        </div>
                        <span
                            class="text-[10px] font-bold <?php echo $productivity >= 80 ? 'text-green-600 bg-green-50' : 'text-orange-600 bg-orange-50'; ?> px-2 py-0.5 rounded-full"><?php echo $productivity >= 80 ? '+4.2%' : '⚠'; ?></span>
                    </div>
                    <p class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1 text-left">Job Completion
                        Rate</p>
                    <h3 class="text-2xl font-black text-slate-900 text-left"><?php echo $productivity; ?>%</h3>
                </div>
                <!-- Total Jobs -->
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-2 bg-blue-50 rounded-lg text-primary">
                            <span class="material-symbols-outlined"
                                data-icon="assessment">assessment</span>
                        </div>
                        <span
                            class="text-[10px] font-bold text-slate-400 bg-slate-50 px-2 py-0.5 rounded-full">Total</span>
                    </div>
                    <p class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1 text-left">Total Repair Jobs</p>
                    <h3 class="text-2xl font-black text-slate-900 text-left"><?php echo $totalJobs; ?></h3>
                </div>
            </div>
            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
                    <div class="flex justify-between items-center mb-6">
                        <h4 class="text-sm font-bold uppercase tracking-widest text-slate-400 text-left">Jobs by Status</h4>
                        <span class="material-symbols-outlined text-slate-300">more_horiz</span>
                    </div>
                    <div class="space-y-4">
                        <?php foreach ($statusData as $status): 
                            $percentage = $maxJobs > 0 ? ($status['count'] / $maxJobs) * 100 : 0;
                            $colors = [
                                'Queued' => 'primary',
                                'In Progress' => 'blue-500',
                                'Diagnostics' => 'purple-500',
                                'Waiting for Parts' => 'orange-500',
                                'Quality Check' => 'yellow-500',
                                'Ready for Pickup' => 'green-500',
                                'Completed' => 'emerald-500',
                                'Cancelled' => 'red-500'
                            ];
                            $colorClass = $colors[$status['job_status']] ?? 'primary';
                        ?>
                        <div class="group">
                            <div class="flex justify-between text-xs font-bold mb-1.5">
                                <span class="text-slate-600"><?php echo htmlspecialchars($status['job_status']); ?></span>
                                <span class="text-slate-400"><?php echo $status['count']; ?> jobs</span>
                            </div>
                            <div class="w-full bg-slate-50 h-3 rounded-full overflow-hidden">
                                <div class="bg-<?php echo $colorClass; ?> h-full rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm relative overflow-hidden">
                    <div class="flex justify-between items-center mb-6">
                        <h4 class="text-sm font-bold uppercase tracking-widest text-slate-400 text-left">Revenue Trends
                        </h4>
                    </div>
                    <div class="h-48 flex items-end justify-start gap-1 relative z-10">
                        <?php 
                        if (count($chartData) > 0) {
                            $maxRevenue = max($chartData);
                            foreach ($chartData as $revenue) {
                                $height = $maxRevenue > 0 ? ($revenue / $maxRevenue) * 100 : 0;
                        ?>
                        <div class="flex-1 bg-blue-500 rounded-t-sm" style="height: <?php echo $height; ?>%; min-height: 20px;"></div>
                        <?php } } ?>
                    </div>
                    <div class="flex justify-start gap-1 mt-2 text-[10px] font-bold text-slate-400 flex-wrap">
                        <?php foreach (array_slice($chartLabels, -7) as $label): ?>
                        <div class="flex-1 text-center"><?php echo $label; ?></div>
                        <?php endforeach; ?>
                    </div>
                    <div class="absolute inset-0 opacity-[0.03] pointer-events-none"
                        style="background-image: radial-gradient(#1152d4 1px, transparent 1px); background-size: 20px 20px;">
                    </div>
                </div>
            </div>
            <!-- Staff Performance Table -->
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden mb-8">
                <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center">
                    <h4 class="text-sm font-bold uppercase tracking-widest text-slate-900 text-left">Staff Performance
                        Rankings</h4>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-slate-50/50">
                                <th
                                    class="px-6 py-3 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-left">
                                    Technician Name</th>
                                <th
                                    class="px-6 py-3 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-left">
                                    Completed Jobs</th>
                                <th
                                    class="px-6 py-3 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-left">
                                    Success Rate</th>
                                <th
                                    class="px-6 py-3 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-left">
                                    Revenue Generated</th>
                                <th
                                    class="px-6 py-3 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-left">
                                    Avg Hours</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($techData as $tech): 
                                $successRate = $tech['completed_jobs'] > 0 ? round(($tech['successful_jobs'] / $tech['completed_jobs']) * 100, 1) : 0;
                                $avgHours = $tech['avg_hours_per_job'] ? round($tech['avg_hours_per_job'], 1) : 0;
                            ?>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="text-left">
                                        <p class="text-sm font-bold text-slate-900"><?php echo htmlspecialchars($tech['assigned_technician']); ?></p>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm font-medium text-slate-600 text-left"><?php echo $tech['completed_jobs']; ?> Jobs</td>
                                <td class="px-6 py-4 text-left">
                                    <div class="flex items-center gap-2">
                                        <div class="w-16 bg-slate-100 h-1.5 rounded-full overflow-hidden">
                                            <div class="bg-green-500 h-full" style="width: <?php echo $successRate; ?>%"></div>
                                        </div>
                                        <span class="text-xs font-bold text-green-600"><?php echo $successRate; ?>%</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm font-black text-slate-900 text-left">₱<?php echo number_format($tech['revenue_generated'], 2); ?></td>
                                <td class="px-6 py-4 text-sm font-medium text-slate-600 text-left"><?php echo $avgHours; ?>h</td>
                            </tr>
                            <?php endforeach; 
                            if (count($techData) === 0): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-slate-500">
                                    No technician data available for the selected period.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
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