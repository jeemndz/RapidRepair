<?php
session_start();
require_once __DIR__ . '/../db.php';
include __DIR__ . '/../session_security.php';

if (!isset($_SESSION['tenantID'])) {
    header('Location: tenantlogin.php');
    exit;
}

$tenantID = (int) $_SESSION['tenantID'];


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
$shopName = !empty($owner['shopName']) ? $owner['shopName'] : 'AutoFix Pro';
$shopQuery = urlencode($loginSlug);
$currentScript = basename($_SERVER['PHP_SELF']);
if ($_SERVER['REQUEST_METHOD'] === 'GET' && (!isset($_GET['shop']) || trim((string) $_GET['shop']) !== $loginSlug)) {
    $redirectParams = $_GET;
    $redirectParams['shop'] = $loginSlug;
    header('Location: ' . $currentScript . '?' . http_build_query($redirectParams));
    exit;
}

if (empty($_SESSION['repairjobs_csrf'])) {
    $_SESSION['repairjobs_csrf'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['repairjobs_csrf'];

$jobStatuses = ['Queued', 'In Progress', 'Diagnostics', 'Waiting for Parts', 'Quality Check', 'Ready for Pickup', 'Completed', 'Cancelled'];
$serviceStatuses = ['Pending', 'In Progress', 'Paused', 'Completed', 'Cancelled'];
$priorityOptions = ['Low', 'Normal', 'High', 'Urgent'];

$search = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$jobStatusFilter = isset($_GET['job_status']) ? trim((string) $_GET['job_status']) : 'All';
$serviceStatusFilter = isset($_GET['service_status']) ? trim((string) $_GET['service_status']) : 'All';
$priorityFilter = isset($_GET['priority']) ? trim((string) $_GET['priority']) : 'All';
if ($jobStatusFilter !== 'All' && !in_array($jobStatusFilter, $jobStatuses, true)) {
    $jobStatusFilter = 'All';
}
if ($serviceStatusFilter !== 'All' && !in_array($serviceStatusFilter, $serviceStatuses, true)) {
    $serviceStatusFilter = 'All';
}
if ($priorityFilter !== 'All' && !in_array($priorityFilter, $priorityOptions, true)) {
    $priorityFilter = 'All';
}

$message = '';
$messageType = 'success';
if (isset($_GET['msg']) && $_GET['msg'] === 'job_updated') {
    $message = 'Repair job status updated successfully.';
} elseif (isset($_GET['msg']) && $_GET['msg'] === 'service_updated') {
    $message = 'Service status updated successfully.';
} elseif (isset($_GET['msg']) && $_GET['msg'] === 'error') {
    $message = 'Unable to process your request. Please try again.';
    $messageType = 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_job_status'])) {
    $postedToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    $repairJobId = isset($_POST['repair_job_id']) ? (int) $_POST['repair_job_id'] : 0;
    $newStatus = isset($_POST['job_status']) ? trim((string) $_POST['job_status']) : '';

    $redirectParams = [
        'shop' => $loginSlug,
        'q' => $search,
        'job_status' => $jobStatusFilter,
        'service_status' => $serviceStatusFilter,
        'priority' => $priorityFilter,
    ];

    if (!hash_equals($csrfToken, $postedToken) || $repairJobId <= 0 || !in_array($newStatus, $jobStatuses, true)) {
        $redirectParams['msg'] = 'error';
        header('Location: repairjobsadmin.php?' . http_build_query(array_filter($redirectParams, static fn($v) => $v !== '')));
        exit;
    }

    // Check if job is already completed - prevent status changes
    $checkCompletedStmt = mysqli_prepare(
        $conn,
        'SELECT job_status FROM repair_jobs WHERE repair_job_id = ? AND tenantID = ? LIMIT 1'
    );
    if ($checkCompletedStmt) {
        mysqli_stmt_bind_param($checkCompletedStmt, 'ii', $repairJobId, $tenantID);
        mysqli_stmt_execute($checkCompletedStmt);
        $checkCompletedResult = mysqli_stmt_get_result($checkCompletedStmt);
        if ($checkCompletedResult && $checkCompletedRow = mysqli_fetch_assoc($checkCompletedResult)) {
            if ($checkCompletedRow['job_status'] === 'Completed') {
                $redirectParams['msg'] = 'error';
                header('Location: repairjobsadmin.php?' . http_build_query(array_filter($redirectParams, static fn($v) => $v !== '')));
                mysqli_stmt_close($checkCompletedStmt);
                exit;
            }
        }
        mysqli_stmt_close($checkCompletedStmt);
    }

    $updateJobStmt = mysqli_prepare(
        $conn,
        'UPDATE repair_jobs
         SET job_status = ?, updated_at = CURRENT_TIMESTAMP,
             work_started_at = CASE WHEN ? = "In Progress" AND work_started_at IS NULL THEN NOW() ELSE work_started_at END,
             completed_at = CASE WHEN ? = "Completed" THEN NOW() ELSE completed_at END
         WHERE repair_job_id = ? AND tenantID = ?
         LIMIT 1'
    );

    if ($updateJobStmt) {
        mysqli_stmt_bind_param($updateJobStmt, 'sssii', $newStatus, $newStatus, $newStatus, $repairJobId, $tenantID);
        mysqli_stmt_execute($updateJobStmt);
        mysqli_stmt_close($updateJobStmt);
        $redirectParams['msg'] = 'job_updated';
    } else {
        $redirectParams['msg'] = 'error';
    }

    header('Location: repairjobsadmin.php?' . http_build_query(array_filter($redirectParams, static fn($v) => $v !== '')));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_service_status'])) {
    $postedToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    $repairJobServiceId = isset($_POST['repair_job_service_id']) ? (int) $_POST['repair_job_service_id'] : 0;
    $newServiceStatus = isset($_POST['service_status']) ? trim((string) $_POST['service_status']) : '';

    $redirectParams = [
        'shop' => $loginSlug,
        'q' => $search,
        'job_status' => $jobStatusFilter,
        'service_status' => $serviceStatusFilter,
        'priority' => $priorityFilter,
    ];

    if (!hash_equals($csrfToken, $postedToken) || $repairJobServiceId <= 0 || !in_array($newServiceStatus, $serviceStatuses, true)) {
        $redirectParams['msg'] = 'error';
        header('Location: repairjobsadmin.php?' . http_build_query(array_filter($redirectParams, static fn($v) => $v !== '')));
        exit;
    }

    $updateServiceStmt = mysqli_prepare(
        $conn,
        'UPDATE repair_job_services
         SET service_status = ?,
             actual_duration_minutes = CASE
                 WHEN ? = "Completed" AND actual_duration_minutes IS NULL AND estimated_duration_minutes IS NOT NULL THEN estimated_duration_minutes
                 ELSE actual_duration_minutes
             END,
             updated_at = CURRENT_TIMESTAMP
         WHERE repair_job_service_id = ? AND tenantID = ?
         LIMIT 1'
    );

    if ($updateServiceStmt) {
        mysqli_stmt_bind_param($updateServiceStmt, 'ssii', $newServiceStatus, $newServiceStatus, $repairJobServiceId, $tenantID);
        mysqli_stmt_execute($updateServiceStmt);
        mysqli_stmt_close($updateServiceStmt);
        $redirectParams['msg'] = 'service_updated';
    } else {
        $redirectParams['msg'] = 'error';
    }

    header('Location: repairjobsadmin.php?' . http_build_query(array_filter($redirectParams, static fn($v) => $v !== '')));
    exit;
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function statusBadgeClass(string $status): string
{
    return match ($status) {
        'Queued' => 'bg-slate-100 text-slate-700',
        'In Progress' => 'bg-blue-100 text-blue-700',
        'Diagnostics' => 'bg-indigo-100 text-indigo-700',
        'Waiting for Parts' => 'bg-amber-100 text-amber-700',
        'Quality Check' => 'bg-purple-100 text-purple-700',
        'Ready for Pickup' => 'bg-emerald-100 text-emerald-700',
        'Completed' => 'bg-green-100 text-green-700',
        'Cancelled' => 'bg-red-100 text-red-700',
        default => 'bg-slate-100 text-slate-700',
    };
}

function serviceStatusSelectClass(string $status): string
{
    return match ($status) {
        'Pending' => 'bg-amber-50 text-amber-800',
        'In Progress' => 'bg-blue-50 text-blue-800',
        'Paused' => 'bg-orange-50 text-orange-800',
        'Completed' => 'bg-emerald-50 text-emerald-800',
        'Cancelled' => 'bg-red-50 text-red-800',
        default => 'bg-slate-50 text-slate-800',
    };
}

function generateJobOrderNo(mysqli $conn, int $tenantID): string
{
    // Get the next sequential number for this tenant
    $countStmt = mysqli_prepare($conn, 'SELECT COUNT(*) as total FROM repair_jobs WHERE tenantID = ?');
    if (!$countStmt) {
        return 'RR-' . str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);
    }
    
    mysqli_stmt_bind_param($countStmt, 'i', $tenantID);
    mysqli_stmt_execute($countStmt);
    $countResult = mysqli_stmt_get_result($countStmt);
    $countRow = mysqli_fetch_assoc($countResult);
    mysqli_stmt_close($countStmt);
    
    $nextNumber = ((int) ($countRow['total'] ?? 0)) + 1;
    return 'RR-' . str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);
}

// Auto-move confirmed appointments into maintenance (repair jobs) if missing.
$confirmedSyncSql = "SELECT
        a.appointment_id,
        a.user_id,
        a.vehicle_id,
        a.notes,
        a.total_amount
    FROM appointments a
    WHERE a.tenantID = ?
      AND a.status = 'Confirmed'
      AND NOT EXISTS (
        SELECT 1
        FROM repair_jobs rj
        WHERE rj.tenantID = a.tenantID
          AND rj.appointment_id = a.appointment_id
      )";
$confirmedSyncStmt = mysqli_prepare($conn, $confirmedSyncSql);
if ($confirmedSyncStmt) {
    mysqli_stmt_bind_param($confirmedSyncStmt, 'i', $tenantID);
    mysqli_stmt_execute($confirmedSyncStmt);
    $confirmedSyncResult = mysqli_stmt_get_result($confirmedSyncStmt);

    while ($confirmedSyncResult && $appointmentRow = mysqli_fetch_assoc($confirmedSyncResult)) {
        $appointmentId = (int) ($appointmentRow['appointment_id'] ?? 0);
        $userId = (int) ($appointmentRow['user_id'] ?? 0);
        $vehicleId = (int) ($appointmentRow['vehicle_id'] ?? 0);
        $concern = trim((string) ($appointmentRow['notes'] ?? ''));
        $grandTotal = (float) ($appointmentRow['total_amount'] ?? 0);

        if ($appointmentId <= 0 || $userId <= 0 || $vehicleId <= 0) {
            continue;
        }

        $serviceItems = [];
        $servicesStmt = mysqli_prepare(
            $conn,
            'SELECT service_id, service_price, duration_minutes, notes
             FROM appointment_services
             WHERE appointment_id = ? AND tenantID = ?'
        );
        if ($servicesStmt) {
            mysqli_stmt_bind_param($servicesStmt, 'ii', $appointmentId, $tenantID);
            mysqli_stmt_execute($servicesStmt);
            $servicesResult = mysqli_stmt_get_result($servicesStmt);
            while ($servicesResult && $serviceRow = mysqli_fetch_assoc($servicesResult)) {
                $serviceItems[] = $serviceRow;
            }
            mysqli_stmt_close($servicesStmt);
        }

        if (count($serviceItems) === 0) {
            continue;
        }

        mysqli_begin_transaction($conn);
        $syncOk = true;

        $jobOrderNo = generateJobOrderNo($conn, $tenantID);
        $insertJobStmt = mysqli_prepare(
            $conn,
            'INSERT INTO repair_jobs
                (tenantID, appointment_id, user_id, vehicle_id, job_order_no, job_status, priority, concern, check_in_time, work_started_at, labor_total, parts_total, grand_total)
             VALUES (?, ?, ?, ?, ?, "In Progress", "Normal", ?, NOW(), NOW(), ?, 0.00, ?)'
        );
        if (!$insertJobStmt) {
            $syncOk = false;
        } else {
            $concernValue = $concern !== '' ? $concern : null;
            mysqli_stmt_bind_param(
                $insertJobStmt,
                'iiiissdd',
                $tenantID,
                $appointmentId,
                $userId,
                $vehicleId,
                $jobOrderNo,
                $concernValue,
                $grandTotal,
                $grandTotal
            );
            if (!mysqli_stmt_execute($insertJobStmt)) {
                $syncOk = false;
            }
            $repairJobId = (int) mysqli_insert_id($conn);
            mysqli_stmt_close($insertJobStmt);
        }

        if ($syncOk) {
            $insertJobServiceStmt = mysqli_prepare(
                $conn,
                'INSERT INTO repair_job_services
                    (repair_job_id, tenantID, service_id, service_price, estimated_duration_minutes, service_status, remarks)
                 VALUES (?, ?, ?, ?, ?, "In Progress", ?)'
            );

            if (!$insertJobServiceStmt) {
                $syncOk = false;
            } else {
                foreach ($serviceItems as $serviceItem) {
                    $serviceId = (int) ($serviceItem['service_id'] ?? 0);
                    if ($serviceId <= 0) {
                        continue;
                    }

                    $servicePrice = (float) ($serviceItem['service_price'] ?? 0);
                    $estimatedDuration = isset($serviceItem['duration_minutes']) ? (int) $serviceItem['duration_minutes'] : null;
                    $remarks = trim((string) ($serviceItem['notes'] ?? ''));
                    $remarksValue = $remarks !== '' ? $remarks : null;

                    mysqli_stmt_bind_param(
                        $insertJobServiceStmt,
                        'iiidis',
                        $repairJobId,
                        $tenantID,
                        $serviceId,
                        $servicePrice,
                        $estimatedDuration,
                        $remarksValue
                    );

                    if (!mysqli_stmt_execute($insertJobServiceStmt)) {
                        $syncOk = false;
                        break;
                    }
                }
                mysqli_stmt_close($insertJobServiceStmt);
            }
        }

        if ($syncOk) {
            $updateAppointmentStmt = mysqli_prepare(
                $conn,
                'UPDATE appointments
                 SET status = "In Progress", updated_at = NOW()
                 WHERE appointment_id = ? AND tenantID = ?
                 LIMIT 1'
            );
            if ($updateAppointmentStmt) {
                mysqli_stmt_bind_param($updateAppointmentStmt, 'ii', $appointmentId, $tenantID);
                mysqli_stmt_execute($updateAppointmentStmt);
                mysqli_stmt_close($updateAppointmentStmt);
            }
        }

        if ($syncOk) {
            mysqli_commit($conn);
        } else {
            mysqli_rollback($conn);
        }
    }

    mysqli_stmt_close($confirmedSyncStmt);
}

$stats = [
    'in_workshop' => 0,
    'waiting_parts' => 0,
    'ready_pickup' => 0,
    'avg_cycle_minutes' => 0.0,
];

$statsStmt = mysqli_prepare(
    $conn,
    "SELECT
        SUM(CASE WHEN job_status IN ('Queued','In Progress','Diagnostics','Waiting for Parts','Quality Check') THEN 1 ELSE 0 END) AS in_workshop,
        SUM(CASE WHEN job_status = 'Waiting for Parts' THEN 1 ELSE 0 END) AS waiting_parts,
        SUM(CASE WHEN job_status = 'Ready for Pickup' THEN 1 ELSE 0 END) AS ready_pickup,
        AVG(CASE WHEN work_started_at IS NOT NULL AND completed_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, work_started_at, completed_at) END) AS avg_cycle_minutes
     FROM repair_jobs
     WHERE tenantID = ?"
);
if ($statsStmt) {
    mysqli_stmt_bind_param($statsStmt, 'i', $tenantID);
    mysqli_stmt_execute($statsStmt);
    $statsResult = mysqli_stmt_get_result($statsStmt);
    if ($statsResult && $statsRow = mysqli_fetch_assoc($statsResult)) {
        $stats['in_workshop'] = (int) ($statsRow['in_workshop'] ?? 0);
        $stats['waiting_parts'] = (int) ($statsRow['waiting_parts'] ?? 0);
        $stats['ready_pickup'] = (int) ($statsRow['ready_pickup'] ?? 0);
        $stats['avg_cycle_minutes'] = (float) ($statsRow['avg_cycle_minutes'] ?? 0);
    }
    mysqli_stmt_close($statsStmt);
}

$searchLike = '%' . $search . '%';
$recordsPerPage = 5;
$upcomingPage = max(1, (int) ($_GET['upcoming_page'] ?? 1));
$progressPage = max(1, (int) ($_GET['progress_page'] ?? 1));
$jobsPage = max(1, (int) ($_GET['jobs_page'] ?? 1));

$upcomingTotalRows = 0;
$upcomingTotalPages = 1;
$upcomingOffset = 0;

$progressTotalRows = 0;
$progressTotalPages = 1;
$progressOffset = 0;

$jobsTotalRows = 0;
$jobsTotalPages = 1;
$jobsOffset = 0;

$progressRows = [];
$progressCountSql = "SELECT COUNT(*) AS total_rows
    FROM repair_jobs rj
    LEFT JOIN users u ON u.user_id = rj.user_id
    LEFT JOIN vehicleinformation v ON v.vehicle_id = rj.vehicle_id AND v.tenantID = rj.tenantID
    WHERE rj.tenantID = ?
      AND (? = 'All' OR rj.job_status = ?)
      AND (? = 'All' OR rj.priority = ?)
      AND (? = '' OR rj.job_order_no LIKE ? OR u.fullName LIKE ? OR v.brand LIKE ? OR v.model LIKE ? OR IFNULL(rj.concern, '') LIKE ?)";
$progressCountStmt = mysqli_prepare($conn, $progressCountSql);
if ($progressCountStmt) {
    mysqli_stmt_bind_param(
        $progressCountStmt,
        'issssssssss',
        $tenantID,
        $jobStatusFilter,
        $jobStatusFilter,
        $priorityFilter,
        $priorityFilter,
        $search,
        $searchLike,
        $searchLike,
        $searchLike,
        $searchLike,
        $searchLike
    );
    mysqli_stmt_execute($progressCountStmt);
    $progressCountResult = mysqli_stmt_get_result($progressCountStmt);
    if ($progressCountResult && $progressCountRow = mysqli_fetch_assoc($progressCountResult)) {
        $progressTotalRows = (int) ($progressCountRow['total_rows'] ?? 0);
    }
    mysqli_stmt_close($progressCountStmt);
}

$progressTotalPages = max(1, (int) ceil($progressTotalRows / $recordsPerPage));
if ($progressPage > $progressTotalPages) {
    $progressPage = $progressTotalPages;
}
$progressOffset = ($progressPage - 1) * $recordsPerPage;

$progressSql = "SELECT
                rj.repair_job_id,
        rj.job_order_no,
                rj.job_status,
        rj.bay_no,
        rj.assigned_technician,
        rj.priority,
                rj.concern,
                rj.progress_notes,
                rj.diagnosis_notes,
        COALESCE(u.fullName, CONCAT('User #', rj.user_id)) AS customer_name,
        v.year_model,
        v.brand,
        v.model,
                v.color
        FROM repair_jobs rj
    LEFT JOIN users u ON u.user_id = rj.user_id
    LEFT JOIN vehicleinformation v ON v.vehicle_id = rj.vehicle_id AND v.tenantID = rj.tenantID
        WHERE rj.tenantID = ?
            AND (? = 'All' OR rj.job_status = ?)
            AND (? = 'All' OR rj.priority = ?)
            AND (? = '' OR rj.job_order_no LIKE ? OR u.fullName LIKE ? OR v.brand LIKE ? OR v.model LIKE ? OR IFNULL(rj.concern, '') LIKE ?)
        ORDER BY rj.updated_at DESC
    LIMIT ?, ?";
$progressStmt = mysqli_prepare($conn, $progressSql);
if ($progressStmt) {
    mysqli_stmt_bind_param(
        $progressStmt,
        'issssssssssii',
        $tenantID,
        $jobStatusFilter,
        $jobStatusFilter,
        $priorityFilter,
        $priorityFilter,
        $search,
        $searchLike,
        $searchLike,
        $searchLike,
        $searchLike,
        $searchLike,
        $progressOffset,
        $recordsPerPage
    );
    mysqli_stmt_execute($progressStmt);
    $progressResult = mysqli_stmt_get_result($progressStmt);
    while ($progressResult && $row = mysqli_fetch_assoc($progressResult)) {
        $progressRows[] = $row;
    }
    mysqli_stmt_close($progressStmt);
}

$jobRows = [];
$jobsCountSql = "SELECT COUNT(*) AS total_rows
    FROM repair_jobs rj
    LEFT JOIN users u ON u.user_id = rj.user_id
    LEFT JOIN vehicleinformation v ON v.vehicle_id = rj.vehicle_id AND v.tenantID = rj.tenantID
    WHERE rj.tenantID = ?
      AND (? = 'All' OR rj.job_status = ?)
      AND (? = 'All' OR rj.priority = ?)
      AND (? = '' OR rj.job_order_no LIKE ? OR u.fullName LIKE ? OR v.brand LIKE ? OR v.model LIKE ? OR rj.assigned_technician LIKE ?)";
$jobsCountStmt = mysqli_prepare($conn, $jobsCountSql);
if ($jobsCountStmt) {
    mysqli_stmt_bind_param(
        $jobsCountStmt,
        'issssssssss',
        $tenantID,
        $jobStatusFilter,
        $jobStatusFilter,
        $priorityFilter,
        $priorityFilter,
        $search,
        $searchLike,
        $searchLike,
        $searchLike,
        $searchLike,
        $searchLike
    );
    mysqli_stmt_execute($jobsCountStmt);
    $jobsCountResult = mysqli_stmt_get_result($jobsCountStmt);
    if ($jobsCountResult && $jobsCountRow = mysqli_fetch_assoc($jobsCountResult)) {
        $jobsTotalRows = (int) ($jobsCountRow['total_rows'] ?? 0);
    }
    mysqli_stmt_close($jobsCountStmt);
}

$jobsTotalPages = max(1, (int) ceil($jobsTotalRows / $recordsPerPage));
if ($jobsPage > $jobsTotalPages) {
    $jobsPage = $jobsTotalPages;
}
$jobsOffset = ($jobsPage - 1) * $recordsPerPage;

$jobsSql = "SELECT
        rj.repair_job_id,
        rj.job_order_no,
        rj.job_status,
        rj.priority,
        rj.assigned_technician,
        rj.bay_no,
        rj.grand_total,
        rj.labor_total,
        rj.parts_total,
        COALESCE(u.fullName, CONCAT('User #', rj.user_id)) AS customer_name,
        v.year_model,
        v.brand,
        v.model,
        COALESCE(GROUP_CONCAT(DISTINCT s.service_name ORDER BY s.service_name SEPARATOR ', '), 'No services linked') AS services,
        COALESCE(SUM(rjs.actual_duration_minutes), 0) AS total_actual_minutes,
        COALESCE(SUM(rjs.estimated_duration_minutes), 0) AS total_estimated_minutes
    FROM repair_jobs rj
    LEFT JOIN users u ON u.user_id = rj.user_id
    LEFT JOIN vehicleinformation v ON v.vehicle_id = rj.vehicle_id AND v.tenantID = rj.tenantID
    LEFT JOIN repair_job_services rjs ON rjs.repair_job_id = rj.repair_job_id AND rjs.tenantID = rj.tenantID
    LEFT JOIN services s ON s.service_id = rjs.service_id AND s.tenantID = rj.tenantID
    WHERE rj.tenantID = ?
      AND (? = 'All' OR rj.job_status = ?)
            AND (? = 'All' OR rj.priority = ?)
      AND (? = '' OR rj.job_order_no LIKE ? OR u.fullName LIKE ? OR v.brand LIKE ? OR v.model LIKE ? OR rj.assigned_technician LIKE ?)
    GROUP BY
        rj.repair_job_id,
        rj.job_order_no,
        rj.job_status,
        rj.priority,
        rj.assigned_technician,
        rj.bay_no,
        rj.grand_total,
        rj.labor_total,
        rj.parts_total,
        u.fullName,
        rj.user_id,
        v.year_model,
        v.brand,
        v.model
    ORDER BY rj.updated_at DESC
    LIMIT ?, ?";
$jobsStmt = mysqli_prepare($conn, $jobsSql);
if ($jobsStmt) {
    mysqli_stmt_bind_param(
        $jobsStmt,
        'issssssssssii',
        $tenantID,
        $jobStatusFilter,
        $jobStatusFilter,
        $priorityFilter,
        $priorityFilter,
        $search,
        $searchLike,
        $searchLike,
        $searchLike,
        $searchLike,
        $searchLike,
        $jobsOffset,
        $recordsPerPage
    );
    mysqli_stmt_execute($jobsStmt);
    $jobsResult = mysqli_stmt_get_result($jobsStmt);
    while ($jobsResult && $job = mysqli_fetch_assoc($jobsResult)) {
        $jobRows[] = $job;
    }
    mysqli_stmt_close($jobsStmt);
}

$avgCycleHours = $stats['avg_cycle_minutes'] > 0 ? $stats['avg_cycle_minutes'] / 60 : 0;

$upcomingAppointments = [];
$upcomingCountStmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total_rows
     FROM appointments a
     WHERE a.tenantID = ?
       AND a.status IN ('Pending', 'Confirmed', 'In Progress')
       AND (a.appointment_date > CURDATE() OR (a.appointment_date = CURDATE() AND a.appointment_time >= CURTIME()))
       AND NOT EXISTS (
            SELECT 1
            FROM repair_jobs rj
            WHERE rj.appointment_id = a.appointment_id
              AND rj.tenantID = a.tenantID
              AND rj.job_status = 'Completed'
       )"
);
if ($upcomingCountStmt) {
    mysqli_stmt_bind_param($upcomingCountStmt, 'i', $tenantID);
    mysqli_stmt_execute($upcomingCountStmt);
    $upcomingCountResult = mysqli_stmt_get_result($upcomingCountStmt);
    if ($upcomingCountResult && $upcomingCountRow = mysqli_fetch_assoc($upcomingCountResult)) {
        $upcomingTotalRows = (int) ($upcomingCountRow['total_rows'] ?? 0);
    }
    mysqli_stmt_close($upcomingCountStmt);
}

$upcomingTotalPages = max(1, (int) ceil($upcomingTotalRows / $recordsPerPage));
if ($upcomingPage > $upcomingTotalPages) {
    $upcomingPage = $upcomingTotalPages;
}
$upcomingOffset = ($upcomingPage - 1) * $recordsPerPage;

$upcomingStmt = mysqli_prepare(
    $conn,
    "SELECT
        a.appointment_id,
        a.appointment_date,
        a.appointment_time,
        a.status,
        a.notes,
        a.total_amount,
        COALESCE(u.fullName, CONCAT('User #', a.user_id)) AS customer_name,
        CONCAT(IFNULL(v.year_model, ''), ' ', IFNULL(v.brand, ''), ' ', IFNULL(v.model, '')) AS vehicle_name,
        IFNULL(v.plate_number, '') AS plate_number
     FROM appointments a
     LEFT JOIN users u ON u.user_id = a.user_id
     LEFT JOIN vehicleinformation v ON v.vehicle_id = a.vehicle_id AND v.tenantID = a.tenantID
     WHERE a.tenantID = ?
       AND a.status IN ('Pending', 'Confirmed', 'In Progress')
       AND (a.appointment_date > CURDATE() OR (a.appointment_date = CURDATE() AND a.appointment_time >= CURTIME()))
       AND NOT EXISTS (
            SELECT 1
            FROM repair_jobs rj
            WHERE rj.appointment_id = a.appointment_id
              AND rj.tenantID = a.tenantID
              AND rj.job_status = 'Completed'
       )
     ORDER BY a.appointment_date ASC, a.appointment_time ASC
         LIMIT ?, ?"
);
if ($upcomingStmt) {
    mysqli_stmt_bind_param($upcomingStmt, 'iii', $tenantID, $upcomingOffset, $recordsPerPage);
    mysqli_stmt_execute($upcomingStmt);
    $upcomingResult = mysqli_stmt_get_result($upcomingStmt);
    while ($upcomingResult && $upcomingRow = mysqli_fetch_assoc($upcomingResult)) {
        $upcomingAppointments[] = $upcomingRow;
    }
    mysqli_stmt_close($upcomingStmt);
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
    <title><?php echo h($shopName); ?> | Repair Jobs</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&amp;display=swap"
        rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
        rel="stylesheet" />
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

<body class="bg-slate-50 text-slate-900 antialiased">
    <aside class="fixed left-0 top-0 bottom-0 w-64 border-r border-slate-200 bg-white z-50 flex flex-col h-full">
        <div class="p-6">
            <div class="flex items-center gap-3 mb-8">
                <div class="bg-blue-700 rounded-lg p-2 text-white">
                    <span class="material-symbols-outlined">directions_car</span>
                </div>
                <div>
                    <h1 class="text-lg font-bold leading-none"><?php echo h($shopName); ?></h1>
                    <p class="text-xs text-slate-500 mt-1">Repair Management</p>
                </div>
            </div>
            <nav class="space-y-1">
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors font-medium"
                    href="dashboardadmin.php?shop=<?php echo h($shopQuery); ?>"><span
                        class="material-symbols-outlined text-[22px]">dashboard</span>Dashboard</a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-blue-50 text-blue-700 font-medium"
                    href="repairjobsadmin.php?shop=<?php echo h($shopQuery); ?>"><span
                        class="material-symbols-outlined text-[22px]">build</span>Repair Jobs</a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="vehicleadmin.php?shop=<?php echo h($shopQuery); ?>"><span
                        class="material-symbols-outlined text-[22px]">directions_car</span>Vehicles</a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="appointmentadmin.php?shop=<?php echo h($shopQuery); ?>"><span
                        class="material-symbols-outlined text-[22px]">event</span>Appointments</a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="reportsadmin.php?shop=<?php echo h($shopQuery); ?>"><span
                        class="material-symbols-outlined text-[22px]">description</span>Reports</a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="inventoryadmin.php?shop=<?php echo h($shopQuery); ?>"><span
                        class="material-symbols-outlined text-[22px]">inventory_2</span>Inventory</a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="customeradmin.php?shop=<?php echo h($shopQuery); ?>"><span
                        class="material-symbols-outlined text-[22px]">group</span>Customers</a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="paymentsadmin.php?shop=<?php echo h($shopQuery); ?>"><span
                        class="material-symbols-outlined text-[22px]">payments</span>Payments</a>
                <div class="pt-4 mt-4 border-t border-slate-100">
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                        href="settingsadmin.php?shop=<?php echo h($shopQuery); ?>"><span
                            class="material-symbols-outlined text-[22px]">settings</span>Settings</a>
                </div>
            </nav>
        </div>
        <div class="mt-auto w-full p-4 border-t border-slate-200">
            <div class="flex items-center gap-3">
                <div
                    class="w-10 h-10 rounded-full bg-slate-200 flex items-center justify-center overflow-hidden shrink-0">
                    <span class="material-symbols-outlined text-slate-500">person</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold truncate"><?php echo h($shopName); ?></p>
                    <p class="text-xs text-slate-500 truncate">Service Lead</p>
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

    <main class="ml-64 min-h-screen bg-slate-50">
        <header
            class="sticky top-0 z-40 w-full border-b border-slate-200 bg-white/90 backdrop-blur-md flex items-center justify-between px-8 h-16">
            <div class="flex items-center gap-6">
                <h2 class="text-lg font-black tracking-tight">Repair Jobs Management</h2>
                <span
                    class="hidden xl:inline-flex items-center px-2.5 py-1 rounded-full bg-blue-50 text-blue-700 text-[11px] font-bold uppercase tracking-wide"><?php echo h($loginSlug); ?></span>
            </div>
        </header>

        <div class="px-8 pb-12 pt-8">
            <?php if ($message !== ''): ?>
                <div
                    class="mb-6 rounded-lg border px-4 py-3 text-sm font-medium <?php echo $messageType === 'error' ? 'border-red-200 bg-red-50 text-red-700' : 'border-green-200 bg-green-50 text-green-700'; ?>">
                    <?php echo h($message); ?>
                </div>
            <?php endif; ?>

            <div class="mb-8 flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
                <div>
                    <h2 class="text-3xl font-black tracking-tight">Repair Jobs</h2>
                    <p class="text-slate-600 font-medium mt-1">Real-time floor management and job tracking.</p>
                </div>
                <p class="text-xs font-semibold text-slate-500">Filter controls are in the table panel below.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">In Workshop</p>
                    <p class="text-2xl font-black mt-2"><?php echo number_format($stats['in_workshop']); ?></p>
                </div>
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Waiting Parts</p>
                    <p class="text-2xl font-black mt-2"><?php echo number_format($stats['waiting_parts']); ?></p>
                </div>
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Ready for Pickup</p>
                    <p class="text-2xl font-black mt-2"><?php echo number_format($stats['ready_pickup']); ?></p>
                </div>
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Avg. Cycle Time</p>
                    <p class="text-2xl font-black mt-2">
                        <?php echo $avgCycleHours > 0 ? number_format($avgCycleHours, 1) . ' hrs' : 'N/A'; ?></p>
                </div>
            </div>

            <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-8">
                <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Upcoming Appointments</h3>
                        <p class="text-xs text-slate-500 font-medium">Pulled from appointment records for this tenant.
                        </p>
                    </div>
                    <a href="appointmentadmin.php?shop=<?php echo h($shopQuery); ?>"
                        class="text-xs font-semibold text-blue-700 hover:underline">Open Appointments Page</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-slate-50/50">
                                <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">
                                    Appointment</th>
                                <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">
                                    Customer</th>
                                <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">
                                    Vehicle</th>
                                <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">
                                    Date / Time</th>
                                <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">
                                    Status</th>
                                <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">
                                    Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (count($upcomingAppointments) === 0): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-10 text-center text-sm text-slate-500">No upcoming
                                        appointments found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($upcomingAppointments as $appointment): ?>
                                    <?php
                                    $vehicleText = trim((string) ($appointment['vehicle_name'] ?? ''));
                                    if ($vehicleText === '') {
                                        $vehicleText = 'Vehicle record';
                                    }
                                    $plateText = trim((string) ($appointment['plate_number'] ?? ''));
                                    ?>
                                    <tr class="hover:bg-slate-50/50 transition-colors">
                                        <td class="px-6 py-4 text-sm font-bold text-slate-900">
                                            #<?php echo (int) $appointment['appointment_id']; ?></td>
                                        <td class="px-6 py-4 text-sm text-slate-700">
                                            <?php echo h($appointment['customer_name']); ?></td>
                                        <td class="px-6 py-4 text-sm text-slate-700">
                                            <?php echo h($vehicleText); ?>
                                            <?php if ($plateText !== ''): ?>
                                                <div class="text-xs text-slate-500 mt-0.5">Plate: <?php echo h($plateText); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-slate-700">
                                            <div class="font-semibold">
                                                <?php echo h(date('M d, Y', strtotime((string) $appointment['appointment_date']))); ?>
                                            </div>
                                            <div class="text-xs text-slate-500">
                                                <?php echo h(date('h:i A', strtotime((string) $appointment['appointment_time']))); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm">
                                            <span
                                                class="inline-flex px-2 py-1 rounded-full text-xs font-bold <?php echo h(statusBadgeClass((string) $appointment['status'])); ?>"><?php echo h($appointment['status']); ?></span>
                                        </td>
                                        <td class="px-6 py-4 text-sm font-semibold text-slate-900">
                                            <?php echo '₱' . number_format((float) ($appointment['total_amount'] ?? 0), 2); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/40 flex items-center justify-between">
                    <p class="text-xs text-slate-500 font-medium">Showing
                        <?php echo number_format(count($upcomingAppointments)); ?> of
                        <?php echo number_format($upcomingTotalRows); ?> records</p>
                    <div class="flex items-center gap-2">
                        <?php if ($upcomingPage > 1): ?>
                            <a href="repairjobsadmin.php?<?php echo h(http_build_query(array_filter([
                                'shop' => $loginSlug,
                                'q' => $search,
                                'job_status' => $jobStatusFilter,
                                'service_status' => $serviceStatusFilter,
                                'priority' => $priorityFilter,
                                'upcoming_page' => $upcomingPage - 1,
                                'progress_page' => $progressPage,
                                'jobs_page' => $jobsPage,
                            ], static fn($v) => $v !== ''))); ?>"
                                class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 bg-white hover:bg-slate-100">Previous</a>
                        <?php endif; ?>
                        <span class="px-2 py-1 text-xs font-semibold text-slate-600">Page
                            <?php echo (int) $upcomingPage; ?> of <?php echo (int) $upcomingTotalPages; ?></span>
                        <?php if ($upcomingPage < $upcomingTotalPages): ?>
                            <a href="repairjobsadmin.php?<?php echo h(http_build_query(array_filter([
                                'shop' => $loginSlug,
                                'q' => $search,
                                'job_status' => $jobStatusFilter,
                                'service_status' => $serviceStatusFilter,
                                'priority' => $priorityFilter,
                                'upcoming_page' => $upcomingPage + 1,
                                'progress_page' => $progressPage,
                                'jobs_page' => $jobsPage,
                            ], static fn($v) => $v !== ''))); ?>"
                                class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 bg-white hover:bg-slate-100">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Active Repair Jobs</h3>
                        <p class="text-xs text-slate-500 font-medium">Job-level status and financial summary from live
                            data.</p>
                    </div>
                    <span class="text-xs font-bold text-slate-500"><?php echo number_format(count($jobRows)); ?>
                        rows</span>
                </div>
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/40">
                    <form method="get" class="flex flex-wrap items-center gap-3">
                        <input type="hidden" name="shop" value="<?php echo h($loginSlug); ?>">
                        <div class="relative flex-1 min-w-[240px]">
                            <span
                                class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">search</span>
                            <input type="text" name="q" value="<?php echo h($search); ?>"
                                placeholder="Filter by job order, customer, vehicle..."
                                class="w-full rounded-lg border-slate-300 pl-9 pr-3 py-2 text-sm" />
                        </div>
                        <select name="job_status" class="rounded-lg border-slate-300 text-sm min-w-[160px]">
                            <option value="All">All Job Status</option>
                            <?php foreach ($jobStatuses as $status): ?>
                                <option value="<?php echo h($status); ?>" <?php echo $jobStatusFilter === $status ? 'selected' : ''; ?>><?php echo h($status); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="service_status" class="rounded-lg border-slate-300 text-sm min-w-[170px]">
                            <option value="All">All Service Status</option>
                            <?php foreach ($serviceStatuses as $status): ?>
                                <option value="<?php echo h($status); ?>" <?php echo $serviceStatusFilter === $status ? 'selected' : ''; ?>><?php echo h($status); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="priority" class="rounded-lg border-slate-300 text-sm min-w-[150px]">
                            <option value="All">All Priorities</option>
                            <?php foreach ($priorityOptions as $priority): ?>
                                <option value="<?php echo h($priority); ?>" <?php echo $priorityFilter === $priority ? 'selected' : ''; ?>><?php echo h($priority); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit"
                            class="inline-flex items-center justify-center w-11 h-10 rounded-lg border border-slate-300 bg-white text-slate-600 hover:bg-slate-100"
                            title="Apply Filters">
                            <span class="material-symbols-outlined text-lg">filter_list</span>
                        </button>
                    </form>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-slate-50/50">
                                <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">
                                    Order Details</th>
                                <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">
                                    Services</th>
                                <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">
                                    Est. Total</th>
                                <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">
                                    Labor Hrs</th>
                                <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">Job
                                    Status</th>
                                <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">
                                    Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (count($jobRows) === 0): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-10 text-center text-sm text-slate-500">No repair jobs
                                        found for this filter.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($jobRows as $job): ?>
                                    <?php
                                    $vehicleText = trim(((string) ($job['year_model'] ?? '')) . ' ' . ((string) ($job['brand'] ?? '')) . ' ' . ((string) ($job['model'] ?? '')));
                                    $estimatedHours = ((float) ($job['total_estimated_minutes'] ?? 0)) / 60;
                                    $actualHours = ((float) ($job['total_actual_minutes'] ?? 0)) / 60;
                                    ?>
                                    <tr class="hover:bg-slate-50/50 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="font-bold text-sm text-slate-900"><?php echo h(!empty($job['job_order_no']) ? $job['job_order_no'] : 'RJO-' . $job['repair_job_id']); ?>
                                            </div>
                                            <div class="text-xs text-slate-500">
                                                <?php echo h($vehicleText !== '' ? $vehicleText : 'Vehicle record'); ?></div>
                                            <div class="text-[11px] text-slate-400 mt-1">Customer:
                                                <?php echo h($job['customer_name']); ?>        <?php echo $job['bay_no'] ? ' | Bay: ' . h($job['bay_no']) : ''; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-slate-700 max-w-md"><?php echo h($job['services']); ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm font-bold text-slate-900">
                                            <?php echo '₱' . number_format((float) ($job['grand_total'] ?? 0), 2); ?></td>
                                        <td class="px-6 py-4 text-sm font-medium text-slate-600">
                                            <?php echo number_format($actualHours, 1); ?> /
                                            <?php echo number_format($estimatedHours, 1); ?></td>
                                        <td class="px-6 py-4">
                                            <span
                                                class="inline-flex px-2 py-1 rounded-full text-xs font-bold <?php echo h(statusBadgeClass((string) $job['job_status'])); ?>"><?php echo h($job['job_status']); ?></span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php if ($job['job_status'] === 'Completed'): ?>
                                                <span class="text-xs text-slate-500 font-semibold">Locked</span>
                                            <?php else: ?>
                                                <form method="post" class="flex items-center gap-2">
                                                    <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                                                    <input type="hidden" name="repair_job_id"
                                                        value="<?php echo (int) $job['repair_job_id']; ?>">
                                                    <input type="hidden" name="update_job_status" value="1">
                                                    <select name="job_status" class="rounded-lg border-slate-300 text-xs">
                                                        <?php foreach ($jobStatuses as $status): ?>
                                                            <option value="<?php echo h($status); ?>" <?php echo $job['job_status'] === $status ? 'selected' : ''; ?>>
                                                                <?php echo h($status); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <button type="submit"
                                                        class="text-blue-700 hover:underline text-xs font-bold">Save</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/40 flex items-center justify-between">
                    <p class="text-xs text-slate-500 font-medium">Showing <?php echo number_format(count($jobRows)); ?>
                        of <?php echo number_format($jobsTotalRows); ?> records</p>
                    <div class="flex items-center gap-2">
                        <?php if ($jobsPage > 1): ?>
                            <a href="repairjobsadmin.php?<?php echo h(http_build_query(array_filter([
                                'shop' => $loginSlug,
                                'q' => $search,
                                'job_status' => $jobStatusFilter,
                                'service_status' => $serviceStatusFilter,
                                'priority' => $priorityFilter,
                                'upcoming_page' => $upcomingPage,
                                'progress_page' => $progressPage,
                                'jobs_page' => $jobsPage - 1,
                            ], static fn($v) => $v !== ''))); ?>"
                                class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 bg-white hover:bg-slate-100">Previous</a>
                        <?php endif; ?>
                        <span class="px-2 py-1 text-xs font-semibold text-slate-600">Page <?php echo (int) $jobsPage; ?>
                            of <?php echo (int) $jobsTotalPages; ?></span>
                        <?php if ($jobsPage < $jobsTotalPages): ?>
                            <a href="repairjobsadmin.php?<?php echo h(http_build_query(array_filter([
                                'shop' => $loginSlug,
                                'q' => $search,
                                'job_status' => $jobStatusFilter,
                                'service_status' => $serviceStatusFilter,
                                'priority' => $priorityFilter,
                                'upcoming_page' => $upcomingPage,
                                'progress_page' => $progressPage,
                                'jobs_page' => $jobsPage + 1,
                            ], static fn($v) => $v !== ''))); ?>"
                                class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 bg-white hover:bg-slate-100">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>
    </main>
</body>

</html>