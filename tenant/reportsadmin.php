<?php
session_start();
require_once __DIR__ . "/../db.php";
include __DIR__ . '/../session_security.php';
include __DIR__ . '/access_control.php';
include __DIR__ . '/../log_helper.php';

$tenantID = isset($_SESSION['tenantID']) ? (int) $_SESSION['tenantID'] : 0;

if ($tenantID === 0) {
    header("Location: tenantlogin.php");
    exit();
}

enforceModuleAccess($tenantID, basename(__FILE__));

$accessibleModules = getAccessibleModules($tenantID);
$isStaffUser = isset($_SESSION['userType']) && $_SESSION['userType'] === 'staff';

function canAccessModule($moduleFile, $accessibleModules)
{
    return in_array($moduleFile, $accessibleModules);
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function money($value)
{
    return '₱' . number_format((float) $value, 2);
}

function percentChange($current, $previous)
{
    $current = (float) $current;
    $previous = (float) $previous;

    if ($previous == 0 && $current == 0) {
        return 0;
    }

    if ($previous == 0) {
        return 100;
    }

    return round((($current - $previous) / abs($previous)) * 100, 1);
}

function getDateRangeFromRequest()
{
    $range = $_GET['date_range'] ?? 'last_30_days';
    $customStart = $_GET['start_date'] ?? '';
    $customEnd = $_GET['end_date'] ?? '';

    $end = new DateTime();
    $start = new DateTime();

    switch ($range) {
        case 'today':
            $start = new DateTime('today');
            $end = new DateTime('today');
            break;
        case 'last_7_days':
            $start->modify('-6 days');
            break;
        case 'last_90_days':
            $start->modify('-89 days');
            break;
        case 'this_month':
            $start = new DateTime('first day of this month');
            $end = new DateTime('last day of this month');
            break;
        case 'last_month':
            $start = new DateTime('first day of last month');
            $end = new DateTime('last day of last month');
            break;
        case 'year_to_date':
            $start->setDate((int) $end->format('Y'), 1, 1);
            break;
        case 'custom':
            if ($customStart !== '' && $customEnd !== '') {
                $start = new DateTime($customStart);
                $end = new DateTime($customEnd);
            } else {
                $range = 'last_30_days';
                $start = new DateTime();
                $start->modify('-29 days');
                $end = new DateTime();
            }
            break;
        case 'last_30_days':
        default:
            $range = 'last_30_days';
            $start->modify('-29 days');
            break;
    }

    if ($start > $end) {
        $temp = $start;
        $start = $end;
        $end = $temp;
    }

    return [
        'range' => $range,
        'start' => $start->format('Y-m-d'),
        'end' => $end->format('Y-m-d'),
    ];
}

function previousDateRange($startDate, $endDate)
{
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $days = (int) $start->diff($end)->format('%a') + 1;

    $prevEnd = clone $start;
    $prevEnd->modify('-1 day');

    $prevStart = clone $prevEnd;
    $prevStart->modify('-' . ($days - 1) . ' days');

    return [
        'start' => $prevStart->format('Y-m-d'),
        'end' => $prevEnd->format('Y-m-d'),
    ];
}

function fetchSingleRow(mysqli $conn, string $sql, string $types, array $params)
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : [];
    $stmt->close();

    return $row ?: [];
}

function fetchRows(mysqli $conn, string $sql, string $types, array $params)
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];

    while ($result && $row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

$loggedInUserName = '';
$loggedInUserRole = '';

if (($_SESSION['userType'] ?? '') === 'owner') {
    $loggedInUserName = $_SESSION['shopName'] ?? 'Shop Owner';
    $loggedInUserRole = 'Administrator';
} else {
    $loggedInUserName = trim(($_SESSION['firstName'] ?? '') . ' ' . ($_SESSION['lastName'] ?? ''));
    $loggedInUserName = $loggedInUserName !== '' ? $loggedInUserName : 'User';
    $loggedInUserRole = $_SESSION['userRole'] ?? 'Staff Member';
}

$shopName = $_SESSION['shopName'] ?? 'Repair Shop';
$ownerRow = fetchSingleRow(
    $conn,
    'SELECT shopName FROM owners WHERE tenantID = ? LIMIT 1',
    'i',
    [$tenantID]
);
if (!empty($ownerRow['shopName'])) {
    $shopName = $ownerRow['shopName'];
}

$dateInfo = getDateRangeFromRequest();
$dateRange = $dateInfo['range'];
$startDateStr = $dateInfo['start'];
$endDateStr = $dateInfo['end'];
$previousRange = previousDateRange($startDateStr, $endDateStr);

$exportSections = $_GET['sections'] ?? ['summary', 'revenue', 'appointments', 'payments', 'technicians'];
if (!is_array($exportSections)) {
    $exportSections = [$exportSections];
}
$allowedSections = ['summary', 'revenue', 'appointments', 'payments', 'technicians', 'recent'];
$exportSections = array_values(array_intersect($allowedSections, $exportSections));
if (count($exportSections) === 0) {
    $exportSections = ['summary', 'revenue', 'appointments', 'payments', 'technicians'];
}

$paymentMethodFilter = $_GET['payment_method'] ?? '';
$paymentStatusFilter = $_GET['payment_status'] ?? '';
$appointmentStatusFilter = $_GET['appointment_status'] ?? '';

$validPaymentMethods = ['', 'Cash', 'GCash', 'Card', 'Bank Transfer'];
$validPaymentStatuses = ['', 'Pending', 'Partial', 'Paid', 'Failed', 'Refunded'];
$validAppointmentStatuses = ['', 'Pending', 'Confirmed', 'For Diagnosis', 'Diagnosing', 'For Approval', 'In Progress', 'Completed', 'Cancelled'];

if (!in_array($paymentMethodFilter, $validPaymentMethods, true)) {
    $paymentMethodFilter = '';
}
if (!in_array($paymentStatusFilter, $validPaymentStatuses, true)) {
    $paymentStatusFilter = '';
}
if (!in_array($appointmentStatusFilter, $validAppointmentStatuses, true)) {
    $appointmentStatusFilter = '';
}

log_event(
    $conn,
    'VIEW Reports',
    'report',
    null,
    'Viewed reports from ' . $startDateStr . ' to ' . $endDateStr
);

$jobMetrics = fetchSingleRow(
    $conn,
    "SELECT
        COUNT(*) AS total_jobs,
        COALESCE(SUM(grand_total), 0) AS job_revenue,
        COALESCE(AVG(NULLIF(grand_total, 0)), 0) AS avg_repair_cost,
        SUM(CASE WHEN job_status = 'Completed' THEN 1 ELSE 0 END) AS completed_jobs,
        SUM(CASE WHEN job_status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled_jobs,
        SUM(CASE WHEN job_status NOT IN ('Completed','Cancelled') THEN 1 ELSE 0 END) AS active_jobs
     FROM repair_jobs
     WHERE tenantID = ? AND DATE(created_at) BETWEEN ? AND ?",
    'iss',
    [$tenantID, $startDateStr, $endDateStr]
);

$prevJobMetrics = fetchSingleRow(
    $conn,
    "SELECT
        COUNT(*) AS total_jobs,
        COALESCE(SUM(grand_total), 0) AS job_revenue,
        COALESCE(AVG(NULLIF(grand_total, 0)), 0) AS avg_repair_cost,
        SUM(CASE WHEN job_status = 'Completed' THEN 1 ELSE 0 END) AS completed_jobs,
        SUM(CASE WHEN job_status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled_jobs
     FROM repair_jobs
     WHERE tenantID = ? AND DATE(created_at) BETWEEN ? AND ?",
    'iss',
    [$tenantID, $previousRange['start'], $previousRange['end']]
);

$paymentWhere = "tenantID = ? AND DATE(paymentDate) BETWEEN ? AND ?";
$paymentTypes = "iss";
$paymentParams = [$tenantID, $startDateStr, $endDateStr];

if ($paymentMethodFilter !== '') {
    $paymentWhere .= " AND paymentMethod = ?";
    $paymentTypes .= "s";
    $paymentParams[] = $paymentMethodFilter;
}

if ($paymentStatusFilter !== '') {
    $paymentWhere .= " AND paymentStatus = ?";
    $paymentTypes .= "s";
    $paymentParams[] = $paymentStatusFilter;
}

$paymentMetrics = fetchSingleRow(
    $conn,
    "SELECT
        COUNT(*) AS total_payments,
        COALESCE(SUM(grand_total), 0) AS billed_total,
        COALESCE(SUM(amountPaid), 0) AS collected_total,
        COALESCE(SUM(balance), 0) AS outstanding_balance,
        SUM(CASE WHEN paymentStatus = 'Paid' THEN 1 ELSE 0 END) AS paid_count,
        SUM(CASE WHEN paymentStatus = 'Partial' THEN 1 ELSE 0 END) AS partial_count,
        SUM(CASE WHEN paymentStatus = 'Pending' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN paymentStatus IN ('Failed','Refunded') THEN 1 ELSE 0 END) AS failed_refunded_count
     FROM payments
     WHERE $paymentWhere",
    $paymentTypes,
    $paymentParams
);

$prevPaymentMetrics = fetchSingleRow(
    $conn,
    "SELECT
        COUNT(*) AS total_payments,
        COALESCE(SUM(grand_total), 0) AS billed_total,
        COALESCE(SUM(amountPaid), 0) AS collected_total,
        COALESCE(SUM(balance), 0) AS outstanding_balance
     FROM payments
     WHERE tenantID = ? AND DATE(paymentDate) BETWEEN ? AND ?",
    'iss',
    [$tenantID, $previousRange['start'], $previousRange['end']]
);

$appointmentWhere = "tenantID = ? AND appointment_date BETWEEN ? AND ?";
$appointmentTypes = "iss";
$appointmentParams = [$tenantID, $startDateStr, $endDateStr];

if ($appointmentStatusFilter !== '') {
    $appointmentWhere .= " AND status = ?";
    $appointmentTypes .= "s";
    $appointmentParams[] = $appointmentStatusFilter;
}

$appointmentMetrics = fetchSingleRow(
    $conn,
    "SELECT
        COUNT(*) AS total_appointments,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed_appointments,
        SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled_appointments,
        SUM(CASE WHEN status IN ('Pending','Confirmed','For Diagnosis','Diagnosing','For Approval','In Progress') THEN 1 ELSE 0 END) AS active_appointments,
        COALESCE(SUM(total_amount), 0) AS appointment_value
     FROM appointments
     WHERE $appointmentWhere",
    $appointmentTypes,
    $appointmentParams
);

$prevAppointmentMetrics = fetchSingleRow(
    $conn,
    "SELECT
        COUNT(*) AS total_appointments,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed_appointments,
        SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled_appointments,
        COALESCE(SUM(total_amount), 0) AS appointment_value
     FROM appointments
     WHERE tenantID = ? AND appointment_date BETWEEN ? AND ?",
    'iss',
    [$tenantID, $previousRange['start'], $previousRange['end']]
);

$monthlyRevenue = fetchRows(
    $conn,
    "SELECT
        DATE_FORMAT(paymentDate, '%Y-%m') AS month_key,
        DATE_FORMAT(paymentDate, '%b %Y') AS label,
        COALESCE(SUM(amountPaid), 0) AS collected,
        COALESCE(SUM(grand_total), 0) AS billed,
        COUNT(*) AS payment_count
     FROM payments
     WHERE $paymentWhere
     GROUP BY DATE_FORMAT(paymentDate, '%Y-%m'), DATE_FORMAT(paymentDate, '%b %Y')
     ORDER BY month_key ASC",
    $paymentTypes,
    $paymentParams
);

$monthlyAppointments = fetchRows(
    $conn,
    "SELECT
        DATE_FORMAT(appointment_date, '%Y-%m') AS month_key,
        COUNT(*) AS appointment_count,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed_count,
        SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled_count
     FROM appointments
     WHERE $appointmentWhere
     GROUP BY DATE_FORMAT(appointment_date, '%Y-%m')
     ORDER BY month_key ASC",
    $appointmentTypes,
    $appointmentParams
);

$appointmentStatusData = fetchRows(
    $conn,
    "SELECT status, COUNT(*) AS total
     FROM appointments
     WHERE $appointmentWhere
     GROUP BY status
     ORDER BY total DESC",
    $appointmentTypes,
    $appointmentParams
);

$paymentStatusData = fetchRows(
    $conn,
    "SELECT paymentStatus, COUNT(*) AS total, COALESCE(SUM(amountPaid), 0) AS collected
     FROM payments
     WHERE $paymentWhere
     GROUP BY paymentStatus
     ORDER BY total DESC",
    $paymentTypes,
    $paymentParams
);

$paymentMethodData = fetchRows(
    $conn,
    "SELECT paymentMethod, COUNT(*) AS total, COALESCE(SUM(amountPaid), 0) AS collected
     FROM payments
     WHERE $paymentWhere
     GROUP BY paymentMethod
     ORDER BY collected DESC",
    $paymentTypes,
    $paymentParams
);

$jobStatusData = fetchRows(
    $conn,
    "SELECT job_status, COUNT(*) AS total, COALESCE(SUM(grand_total), 0) AS revenue
     FROM repair_jobs
     WHERE tenantID = ? AND DATE(created_at) BETWEEN ? AND ?
     GROUP BY job_status
     ORDER BY total DESC",
    'iss',
    [$tenantID, $startDateStr, $endDateStr]
);

$techData = fetchRows(
    $conn,
    "SELECT
        COALESCE(NULLIF(assigned_technician, ''), 'Unassigned') AS assigned_technician,
        COUNT(*) AS total_jobs,
        SUM(CASE WHEN job_status = 'Completed' THEN 1 ELSE 0 END) AS completed_jobs,
        COALESCE(SUM(grand_total), 0) AS revenue_generated,
        COALESCE(AVG(CASE WHEN work_started_at IS NOT NULL AND completed_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, work_started_at, completed_at) END), 0) AS avg_hours_per_job
     FROM repair_jobs
     WHERE tenantID = ? AND DATE(created_at) BETWEEN ? AND ?
     GROUP BY COALESCE(NULLIF(assigned_technician, ''), 'Unassigned')
     ORDER BY revenue_generated DESC, total_jobs DESC
     LIMIT 10",
    'iss',
    [$tenantID, $startDateStr, $endDateStr]
);

$recentPayments = fetchRows(
    $conn,
    "SELECT
        payment_id,
        repair_job_id,
        appointment_id,
        paymentMethod,
        paymentStatus,
        grand_total,
        amountPaid,
        balance,
        paymentDate,
        referenceNumber
     FROM payments
     WHERE $paymentWhere
     ORDER BY paymentDate DESC, payment_id DESC
     LIMIT 12",
    $paymentTypes,
    $paymentParams
);

$recentAppointments = fetchRows(
    $conn,
    "SELECT
        appointment_id,
        appointment_date,
        appointment_time,
        status,
        total_amount,
        notes
     FROM appointments
     WHERE $appointmentWhere
     ORDER BY appointment_date DESC, appointment_time DESC
     LIMIT 12",
    $appointmentTypes,
    $appointmentParams
);

$totalJobs = (int) ($jobMetrics['total_jobs'] ?? 0);
$completedJobs = (int) ($jobMetrics['completed_jobs'] ?? 0);
$cancelledJobs = (int) ($jobMetrics['cancelled_jobs'] ?? 0);
$completionRate = $totalJobs > 0 ? round(($completedJobs / $totalJobs) * 100, 1) : 0;
$cancellationRate = $totalJobs > 0 ? round(($cancelledJobs / $totalJobs) * 100, 1) : 0;

$totalAppointments = (int) ($appointmentMetrics['total_appointments'] ?? 0);
$completedAppointments = (int) ($appointmentMetrics['completed_appointments'] ?? 0);
$appointmentCompletionRate = $totalAppointments > 0 ? round(($completedAppointments / $totalAppointments) * 100, 1) : 0;

$collectionRate = (float) ($paymentMetrics['billed_total'] ?? 0) > 0
    ? round(((float) ($paymentMetrics['collected_total'] ?? 0) / (float) ($paymentMetrics['billed_total'] ?? 0)) * 100, 1)
    : 0;

$reportQuery = http_build_query([
    'date_range' => $dateRange,
    'start_date' => $startDateStr,
    'end_date' => $endDateStr,
    'payment_method' => $paymentMethodFilter,
    'payment_status' => $paymentStatusFilter,
    'appointment_status' => $appointmentStatusFilter,
]);

$chartMonthlyLabels = array_column($monthlyRevenue, 'label');
$chartMonthlyCollected = array_map('floatval', array_column($monthlyRevenue, 'collected'));
$chartMonthlyBilled = array_map('floatval', array_column($monthlyRevenue, 'billed'));

$appointmentStatusLabels = array_column($appointmentStatusData, 'status');
$appointmentStatusValues = array_map('intval', array_column($appointmentStatusData, 'total'));

$paymentMethodLabels = array_column($paymentMethodData, 'paymentMethod');
$paymentMethodValues = array_map('floatval', array_column($paymentMethodData, 'collected'));

$jobStatusLabels = array_column($jobStatusData, 'job_status');
$jobStatusValues = array_map('intval', array_column($jobStatusData, 'total'));

?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title><?php echo h($shopName); ?> - Reports Management</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&amp;display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            display: inline-block;
            line-height: 1;
            white-space: nowrap;
        }
        .report-table th, .report-table td {
            border: 1px solid #cbd5e1;
            padding: 7px 8px;
            font-size: 11px;
        }
        .report-table th {
            background: #0f5f9e;
            color: #fff;
            font-weight: 800;
            text-transform: uppercase;
        }
        .report-card {
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .chart-box {
            height: 260px;
        }
        @media print {
            aside, header, .no-print { display: none !important; }
            main { margin-left: 0 !important; }
            body { background: white !important; }
            #printArea { box-shadow: none !important; border: none !important; }
        }
    </style>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        primary: "#1152d4",
                        background: "#f6f6f8",
                        surface: "#f6f6f8",
                        "on-surface": "#0f172a",
                        "on-background": "#0f172a",
                        "on-surface-variant": "#64748b",
                        error: "#ef4444"
                    },
                    borderRadius: {
                        DEFAULT: "0.125rem",
                        lg: "0.25rem",
                        xl: "0.5rem",
                        full: "9999px"
                    }
                }
            }
        };
    </script>
</head>

<body class="bg-surface text-on-surface">
    <!-- Mobile Menu Toggle -->
    <div class="md:hidden fixed top-0 left-0 right-0 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 px-4 py-3 z-50 flex items-center justify-between">
        <button id="sidebarToggle" type="button" class="inline-flex items-center justify-center w-10 h-10 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
            <span class="material-symbols-outlined">menu</span>
        </button>
        <h2 class="text-lg font-bold truncate flex-1 ml-3"><?php echo h($shopName); ?></h2>
    </div>
    <div id="sidebarOverlay" class="hidden fixed inset-0 bg-black/50 z-30 md:hidden"></div>
    <aside id="sidebar" class="fixed md:fixed left-0 top-0 h-screen w-64 flex flex-col bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 z-40 -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out md:transition-none pt-16 md:pt-0 overflow-y-auto">
        <div class="p-6">
            <div class="flex items-center gap-3 mb-8">
                <div class="bg-primary rounded-lg p-2 text-white">
                    <span class="material-symbols-outlined">directions_car</span>
                </div>
                <div>
                    <h1 class="text-lg font-bold leading-none"><?php echo h($shopName); ?></h1>
                    <p class="text-xs text-slate-500 mt-1">Your Repair Shop</p>
                </div>
            </div>

            <nav class="space-y-1">
                <?php if (canAccessModule('dashboardadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" href="dashboardadmin.php">
                        <span class="material-symbols-outlined text-[22px]">dashboard</span>
                        <span class="font-medium">Dashboard</span>
                    </a>
                <?php endif; ?>

                <?php if (canAccessModule('repairjobsadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" href="repairjobsadmin.php">
                        <span class="material-symbols-outlined text-[22px]">build</span>
                        <span class="font-medium">Repair Jobs</span>
                    </a>
                <?php endif; ?>

                <?php if (canAccessModule('vehicleadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" href="vehicleadmin.php">
                        <span class="material-symbols-outlined text-[22px]">directions_car</span>
                        <span class="font-medium">Vehicles</span>
                    </a>
                <?php endif; ?>

                <?php if (canAccessModule('appointmentadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" href="appointmentadmin.php">
                        <span class="material-symbols-outlined text-[22px]">event</span>
                        <span class="font-medium">Appointments</span>
                    </a>
                <?php endif; ?>

                <?php if (canAccessModule('reportsadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-primary/10 text-primary font-bold" href="reportsadmin.php">
                        <span class="material-symbols-outlined text-[22px]">description</span>
                        <span class="font-medium">Reports</span>
                    </a>
                <?php endif; ?>

                <?php if (canAccessModule('inventoryadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" href="inventoryadmin.php">
                        <span class="material-symbols-outlined text-[22px]">inventory_2</span>
                        <span class="font-medium">Inventory</span>
                    </a>
                <?php endif; ?>

                <?php if (canAccessModule('customeradmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" href="customeradmin.php">
                        <span class="material-symbols-outlined text-[22px]">group</span>
                        <span class="font-medium">Customers</span>
                    </a>
                <?php endif; ?>

                <?php if (canAccessModule('paymentsadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" href="paymentsadmin.php">
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
                                    <a href="accountbillingadmin.php" class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors border-b border-slate-100 dark:border-slate-800">
                                        <span class="material-symbols-outlined text-[18px]">receipt_long</span>
                                        <span>Account Billing</span>
                                    </a>
                                <?php endif; ?>
                                <a href="websitecustomadmin.php" class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors border-b border-slate-100 dark:border-slate-800">
                                    <span class="material-symbols-outlined text-[18px]">palette</span>
                                    <span>Website Customizer</span>
                                </a>
                                <?php if (canAccessModule('settingsadmin.php', $accessibleModules)): ?>
                                    <a href="settingsadmin.php" class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                        <span class="material-symbols-outlined text-[18px]">settings</span>
                                        <span>Settings</span>
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
                <div class="size-10 rounded-full bg-slate-200 flex items-center justify-center overflow-hidden">
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

    <main class="ml-64 min-h-screen bg-background">
        <header class="sticky top-0 z-40 w-full border-b border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md flex items-center justify-between px-8 h-16">
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

        <div class="p-8 max-w-none">
            <div class="flex flex-col xl:flex-row xl:items-end justify-between gap-4 mb-8 no-print">
                <div>
                    <h1 class="text-[30px] font-black text-on-background tracking-tight">Performance Reports</h1>
                    <p class="text-on-surface-variant font-medium mt-1">
                        Generate visual PDF reports from appointments, payments, and repair jobs.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" onclick="window.print()" class="bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-5 py-2.5 rounded-lg font-bold text-sm flex items-center gap-2 transition-all shadow-sm">
                        <span class="material-symbols-outlined text-[18px]">print</span>
                        Print
                    </button>
                    <button type="button" onclick="exportReportPDF()" class="bg-primary hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg font-bold text-sm flex items-center gap-2 transition-all shadow-sm">
                        <span class="material-symbols-outlined text-[18px]">download</span>
                        Export PDF
                    </button>
                </div>
            </div>

            <form method="GET" class="bg-white border border-slate-200 rounded-xl p-5 mb-8 no-print">
                <div class="flex items-center justify-between gap-4 mb-4">
                    <div>
                        <h3 class="text-sm font-black uppercase tracking-widest text-slate-900">Report Filters</h3>
                        <p class="text-xs text-slate-500 mt-1">Choose what records and report sections will be included in the PDF export.</p>
                    </div>
                    <button type="submit" class="bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-slate-800">
                        Apply Filters
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-4">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Date Range</label>
                        <select name="date_range" id="dateRangeSelect" class="w-full bg-slate-50 border-slate-200 text-sm rounded-lg py-2.5">
                            <option value="today" <?php echo $dateRange === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="last_7_days" <?php echo $dateRange === 'last_7_days' ? 'selected' : ''; ?>>Last 7 Days</option>
                            <option value="last_30_days" <?php echo $dateRange === 'last_30_days' ? 'selected' : ''; ?>>Last 30 Days</option>
                            <option value="last_90_days" <?php echo $dateRange === 'last_90_days' ? 'selected' : ''; ?>>Last 90 Days</option>
                            <option value="this_month" <?php echo $dateRange === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                            <option value="last_month" <?php echo $dateRange === 'last_month' ? 'selected' : ''; ?>>Last Month</option>
                            <option value="year_to_date" <?php echo $dateRange === 'year_to_date' ? 'selected' : ''; ?>>Year to Date</option>
                            <option value="custom" <?php echo $dateRange === 'custom' ? 'selected' : ''; ?>>Custom</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Start Date</label>
                        <input type="date" name="start_date" value="<?php echo h($startDateStr); ?>" class="w-full bg-slate-50 border-slate-200 text-sm rounded-lg py-2.5" />
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">End Date</label>
                        <input type="date" name="end_date" value="<?php echo h($endDateStr); ?>" class="w-full bg-slate-50 border-slate-200 text-sm rounded-lg py-2.5" />
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Payment Method</label>
                        <select name="payment_method" class="w-full bg-slate-50 border-slate-200 text-sm rounded-lg py-2.5">
                            <?php foreach ($validPaymentMethods as $method): ?>
                                <option value="<?php echo h($method); ?>" <?php echo $paymentMethodFilter === $method ? 'selected' : ''; ?>>
                                    <?php echo $method === '' ? 'All Methods' : h($method); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Payment Status</label>
                        <select name="payment_status" class="w-full bg-slate-50 border-slate-200 text-sm rounded-lg py-2.5">
                            <?php foreach ($validPaymentStatuses as $status): ?>
                                <option value="<?php echo h($status); ?>" <?php echo $paymentStatusFilter === $status ? 'selected' : ''; ?>>
                                    <?php echo $status === '' ? 'All Statuses' : h($status); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Appointment Status</label>
                        <select name="appointment_status" class="w-full bg-slate-50 border-slate-200 text-sm rounded-lg py-2.5">
                            <?php foreach ($validAppointmentStatuses as $status): ?>
                                <option value="<?php echo h($status); ?>" <?php echo $appointmentStatusFilter === $status ? 'selected' : ''; ?>>
                                    <?php echo $status === '' ? 'All Statuses' : h($status); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mt-5">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">PDF Sections</label>
                    <div class="flex flex-wrap gap-2">
                        <?php
                        $sectionLabels = [
                            'summary' => 'Summary',
                            'revenue' => 'Revenue',
                            'appointments' => 'Appointments',
                            'payments' => 'Payments',
                            'technicians' => 'Technicians',
                            'recent' => 'Recent Records'
                        ];
                        foreach ($sectionLabels as $key => $label):
                        ?>
                            <label class="inline-flex items-center gap-2 bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-semibold text-slate-700">
                                <input type="checkbox" name="sections[]" value="<?php echo h($key); ?>" <?php echo in_array($key, $exportSections, true) ? 'checked' : ''; ?> class="rounded border-slate-300 text-primary focus:ring-primary" />
                                <?php echo h($label); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </form>

            <section id="printArea" class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="bg-[#0f5f9e] text-white px-8 py-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-2xl font-black tracking-tight"><?php echo h($shopName); ?> Performance Report</h2>
                            <p class="text-blue-100 text-sm mt-1">Report period: <?php echo h($startDateStr); ?> to <?php echo h($endDateStr); ?></p>
                        </div>
                        <div class="text-right text-xs text-blue-100">
                            <p>Generated by: <?php echo h($loggedInUserName); ?></p>
                            <p>Date generated: <?php echo date('Y-m-d h:i A'); ?></p>
                        </div>
                    </div>
                </div>

                <div class="p-6 bg-slate-50 border-b border-slate-200">
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                        <div class="report-card bg-white border border-slate-200 rounded-xl p-5">
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Collected Revenue</p>
                            <h3 class="text-2xl font-black text-slate-900 mt-1"><?php echo money($paymentMetrics['collected_total'] ?? 0); ?></h3>
                            <?php $revenueChange = percentChange($paymentMetrics['collected_total'] ?? 0, $prevPaymentMetrics['collected_total'] ?? 0); ?>
                            <p class="text-xs font-bold mt-2 <?php echo $revenueChange >= 0 ? 'text-emerald-600' : 'text-red-600'; ?>">
                                <?php echo $revenueChange >= 0 ? '+' : ''; ?><?php echo $revenueChange; ?>% vs previous period
                            </p>
                        </div>

                        <div class="report-card bg-white border border-slate-200 rounded-xl p-5">
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Repair Jobs</p>
                            <h3 class="text-2xl font-black text-slate-900 mt-1"><?php echo number_format($totalJobs); ?></h3>
                            <?php $jobChange = percentChange($jobMetrics['total_jobs'] ?? 0, $prevJobMetrics['total_jobs'] ?? 0); ?>
                            <p class="text-xs font-bold mt-2 <?php echo $jobChange >= 0 ? 'text-emerald-600' : 'text-red-600'; ?>">
                                <?php echo $jobChange >= 0 ? '+' : ''; ?><?php echo $jobChange; ?>% vs previous period
                            </p>
                        </div>

                        <div class="report-card bg-white border border-slate-200 rounded-xl p-5">
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Appointments</p>
                            <h3 class="text-2xl font-black text-slate-900 mt-1"><?php echo number_format($totalAppointments); ?></h3>
                            <?php $appointmentChange = percentChange($appointmentMetrics['total_appointments'] ?? 0, $prevAppointmentMetrics['total_appointments'] ?? 0); ?>
                            <p class="text-xs font-bold mt-2 <?php echo $appointmentChange >= 0 ? 'text-emerald-600' : 'text-red-600'; ?>">
                                <?php echo $appointmentChange >= 0 ? '+' : ''; ?><?php echo $appointmentChange; ?>% vs previous period
                            </p>
                        </div>

                        <div class="report-card bg-white border border-slate-200 rounded-xl p-5">
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Collection Rate</p>
                            <h3 class="text-2xl font-black text-slate-900 mt-1"><?php echo $collectionRate; ?>%</h3>
                            <p class="text-xs font-bold mt-2 text-slate-500">
                                Paid/collected amount versus total billed
                            </p>
                        </div>
                    </div>
                </div>

                <?php if (in_array('summary', $exportSections, true)): ?>
                <div class="p-6 report-card">
                    <h3 class="text-sm font-black uppercase tracking-widest text-slate-900 mb-4">Summary Comparison Table</h3>
                    <div class="overflow-x-auto">
                        <table class="report-table w-full border-collapse text-left">
                            <thead>
                                <tr>
                                    <th>Metric</th>
                                    <th>Current Period</th>
                                    <th>Previous Period</th>
                                    <th>Change</th>
                                    <th>Purpose</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Total Collected</td>
                                    <td><?php echo money($paymentMetrics['collected_total'] ?? 0); ?></td>
                                    <td><?php echo money($prevPaymentMetrics['collected_total'] ?? 0); ?></td>
                                    <td><?php echo percentChange($paymentMetrics['collected_total'] ?? 0, $prevPaymentMetrics['collected_total'] ?? 0); ?>%</td>
                                    <td>Compare sales collection performance.</td>
                                </tr>
                                <tr>
                                    <td>Total Billed</td>
                                    <td><?php echo money($paymentMetrics['billed_total'] ?? 0); ?></td>
                                    <td><?php echo money($prevPaymentMetrics['billed_total'] ?? 0); ?></td>
                                    <td><?php echo percentChange($paymentMetrics['billed_total'] ?? 0, $prevPaymentMetrics['billed_total'] ?? 0); ?>%</td>
                                    <td>Compare expected revenue from invoices.</td>
                                </tr>
                                <tr>
                                    <td>Repair Jobs</td>
                                    <td><?php echo number_format($totalJobs); ?></td>
                                    <td><?php echo number_format((int) ($prevJobMetrics['total_jobs'] ?? 0)); ?></td>
                                    <td><?php echo percentChange($jobMetrics['total_jobs'] ?? 0, $prevJobMetrics['total_jobs'] ?? 0); ?>%</td>
                                    <td>Compare workload volume.</td>
                                </tr>
                                <tr>
                                    <td>Appointments</td>
                                    <td><?php echo number_format($totalAppointments); ?></td>
                                    <td><?php echo number_format((int) ($prevAppointmentMetrics['total_appointments'] ?? 0)); ?></td>
                                    <td><?php echo percentChange($appointmentMetrics['total_appointments'] ?? 0, $prevAppointmentMetrics['total_appointments'] ?? 0); ?>%</td>
                                    <td>Compare customer booking activity.</td>
                                </tr>
                                <tr>
                                    <td>Job Completion Rate</td>
                                    <td><?php echo $completionRate; ?>%</td>
                                    <td><?php
                                        $prevTotalJobs = (int) ($prevJobMetrics['total_jobs'] ?? 0);
                                        $prevCompletedJobs = (int) ($prevJobMetrics['completed_jobs'] ?? 0);
                                        echo $prevTotalJobs > 0 ? round(($prevCompletedJobs / $prevTotalJobs) * 100, 1) : 0;
                                    ?>%</td>
                                    <td><?php echo percentChange($completionRate, $prevTotalJobs > 0 ? ($prevCompletedJobs / $prevTotalJobs) * 100 : 0); ?>%</td>
                                    <td>Compare service completion efficiency.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 px-6 pb-6">
                    <?php if (in_array('revenue', $exportSections, true)): ?>
                    <div class="report-card bg-white border border-slate-200 rounded-xl p-5">
                        <h3 class="text-sm font-black uppercase tracking-widest text-slate-900 mb-4">Revenue Bar Graph</h3>
                        <div class="chart-box">
                            <canvas id="monthlyRevenueChart"></canvas>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (in_array('appointments', $exportSections, true)): ?>
                    <div class="report-card bg-white border border-slate-200 rounded-xl p-5">
                        <h3 class="text-sm font-black uppercase tracking-widest text-slate-900 mb-4">Appointment Status Pie Graph</h3>
                        <div class="chart-box">
                            <canvas id="appointmentStatusChart"></canvas>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (in_array('payments', $exportSections, true)): ?>
                    <div class="report-card bg-white border border-slate-200 rounded-xl p-5">
                        <h3 class="text-sm font-black uppercase tracking-widest text-slate-900 mb-4">Payment Method Pie Graph</h3>
                        <div class="chart-box">
                            <canvas id="paymentMethodChart"></canvas>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (in_array('technicians', $exportSections, true)): ?>
                    <div class="report-card bg-white border border-slate-200 rounded-xl p-5">
                        <h3 class="text-sm font-black uppercase tracking-widest text-slate-900 mb-4">Repair Job Status Bar Graph</h3>
                        <div class="chart-box">
                            <canvas id="jobStatusChart"></canvas>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (in_array('revenue', $exportSections, true)): ?>
                <div class="p-6 report-card border-t border-slate-200">
                    <h3 class="text-sm font-black uppercase tracking-widest text-slate-900 mb-4">Monthly Revenue Analysis</h3>
                    <div class="overflow-x-auto">
                        <table class="report-table w-full border-collapse text-left">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Total Billed</th>
                                    <th>Total Collected</th>
                                    <th>No. of Payments</th>
                                    <th>Collection Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($monthlyRevenue) === 0): ?>
                                    <tr><td colspan="5" class="text-center">No revenue records found.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($monthlyRevenue as $row): ?>
                                    <?php $rowRate = (float) $row['billed'] > 0 ? round(((float) $row['collected'] / (float) $row['billed']) * 100, 1) : 0; ?>
                                    <tr>
                                        <td><?php echo h($row['label']); ?></td>
                                        <td><?php echo money($row['billed']); ?></td>
                                        <td><?php echo money($row['collected']); ?></td>
                                        <td><?php echo number_format((int) $row['payment_count']); ?></td>
                                        <td><?php echo $rowRate; ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (in_array('appointments', $exportSections, true)): ?>
                <div class="p-6 report-card border-t border-slate-200">
                    <h3 class="text-sm font-black uppercase tracking-widest text-slate-900 mb-4">Appointment Analysis</h3>
                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                        <div class="overflow-x-auto">
                            <table class="report-table w-full border-collapse text-left">
                                <thead>
                                    <tr>
                                        <th>Status</th>
                                        <th>Total</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($appointmentStatusData) === 0): ?>
                                        <tr><td colspan="3" class="text-center">No appointment records found.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($appointmentStatusData as $row): ?>
                                        <tr>
                                            <td><?php echo h($row['status']); ?></td>
                                            <td><?php echo number_format((int) $row['total']); ?></td>
                                            <td><?php echo $totalAppointments > 0 ? round(((int) $row['total'] / $totalAppointments) * 100, 1) : 0; ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="report-table w-full border-collapse text-left">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th>Appointments</th>
                                        <th>Completed</th>
                                        <th>Cancelled</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($monthlyAppointments) === 0): ?>
                                        <tr><td colspan="4" class="text-center">No monthly appointment records found.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($monthlyAppointments as $row): ?>
                                        <tr>
                                            <td><?php echo h($row['month_key']); ?></td>
                                            <td><?php echo number_format((int) $row['appointment_count']); ?></td>
                                            <td><?php echo number_format((int) $row['completed_count']); ?></td>
                                            <td><?php echo number_format((int) $row['cancelled_count']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (in_array('payments', $exportSections, true)): ?>
                <div class="p-6 report-card border-t border-slate-200">
                    <h3 class="text-sm font-black uppercase tracking-widest text-slate-900 mb-4">Payment Analysis</h3>
                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                        <div class="overflow-x-auto">
                            <table class="report-table w-full border-collapse text-left">
                                <thead>
                                    <tr>
                                        <th>Payment Status</th>
                                        <th>Total</th>
                                        <th>Collected</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($paymentStatusData) === 0): ?>
                                        <tr><td colspan="3" class="text-center">No payment status records found.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($paymentStatusData as $row): ?>
                                        <tr>
                                            <td><?php echo h($row['paymentStatus']); ?></td>
                                            <td><?php echo number_format((int) $row['total']); ?></td>
                                            <td><?php echo money($row['collected']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="report-table w-full border-collapse text-left">
                                <thead>
                                    <tr>
                                        <th>Payment Method</th>
                                        <th>Total</th>
                                        <th>Collected</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($paymentMethodData) === 0): ?>
                                        <tr><td colspan="3" class="text-center">No payment method records found.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($paymentMethodData as $row): ?>
                                        <tr>
                                            <td><?php echo h($row['paymentMethod']); ?></td>
                                            <td><?php echo number_format((int) $row['total']); ?></td>
                                            <td><?php echo money($row['collected']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (in_array('technicians', $exportSections, true)): ?>
                <div class="p-6 report-card border-t border-slate-200">
                    <h3 class="text-sm font-black uppercase tracking-widest text-slate-900 mb-4">Technician / Job Performance</h3>
                    <div class="overflow-x-auto">
                        <table class="report-table w-full border-collapse text-left">
                            <thead>
                                <tr>
                                    <th>Technician</th>
                                    <th>Total Jobs</th>
                                    <th>Completed Jobs</th>
                                    <th>Completion Rate</th>
                                    <th>Revenue Generated</th>
                                    <th>Average Hours</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($techData) === 0): ?>
                                    <tr><td colspan="6" class="text-center">No technician data available.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($techData as $tech): ?>
                                    <?php $techRate = (int) $tech['total_jobs'] > 0 ? round(((int) $tech['completed_jobs'] / (int) $tech['total_jobs']) * 100, 1) : 0; ?>
                                    <tr>
                                        <td><?php echo h($tech['assigned_technician']); ?></td>
                                        <td><?php echo number_format((int) $tech['total_jobs']); ?></td>
                                        <td><?php echo number_format((int) $tech['completed_jobs']); ?></td>
                                        <td><?php echo $techRate; ?>%</td>
                                        <td><?php echo money($tech['revenue_generated']); ?></td>
                                        <td><?php echo round((float) $tech['avg_hours_per_job'], 1); ?>h</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (in_array('recent', $exportSections, true)): ?>
                <div class="p-6 report-card border-t border-slate-200">
                    <h3 class="text-sm font-black uppercase tracking-widest text-slate-900 mb-4">Recent Records</h3>
                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                        <div class="overflow-x-auto">
                            <h4 class="font-black text-xs uppercase tracking-widest text-slate-500 mb-2">Recent Payments</h4>
                            <table class="report-table w-full border-collapse text-left">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Date</th>
                                        <th>Method</th>
                                        <th>Status</th>
                                        <th>Paid</th>
                                        <th>Balance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($recentPayments) === 0): ?>
                                        <tr><td colspan="6" class="text-center">No recent payments.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($recentPayments as $payment): ?>
                                        <tr>
                                            <td>#<?php echo (int) $payment['payment_id']; ?></td>
                                            <td><?php echo h(date('Y-m-d', strtotime($payment['paymentDate']))); ?></td>
                                            <td><?php echo h($payment['paymentMethod']); ?></td>
                                            <td><?php echo h($payment['paymentStatus']); ?></td>
                                            <td><?php echo money($payment['amountPaid']); ?></td>
                                            <td><?php echo money($payment['balance']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="overflow-x-auto">
                            <h4 class="font-black text-xs uppercase tracking-widest text-slate-500 mb-2">Recent Appointments</h4>
                            <table class="report-table w-full border-collapse text-left">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Status</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($recentAppointments) === 0): ?>
                                        <tr><td colspan="5" class="text-center">No recent appointments.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($recentAppointments as $appointment): ?>
                                        <tr>
                                            <td>#<?php echo (int) $appointment['appointment_id']; ?></td>
                                            <td><?php echo h($appointment['appointment_date']); ?></td>
                                            <td><?php echo h(date('h:i A', strtotime($appointment['appointment_time']))); ?></td>
                                            <td><?php echo h($appointment['status']); ?></td>
                                            <td><?php echo money($appointment['total_amount']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 text-xs text-slate-500">
                    <p>
                        This report uses important comparable records from payments, appointments, and repair jobs.
                        Use the same date range filters in future exports to compare period performance accurately.
                    </p>
                </div>
            </section>
        </div>
    </main>

    <script>
        const chartColors = [
            '#1152d4', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444',
            '#8b5cf6', '#14b8a6', '#f97316', '#64748b', '#22c55e'
        ];

        const monthlyLabels = <?php echo json_encode($chartMonthlyLabels); ?>;
        const monthlyCollected = <?php echo json_encode($chartMonthlyCollected); ?>;
        const monthlyBilled = <?php echo json_encode($chartMonthlyBilled); ?>;

        const appointmentStatusLabels = <?php echo json_encode($appointmentStatusLabels); ?>;
        const appointmentStatusValues = <?php echo json_encode($appointmentStatusValues); ?>;

        const paymentMethodLabels = <?php echo json_encode($paymentMethodLabels); ?>;
        const paymentMethodValues = <?php echo json_encode($paymentMethodValues); ?>;

        const jobStatusLabels = <?php echo json_encode($jobStatusLabels); ?>;
        const jobStatusValues = <?php echo json_encode($jobStatusValues); ?>;

        function makeBarChart(canvasId, labels, datasets) {
            const el = document.getElementById(canvasId);
            if (!el) return;

            new Chart(el, {
                type: 'bar',
                data: {
                    labels,
                    datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value >= 1000 ? '₱' + Number(value).toLocaleString() : value;
                                }
                            }
                        }
                    }
                }
            });
        }

        function makePieChart(canvasId, labels, values) {
            const el = document.getElementById(canvasId);
            if (!el) return;

            new Chart(el, {
                type: 'pie',
                data: {
                    labels,
                    datasets: [{
                        data: values,
                        backgroundColor: chartColors
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        }

        makeBarChart('monthlyRevenueChart', monthlyLabels, [
            {
                label: 'Billed',
                data: monthlyBilled,
                backgroundColor: '#93c5fd'
            },
            {
                label: 'Collected',
                data: monthlyCollected,
                backgroundColor: '#1152d4'
            }
        ]);

        makePieChart('appointmentStatusChart', appointmentStatusLabels, appointmentStatusValues);
        makePieChart('paymentMethodChart', paymentMethodLabels, paymentMethodValues);

        makeBarChart('jobStatusChart', jobStatusLabels, [
            {
                label: 'Repair Jobs',
                data: jobStatusValues,
                backgroundColor: '#10b981'
            }
        ]);

        function exportReportPDF() {
            const element = document.getElementById('printArea');
            const fileName = '<?php echo preg_replace('/[^A-Za-z0-9_-]/', '_', $shopName); ?>_report_<?php echo $startDateStr; ?>_to_<?php echo $endDateStr; ?>.pdf';

            const options = {
                margin: [8, 8, 8, 8],
                filename: fileName,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: {
                    scale: 2,
                    useCORS: true,
                    scrollY: 0
                },
                jsPDF: {
                    unit: 'mm',
                    format: 'a4',
                    orientation: 'landscape'
                },
                pagebreak: {
                    mode: ['avoid-all', 'css', 'legacy'],
                    avoid: ['.report-card']
                }
            };

            html2pdf().set(options).from(element).save();
        }

        document.querySelectorAll('.settings-dropdown-btn').forEach(button => {
            button.addEventListener('click', function (e) {
                e.preventDefault();
                const dropdown = document.querySelector('[data-dropdown="settings"].settings-dropdown');
                if (dropdown) dropdown.classList.toggle('hidden');
            });
        });

        document.addEventListener('click', function (e) {
            const dropdownBtn = document.querySelector('.settings-dropdown-btn');
            const dropdown = document.querySelector('[data-dropdown="settings"].settings-dropdown');
            if (dropdown && dropdownBtn && !dropdownBtn.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.add('hidden');
            }
        });
    </script>

    <script>
    (function() {
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const navLinks = document.querySelectorAll('aside a');

        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('-translate-x-full');
                sidebarOverlay.classList.toggle('hidden');
            });
        }

        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.add('-translate-x-full');
                sidebarOverlay.classList.add('hidden');
            });
        }

        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth < 768) {
                    sidebar.classList.add('-translate-x-full');
                    sidebarOverlay.classList.add('hidden');
                }
            });
        });

        window.addEventListener('resize', function() {
            if (window.innerWidth >= 768) {
                sidebar.classList.remove('-translate-x-full');
                sidebarOverlay.classList.add('hidden');
            }
        });
    })();
    </script>
</body>
</html>
