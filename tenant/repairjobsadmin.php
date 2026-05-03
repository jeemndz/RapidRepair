<?php
session_start();
require_once __DIR__ . '/../db.php';
include __DIR__ . '/../session_security.php';
include __DIR__ . '/access_control.php';
include __DIR__ . '/../log_helper.php';

if (!isset($_SESSION['tenantID'])) {
    header('Location: tenantlogin.php');
    exit;
}

$tenantID = (int) $_SESSION['tenantID'];

enforceModuleAccess($tenantID, basename(__FILE__));

$accessibleModules = getAccessibleModules($tenantID);
$isStaffUser = isset($_SESSION['userType']) && $_SESSION['userType'] === 'staff';

function canAccessModule($moduleFile, $accessibleModules) {
    return in_array($moduleFile, $accessibleModules, true);
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
        'Pending' => 'bg-amber-100 text-amber-700',
        'Approved' => 'bg-green-100 text-green-700',
        'Declined' => 'bg-red-100 text-red-700',
        'Draft' => 'bg-slate-100 text-slate-700',
        'Submitted' => 'bg-blue-100 text-blue-700',
        default => 'bg-slate-100 text-slate-700',
    };
}

function generateJobOrderNo(mysqli $conn, int $tenantID): string
{
    $countStmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS total FROM repair_jobs WHERE tenantID = ?');
    if (!$countStmt) {
        return 'RR-' . str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);
    }

    mysqli_stmt_bind_param($countStmt, 'i', $tenantID);
    mysqli_stmt_execute($countStmt);
    $countResult = mysqli_stmt_get_result($countStmt);
    $countRow = $countResult ? mysqli_fetch_assoc($countResult) : null;
    mysqli_stmt_close($countStmt);

    $nextNumber = ((int) ($countRow['total'] ?? 0)) + 1;
    return 'RR-' . str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);
}

function getRedirectParams(string $loginSlug, string $search, string $jobStatusFilter, string $serviceStatusFilter, string $priorityFilter): array
{
    return [
        'shop' => $loginSlug,
        'q' => $search,
        'job_status' => $jobStatusFilter,
        'service_status' => $serviceStatusFilter,
        'priority' => $priorityFilter,
    ];
}

/**
 * Get the original/main diagnostic service total already selected in the booking.
 * This amount must remain included when adding recommended diagnostic sub-services
 * and later when adding used parts/inventory costs.
 */
function getDiagnosticMainServiceTotal(mysqli $conn, int $tenantID, int $repairJobId): float
{
    $total = 0.00;

    $stmt = mysqli_prepare(
        $conn,
        'SELECT COALESCE(SUM(rjs.service_price), 0) AS total
         FROM repair_job_services rjs
         INNER JOIN services s ON s.service_id = rjs.service_id AND s.tenantID = rjs.tenantID
         WHERE rjs.repair_job_id = ?
           AND rjs.tenantID = ?
           AND rjs.service_status <> "Cancelled"
           AND (
                s.service_type = "Main"
                OR s.category = "Diagnostics"
                OR LOWER(s.service_name) LIKE "%diagnostic%"
           )'
    );

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $repairJobId, $tenantID);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result && $row = mysqli_fetch_assoc($result)) {
            $total = (float) ($row['total'] ?? 0);
        }
        mysqli_stmt_close($stmt);
    }

    return round($total, 2);
}

/**
 * Create a payment record for a completed repair job.
 * Returns inserted payment_id on success, 0 on failure.
 */
function createPaymentForJob(mysqli $conn, int $tenantID, int $repairJobId): int
{
    $stmt = mysqli_prepare(
        $conn,
        'SELECT user_id, appointment_id, labor_total, parts_total, grand_total
         FROM repair_jobs
         WHERE repair_job_id = ? AND tenantID = ? LIMIT 1'
    );

    if (!$stmt) {
        return 0;
    }

    mysqli_stmt_bind_param($stmt, 'ii', $repairJobId, $tenantID);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    if (!$row) {
        return 0;
    }

    $userId = (int) ($row['user_id'] ?? 0);
    $appointmentId = (int) ($row['appointment_id'] ?? 0);
    $laborTotal = (float) ($row['labor_total'] ?? 0.00);
    $partsTotal = (float) ($row['parts_total'] ?? 0.00);
    $grandTotal = (float) ($row['grand_total'] ?? 0.00);

    $amountPaid = 0.00;
    $balance = round($grandTotal - $amountPaid, 2);
    $paymentMethod = 'Cash';
    $paymentStatus = 'Pending';
    $referenceNumber = '';
    $gcashReferenceNumber = '';
    $remarks = 'Auto-generated payment for completed repair job';
    $invoiceItems = json_encode([]);

    $insertStmt = mysqli_prepare(
        $conn,
        'INSERT INTO payments
            (tenantID, user_id, appointment_id, repair_job_id, paymentAmount, labor_total, parts_total, grand_total, amountPaid, balance, paymentMethod, paymentDate, paymentStatus, referenceNumber, gcashReferenceNumber, remarks, invoice_items, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, NOW())'
    );

    if (!$insertStmt) {
        return 0;
    }

    $paymentAmount = $grandTotal;
    mysqli_stmt_bind_param(
        $insertStmt,
        'iiiiddddddssssss',
        $tenantID,
        $userId,
        $appointmentId,
        $repairJobId,
        $paymentAmount,
        $laborTotal,
        $partsTotal,
        $grandTotal,
        $amountPaid,
        $balance,
        $paymentMethod,
        $paymentStatus,
        $referenceNumber,
        $gcashReferenceNumber,
        $remarks,
        $invoiceItems
    );

    $insertedId = 0;
    if (mysqli_stmt_execute($insertStmt)) {
        $insertedId = (int) mysqli_insert_id($conn);
        log_event($conn, 'CREATE Payment', 'payment', $insertedId, 'Auto-created payment for repair_job_id ' . $repairJobId);
    }

    mysqli_stmt_close($insertStmt);
    return $insertedId;
}


/**
 * When a repair job is cancelled, keep related database records aligned.
 * This updates:
 * - appointments.status to Cancelled
 * - repair_job_services.service_status to Cancelled
 * - pending diagnostic reports to Declined
 * - pending payment records for the job to Failed
 */
function cancelRepairJobRelatedRecords(mysqli $conn, int $tenantID, int $repairJobId): void
{
    $appointmentStmt = mysqli_prepare(
        $conn,
        'UPDATE appointments a
         INNER JOIN repair_jobs rj ON rj.appointment_id = a.appointment_id AND rj.tenantID = a.tenantID
         SET a.status = "Cancelled",
             a.updated_at = NOW()
         WHERE rj.repair_job_id = ?
           AND rj.tenantID = ?
           AND a.status <> "Cancelled"'
    );
    if ($appointmentStmt) {
        mysqli_stmt_bind_param($appointmentStmt, 'ii', $repairJobId, $tenantID);
        if (mysqli_stmt_execute($appointmentStmt)) {
            log_event($conn, 'UPDATE Appointment', 'appointment', $repairJobId, 'Updated appointment status to Cancelled from cancelled repair job');
        }
        mysqli_stmt_close($appointmentStmt);
    }

    $servicesStmt = mysqli_prepare(
        $conn,
        'UPDATE repair_job_services
         SET service_status = "Cancelled",
             updated_at = CURRENT_TIMESTAMP
         WHERE repair_job_id = ?
           AND tenantID = ?
           AND service_status <> "Cancelled"'
    );
    if ($servicesStmt) {
        mysqli_stmt_bind_param($servicesStmt, 'ii', $repairJobId, $tenantID);
        if (mysqli_stmt_execute($servicesStmt)) {
            log_event($conn, 'UPDATE RepairJobService', 'repair_job_service', $repairJobId, 'Cancelled services linked to repair job');
        }
        mysqli_stmt_close($servicesStmt);
    }

    $diagnosticStmt = mysqli_prepare(
        $conn,
        'UPDATE diagnostic_reports
         SET customer_approval = CASE WHEN customer_approval = "Pending" THEN "Declined" ELSE customer_approval END,
             diagnosis_status = CASE WHEN diagnosis_status IN ("Draft", "Submitted") THEN "Declined" ELSE diagnosis_status END,
             declined_at = CASE WHEN declined_at IS NULL THEN NOW() ELSE declined_at END,
             updated_at = CURRENT_TIMESTAMP
         WHERE repair_job_id = ?
           AND tenantID = ?
           AND (customer_approval = "Pending" OR diagnosis_status IN ("Draft", "Submitted"))'
    );
    if ($diagnosticStmt) {
        mysqli_stmt_bind_param($diagnosticStmt, 'ii', $repairJobId, $tenantID);
        if (mysqli_stmt_execute($diagnosticStmt)) {
            log_event($conn, 'UPDATE DiagnosticReport', 'diagnostic_report', $repairJobId, 'Declined pending diagnostic report because repair job was cancelled');
        }
        mysqli_stmt_close($diagnosticStmt);
    }

    $paymentStmt = mysqli_prepare(
        $conn,
        'UPDATE payments
         SET paymentStatus = "Failed",
             remarks = TRIM(CONCAT(COALESCE(remarks, ""), CASE WHEN COALESCE(remarks, "") = "" THEN "" ELSE " | " END, "Auto-marked failed because repair job was cancelled")),
             updated_at = CURRENT_TIMESTAMP
         WHERE repair_job_id = ?
           AND tenantID = ?
           AND paymentStatus = "Pending"'
    );
    if ($paymentStmt) {
        mysqli_stmt_bind_param($paymentStmt, 'ii', $repairJobId, $tenantID);
        if (mysqli_stmt_execute($paymentStmt)) {
            log_event($conn, 'UPDATE Payment', 'payment', $repairJobId, 'Marked pending payment as Failed because repair job was cancelled');
        }
        mysqli_stmt_close($paymentStmt);
    }
}

$message = '';
$messageType = 'success';

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'job_updated') {
        $message = 'Repair job status updated successfully.';
    } elseif ($_GET['msg'] === 'repair_started') {
        $message = 'Repair job started successfully.';
    } elseif ($_GET['msg'] === 'service_updated') {
        $message = 'Service status updated successfully.';
    } elseif ($_GET['msg'] === 'diagnostic_saved') {
        $message = 'Diagnostic report submitted to customer for approval.';
    } elseif ($_GET['msg'] === 'error') {
        $message = 'Unable to process your request. Please try again.';
        $messageType = 'error';
    }
}

/**
 * POST: Start repair now
 * Allows the tenant to manually start a queued repair job even before the appointment date/time.
 * This sets work_started_at so the automatic schedule sync will not move it back to Queued.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_repair_now'])) {
    $postedToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    $repairJobId = isset($_POST['repair_job_id']) ? (int) $_POST['repair_job_id'] : 0;

    $redirectParams = getRedirectParams($loginSlug, $search, $jobStatusFilter, $serviceStatusFilter, $priorityFilter);

    if (!hash_equals($csrfToken, $postedToken) || $repairJobId <= 0) {
        $redirectParams['msg'] = 'error';
        header('Location: repairjobsadmin.php?' . http_build_query(array_filter($redirectParams, static fn($v) => $v !== '')));
        exit;
    }

    $jobCheckStmt = mysqli_prepare(
        $conn,
        "SELECT rj.repair_job_id, rj.appointment_id, rj.job_status,
                CASE WHEN EXISTS (
                    SELECT 1
                    FROM repair_job_services rjs
                    INNER JOIN services s ON s.service_id = rjs.service_id AND s.tenantID = rjs.tenantID
                    WHERE rjs.repair_job_id = rj.repair_job_id
                      AND rjs.tenantID = rj.tenantID
                      AND (s.category = 'Diagnostics' OR LOWER(s.service_name) LIKE '%diagnostic%')
                ) THEN 1 ELSE 0 END AS has_diagnostic_service
         FROM repair_jobs rj
         WHERE rj.repair_job_id = ?
           AND rj.tenantID = ?
           AND rj.job_status = 'Queued'
         LIMIT 1"
    );

    $jobStartRow = null;
    if ($jobCheckStmt) {
        mysqli_stmt_bind_param($jobCheckStmt, 'ii', $repairJobId, $tenantID);
        mysqli_stmt_execute($jobCheckStmt);
        $jobCheckResult = mysqli_stmt_get_result($jobCheckStmt);
        $jobStartRow = $jobCheckResult ? mysqli_fetch_assoc($jobCheckResult) : null;
        mysqli_stmt_close($jobCheckStmt);
    }

    if (!$jobStartRow) {
        $redirectParams['msg'] = 'error';
        header('Location: repairjobsadmin.php?' . http_build_query(array_filter($redirectParams, static fn($v) => $v !== '')));
        exit;
    }

    $newJobStatus = ((int) ($jobStartRow['has_diagnostic_service'] ?? 0)) > 0 ? 'Diagnostics' : 'In Progress';
    $newAppointmentStatus = $newJobStatus === 'Diagnostics' ? 'Diagnosing' : 'In Progress';
    $appointmentId = (int) ($jobStartRow['appointment_id'] ?? 0);

    mysqli_begin_transaction($conn);
    $startOk = true;

    $startJobStmt = mysqli_prepare(
        $conn,
        'UPDATE repair_jobs
         SET job_status = ?,
             work_started_at = COALESCE(work_started_at, NOW()),
             updated_at = CURRENT_TIMESTAMP
         WHERE repair_job_id = ?
           AND tenantID = ?
           AND job_status = "Queued"
         LIMIT 1'
    );

    if (!$startJobStmt) {
        $startOk = false;
    } else {
        mysqli_stmt_bind_param($startJobStmt, 'sii', $newJobStatus, $repairJobId, $tenantID);
        if (!mysqli_stmt_execute($startJobStmt) || mysqli_stmt_affected_rows($startJobStmt) <= 0) {
            $startOk = false;
        } else {
            log_event($conn, 'START RepairJob', 'repair_job', $repairJobId, 'Manually started repair job as ' . $newJobStatus);
        }
        mysqli_stmt_close($startJobStmt);
    }

    if ($startOk) {
        $startServicesStmt = mysqli_prepare(
            $conn,
            'UPDATE repair_job_services
             SET service_status = "In Progress",
                 updated_at = CURRENT_TIMESTAMP
             WHERE repair_job_id = ?
               AND tenantID = ?
               AND service_status = "Pending"'
        );
        if ($startServicesStmt) {
            mysqli_stmt_bind_param($startServicesStmt, 'ii', $repairJobId, $tenantID);
            mysqli_stmt_execute($startServicesStmt);
            mysqli_stmt_close($startServicesStmt);
        }
    }

    if ($startOk && $appointmentId > 0) {
        $startAppointmentStmt = mysqli_prepare(
            $conn,
            'UPDATE appointments
             SET status = ?,
                 updated_at = NOW()
             WHERE appointment_id = ?
               AND tenantID = ?
               AND status NOT IN ("Completed", "Cancelled")
             LIMIT 1'
        );
        if ($startAppointmentStmt) {
            mysqli_stmt_bind_param($startAppointmentStmt, 'sii', $newAppointmentStatus, $appointmentId, $tenantID);
            mysqli_stmt_execute($startAppointmentStmt);
            mysqli_stmt_close($startAppointmentStmt);
        }
    }

    if ($startOk) {
        mysqli_commit($conn);
        $redirectParams['msg'] = 'repair_started';
    } else {
        mysqli_rollback($conn);
        $redirectParams['msg'] = 'error';
    }

    header('Location: repairjobsadmin.php?' . http_build_query(array_filter($redirectParams, static fn($v) => $v !== '')));
    exit;
}

/**
 * POST: Update job status
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_job_status'])) {
    $postedToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    $repairJobId = isset($_POST['repair_job_id']) ? (int) $_POST['repair_job_id'] : 0;
    $newStatus = isset($_POST['job_status']) ? trim((string) $_POST['job_status']) : '';

    $redirectParams = getRedirectParams($loginSlug, $search, $jobStatusFilter, $serviceStatusFilter, $priorityFilter);

    if (!hash_equals($csrfToken, $postedToken) || $repairJobId <= 0 || !in_array($newStatus, $jobStatuses, true)) {
        $redirectParams['msg'] = 'error';
        header('Location: repairjobsadmin.php?' . http_build_query(array_filter($redirectParams, static fn($v) => $v !== '')));
        exit;
    }

    $checkStmt = mysqli_prepare($conn, 'SELECT job_status FROM repair_jobs WHERE repair_job_id = ? AND tenantID = ? LIMIT 1');
    if ($checkStmt) {
        mysqli_stmt_bind_param($checkStmt, 'ii', $repairJobId, $tenantID);
        mysqli_stmt_execute($checkStmt);
        $checkResult = mysqli_stmt_get_result($checkStmt);
        $checkRow = $checkResult ? mysqli_fetch_assoc($checkResult) : null;
        mysqli_stmt_close($checkStmt);

        if ($checkRow && in_array((string) $checkRow['job_status'], ['Completed', 'Cancelled'], true)) {
            $redirectParams['msg'] = 'error';
            header('Location: repairjobsadmin.php?' . http_build_query(array_filter($redirectParams, static fn($v) => $v !== '')));
            exit;
        }
    }

    $updateJobStmt = mysqli_prepare(
        $conn,
        'UPDATE repair_jobs
         SET job_status = ?,
             updated_at = CURRENT_TIMESTAMP,
             work_started_at = CASE WHEN ? = "In Progress" AND work_started_at IS NULL THEN NOW() ELSE work_started_at END,
             completed_at = CASE WHEN ? = "Completed" THEN NOW() ELSE completed_at END
         WHERE repair_job_id = ? AND tenantID = ?
         LIMIT 1'
    );

    if ($updateJobStmt) {
        mysqli_stmt_bind_param($updateJobStmt, 'sssii', $newStatus, $newStatus, $newStatus, $repairJobId, $tenantID);
        if (mysqli_stmt_execute($updateJobStmt)) {
            log_event($conn, 'UPDATE RepairJob', 'repair_job', $repairJobId, 'Updated job_status to ' . $newStatus);
        }
        mysqli_stmt_close($updateJobStmt);

        // If the job was cancelled, update related database records (appointment, services, diagnostics, pending payments).
        if ($newStatus === 'Cancelled') {
            cancelRepairJobRelatedRecords($conn, $tenantID, $repairJobId);
        }

        // If the job was completed, create a payment record (best-effort)
        if ($newStatus === 'Completed') {
            $paymentId = createPaymentForJob($conn, $tenantID, $repairJobId);
            if (!$paymentId) {
                // non-fatal: log failure to create payment
                log_event($conn, 'ERROR Create Payment', 'repair_job', $repairJobId, 'Failed to auto-create payment for completed job');
            }
        }

        if ($newStatus === 'Diagnostics') {
            $apptStmt = mysqli_prepare(
                $conn,
                'UPDATE appointments a
                 INNER JOIN repair_jobs rj ON rj.appointment_id = a.appointment_id AND rj.tenantID = a.tenantID
                 SET a.status = "Diagnosing", a.updated_at = NOW()
                 WHERE rj.repair_job_id = ? AND rj.tenantID = ?
                  '
            );
            if ($apptStmt) {
                mysqli_stmt_bind_param($apptStmt, 'ii', $repairJobId, $tenantID);
                if (mysqli_stmt_execute($apptStmt)) {
                    log_event($conn, 'UPDATE Appointment', 'appointment', $repairJobId, 'Updated status to Diagnosing from repair job status update');
                }
                mysqli_stmt_close($apptStmt);
            }
        }

        $redirectParams['msg'] = 'job_updated';
    } else {
        $redirectParams['msg'] = 'error';
    }

    header('Location: repairjobsadmin.php?' . http_build_query(array_filter($redirectParams, static fn($v) => $v !== '')));
    exit;
}

/**
 * POST: Update service status
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_service_status'])) {
    $postedToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    $repairJobServiceId = isset($_POST['repair_job_service_id']) ? (int) $_POST['repair_job_service_id'] : 0;
    $newServiceStatus = isset($_POST['service_status']) ? trim((string) $_POST['service_status']) : '';

    $redirectParams = getRedirectParams($loginSlug, $search, $jobStatusFilter, $serviceStatusFilter, $priorityFilter);

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
        if (mysqli_stmt_execute($updateServiceStmt)) {
            log_event($conn, 'UPDATE RepairJobService', 'repair_job_service', $repairJobServiceId, 'Updated service_status to ' . $newServiceStatus);
        }
        mysqli_stmt_close($updateServiceStmt);
        $redirectParams['msg'] = 'service_updated';
    } else {
        $redirectParams['msg'] = 'error';
    }

    header('Location: repairjobsadmin.php?' . http_build_query(array_filter($redirectParams, static fn($v) => $v !== '')));
    exit;
}

/**
 * POST: Submit diagnostic report + recommended sub-services
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_diagnostic_report'])) {
    $postedToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    $repairJobId = isset($_POST['repair_job_id']) ? (int) $_POST['repair_job_id'] : 0;
    $mechanicName = trim((string) ($_POST['mechanic_name'] ?? ''));
    $problemDescription = trim((string) ($_POST['problem_description'] ?? ''));
    $findings = trim((string) ($_POST['findings'] ?? ''));
    $recommendedAction = trim((string) ($_POST['recommended_action'] ?? ''));
    $recommendedServiceIdsRaw = isset($_POST['recommended_service_ids']) && is_array($_POST['recommended_service_ids'])
        ? $_POST['recommended_service_ids']
        : [];
    $recommendedServiceIds = array_values(array_unique(array_filter(array_map('intval', $recommendedServiceIdsRaw), static fn($id) => $id > 0)));

    $redirectParams = getRedirectParams($loginSlug, $search, $jobStatusFilter, $serviceStatusFilter, $priorityFilter);

    if (
        !hash_equals($csrfToken, $postedToken) ||
        $repairJobId <= 0 ||
        $mechanicName === '' ||
        $problemDescription === '' ||
        $findings === '' ||
        $recommendedAction === '' ||
        count($recommendedServiceIds) === 0
    ) {
        $redirectParams['msg'] = 'error';
        header('Location: repairjobsadmin.php?' . http_build_query(array_filter($redirectParams, static fn($v) => $v !== '')));
        exit;
    }

    $jobStmt = mysqli_prepare(
        $conn,
        'SELECT repair_job_id, appointment_id, assigned_technician
         FROM repair_jobs
         WHERE repair_job_id = ? AND tenantID = ?
         LIMIT 1'
    );

    $jobRow = null;
    if ($jobStmt) {
        mysqli_stmt_bind_param($jobStmt, 'ii', $repairJobId, $tenantID);
        mysqli_stmt_execute($jobStmt);
        $jobResult = mysqli_stmt_get_result($jobStmt);
        $jobRow = $jobResult ? mysqli_fetch_assoc($jobResult) : null;
        mysqli_stmt_close($jobStmt);
    }

    if (!$jobRow) {
        $redirectParams['msg'] = 'error';
        header('Location: repairjobsadmin.php?' . http_build_query(array_filter($redirectParams, static fn($v) => $v !== '')));
        exit;
    }

    $serviceRows = [];
    $diagnosticMainTotal = getDiagnosticMainServiceTotal($conn, $tenantID, $repairJobId);
    $recommendedServicesTotal = 0.00;
    $estimatedTotal = $diagnosticMainTotal;

    foreach ($recommendedServiceIds as $serviceId) {
        $serviceStmt = mysqli_prepare(
            $conn,
            'SELECT service_id, parent_service_id, service_name, price, duration_minutes
             FROM services
             WHERE service_id = ?
               AND tenantID = ?
               AND service_type = "Sub"
               AND status = "Active"
             LIMIT 1'
        );

        if ($serviceStmt) {
            mysqli_stmt_bind_param($serviceStmt, 'ii', $serviceId, $tenantID);
            mysqli_stmt_execute($serviceStmt);
            $serviceResult = mysqli_stmt_get_result($serviceStmt);
            $serviceRow = $serviceResult ? mysqli_fetch_assoc($serviceResult) : null;
            mysqli_stmt_close($serviceStmt);

            if ($serviceRow) {
                $serviceRows[] = $serviceRow;
                $recommendedServicesTotal += (float) ($serviceRow['price'] ?? 0);
                $estimatedTotal = $diagnosticMainTotal + $recommendedServicesTotal;
            }
        }
    }

    if (count($serviceRows) === 0) {
        $redirectParams['msg'] = 'error';
        header('Location: repairjobsadmin.php?' . http_build_query(array_filter($redirectParams, static fn($v) => $v !== '')));
        exit;
    }

    mysqli_begin_transaction($conn);
    $saveOk = true;
    $diagnosticId = 0;
    $appointmentId = (int) ($jobRow['appointment_id'] ?? 0);

    $existingStmt = mysqli_prepare(
        $conn,
        'SELECT diagnostic_id
         FROM diagnostic_reports
         WHERE repair_job_id = ? AND tenantID = ?
         ORDER BY diagnostic_id DESC
         LIMIT 1'
    );

    if ($existingStmt) {
        mysqli_stmt_bind_param($existingStmt, 'ii', $repairJobId, $tenantID);
        mysqli_stmt_execute($existingStmt);
        $existingResult = mysqli_stmt_get_result($existingStmt);
        $existingRow = $existingResult ? mysqli_fetch_assoc($existingResult) : null;
        mysqli_stmt_close($existingStmt);

        if ($existingRow) {
            $diagnosticId = (int) $existingRow['diagnostic_id'];
        }
    }

    if ($diagnosticId > 0) {
        $updateReportStmt = mysqli_prepare(
            $conn,
            'UPDATE diagnostic_reports
             SET mechanic_name = ?,
                 problem_description = ?,
                 findings = ?,
                 recommended_action = ?,
                 estimated_total = ?,
                 customer_approval = "Pending",
                 diagnosis_status = "Submitted",
                 approved_at = NULL,
                 declined_at = NULL,
                 customer_notes = NULL,
                 updated_at = CURRENT_TIMESTAMP
             WHERE diagnostic_id = ? AND tenantID = ?
             LIMIT 1'
        );

        if (!$updateReportStmt) {
            $saveOk = false;
        } else {
            mysqli_stmt_bind_param(
                $updateReportStmt,
                'ssssdii',
                $mechanicName,
                $problemDescription,
                $findings,
                $recommendedAction,
                $estimatedTotal,
                $diagnosticId,
                $tenantID
            );
            if (!mysqli_stmt_execute($updateReportStmt)) {
                $saveOk = false;
            } else {
                log_event($conn, 'UPDATE DiagnosticReport', 'diagnostic_report', $diagnosticId, 'Updated findings to ' . $findings);
            }
            mysqli_stmt_close($updateReportStmt);
        }

        if ($saveOk) {
            $deleteServicesStmt = mysqli_prepare(
                $conn,
                'DELETE FROM diagnostic_report_services WHERE diagnostic_id = ? AND tenantID = ?'
            );
            if ($deleteServicesStmt) {
                mysqli_stmt_bind_param($deleteServicesStmt, 'ii', $diagnosticId, $tenantID);
                if (!mysqli_stmt_execute($deleteServicesStmt)) {
                    $saveOk = false;
                } else {
                    log_event($conn, 'DELETE DiagnosticReportService', 'diagnostic_report_service', $diagnosticId, 'Deleted DiagnosticReportService records for diagnostic ID: ' . $diagnosticId);
                }
                mysqli_stmt_close($deleteServicesStmt);
            } else {
                $saveOk = false;
            }
        }
    } else {
        $insertReportStmt = mysqli_prepare(
            $conn,
            'INSERT INTO diagnostic_reports
                (appointment_id, repair_job_id, tenantID, mechanic_name, problem_description, findings, recommended_action, estimated_total, customer_approval, diagnosis_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, "Pending", "Submitted")'
        );

        if (!$insertReportStmt) {
            $saveOk = false;
        } else {
            mysqli_stmt_bind_param(
                $insertReportStmt,
                'iiissssd',
                $appointmentId,
                $repairJobId,
                $tenantID,
                $mechanicName,
                $problemDescription,
                $findings,
                $recommendedAction,
                $estimatedTotal
            );
            if (!mysqli_stmt_execute($insertReportStmt)) {
                $saveOk = false;
            } else {
                $diagnosticId = (int) mysqli_insert_id($conn);
                log_event($conn, 'CREATE DiagnosticReport', 'diagnostic_report', $diagnosticId, 'Created DiagnosticReport with details: ' . $problemDescription);
            }
            mysqli_stmt_close($insertReportStmt);
        }
    }

    if ($saveOk && $diagnosticId > 0) {
        $insertReportServiceStmt = mysqli_prepare(
            $conn,
            'INSERT INTO diagnostic_report_services
                (diagnostic_id, tenantID, service_id, parent_service_id, service_name, service_price, duration_minutes, approval_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, "Pending")'
        );

        if (!$insertReportServiceStmt) {
            $saveOk = false;
        } else {
            foreach ($serviceRows as $serviceRow) {
                $serviceId = (int) $serviceRow['service_id'];
                $parentServiceId = isset($serviceRow['parent_service_id']) ? (int) $serviceRow['parent_service_id'] : 0;
                $serviceName = (string) $serviceRow['service_name'];
                $servicePrice = (float) $serviceRow['price'];
                $durationMinutes = (int) ($serviceRow['duration_minutes'] ?? 0);

                mysqli_stmt_bind_param(
                    $insertReportServiceStmt,
                    'iiiisdi',
                    $diagnosticId,
                    $tenantID,
                    $serviceId,
                    $parentServiceId,
                    $serviceName,
                    $servicePrice,
                    $durationMinutes
                );

                if (!mysqli_stmt_execute($insertReportServiceStmt)) {
                    $saveOk = false;
                    break;
                } else {
                    log_event($conn, 'CREATE DiagnosticReportService', 'diagnostic_report_service', $serviceId, 'Created DiagnosticReportService for diagnostic ID: ' . $diagnosticId);
                }
            }
            mysqli_stmt_close($insertReportServiceStmt);
        }
    }

    if ($saveOk) {
        $updateJobStmt = mysqli_prepare(
            $conn,
            'UPDATE repair_jobs
             SET job_status = "Diagnostics",
                 diagnosis_notes = ?,
                 labor_total = ?,
                 grand_total = labor_total + parts_total,
                 updated_at = NOW()
             WHERE repair_job_id = ? AND tenantID = ?
             LIMIT 1'
        );

        if ($updateJobStmt) {
            mysqli_stmt_bind_param(
                $updateJobStmt,
                'sdii',
                $findings,
                $estimatedTotal,
                $repairJobId,
                $tenantID
            );

            if (!mysqli_stmt_execute($updateJobStmt)) {
                $saveOk = false;
            } else {
                log_event(
                    $conn,
                    'UPDATE RepairJob',
                    'repair_job',
                    $repairJobId,
                    'Updated labor_total to ' . number_format($estimatedTotal, 2) . ' and recalculated grand_total'
                );
            }

            mysqli_stmt_close($updateJobStmt);
        } else {
            $saveOk = false;
        }
    }

    if ($saveOk && $appointmentId > 0) {
        $updateAppointmentStmt = mysqli_prepare(
            $conn,
            'UPDATE appointments
             SET status = "For Approval",
                 total_amount = ?,
                 updated_at = NOW()
             WHERE appointment_id = ? AND tenantID = ?
             LIMIT 1'
        );

        if ($updateAppointmentStmt) {
            mysqli_stmt_bind_param($updateAppointmentStmt, 'dii', $estimatedTotal, $appointmentId, $tenantID);
            if (!mysqli_stmt_execute($updateAppointmentStmt)) {
                $saveOk = false;
            } else {
                log_event($conn, 'UPDATE Appointment', 'appointment', $appointmentId, 'Updated total_amount to ' . number_format($estimatedTotal, 2));
            }
            mysqli_stmt_close($updateAppointmentStmt);
        } else {
            $saveOk = false;
        }
    }

    if ($saveOk) {
        mysqli_commit($conn);
        $redirectParams['msg'] = 'diagnostic_saved';
    } else {
        mysqli_rollback($conn);
        $redirectParams['msg'] = 'error';
    }

    header('Location: repairjobsadmin.php?' . http_build_query(array_filter($redirectParams, static fn($v) => $v !== '')));
    exit;
}

/**
 * POST: Complete job with parts
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_with_parts'])) {
    $postedToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    $repairJobId = isset($_POST['repair_job_id']) ? (int) $_POST['repair_job_id'] : 0;
    $noPartsUsed = isset($_POST['no_parts_used']) && $_POST['no_parts_used'] === '1';
    $selectedParts = isset($_POST['selected_parts']) && is_array($_POST['selected_parts']) ? $_POST['selected_parts'] : [];
    $sourcedPartsRaw = isset($_POST['sourced_parts']) && is_array($_POST['sourced_parts']) ? $_POST['sourced_parts'] : [];

    $redirectParams = getRedirectParams($loginSlug, $search, $jobStatusFilter, $serviceStatusFilter, $priorityFilter);

    if (!hash_equals($csrfToken, $postedToken) || $repairJobId <= 0) {
        $redirectParams['msg'] = 'error';
        header('Location: repairjobsadmin.php?' . http_build_query(array_filter($redirectParams, static fn($v) => $v !== '')));
        exit;
    }

    $partsDataForDelete = [];
    $sourcedParts = [];

    if (!$noPartsUsed) {
        foreach ($selectedParts as $partData) {
            $partData = is_array($partData) ? $partData : json_decode($partData, true);
            if (is_array($partData) && isset($partData['item_id'], $partData['quantity'])) {
                $itemId = (int) $partData['item_id'];
                $quantity = (int) $partData['quantity'];
                if ($itemId > 0 && $quantity > 0) {
                    $partsDataForDelete[] = ['item_id' => $itemId, 'quantity' => $quantity];
                }
            }
        }

        foreach ($sourcedPartsRaw as $sourcedPartRow) {
            if (!is_array($sourcedPartRow)) {
                continue;
            }

            $partName = trim((string) ($sourcedPartRow['part_name'] ?? ''));
            $quantity = (int) ($sourcedPartRow['quantity'] ?? 0);
            $unitCost = (float) ($sourcedPartRow['unit_cost'] ?? 0);
            $supplier = trim((string) ($sourcedPartRow['supplier'] ?? ''));

            if ($partName !== '' && $quantity > 0 && $unitCost >= 0) {
                $sourcedParts[] = [
                    'part_name' => $partName,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'supplier' => $supplier,
                ];
            }
        }
    }

    mysqli_begin_transaction($conn);
    $completeOk = true;
    $totalPartsCost = 0.00;

    foreach ($partsDataForDelete as $partUsed) {
        $priceStmt = mysqli_prepare(
            $conn,
            'SELECT unit_price, stock_quantity
             FROM inventory_items
             WHERE item_id = ? AND tenantID = ? AND status = "Active"
             LIMIT 1'
        );

        $itemPrice = 0.00;
        $stockQuantity = 0;

        if ($priceStmt) {
            mysqli_stmt_bind_param($priceStmt, 'ii', $partUsed['item_id'], $tenantID);
            mysqli_stmt_execute($priceStmt);
            $priceResult = mysqli_stmt_get_result($priceStmt);
            if ($priceResult && $priceRow = mysqli_fetch_assoc($priceResult)) {
                $itemPrice = (float) ($priceRow['unit_price'] ?? 0);
                $stockQuantity = (int) ($priceRow['stock_quantity'] ?? 0);
            }
            mysqli_stmt_close($priceStmt);
        }

        if ($stockQuantity < $partUsed['quantity']) {
            $completeOk = false;
            break;
        }

        $totalPartsCost += $itemPrice * (float) $partUsed['quantity'];

        $updateInventoryStmt = mysqli_prepare(
            $conn,
            'UPDATE inventory_items
             SET stock_quantity = stock_quantity - ?, updated_at = NOW()
             WHERE item_id = ? AND tenantID = ?
             LIMIT 1'
        );

        if (!$updateInventoryStmt) {
            $completeOk = false;
            break;
        }

        mysqli_stmt_bind_param($updateInventoryStmt, 'iii', $partUsed['quantity'], $partUsed['item_id'], $tenantID);
        if (!mysqli_stmt_execute($updateInventoryStmt)) {
            $completeOk = false;
            mysqli_stmt_close($updateInventoryStmt);
            break;
        }
        log_event($conn, 'UPDATE InventoryItem', 'inventory_item', (int) $partUsed['item_id'], 'Updated stock quantity by decrementing ' . (int) $partUsed['quantity']);
        mysqli_stmt_close($updateInventoryStmt);

        $movementStmt = mysqli_prepare(
            $conn,
            'INSERT INTO stock_movements (tenantID, item_id, movement_type, quantity, reference_type, reference_id, notes)
             VALUES (?, ?, "OUT", ?, "RepairJob", ?, ?)'
        );

        if ($movementStmt) {
            $notes = 'Used in repair job #' . $repairJobId;
            mysqli_stmt_bind_param($movementStmt, 'iiiis', $tenantID, $partUsed['item_id'], $partUsed['quantity'], $repairJobId, $notes);
            if (mysqli_stmt_execute($movementStmt)) {
                log_event($conn, 'CREATE StockMovement', 'stock_movement', (int) $partUsed['item_id'], 'Created StockMovement with details: OUT quantity ' . (int) $partUsed['quantity']);
            }
            mysqli_stmt_close($movementStmt);
        }
    }

    if ($completeOk && count($sourcedParts) > 0) {
        foreach ($sourcedParts as $sourcedPart) {
            $totalPartsCost += (float) $sourcedPart['unit_cost'] * (int) $sourcedPart['quantity'];
            log_event(
                $conn,
                'ADD SourcedPart',
                'repair_job',
                $repairJobId,
                'Added sourced-out part: ' . $sourcedPart['part_name'] .
                ' x' . (int) $sourcedPart['quantity'] .
                ' @ ' . number_format((float) $sourcedPart['unit_cost'], 2) .
                ($sourcedPart['supplier'] !== '' ? ' from ' . $sourcedPart['supplier'] : '')
            );
        }
    }

    $laborTotal = 0.00;

    if ($completeOk) {
        $jobLaborStmt = mysqli_prepare(
            $conn,
            'SELECT labor_total
             FROM repair_jobs
             WHERE repair_job_id = ? AND tenantID = ?
             LIMIT 1'
        );

        if ($jobLaborStmt) {
            mysqli_stmt_bind_param($jobLaborStmt, 'ii', $repairJobId, $tenantID);
            mysqli_stmt_execute($jobLaborStmt);
            $jobLaborResult = mysqli_stmt_get_result($jobLaborStmt);

            if ($jobLaborResult && $jobLaborRow = mysqli_fetch_assoc($jobLaborResult)) {
                $laborTotal = (float) ($jobLaborRow['labor_total'] ?? 0);
            }

            mysqli_stmt_close($jobLaborStmt);
        }
    }

    /**
     * Fallback: if labor_total is still 0, compute it from active repair job services.
     * This ensures the final amount is always service/labor total + inventory parts total.
     */
    if ($completeOk && $laborTotal <= 0) {
        $laborStmt = mysqli_prepare(
            $conn,
            'SELECT COALESCE(SUM(service_price), 0) AS labor_total
             FROM repair_job_services
             WHERE repair_job_id = ?
               AND tenantID = ?
               AND service_status <> "Cancelled"'
        );

        if ($laborStmt) {
            mysqli_stmt_bind_param($laborStmt, 'ii', $repairJobId, $tenantID);
            mysqli_stmt_execute($laborStmt);
            $laborResult = mysqli_stmt_get_result($laborStmt);

            if ($laborResult && $laborRow = mysqli_fetch_assoc($laborResult)) {
                $laborTotal = (float) ($laborRow['labor_total'] ?? 0);
            }

            mysqli_stmt_close($laborStmt);
        }
    }

    $newGrandTotal = round($laborTotal + $totalPartsCost, 2);

    if ($completeOk) {
        $updateJobStmt = mysqli_prepare(
            $conn,
            'UPDATE repair_jobs
             SET job_status = "Completed",
                 labor_total = ?,
                 parts_total = ?,
                 grand_total = ?,
                 updated_at = CURRENT_TIMESTAMP,
                 completed_at = NOW()
             WHERE repair_job_id = ? AND tenantID = ?
             LIMIT 1'
        );

        if ($updateJobStmt) {
            mysqli_stmt_bind_param(
                $updateJobStmt,
                'dddii',
                $laborTotal,
                $totalPartsCost,
                $newGrandTotal,
                $repairJobId,
                $tenantID
            );

            if (!mysqli_stmt_execute($updateJobStmt)) {
                $completeOk = false;
            } else {
                log_event(
                    $conn,
                    'UPDATE RepairJob',
                    'repair_job',
                    $repairJobId,
                    'Updated labor_total to ' . number_format($laborTotal, 2) .
                    ', parts_total to ' . number_format($totalPartsCost, 2) .
                    ', grand_total to ' . number_format($newGrandTotal, 2)
                );
            }

            mysqli_stmt_close($updateJobStmt);
        } else {
            $completeOk = false;
        }
    }

    if ($completeOk) {
        $updateApptStmt = mysqli_prepare(
            $conn,
            'UPDATE appointments a
             INNER JOIN repair_jobs rj ON rj.appointment_id = a.appointment_id AND rj.tenantID = a.tenantID
             SET a.status = "Completed", a.total_amount = ?, a.updated_at = NOW()
             WHERE rj.repair_job_id = ? AND rj.tenantID = ?'
        );
        if ($updateApptStmt) {
            mysqli_stmt_bind_param($updateApptStmt, 'dii', $newGrandTotal, $repairJobId, $tenantID);
            if (mysqli_stmt_execute($updateApptStmt)) {
                log_event($conn, 'UPDATE Appointment', 'appointment', $repairJobId, 'Updated total_amount to ' . number_format($newGrandTotal, 2));
            }
            mysqli_stmt_close($updateApptStmt);
        }
    }

    if ($completeOk) {
        // Create payment record for completed job (best-effort)
        $paymentId = createPaymentForJob($conn, $tenantID, $repairJobId);
        if (!$paymentId) {
            log_event($conn, 'ERROR Create Payment', 'repair_job', $repairJobId, 'Failed to auto-create payment for completed job (parts flow)');
        }
    }

    if ($completeOk) {
        mysqli_commit($conn);
        $redirectParams['msg'] = 'job_updated';
    } else {
        mysqli_rollback($conn);
        $redirectParams['msg'] = 'error';
    }

    header('Location: repairjobsadmin.php?' . http_build_query(array_filter($redirectParams, static fn($v) => $v !== '')));
    exit;
}

/**
 * Auto-sync confirmed/diagnostic appointments to repair jobs.
 */
$confirmedSyncSql = "SELECT
        a.appointment_id,
        a.user_id,
        a.vehicle_id,
        a.notes,
        a.total_amount,
        a.appointment_date,
        a.appointment_time,
        a.status,
        SUM(CASE WHEN s.category = 'Diagnostics' OR LOWER(s.service_name) LIKE '%diagnostic%' THEN 1 ELSE 0 END) AS diagnostic_count
    FROM appointments a
    LEFT JOIN appointment_services aps ON aps.appointment_id = a.appointment_id AND aps.tenantID = a.tenantID
    LEFT JOIN services s ON s.service_id = aps.service_id AND s.tenantID = a.tenantID
    WHERE a.tenantID = ?
      AND a.status IN ('Confirmed', 'For Diagnosis', 'Diagnosing')
      AND NOT EXISTS (
        SELECT 1
        FROM repair_jobs rj
        WHERE rj.tenantID = a.tenantID
          AND rj.appointment_id = a.appointment_id
      )
    GROUP BY a.appointment_id, a.user_id, a.vehicle_id, a.notes, a.total_amount, a.appointment_date, a.appointment_time, a.status";

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
        $appointmentDate = trim((string) ($appointmentRow['appointment_date'] ?? ''));
        $appointmentTime = trim((string) ($appointmentRow['appointment_time'] ?? ''));
        $appointmentDateTimeValue = trim($appointmentDate . ' ' . ($appointmentTime !== '' ? $appointmentTime : '00:00:00'));
        $hasAppointmentStarted = $appointmentDateTimeValue !== '' && strtotime($appointmentDateTimeValue) !== false
            ? strtotime($appointmentDateTimeValue) <= time()
            : true;
        $isDiagnostic = ((int) ($appointmentRow['diagnostic_count'] ?? 0)) > 0 || in_array((string) $appointmentRow['status'], ['For Diagnosis', 'Diagnosing'], true);

        if ($appointmentId <= 0 || $userId <= 0 || $vehicleId <= 0) {
            continue;
        }

        $serviceItems = [];
        $servicesStmt = mysqli_prepare(
            $conn,
            'SELECT aps.service_id, aps.service_price, aps.duration_minutes, aps.notes
             FROM appointment_services aps
             WHERE aps.appointment_id = ? AND aps.tenantID = ?'
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
        $initialJobStatus = $hasAppointmentStarted ? ($isDiagnostic ? 'Diagnostics' : 'In Progress') : 'Queued';
        $initialAppointmentStatus = $hasAppointmentStarted ? ($isDiagnostic ? 'Diagnosing' : 'In Progress') : (string) $appointmentRow['status'];
        $workStartedSql = $hasAppointmentStarted ? 'NOW()' : 'NULL';
        $concernValue = $concern !== '' ? $concern : null;

        $insertJobStmt = mysqli_prepare(
            $conn,
            'INSERT INTO repair_jobs
                (tenantID, appointment_id, user_id, vehicle_id, job_order_no, job_status, priority, concern, check_in_time, work_started_at, labor_total, parts_total, grand_total)
             VALUES (?, ?, ?, ?, ?, ?, "Normal", ?, NOW(), ' . $workStartedSql . ', ?, 0.00, ?)'
        );

        if (!$insertJobStmt) {
            $syncOk = false;
        } else {
            mysqli_stmt_bind_param(
                $insertJobStmt,
                'iiiisssdd',
                $tenantID,
                $appointmentId,
                $userId,
                $vehicleId,
                $jobOrderNo,
                $initialJobStatus,
                $concernValue,
                $grandTotal,
                $grandTotal
            );
            if (!mysqli_stmt_execute($insertJobStmt)) {
                $syncOk = false;
            } else {
                $repairJobId = (int) mysqli_insert_id($conn);
                log_event($conn, 'CREATE RepairJob', 'repair_job', $repairJobId, 'Created RepairJob from appointment ID: ' . $appointmentId);
            }
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
                    } else {
                        log_event($conn, 'CREATE RepairJobService', 'repair_job_service', $serviceId, 'Created RepairJobService for repair job ID: ' . $repairJobId);
                    }
                }
                mysqli_stmt_close($insertJobServiceStmt);
            }
        }

        if ($syncOk) {
            $updateAppointmentStmt = mysqli_prepare(
                $conn,
                'UPDATE appointments
                 SET status = ?, updated_at = NOW()
                 WHERE appointment_id = ? AND tenantID = ?
                 LIMIT 1'
            );
            if ($updateAppointmentStmt) {
                mysqli_stmt_bind_param($updateAppointmentStmt, 'sii', $initialAppointmentStatus, $appointmentId, $tenantID);
                if (!mysqli_stmt_execute($updateAppointmentStmt)) {
                    $syncOk = false;
                } else {
                    log_event($conn, 'UPDATE Appointment', 'appointment', $appointmentId, 'Updated status to ' . $initialAppointmentStatus);
                }
                mysqli_stmt_close($updateAppointmentStmt);
            } else {
                $syncOk = false;
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


/**
 * Keep repair job status aligned with the appointment schedule.
 * Future scheduled jobs stay Queued. Jobs only move into In Progress/Diagnostics when their appointment date/time has arrived.
 */
$queueFutureJobsStmt = mysqli_prepare(
    $conn,
    "UPDATE repair_jobs rj
     INNER JOIN appointments a ON a.appointment_id = rj.appointment_id AND a.tenantID = rj.tenantID
     SET rj.job_status = 'Queued',
         rj.work_started_at = NULL,
         rj.updated_at = NOW()
     WHERE rj.tenantID = ?
       AND rj.job_status IN ('In Progress', 'Diagnostics')
       AND rj.work_started_at IS NULL
       AND TIMESTAMP(a.appointment_date, COALESCE(a.appointment_time, '00:00:00')) > NOW()"
);
if ($queueFutureJobsStmt) {
    mysqli_stmt_bind_param($queueFutureJobsStmt, 'i', $tenantID);
    mysqli_stmt_execute($queueFutureJobsStmt);
    mysqli_stmt_close($queueFutureJobsStmt);
}

$startDueJobsStmt = mysqli_prepare(
    $conn,
    "UPDATE repair_jobs rj
     INNER JOIN appointments a ON a.appointment_id = rj.appointment_id AND a.tenantID = rj.tenantID
     SET rj.job_status = CASE
            WHEN EXISTS (
                SELECT 1
                FROM repair_job_services rjs
                INNER JOIN services s ON s.service_id = rjs.service_id AND s.tenantID = rjs.tenantID
                WHERE rjs.repair_job_id = rj.repair_job_id
                  AND rjs.tenantID = rj.tenantID
                  AND (s.category = 'Diagnostics' OR LOWER(s.service_name) LIKE '%diagnostic%')
            ) THEN 'Diagnostics'
            ELSE 'In Progress'
         END,
         rj.work_started_at = COALESCE(rj.work_started_at, NOW()),
         rj.updated_at = NOW()
     WHERE rj.tenantID = ?
       AND rj.job_status = 'Queued'
       AND TIMESTAMP(a.appointment_date, COALESCE(a.appointment_time, '00:00:00')) <= NOW()"
);
if ($startDueJobsStmt) {
    mysqli_stmt_bind_param($startDueJobsStmt, 'i', $tenantID);
    mysqli_stmt_execute($startDueJobsStmt);
    mysqli_stmt_close($startDueJobsStmt);
}

/**
 * Lightweight polling endpoint for auto-refresh.
 * The page reloads only when the active repair-jobs signature changes.
 */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'repair_job_count') {
    header('Content-Type: application/json');

    $signature = '0|0|';
    $countStmt = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) AS total_active,
                COALESCE(MAX(repair_job_id), 0) AS latest_id,
                COALESCE(MAX(updated_at), '') AS latest_update
         FROM repair_jobs
         WHERE tenantID = ?
           AND job_status IN ('Queued', 'In Progress', 'Diagnostics', 'Waiting for Parts', 'Quality Check', 'Ready for Pickup')"
    );

    if ($countStmt) {
        mysqli_stmt_bind_param($countStmt, 'i', $tenantID);
        mysqli_stmt_execute($countStmt);
        $countResult = mysqli_stmt_get_result($countStmt);
        if ($countResult && $countRow = mysqli_fetch_assoc($countResult)) {
            $signature = (int) ($countRow['total_active'] ?? 0) . '|' .
                         (int) ($countRow['latest_id'] ?? 0) . '|' .
                         (string) ($countRow['latest_update'] ?? '');
        }
        mysqli_stmt_close($countStmt);
    }

    echo json_encode(['signature' => $signature]);
    exit;
}

/**
 * Modal state: Parts completion
 */
$showPartsModal = false;
$partsModalJobId = 0;
$partsModalJobDetails = null;
$inventoryItems = [];

if (isset($_GET['show_parts_modal'])) {
    $partsModalJobId = max(0, (int) $_GET['show_parts_modal']);
    if ($partsModalJobId > 0) {
        $jobDetailsStmt = mysqli_prepare(
            $conn,
            'SELECT rj.repair_job_id, rj.job_order_no, rj.job_status,
                    COALESCE(u.fullName, CONCAT("User #", rj.user_id)) AS customer_name,
                    CONCAT(IFNULL(v.year_model, ""), " ", IFNULL(v.brand, ""), " ", IFNULL(v.model, "")) AS vehicle_name
             FROM repair_jobs rj
             LEFT JOIN appointments a ON a.appointment_id = rj.appointment_id AND a.tenantID = rj.tenantID
    LEFT JOIN users u ON u.user_id = rj.user_id
             LEFT JOIN vehicleinformation v ON v.vehicle_id = rj.vehicle_id AND v.tenantID = rj.tenantID
             WHERE rj.repair_job_id = ? AND rj.tenantID = ? LIMIT 1'
        );

        if ($jobDetailsStmt) {
            mysqli_stmt_bind_param($jobDetailsStmt, 'ii', $partsModalJobId, $tenantID);
            mysqli_stmt_execute($jobDetailsStmt);
            $jobDetailsResult = mysqli_stmt_get_result($jobDetailsStmt);
            $partsModalJobDetails = $jobDetailsResult ? mysqli_fetch_assoc($jobDetailsResult) : null;
            mysqli_stmt_close($jobDetailsStmt);

            if ($partsModalJobDetails) {
                $showPartsModal = true;
                log_event(
                    $conn,
                    'VIEW RepairJob Parts Modal',
                    'repair_job',
                    $partsModalJobId,
                    'Opened completion modal for repair job ' . ($partsModalJobDetails['job_order_no'] ?? ('#' . $partsModalJobId))
                );
            }
        }
    }
}

if ($showPartsModal) {
    $inventoryStmt = mysqli_prepare(
        $conn,
        'SELECT item_id, part_name, part_code, category, stock_quantity, unit_price, status
         FROM inventory_items
         WHERE tenantID = ? AND status = "Active"
         ORDER BY category ASC, part_name ASC'
    );

    if ($inventoryStmt) {
        mysqli_stmt_bind_param($inventoryStmt, 'i', $tenantID);
        mysqli_stmt_execute($inventoryStmt);
        $inventoryResult = mysqli_stmt_get_result($inventoryStmt);
        while ($inventoryResult && $row = mysqli_fetch_assoc($inventoryResult)) {
            $inventoryItems[] = $row;
        }
        mysqli_stmt_close($inventoryStmt);
    }
}

/**
 * Modal state: Diagnostic report
 */
$showDiagnosticModal = false;
$diagnosticModalJobId = isset($_GET['diagnostic_job']) ? max(0, (int) $_GET['diagnostic_job']) : 0;
$diagnosticModalJob = null;
$existingDiagnosticReport = null;
$existingDiagnosticServiceIds = [];
$subServiceOptions = [];
$diagnosticMainServiceTotal = 0.00;

if ($diagnosticModalJobId > 0) {
    $diagnosticMainServiceTotal = getDiagnosticMainServiceTotal($conn, $tenantID, $diagnosticModalJobId);
    $diagnosticJobStmt = mysqli_prepare(
        $conn,
        'SELECT
            rj.repair_job_id,
            rj.appointment_id,
            rj.job_order_no,
            rj.job_status,
            rj.assigned_technician,
            rj.concern,
            COALESCE(u.fullName, CONCAT("User #", rj.user_id)) AS customer_name,
            CONCAT(IFNULL(v.year_model, ""), " ", IFNULL(v.brand, ""), " ", IFNULL(v.model, "")) AS vehicle_name,
            IFNULL(v.plate_number, "") AS plate_number
         FROM repair_jobs rj
         LEFT JOIN users u ON u.user_id = rj.user_id
         LEFT JOIN vehicleinformation v ON v.vehicle_id = rj.vehicle_id AND v.tenantID = rj.tenantID
         WHERE rj.repair_job_id = ? AND rj.tenantID = ?
         LIMIT 1'
    );

    if ($diagnosticJobStmt) {
        mysqli_stmt_bind_param($diagnosticJobStmt, 'ii', $diagnosticModalJobId, $tenantID);
        mysqli_stmt_execute($diagnosticJobResult = $diagnosticJobStmt);
        $diagnosticResult = mysqli_stmt_get_result($diagnosticJobStmt);
        $diagnosticModalJob = $diagnosticResult ? mysqli_fetch_assoc($diagnosticResult) : null;
        mysqli_stmt_close($diagnosticJobStmt);
    }

    if ($diagnosticModalJob) {
        $showDiagnosticModal = true;
        log_event(
            $conn,
            'VIEW Diagnostic Modal',
            'diagnostic_report',
            $diagnosticModalJobId,
            'Opened diagnostic report modal for repair job ' . ($diagnosticModalJob['job_order_no'] ?? ('#' . $diagnosticModalJobId))
        );

        $reportStmt = mysqli_prepare(
            $conn,
            'SELECT *
             FROM diagnostic_reports
             WHERE repair_job_id = ? AND tenantID = ?
             ORDER BY diagnostic_id DESC
             LIMIT 1'
        );

        if ($reportStmt) {
            mysqli_stmt_bind_param($reportStmt, 'ii', $diagnosticModalJobId, $tenantID);
            mysqli_stmt_execute($reportStmt);
            $reportResult = mysqli_stmt_get_result($reportStmt);
            $existingDiagnosticReport = $reportResult ? mysqli_fetch_assoc($reportResult) : null;
            mysqli_stmt_close($reportStmt);
        }

        if ($existingDiagnosticReport) {
            $diagnosticId = (int) $existingDiagnosticReport['diagnostic_id'];
            $reportServicesStmt = mysqli_prepare(
                $conn,
                'SELECT service_id FROM diagnostic_report_services WHERE diagnostic_id = ? AND tenantID = ?'
            );

            if ($reportServicesStmt) {
                mysqli_stmt_bind_param($reportServicesStmt, 'ii', $diagnosticId, $tenantID);
                mysqli_stmt_execute($reportServicesStmt);
                $reportServicesResult = mysqli_stmt_get_result($reportServicesStmt);
                while ($reportServicesResult && $row = mysqli_fetch_assoc($reportServicesResult)) {
                    $existingDiagnosticServiceIds[] = (int) $row['service_id'];
                }
                mysqli_stmt_close($reportServicesStmt);
            }
        }

        $subServicesStmt = mysqli_prepare(
            $conn,
            'SELECT
                sub.service_id,
                sub.parent_service_id,
                sub.service_name,
                sub.description,
                sub.price,
                sub.duration_minutes,
                sub.category,
                COALESCE(parent.service_name, "Other Services") AS parent_service_name
             FROM services sub
             LEFT JOIN services parent ON parent.service_id = sub.parent_service_id AND parent.tenantID = sub.tenantID
             WHERE sub.tenantID = ?
               AND sub.service_type = "Sub"
               AND sub.status = "Active"
             ORDER BY parent.service_name ASC, sub.service_name ASC'
        );

        if ($subServicesStmt) {
            mysqli_stmt_bind_param($subServicesStmt, 'i', $tenantID);
            mysqli_stmt_execute($subServicesStmt);
            $subServicesResult = mysqli_stmt_get_result($subServicesStmt);
            while ($subServicesResult && $row = mysqli_fetch_assoc($subServicesResult)) {
                $subServiceOptions[] = $row;
            }
            mysqli_stmt_close($subServicesStmt);
        }
    }
}

/**
 * Stats
 */
$stats = [
    'in_workshop' => 0,
    'waiting_parts' => 0,
    'ready_pickup' => 0,
    'avg_cycle_minutes' => 0.0,
    'for_approval' => 0,
];

$notificationStats = [
    'approved_diagnostics' => 0,
    'new_repair_jobs' => 0,
];
$notificationItems = [];

$statsStmt = mysqli_prepare(
    $conn,
    "SELECT
        SUM(CASE WHEN rj.job_status IN ('Queued','In Progress','Diagnostics','Waiting for Parts','Quality Check') THEN 1 ELSE 0 END) AS in_workshop,
        SUM(CASE WHEN rj.job_status = 'Waiting for Parts' THEN 1 ELSE 0 END) AS waiting_parts,
        SUM(CASE WHEN rj.job_status = 'Ready for Pickup' THEN 1 ELSE 0 END) AS ready_pickup,
        AVG(CASE WHEN rj.work_started_at IS NOT NULL AND rj.completed_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, rj.work_started_at, rj.completed_at) END) AS avg_cycle_minutes,
        (
            SELECT COUNT(*)
            FROM diagnostic_reports dr
            WHERE dr.tenantID = ?
              AND dr.customer_approval = 'Pending'
              AND dr.diagnosis_status = 'Submitted'
        ) AS for_approval
     FROM repair_jobs rj
     WHERE rj.tenantID = ?"
);

if ($statsStmt) {
    mysqli_stmt_bind_param($statsStmt, 'ii', $tenantID, $tenantID);
    mysqli_stmt_execute($statsStmt);
    $statsResult = mysqli_stmt_get_result($statsStmt);
    if ($statsResult && $statsRow = mysqli_fetch_assoc($statsResult)) {
        $stats['in_workshop'] = (int) ($statsRow['in_workshop'] ?? 0);
        $stats['waiting_parts'] = (int) ($statsRow['waiting_parts'] ?? 0);
        $stats['ready_pickup'] = (int) ($statsRow['ready_pickup'] ?? 0);
        $stats['avg_cycle_minutes'] = (float) ($statsRow['avg_cycle_minutes'] ?? 0);
        $stats['for_approval'] = (int) ($statsRow['for_approval'] ?? 0);
    }
    mysqli_stmt_close($statsStmt);
}

$approvedDiagnosticsStmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total
     FROM diagnostic_reports dr
     WHERE dr.tenantID = ?
       AND dr.customer_approval = 'Approved'
       AND dr.diagnosis_status = 'Approved'
       AND dr.approved_at >= CURDATE()"
);
if ($approvedDiagnosticsStmt) {
    mysqli_stmt_bind_param($approvedDiagnosticsStmt, 'i', $tenantID);
    mysqli_stmt_execute($approvedDiagnosticsStmt);
    $approvedDiagnosticsResult = mysqli_stmt_get_result($approvedDiagnosticsStmt);
    if ($approvedDiagnosticsResult && $approvedDiagnosticsRow = mysqli_fetch_assoc($approvedDiagnosticsResult)) {
        $notificationStats['approved_diagnostics'] = (int) ($approvedDiagnosticsRow['total'] ?? 0);
    }
    mysqli_stmt_close($approvedDiagnosticsStmt);
}

$newRepairJobsStmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total
     FROM repair_jobs rj
     WHERE rj.tenantID = ?
       AND rj.check_in_time >= CURDATE()
       AND rj.job_status IN ('Queued', 'In Progress', 'Diagnostics')"
);
if ($newRepairJobsStmt) {
    mysqli_stmt_bind_param($newRepairJobsStmt, 'i', $tenantID);
    mysqli_stmt_execute($newRepairJobsStmt);
    $newRepairJobsResult = mysqli_stmt_get_result($newRepairJobsStmt);
    if ($newRepairJobsResult && $newRepairJobsRow = mysqli_fetch_assoc($newRepairJobsResult)) {
        $notificationStats['new_repair_jobs'] = (int) ($newRepairJobsRow['total'] ?? 0);
    }
    mysqli_stmt_close($newRepairJobsStmt);
}

if ($notificationStats['approved_diagnostics'] > 0) {
    $approvedRecentStmt = mysqli_prepare(
        $conn,
        "SELECT dr.diagnostic_id, dr.repair_job_id, dr.approved_at, rj.job_order_no,
                COALESCE(u.fullName, CONCAT('User #', rj.user_id)) AS customer_name
         FROM diagnostic_reports dr
         INNER JOIN repair_jobs rj ON rj.repair_job_id = dr.repair_job_id AND rj.tenantID = dr.tenantID
         LEFT JOIN users u ON u.user_id = rj.user_id
         WHERE dr.tenantID = ?
           AND dr.customer_approval = 'Approved'
           AND dr.diagnosis_status = 'Approved'
           AND dr.approved_at >= CURDATE()
         ORDER BY dr.approved_at DESC
         LIMIT 5"
    );
    if ($approvedRecentStmt) {
        mysqli_stmt_bind_param($approvedRecentStmt, 'i', $tenantID);
        mysqli_stmt_execute($approvedRecentStmt);
        $approvedRecentResult = mysqli_stmt_get_result($approvedRecentStmt);
        while ($approvedRecentResult && $row = mysqli_fetch_assoc($approvedRecentResult)) {
            $notificationItems[] = [
                'type' => 'approved_diagnostic',
                'title' => 'Diagnostic approved by customer',
                'detail' => 'Job ' . ($row['job_order_no'] ?? ('#' . (int) $row['repair_job_id'])) . ' - ' . ($row['customer_name'] ?? 'Customer'),
                'time' => !empty($row['approved_at']) ? date('M d, h:i A', strtotime((string) $row['approved_at'])) : '',
            ];
        }
        mysqli_stmt_close($approvedRecentStmt);
    }
}

if ($notificationStats['new_repair_jobs'] > 0) {
    $newJobsRecentStmt = mysqli_prepare(
        $conn,
        "SELECT rj.repair_job_id, rj.job_order_no, rj.check_in_time,
                COALESCE(u.fullName, CONCAT('User #', rj.user_id)) AS customer_name
         FROM repair_jobs rj
         LEFT JOIN users u ON u.user_id = rj.user_id
         WHERE rj.tenantID = ?
           AND rj.check_in_time >= CURDATE()
           AND rj.job_status IN ('Queued', 'In Progress', 'Diagnostics')
         ORDER BY rj.check_in_time DESC
         LIMIT 5"
    );
    if ($newJobsRecentStmt) {
        mysqli_stmt_bind_param($newJobsRecentStmt, 'i', $tenantID);
        mysqli_stmt_execute($newJobsRecentStmt);
        $newJobsRecentResult = mysqli_stmt_get_result($newJobsRecentStmt);
        while ($newJobsRecentResult && $row = mysqli_fetch_assoc($newJobsRecentResult)) {
            $notificationItems[] = [
                'type' => 'new_job',
                'title' => 'New repair job arrived',
                'detail' => 'Job ' . ($row['job_order_no'] ?? ('#' . (int) $row['repair_job_id'])) . ' - ' . ($row['customer_name'] ?? 'Customer'),
                'time' => !empty($row['check_in_time']) ? date('M d, h:i A', strtotime((string) $row['check_in_time'])) : '',
            ];
        }
        mysqli_stmt_close($newJobsRecentStmt);
    }
}

usort($notificationItems, static function (array $left, array $right): int {
    return strcmp($right['time'] ?? '', $left['time'] ?? '');
});

$notificationCount = $notificationStats['approved_diagnostics'] + $notificationStats['new_repair_jobs'];

$avgCycleHours = $stats['avg_cycle_minutes'] > 0 ? $stats['avg_cycle_minutes'] / 60 : 0;

$searchLike = '%' . $search . '%';
$recordsPerPage = 5;
$upcomingPage = max(1, (int) ($_GET['upcoming_page'] ?? 1));
$jobsPage = max(1, (int) ($_GET['jobs_page'] ?? 1));
$diagnosticsPage = max(1, (int) ($_GET['diagnostics_page'] ?? 1));

$upcomingSortBy = trim((string) ($_GET['upcoming_sort_by'] ?? 'appointment_id'));
$upcomingSortDir = strtoupper(trim((string) ($_GET['upcoming_sort_dir'] ?? 'DESC')));
$allowedUpcomingSorts = [
    'appointment_id' => 'a.appointment_id',
    'appointment_date' => 'a.appointment_date',
    'appointment_time' => 'a.appointment_time',
    'status' => 'a.status',
    'total_amount' => 'a.total_amount',
];
if (!isset($allowedUpcomingSorts[$upcomingSortBy])) {
    $upcomingSortBy = 'appointment_id';
}
if (!in_array($upcomingSortDir, ['ASC', 'DESC'], true)) {
    $upcomingSortDir = 'DESC';
}
$upcomingOrderBy = $allowedUpcomingSorts[$upcomingSortBy] . ' ' . $upcomingSortDir;

$jobsSortBy = trim((string) ($_GET['jobs_sort_by'] ?? 'repair_job_id'));
$jobsSortDir = strtoupper(trim((string) ($_GET['jobs_sort_dir'] ?? 'DESC')));
$allowedJobsSorts = [
    'repair_job_id' => 'rj.repair_job_id',
    'appointment_id' => 'rj.appointment_id',
    'appointment_date' => 'a.appointment_date',
    'appointment_time' => 'a.appointment_time',
    'job_status' => 'rj.job_status',
    'priority' => 'rj.priority',
    'grand_total' => 'rj.grand_total',
    'updated_at' => 'rj.updated_at',
    'completed_at' => 'rj.completed_at',
];
if (!isset($allowedJobsSorts[$jobsSortBy])) {
    $jobsSortBy = 'repair_job_id';
}
if (!in_array($jobsSortDir, ['ASC', 'DESC'], true)) {
    $jobsSortDir = 'DESC';
}
$jobsOrderBy = $allowedJobsSorts[$jobsSortBy] . ' ' . $jobsSortDir;

$diagnosticsSortBy = trim((string) ($_GET['diagnostics_sort_by'] ?? 'updated_at'));
$diagnosticsSortDir = strtoupper(trim((string) ($_GET['diagnostics_sort_dir'] ?? 'DESC')));
$allowedDiagnosticSorts = [
    'diagnostic_id' => 'dr.diagnostic_id',
    'repair_job_id' => 'dr.repair_job_id',
    'appointment_id' => 'dr.appointment_id',
    'mechanic_name' => 'dr.mechanic_name',
    'estimated_total' => 'dr.estimated_total',
    'customer_approval' => 'dr.customer_approval',
    'diagnosis_status' => 'dr.diagnosis_status',
    'created_at' => 'dr.created_at',
    'updated_at' => 'dr.updated_at',
];
if (!isset($allowedDiagnosticSorts[$diagnosticsSortBy])) {
    $diagnosticsSortBy = 'updated_at';
}
if (!in_array($diagnosticsSortDir, ['ASC', 'DESC'], true)) {
    $diagnosticsSortDir = 'DESC';
}
$diagnosticsOrderBy = $allowedDiagnosticSorts[$diagnosticsSortBy] . ' ' . $diagnosticsSortDir;

/**
 * Upcoming appointments
 */
$upcomingAppointments = [];
$upcomingTotalRows = 0;
$upcomingTotalPages = 1;
$upcomingOffset = 0;

$upcomingCountStmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total_rows
     FROM appointments a
     WHERE a.tenantID = ?
       AND a.status IN ('Pending', 'Confirmed', 'For Diagnosis', 'Diagnosing', 'For Approval', 'In Progress')
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
       AND a.status IN ('Pending', 'Confirmed', 'For Diagnosis', 'Diagnosing', 'For Approval', 'In Progress')
       AND (a.appointment_date > CURDATE() OR (a.appointment_date = CURDATE() AND a.appointment_time >= CURTIME()))
       AND NOT EXISTS (
            SELECT 1
            FROM repair_jobs rj
            WHERE rj.appointment_id = a.appointment_id
              AND rj.tenantID = a.tenantID
              AND rj.job_status = 'Completed'
       )
         ORDER BY {$upcomingOrderBy}, a.appointment_id DESC
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

/**
 * Active repair jobs
 */
$jobRows = [];
$jobsTotalRows = 0;
$jobsTotalPages = 1;
$jobsOffset = 0;

$jobsCountSql = "SELECT COUNT(DISTINCT rj.repair_job_id) AS total_rows
    FROM repair_jobs rj
    LEFT JOIN users u ON u.user_id = rj.user_id
    LEFT JOIN vehicleinformation v ON v.vehicle_id = rj.vehicle_id AND v.tenantID = rj.tenantID
    LEFT JOIN repair_job_services rjs ON rjs.repair_job_id = rj.repair_job_id AND rjs.tenantID = rj.tenantID
    WHERE rj.tenantID = ?
      AND rj.job_status IN ('Queued', 'In Progress', 'Diagnostics', 'Waiting for Parts', 'Quality Check', 'Ready for Pickup')
      AND (? = 'All' OR rj.job_status = ?)
      AND (? = 'All' OR rj.priority = ?)
      AND (? = 'All' OR rjs.service_status = ?)
      AND (? = '' OR rj.job_order_no LIKE ? OR u.fullName LIKE ? OR v.brand LIKE ? OR v.model LIKE ? OR rj.assigned_technician LIKE ?)";

$jobsCountStmt = mysqli_prepare($conn, $jobsCountSql);
if ($jobsCountStmt) {
    mysqli_stmt_bind_param(
        $jobsCountStmt,
        'issssssssssss',
        $tenantID,
        $jobStatusFilter,
        $jobStatusFilter,
        $priorityFilter,
        $priorityFilter,
        $serviceStatusFilter,
        $serviceStatusFilter,
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
        rj.appointment_id,
        a.appointment_date,
        a.appointment_time,
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
        COALESCE(SUM(rjs.estimated_duration_minutes), 0) AS total_estimated_minutes,
        COALESCE(SUM(CASE WHEN s.category = 'Diagnostics' OR LOWER(s.service_name) LIKE '%diagnostic%' THEN 1 ELSE 0 END), 0) AS diagnostic_service_count,
        dr.diagnostic_id,
        dr.customer_approval,
        dr.diagnosis_status
    FROM repair_jobs rj
    LEFT JOIN appointments a ON a.appointment_id = rj.appointment_id AND a.tenantID = rj.tenantID
    LEFT JOIN users u ON u.user_id = rj.user_id
    LEFT JOIN vehicleinformation v ON v.vehicle_id = rj.vehicle_id AND v.tenantID = rj.tenantID
    LEFT JOIN repair_job_services rjs ON rjs.repair_job_id = rj.repair_job_id AND rjs.tenantID = rj.tenantID
    LEFT JOIN services s ON s.service_id = rjs.service_id AND s.tenantID = rj.tenantID
    LEFT JOIN diagnostic_reports dr ON dr.repair_job_id = rj.repair_job_id AND dr.tenantID = rj.tenantID
    WHERE rj.tenantID = ?
      AND rj.job_status IN ('Queued', 'In Progress', 'Diagnostics', 'Waiting for Parts', 'Quality Check', 'Ready for Pickup')
      AND (? = 'All' OR rj.job_status = ?)
      AND (? = 'All' OR rj.priority = ?)
      AND (? = 'All' OR rjs.service_status = ?)
      AND (? = '' OR rj.job_order_no LIKE ? OR u.fullName LIKE ? OR v.brand LIKE ? OR v.model LIKE ? OR rj.assigned_technician LIKE ?)
    GROUP BY
        rj.repair_job_id,
        rj.appointment_id,
        a.appointment_date,
        a.appointment_time,
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
        v.model,
        dr.diagnostic_id,
        dr.customer_approval,
        dr.diagnosis_status
    ORDER BY {$jobsOrderBy}, rj.repair_job_id DESC
    LIMIT ?, ?";

$jobsStmt = mysqli_prepare($conn, $jobsSql);
if ($jobsStmt) {
    mysqli_stmt_bind_param(
        $jobsStmt,
        'issssssssssssii',
        $tenantID,
        $jobStatusFilter,
        $jobStatusFilter,
        $priorityFilter,
        $priorityFilter,
        $serviceStatusFilter,
        $serviceStatusFilter,
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

/**
 * Diagnostic reports table
 */
$diagnosticRows = [];
$diagnosticTotalRows = 0;
$diagnosticTotalPages = 1;
$diagnosticOffset = 0;

$diagnosticCountStmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total_rows
     FROM diagnostic_reports dr
     INNER JOIN repair_jobs rj ON rj.repair_job_id = dr.repair_job_id AND rj.tenantID = dr.tenantID
     LEFT JOIN users u ON u.user_id = rj.user_id
     WHERE dr.tenantID = ?
       AND (? = '' OR rj.job_order_no LIKE ? OR u.fullName LIKE ? OR dr.mechanic_name LIKE ? OR dr.findings LIKE ?)"
);

if ($diagnosticCountStmt) {
    mysqli_stmt_bind_param(
        $diagnosticCountStmt,
        'isssss',
        $tenantID,
        $search,
        $searchLike,
        $searchLike,
        $searchLike,
        $searchLike
    );
    mysqli_stmt_execute($diagnosticCountStmt);
    $diagnosticCountResult = mysqli_stmt_get_result($diagnosticCountStmt);
    if ($diagnosticCountResult && $row = mysqli_fetch_assoc($diagnosticCountResult)) {
        $diagnosticTotalRows = (int) ($row['total_rows'] ?? 0);
    }
    mysqli_stmt_close($diagnosticCountStmt);
}

$diagnosticTotalPages = max(1, (int) ceil($diagnosticTotalRows / $recordsPerPage));
if ($diagnosticsPage > $diagnosticTotalPages) {
    $diagnosticsPage = $diagnosticTotalPages;
}
$diagnosticOffset = ($diagnosticsPage - 1) * $recordsPerPage;

$diagnosticStmt = mysqli_prepare(
    $conn,
    "SELECT
        dr.diagnostic_id,
        dr.repair_job_id,
        dr.appointment_id,
        dr.mechanic_name,
        dr.findings,
        dr.recommended_action,
        dr.estimated_total,
        dr.customer_approval,
        dr.diagnosis_status,
        dr.created_at,
        dr.updated_at,
        rj.job_order_no,
        COALESCE(u.fullName, CONCAT('User #', rj.user_id)) AS customer_name,
        CONCAT(IFNULL(v.year_model, ''), ' ', IFNULL(v.brand, ''), ' ', IFNULL(v.model, '')) AS vehicle_name,
        COALESCE(GROUP_CONCAT(DISTINCT drs.service_name ORDER BY drs.service_name SEPARATOR ', '), 'No recommendations') AS recommended_services
     FROM diagnostic_reports dr
     INNER JOIN repair_jobs rj ON rj.repair_job_id = dr.repair_job_id AND rj.tenantID = dr.tenantID
     LEFT JOIN users u ON u.user_id = rj.user_id
     LEFT JOIN vehicleinformation v ON v.vehicle_id = rj.vehicle_id AND v.tenantID = rj.tenantID
     LEFT JOIN diagnostic_report_services drs ON drs.diagnostic_id = dr.diagnostic_id AND drs.tenantID = dr.tenantID
     WHERE dr.tenantID = ?
       AND (? = '' OR rj.job_order_no LIKE ? OR u.fullName LIKE ? OR dr.mechanic_name LIKE ? OR dr.findings LIKE ?)
     GROUP BY
        dr.diagnostic_id,
        dr.repair_job_id,
        dr.appointment_id,
        dr.mechanic_name,
        dr.findings,
        dr.recommended_action,
        dr.estimated_total,
        dr.customer_approval,
        dr.diagnosis_status,
        dr.created_at,
        dr.updated_at,
        rj.job_order_no,
        u.fullName,
        rj.user_id,
        v.year_model,
        v.brand,
        v.model
      ORDER BY {$diagnosticsOrderBy}, dr.diagnostic_id DESC
     LIMIT ?, ?"
);

if ($diagnosticStmt) {
    mysqli_stmt_bind_param(
        $diagnosticStmt,
        'isssssii',
        $tenantID,
        $search,
        $searchLike,
        $searchLike,
        $searchLike,
        $searchLike,
        $diagnosticOffset,
        $recordsPerPage
    );
    mysqli_stmt_execute($diagnosticResult = $diagnosticStmt);
    $diagnosticResult = mysqli_stmt_get_result($diagnosticStmt);
    while ($diagnosticResult && $row = mysqli_fetch_assoc($diagnosticResult)) {
        $diagnosticRows[] = $row;
    }
    mysqli_stmt_close($diagnosticStmt);
}

/**
 * Repair Jobs History table
 * Uses the existing repair_jobs table only.
 */
$historyRows = [];
$historyStmt = mysqli_prepare(
    $conn,
    "SELECT
        rj.repair_job_id,
        rj.appointment_id,
        rj.job_order_no,
        rj.job_status,
        rj.priority,
        rj.assigned_technician,
        rj.bay_no,
        rj.labor_total,
        rj.parts_total,
        rj.grand_total,
        rj.completed_at,
        rj.updated_at,
        COALESCE(u.fullName, CONCAT('User #', rj.user_id)) AS customer_name,
        CONCAT(IFNULL(v.year_model, ''), ' ', IFNULL(v.brand, ''), ' ', IFNULL(v.model, '')) AS vehicle_name,
        COALESCE(GROUP_CONCAT(DISTINCT s.service_name ORDER BY s.service_name SEPARATOR ', '), 'No services linked') AS services
     FROM repair_jobs rj
     LEFT JOIN users u ON u.user_id = rj.user_id
     LEFT JOIN vehicleinformation v ON v.vehicle_id = rj.vehicle_id AND v.tenantID = rj.tenantID
     LEFT JOIN repair_job_services rjs ON rjs.repair_job_id = rj.repair_job_id AND rjs.tenantID = rj.tenantID
     LEFT JOIN services s ON s.service_id = rjs.service_id AND s.tenantID = rj.tenantID
     WHERE rj.tenantID = ?
       AND rj.job_status IN ('Completed', 'Cancelled')
     GROUP BY
        rj.repair_job_id,
        rj.appointment_id,
        rj.job_order_no,
        rj.job_status,
        rj.priority,
        rj.assigned_technician,
        rj.bay_no,
        rj.labor_total,
        rj.parts_total,
        rj.grand_total,
        rj.completed_at,
        rj.updated_at,
        u.fullName,
        rj.user_id,
        v.year_model,
        v.brand,
        v.model
     ORDER BY COALESCE(rj.completed_at, rj.updated_at) DESC, rj.repair_job_id DESC
     LIMIT 200"
);

if ($historyStmt) {
    mysqli_stmt_bind_param($historyStmt, 'i', $tenantID);
    mysqli_stmt_execute($historyResult = $historyStmt);
    $historyResult = mysqli_stmt_get_result($historyStmt);
    while ($historyResult && $historyRow = mysqli_fetch_assoc($historyResult)) {
        $historyRows[] = $historyRow;
    }
    mysqli_stmt_close($historyStmt);
}

$activeJobsSignature = count($jobRows) . '|0|';
$activeSignatureStmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total_active,
            COALESCE(MAX(repair_job_id), 0) AS latest_id,
            COALESCE(MAX(updated_at), '') AS latest_update
     FROM repair_jobs
     WHERE tenantID = ?
       AND job_status IN ('Queued', 'In Progress', 'Diagnostics', 'Waiting for Parts', 'Quality Check', 'Ready for Pickup')"
);
if ($activeSignatureStmt) {
    mysqli_stmt_bind_param($activeSignatureStmt, 'i', $tenantID);
    mysqli_stmt_execute($activeSignatureStmt);
    $activeSignatureResult = mysqli_stmt_get_result($activeSignatureStmt);
    if ($activeSignatureResult && $activeSignatureRow = mysqli_fetch_assoc($activeSignatureResult)) {
        $activeJobsSignature = (int) ($activeSignatureRow['total_active'] ?? 0) . '|' .
                               (int) ($activeSignatureRow['latest_id'] ?? 0) . '|' .
                               (string) ($activeSignatureRow['latest_update'] ?? '');
    }
    mysqli_stmt_close($activeSignatureStmt);
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
    </style>
</head>

<body class="bg-slate-50 text-slate-900 antialiased">
<div class="flex h-screen overflow-hidden">
    <aside class="w-64 flex-shrink-0 border-r border-slate-200 bg-white h-screen sticky top-0 flex flex-col overflow-y-auto">
        <div class="p-6">
            <div class="flex items-center gap-3 mb-8">
                <div class="bg-blue-700 rounded-lg p-2 text-white">
                    <span class="material-symbols-outlined">directions_car</span>
                </div>
                <div>
                    <h1 class="text-lg font-bold leading-none"><?php echo h($shopName); ?></h1>
                    <p class="text-xs text-slate-500 mt-1">Your Repair Shop</p>
                </div>
            </div>

            <nav class="space-y-1">
                <?php if (canAccessModule('dashboardadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors font-medium"
                       href="dashboardadmin.php?shop=<?php echo h($shopQuery); ?>">
                        <span class="material-symbols-outlined text-[22px]">dashboard</span>Dashboard
                    </a>
                <?php endif; ?>

                <?php if (canAccessModule('repairjobsadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-blue-50 text-blue-700 font-medium"
                       href="repairjobsadmin.php?shop=<?php echo h($shopQuery); ?>">
                        <span class="material-symbols-outlined text-[22px]">build</span>Repair Jobs
                    </a>
                <?php endif; ?>

                <?php if (canAccessModule('vehicleadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                       href="vehicleadmin.php?shop=<?php echo h($shopQuery); ?>">
                        <span class="material-symbols-outlined text-[22px]">directions_car</span>Vehicles
                    </a>
                <?php endif; ?>

                <?php if (canAccessModule('appointmentadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                       href="appointmentadmin.php?shop=<?php echo h($shopQuery); ?>">
                        <span class="material-symbols-outlined text-[22px]">event</span>Appointments
                    </a>
                <?php endif; ?>

                <?php if (canAccessModule('reportsadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                       href="reportsadmin.php?shop=<?php echo h($shopQuery); ?>">
                        <span class="material-symbols-outlined text-[22px]">description</span>Reports
                    </a>
                <?php endif; ?>

                <?php if (canAccessModule('inventoryadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                       href="inventoryadmin.php?shop=<?php echo h($shopQuery); ?>">
                        <span class="material-symbols-outlined text-[22px]">inventory_2</span>Inventory
                    </a>
                <?php endif; ?>

                <?php if (canAccessModule('customeradmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                       href="customeradmin.php?shop=<?php echo h($shopQuery); ?>">
                        <span class="material-symbols-outlined text-[22px]">group</span>Customers
                    </a>
                <?php endif; ?>

                <?php if (canAccessModule('paymentsadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                       href="paymentsadmin.php?shop=<?php echo h($shopQuery); ?>">
                        <span class="material-symbols-outlined text-[22px]">payments</span>Payments
                    </a>
                <?php endif; ?>

                <div class="pt-4 mt-4 border-t border-slate-100">
                    <div class="relative">
                        <button class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors w-full text-left settings-dropdown-btn" data-dropdown="settings">
                            <span class="material-symbols-outlined text-[22px]">settings</span>
                            <span>Settings</span>
                            <span class="material-symbols-outlined text-[16px] ml-auto">expand_more</span>
                        </button>
                        <div class="absolute left-0 top-full mt-1 w-full bg-white border border-slate-200 rounded-lg shadow-lg hidden z-50 settings-dropdown" data-dropdown="settings">
                            <?php if (canAccessModule('accountbillingadmin.php', $accessibleModules)): ?>
                                <a class="flex items-center gap-3 px-3 py-2.5 rounded-t-lg text-slate-600 hover:bg-blue-50 transition-colors text-sm"
                                   href="accountbillingadmin.php?shop=<?php echo h($shopQuery); ?>">
                                    <span class="material-symbols-outlined text-[18px]">receipt_long</span>Account Billing
                                </a>
                            <?php endif; ?>
                            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 hover:bg-blue-50 transition-colors text-sm border-t border-slate-100"
                               href="websitecustomadmin.php?shop=<?php echo h($shopQuery); ?>">
                                <span class="material-symbols-outlined text-[18px]">palette</span>Website Customizer
                            </a>
                            <?php if (canAccessModule('settingsadmin.php', $accessibleModules)): ?>
                                <a class="flex items-center gap-3 px-3 py-2.5 rounded-b-lg text-slate-600 hover:bg-blue-50 transition-colors text-sm border-t border-slate-100"
                                   href="settingsadmin.php?shop=<?php echo h($shopQuery); ?>">
                                    <span class="material-symbols-outlined text-[18px]">settings</span>Settings
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </nav>
        </div>

        <div class="mt-auto w-full p-4 border-t border-slate-200">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-slate-200 flex items-center justify-center overflow-hidden shrink-0">
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

    <main class="flex-1 overflow-y-auto flex flex-col bg-slate-50">
        <header class="sticky top-0 z-40 w-full border-b border-slate-200 bg-white/90 backdrop-blur-md flex items-center justify-between px-8 h-16">
            <h2 class="text-lg font-black tracking-tight">Repair Jobs Management</h2>
            <div class="flex items-center gap-4">
                <button type="button" id="notificationBtn" class="relative p-2 text-slate-500 hover:text-blue-700 transition-all">
                    <span class="material-symbols-outlined">notifications</span>
                    <?php if ($notificationCount > 0): ?>
                        <span class="absolute right-1 top-1 flex h-2.5 w-2.5 rounded-full bg-red-500"></span>
                    <?php endif; ?>
                </button>
                <button class="p-2 text-slate-500 hover:text-blue-700 transition-all">
                    <span class="material-symbols-outlined">help_outline</span>
                </button>
            </div>
        </header>

        <div id="notificationPanel" class="hidden fixed right-8 top-20 z-50 w-80 rounded-2xl border border-slate-200 bg-white shadow-2xl opacity-0 translate-y-2 transition-all duration-300 ease-out">
            <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <p class="font-bold text-slate-900">Notifications</p>
                    <p class="text-xs text-slate-500">Repair workflow alerts</p>
                </div>
                <span class="material-symbols-outlined text-slate-400">notifications</span>
            </div>
            <div class="p-4 space-y-3">
                <div class="rounded-xl bg-blue-50 border border-blue-100 p-3">
                    <p class="text-sm font-bold text-blue-900"><?php echo number_format($notificationStats['approved_diagnostics']); ?> approved diagnostic(s)</p>
                    <p class="text-xs text-blue-700 mt-1">Customer approval received today.</p>
                </div>
                <div class="rounded-xl bg-amber-50 border border-amber-100 p-3">
                    <p class="text-sm font-bold text-amber-900"><?php echo number_format($notificationStats['new_repair_jobs']); ?> new repair job(s)</p>
                    <p class="text-xs text-amber-700 mt-1">Arrived today and waiting in the queue.</p>
                </div>
                <?php if (count($notificationItems) > 0): ?>
                    <div class="space-y-2 pt-1">
                        <?php foreach ($notificationItems as $item): ?>
                            <div class="rounded-xl border border-slate-200 p-3">
                                <p class="text-sm font-bold text-slate-900"><?php echo h($item['title']); ?></p>
                                <p class="text-xs text-slate-600 mt-1"><?php echo h($item['detail']); ?></p>
                                <?php if (!empty($item['time'])): ?>
                                    <p class="text-[11px] text-slate-400 mt-1"><?php echo h($item['time']); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="rounded-xl border border-slate-200 p-3 text-sm text-slate-500">
                        No new notifications right now.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="px-8 pb-12 pt-8">
            <?php if ($message !== ''): ?>
                <div class="mb-6 flex items-start gap-3 rounded-2xl border px-5 py-4 shadow-sm <?php echo $messageType === 'error' ? 'border-red-200 bg-red-50 text-red-700' : 'border-emerald-200 bg-emerald-50 text-emerald-800'; ?>">
                    <span class="material-symbols-outlined"><?php echo $messageType === 'error' ? 'error' : 'check_circle'; ?></span>
                    <div>
                        <p class="text-sm font-bold"><?php echo h($message); ?></p>
                        <p class="text-xs opacity-80 mt-0.5"><?php echo $messageType === 'error' ? 'Please check your form values and try again.' : 'Your latest change has been saved.'; ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="mb-8 flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
                <div>
                    <h2 class="text-3xl font-black tracking-tight">Repair Jobs</h2>
                    <p class="text-slate-600 font-medium mt-1">Real-time floor management, diagnostics, and job tracking.</p>
                </div>
                <p class="text-xs font-semibold text-slate-500">Diagnostic reports are sent to the customer for approval.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">In Workshop</p>
                    <p class="text-2xl font-black mt-2"><?php echo number_format($stats['in_workshop']); ?></p>
                </div>
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Waiting Parts</p>
                    <p class="text-2xl font-black mt-2"><?php echo number_format($stats['waiting_parts']); ?></p>
                </div>
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Ready Pickup</p>
                    <p class="text-2xl font-black mt-2"><?php echo number_format($stats['ready_pickup']); ?></p>
                </div>
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">For Approval</p>
                    <p class="text-2xl font-black mt-2"><?php echo number_format($stats['for_approval']); ?></p>
                </div>
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Avg. Cycle</p>
                    <p class="text-2xl font-black mt-2"><?php echo $avgCycleHours > 0 ? number_format($avgCycleHours, 1) . ' hrs' : 'N/A'; ?></p>
                </div>
            </div>

            <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-8">
                <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Upcoming Appointments</h3>
                        <p class="text-xs text-slate-500 font-medium">Pending, confirmed, diagnostic, approval, and in-progress appointments.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <form method="get" class="flex items-center gap-2">
                            <input type="hidden" name="shop" value="<?php echo h($loginSlug); ?>">
                            <input type="hidden" name="q" value="<?php echo h($search); ?>">
                            <input type="hidden" name="job_status" value="<?php echo h($jobStatusFilter); ?>">
                            <input type="hidden" name="service_status" value="<?php echo h($serviceStatusFilter); ?>">
                            <input type="hidden" name="priority" value="<?php echo h($priorityFilter); ?>">
                            <input type="hidden" name="jobs_sort_by" value="<?php echo h($jobsSortBy); ?>">
                            <input type="hidden" name="jobs_sort_dir" value="<?php echo h($jobsSortDir); ?>">
                            <input type="hidden" name="diagnostics_sort_by" value="<?php echo h($diagnosticsSortBy); ?>">
                            <input type="hidden" name="diagnostics_sort_dir" value="<?php echo h($diagnosticsSortDir); ?>">
                            <select name="upcoming_sort_by" class="rounded-lg border-slate-300 text-xs min-w-[150px]">
                                <option value="appointment_id" <?php echo $upcomingSortBy === 'appointment_id' ? 'selected' : ''; ?>>Sort: Appointment ID</option>
                                <option value="appointment_date" <?php echo $upcomingSortBy === 'appointment_date' ? 'selected' : ''; ?>>Sort: Date</option>
                                <option value="appointment_time" <?php echo $upcomingSortBy === 'appointment_time' ? 'selected' : ''; ?>>Sort: Time</option>
                                <option value="status" <?php echo $upcomingSortBy === 'status' ? 'selected' : ''; ?>>Sort: Status</option>
                                <option value="total_amount" <?php echo $upcomingSortBy === 'total_amount' ? 'selected' : ''; ?>>Sort: Amount</option>
                            </select>
                            <select name="upcoming_sort_dir" class="rounded-lg border-slate-300 text-xs min-w-[110px]">
                                <option value="DESC" <?php echo $upcomingSortDir === 'DESC' ? 'selected' : ''; ?>>Descending</option>
                                <option value="ASC" <?php echo $upcomingSortDir === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                            </select>
                            <button type="submit" class="px-3 py-2 rounded-lg border border-slate-300 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-100">Apply</button>
                        </form>
                        <a href="appointmentadmin.php?shop=<?php echo h($shopQuery); ?>" class="text-xs font-semibold text-blue-700 hover:underline">Open Appointments Page</a>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                        <tr class="bg-slate-50/50">
                            <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">Appointment</th>
                            <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">Customer</th>
                            <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">Vehicle</th>
                            <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">Date / Time</th>
                            <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">Status</th>
                            <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">Amount</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                        <?php if (count($upcomingAppointments) === 0): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-slate-500">No upcoming appointments found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($upcomingAppointments as $appointment): ?>
                                <?php
                                $vehicleText = trim((string) ($appointment['vehicle_name'] ?? ''));
                                if ($vehicleText === '') $vehicleText = 'Vehicle record';
                                $plateText = trim((string) ($appointment['plate_number'] ?? ''));
                                ?>
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-6 py-4 text-sm font-bold text-slate-900">#<?php echo (int) $appointment['appointment_id']; ?></td>
                                    <td class="px-6 py-4 text-sm text-slate-700"><?php echo h($appointment['customer_name']); ?></td>
                                    <td class="px-6 py-4 text-sm text-slate-700">
                                        <?php echo h($vehicleText); ?>
                                        <?php if ($plateText !== ''): ?>
                                            <div class="text-xs text-slate-500 mt-0.5">Plate: <?php echo h($plateText); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-700">
                                        <div class="font-semibold"><?php echo h(date('M d, Y', strtotime((string) $appointment['appointment_date']))); ?></div>
                                        <div class="text-xs text-slate-500"><?php echo h(date('h:i A', strtotime((string) $appointment['appointment_time']))); ?></div>
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <span class="inline-flex px-2 py-1 rounded-full text-xs font-bold <?php echo h(statusBadgeClass((string) $appointment['status'])); ?>">
                                            <?php echo h($appointment['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-semibold text-slate-900">₱<?php echo number_format((float) ($appointment['total_amount'] ?? 0), 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/40 flex items-center justify-between">
                    <p class="text-xs text-slate-500 font-medium">Showing <?php echo number_format(count($upcomingAppointments)); ?> of <?php echo number_format($upcomingTotalRows); ?> records</p>
                    <div class="flex items-center gap-2">
                        <?php if ($upcomingPage > 1): ?>
                            <a href="repairjobsadmin.php?<?php echo h(http_build_query(array_filter([
                                'shop' => $loginSlug,
                                'q' => $search,
                                'job_status' => $jobStatusFilter,
                                'service_status' => $serviceStatusFilter,
                                'priority' => $priorityFilter,
                                'upcoming_sort_by' => $upcomingSortBy,
                                'upcoming_sort_dir' => $upcomingSortDir,
                                'jobs_sort_by' => $jobsSortBy,
                                'jobs_sort_dir' => $jobsSortDir,
                                'diagnostics_sort_by' => $diagnosticsSortBy,
                                'diagnostics_sort_dir' => $diagnosticsSortDir,
                                'upcoming_page' => $upcomingPage - 1,
                                'jobs_page' => $jobsPage,
                                'diagnostics_page' => $diagnosticsPage,
                            ], static fn($v) => $v !== ''))); ?>" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 bg-white hover:bg-slate-100">Previous</a>
                        <?php endif; ?>

                        <span class="px-2 py-1 text-xs font-semibold text-slate-600">Page <?php echo (int) $upcomingPage; ?> of <?php echo (int) $upcomingTotalPages; ?></span>

                        <?php if ($upcomingPage < $upcomingTotalPages): ?>
                            <a href="repairjobsadmin.php?<?php echo h(http_build_query(array_filter([
                                'shop' => $loginSlug,
                                'q' => $search,
                                'job_status' => $jobStatusFilter,
                                'service_status' => $serviceStatusFilter,
                                'priority' => $priorityFilter,
                                'upcoming_sort_by' => $upcomingSortBy,
                                'upcoming_sort_dir' => $upcomingSortDir,
                                'jobs_sort_by' => $jobsSortBy,
                                'jobs_sort_dir' => $jobsSortDir,
                                'diagnostics_sort_by' => $diagnosticsSortBy,
                                'diagnostics_sort_dir' => $diagnosticsSortDir,
                                'upcoming_page' => $upcomingPage + 1,
                                'jobs_page' => $jobsPage,
                                'diagnostics_page' => $diagnosticsPage,
                            ], static fn($v) => $v !== ''))); ?>" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 bg-white hover:bg-slate-100">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-8">
                <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Active Repair Jobs</h3>
                        <p class="text-xs text-slate-500 font-medium">Job-level status, diagnostics, and financial summary.</p>
                    </div>
                    <span class="text-xs font-bold text-slate-500"><?php echo number_format(count($jobRows)); ?> rows</span>
                </div>

                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/40">
                    <form method="get" class="flex flex-wrap items-center gap-3">
                        <input type="hidden" name="shop" value="<?php echo h($loginSlug); ?>">
                        <input type="hidden" name="upcoming_sort_by" value="<?php echo h($upcomingSortBy); ?>">
                        <input type="hidden" name="upcoming_sort_dir" value="<?php echo h($upcomingSortDir); ?>">
                        <input type="hidden" name="diagnostics_sort_by" value="<?php echo h($diagnosticsSortBy); ?>">
                        <input type="hidden" name="diagnostics_sort_dir" value="<?php echo h($diagnosticsSortDir); ?>">
                        <div class="relative flex-1 min-w-[240px]">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">search</span>
                            <input type="text" name="q" value="<?php echo h($search); ?>" placeholder="Filter by job order, customer, vehicle..." class="w-full rounded-lg border-slate-300 pl-9 pr-3 py-2 text-sm" />
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
                        <select name="jobs_sort_by" class="rounded-lg border-slate-300 text-sm min-w-[170px]">
                            <option value="repair_job_id" <?php echo $jobsSortBy === 'repair_job_id' ? 'selected' : ''; ?>>Sort: Repair Job ID</option>
                            <option value="appointment_id" <?php echo $jobsSortBy === 'appointment_id' ? 'selected' : ''; ?>>Sort: Appointment ID</option>
                            <option value="appointment_date" <?php echo $jobsSortBy === 'appointment_date' ? 'selected' : ''; ?>>Sort: Repair Date</option>
                            <option value="appointment_time" <?php echo $jobsSortBy === 'appointment_time' ? 'selected' : ''; ?>>Sort: Repair Time</option>
                            <option value="job_status" <?php echo $jobsSortBy === 'job_status' ? 'selected' : ''; ?>>Sort: Job Status</option>
                            <option value="priority" <?php echo $jobsSortBy === 'priority' ? 'selected' : ''; ?>>Sort: Priority</option>
                            <option value="grand_total" <?php echo $jobsSortBy === 'grand_total' ? 'selected' : ''; ?>>Sort: Grand Total</option>
                            <option value="updated_at" <?php echo $jobsSortBy === 'updated_at' ? 'selected' : ''; ?>>Sort: Last Updated</option>
                            <option value="completed_at" <?php echo $jobsSortBy === 'completed_at' ? 'selected' : ''; ?>>Sort: Completed Time</option>
                        </select>
                        <select name="jobs_sort_dir" class="rounded-lg border-slate-300 text-sm min-w-[120px]">
                            <option value="DESC" <?php echo $jobsSortDir === 'DESC' ? 'selected' : ''; ?>>Descending</option>
                            <option value="ASC" <?php echo $jobsSortDir === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                        </select>
                        <button type="submit" class="inline-flex items-center justify-center w-11 h-10 rounded-lg border border-slate-300 bg-white text-slate-600 hover:bg-slate-100" title="Apply Filters">
                            <span class="material-symbols-outlined text-lg">filter_list</span>
                        </button>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                        <tr class="bg-slate-50/50">
                            <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">Order Details</th>
                            <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">Services</th>
                            <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">Repair Date & Time</th>
                            <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">Total</th>
                            <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">Labor Hrs</th>
                            <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">Status</th>
                            <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">Actions</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                        <?php if (count($jobRows) === 0): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-10 text-center text-sm text-slate-500">No repair jobs found for this filter.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($jobRows as $job): ?>
                                <?php
                                $vehicleText = trim(((string) ($job['year_model'] ?? '')) . ' ' . ((string) ($job['brand'] ?? '')) . ' ' . ((string) ($job['model'] ?? '')));
                                $estimatedHours = ((float) ($job['total_estimated_minutes'] ?? 0)) / 60;
                                $actualHours = ((float) ($job['total_actual_minutes'] ?? 0)) / 60;
                                $repairDateRaw = trim((string) ($job['appointment_date'] ?? ''));
                                $repairTimeRaw = trim((string) ($job['appointment_time'] ?? ''));
                                $repairDateLabel = $repairDateRaw !== '' ? date('M d, Y', strtotime($repairDateRaw)) : 'No date set';
                                $repairTimeLabel = $repairTimeRaw !== '' ? date('h:i A', strtotime($repairTimeRaw)) : 'No time set';
                                $hasDiagnosticReport = !empty($job['diagnostic_id']);
                                $hasDiagnosticMainService = ((int) ($job['diagnostic_service_count'] ?? 0)) > 0;
                                ?>
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-sm text-slate-900"><?php echo h(!empty($job['job_order_no']) ? $job['job_order_no'] : 'RJO-' . $job['repair_job_id']); ?></div>
                                        <div class="text-xs text-slate-500"><?php echo h($vehicleText !== '' ? $vehicleText : 'Vehicle record'); ?></div>
                                        <div class="text-[11px] text-slate-400 mt-1">
                                            Customer: <?php echo h($job['customer_name']); ?>
                                            <?php echo $job['bay_no'] ? ' | Bay: ' . h($job['bay_no']) : ''; ?>
                                        </div>
                                        <?php if ($hasDiagnosticReport): ?>
                                            <div class="mt-1 flex items-center gap-1 text-[11px] text-blue-700 font-bold">
                                                <span class="material-symbols-outlined text-sm">clinical_notes</span>
                                                Diagnostic: <?php echo h($job['diagnosis_status']); ?> / <?php echo h($job['customer_approval']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-700 max-w-md"><?php echo h($job['services']); ?></td>
                                    <td class="px-6 py-4 text-sm text-slate-700">
                                        <div class="font-semibold text-slate-900"><?php echo h($repairDateLabel); ?></div>
                                        <div class="text-xs text-slate-500"><?php echo h($repairTimeLabel); ?></div>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-bold text-slate-900">₱<?php echo number_format((float) ($job['grand_total'] ?? 0), 2); ?></td>
                                    <td class="px-6 py-4 text-sm font-medium text-slate-600"><?php echo number_format($actualHours, 1); ?> / <?php echo number_format($estimatedHours, 1); ?></td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex px-2 py-1 rounded-full text-xs font-bold <?php echo h(statusBadgeClass((string) $job['job_status'])); ?>">
                                            <?php echo h($job['job_status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($job['job_status'] === 'Completed' || $job['job_status'] === 'Cancelled'): ?>
                                            <span class="text-xs text-slate-500 font-semibold">Done</span>
                                        <?php else: ?>
                                            <div class="flex flex-col gap-2">
                                                <form method="get" class="flex items-center gap-2">
                                                    <input type="hidden" name="shop" value="<?php echo h($loginSlug); ?>">
                                                    <input type="hidden" name="q" value="<?php echo h($search); ?>">
                                                    <input type="hidden" name="job_status" value="<?php echo h($jobStatusFilter); ?>">
                                                    <input type="hidden" name="service_status" value="<?php echo h($serviceStatusFilter); ?>">
                                                    <input type="hidden" name="priority" value="<?php echo h($priorityFilter); ?>">
                                                    <select name="job_status" class="rounded-lg border-slate-300 text-xs" onchange="handleJobStatusChange(this, <?php echo (int) $job['repair_job_id']; ?>)">
                                                        <?php foreach ($jobStatuses as $status): ?>
                                                            <option value="<?php echo h($status); ?>" <?php echo $job['job_status'] === $status ? 'selected' : ''; ?>>
                                                                <?php echo h($status); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </form>

                                                <?php if ($job['job_status'] === 'Queued'): ?>
                                                    <form method="post" onsubmit="return confirm('Start this repair job now even before the scheduled appointment time?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                                                        <input type="hidden" name="repair_job_id" value="<?php echo (int) $job['repair_job_id']; ?>">
                                                        <input type="hidden" name="start_repair_now" value="1">
                                                        <button type="submit" class="inline-flex items-center justify-center gap-1 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-blue-700">
                                                            <span class="material-symbols-outlined text-sm">play_arrow</span>
                                                            Start Repair Now
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <?php if ($job['job_status'] === 'Diagnostics' || ($job['job_status'] === 'In Progress' && !$hasDiagnosticMainService)): ?>
                                                    <a href="repairjobsadmin.php?<?php echo h(http_build_query(array_filter([
                                                        'shop' => $loginSlug,
                                                        'q' => $search,
                                                        'job_status' => $jobStatusFilter,
                                                        'service_status' => $serviceStatusFilter,
                                                        'priority' => $priorityFilter,
                                                        'diagnostic_job' => (int) $job['repair_job_id'],
                                                    ], static fn($v) => $v !== ''))); ?>"
                                                       class="inline-flex items-center justify-center gap-1 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-indigo-700">
                                                        <span class="material-symbols-outlined text-sm">clinical_notes</span>
                                                        <?php echo $hasDiagnosticReport ? 'Edit Diagnostic' : 'Create Diagnostic'; ?>
                                                    </a>
                                                <?php elseif ($job['job_status'] === 'In Progress' && $hasDiagnosticMainService): ?>
                                                    <span class="inline-flex items-center justify-center gap-1 rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600">
                                                        <span class="material-symbols-outlined text-sm">hourglass_top</span>
                                                        In Progress
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/40 flex items-center justify-between">
                    <p class="text-xs text-slate-500 font-medium">Showing <?php echo number_format(count($jobRows)); ?> of <?php echo number_format($jobsTotalRows); ?> records</p>
                    <div class="flex items-center gap-2">
                        <?php if ($jobsPage > 1): ?>
                            <a href="repairjobsadmin.php?<?php echo h(http_build_query(array_filter([
                                'shop' => $loginSlug,
                                'q' => $search,
                                'job_status' => $jobStatusFilter,
                                'service_status' => $serviceStatusFilter,
                                'priority' => $priorityFilter,
                                'upcoming_sort_by' => $upcomingSortBy,
                                'upcoming_sort_dir' => $upcomingSortDir,
                                'jobs_sort_by' => $jobsSortBy,
                                'jobs_sort_dir' => $jobsSortDir,
                                'diagnostics_sort_by' => $diagnosticsSortBy,
                                'diagnostics_sort_dir' => $diagnosticsSortDir,
                                'upcoming_page' => $upcomingPage,
                                'jobs_page' => $jobsPage - 1,
                                'diagnostics_page' => $diagnosticsPage,
                            ], static fn($v) => $v !== ''))); ?>" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 bg-white hover:bg-slate-100">Previous</a>
                        <?php endif; ?>

                        <span class="px-2 py-1 text-xs font-semibold text-slate-600">Page <?php echo (int) $jobsPage; ?> of <?php echo (int) $jobsTotalPages; ?></span>

                        <?php if ($jobsPage < $jobsTotalPages): ?>
                            <a href="repairjobsadmin.php?<?php echo h(http_build_query(array_filter([
                                'shop' => $loginSlug,
                                'q' => $search,
                                'job_status' => $jobStatusFilter,
                                'service_status' => $serviceStatusFilter,
                                'priority' => $priorityFilter,
                                'upcoming_sort_by' => $upcomingSortBy,
                                'upcoming_sort_dir' => $upcomingSortDir,
                                'jobs_sort_by' => $jobsSortBy,
                                'jobs_sort_dir' => $jobsSortDir,
                                'diagnostics_sort_by' => $diagnosticsSortBy,
                                'diagnostics_sort_dir' => $diagnosticsSortDir,
                                'upcoming_page' => $upcomingPage,
                                'jobs_page' => $jobsPage + 1,
                                'diagnostics_page' => $diagnosticsPage,
                            ], static fn($v) => $v !== ''))); ?>" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 bg-white hover:bg-slate-100">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-8" id="repairHistorySection">
                <div class="px-6 py-5 border-b border-slate-100 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Repair Jobs History</h3>
                        <p class="text-xs text-slate-500 font-medium">Completed and cancelled repair jobs from the existing repair_jobs table.</p>
                    </div>
                    <span class="text-xs font-bold text-slate-500"><span id="historyVisibleCount"><?php echo number_format(count($historyRows)); ?></span> rows</span>
                </div>

                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/40 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="relative flex-1 min-w-[240px] max-w-xl">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">search</span>
                        <input type="text" id="historySearch" placeholder="Search job order, customer, vehicle, service, technician..." class="w-full rounded-lg border-slate-300 pl-9 pr-3 py-2 text-sm" autocomplete="off" />
                    </div>
                    <div class="flex flex-wrap gap-2" id="historyFilterButtons">
                        <button type="button" data-history-filter="All" class="history-filter-btn rounded-lg bg-slate-900 px-3 py-2 text-xs font-bold text-white hover:bg-slate-800">All</button>
                        <button type="button" data-history-filter="Completed" class="history-filter-btn rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100">Completed</button>
                        <button type="button" data-history-filter="Cancelled" class="history-filter-btn rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100">Cancelled</button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left" id="historyTable">
                        <thead>
                        <tr class="bg-slate-50/50">
                            <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">Order Details</th>
                            <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">Services</th>
                            <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">Technician / Bay</th>
                            <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">Totals</th>
                            <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">Final Status</th>
                            <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">Date</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (count($historyRows) === 0): ?>
                            <tr class="history-empty-row">
                                <td colspan="6" class="px-6 py-10 text-center text-sm font-semibold text-slate-500">No completed or cancelled repair jobs yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($historyRows as $history): ?>
                                <?php
                                    $historyVehicle = trim((string) ($history['vehicle_name'] ?? ''));
                                    $historyDateValue = $history['job_status'] === 'Completed'
                                        ? ($history['completed_at'] ?: $history['updated_at'])
                                        : ($history['updated_at'] ?: $history['completed_at']);
                                    $historySearchText = strtolower(trim(implode(' ', [
                                        $history['job_order_no'] ?? '',
                                        $history['customer_name'] ?? '',
                                        $historyVehicle,
                                        $history['services'] ?? '',
                                        $history['assigned_technician'] ?? '',
                                        $history['bay_no'] ?? '',
                                        $history['job_status'] ?? '',
                                    ])));
                                ?>
                                <tr class="border-t border-slate-100 history-row" data-history-status="<?php echo h($history['job_status']); ?>" data-history-search="<?php echo h($historySearchText); ?>">
                                    <td class="px-6 py-4 align-top">
                                        <p class="text-sm font-bold text-slate-900"><?php echo h($history['job_order_no']); ?></p>
                                        <p class="text-xs font-semibold text-slate-600 mt-1"><?php echo h($history['customer_name']); ?></p>
                                        <p class="text-xs text-slate-500 mt-1"><?php echo h($historyVehicle !== '' ? $historyVehicle : 'Vehicle not set'); ?></p>
                                    </td>
                                    <td class="px-6 py-4 align-top max-w-[280px]">
                                        <p class="text-xs text-slate-600 leading-relaxed"><?php echo h($history['services']); ?></p>
                                    </td>
                                    <td class="px-6 py-4 align-top">
                                        <p class="text-xs font-semibold text-slate-700"><?php echo h($history['assigned_technician'] ?: 'Unassigned'); ?></p>
                                        <p class="text-xs text-slate-500 mt-1">Bay: <?php echo h($history['bay_no'] ?: 'N/A'); ?></p>
                                    </td>
                                    <td class="px-6 py-4 align-top">
                                        <p class="text-sm font-black text-slate-900">₱<?php echo number_format((float) ($history['grand_total'] ?? 0), 2); ?></p>
                                        <p class="text-[11px] text-slate-500 mt-1">Labor: ₱<?php echo number_format((float) ($history['labor_total'] ?? 0), 2); ?></p>
                                        <p class="text-[11px] text-slate-500">Parts: ₱<?php echo number_format((float) ($history['parts_total'] ?? 0), 2); ?></p>
                                    </td>
                                    <td class="px-6 py-4 align-top">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold <?php echo h(statusBadgeClass((string) $history['job_status'])); ?>">
                                            <?php echo h($history['job_status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 align-top">
                                        <p class="text-xs font-semibold text-slate-700">
                                            <?php echo !empty($historyDateValue) ? h(date('M d, Y', strtotime((string) $historyDateValue))) : '-'; ?>
                                        </p>
                                        <p class="text-[11px] text-slate-500 mt-1">
                                            <?php echo !empty($historyDateValue) ? h(date('h:i A', strtotime((string) $historyDateValue))) : ''; ?>
                                        </p>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr id="historyNoResultsRow" class="hidden">
                                <td colspan="6" class="px-6 py-10 text-center text-sm font-semibold text-slate-500">No history records match your search/filter.</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Diagnostic Reports</h3>
                        <p class="text-xs text-slate-500 font-medium">Recommended sub-services waiting for customer approval.</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <form method="get" class="flex items-center gap-2">
                            <input type="hidden" name="shop" value="<?php echo h($loginSlug); ?>">
                            <input type="hidden" name="q" value="<?php echo h($search); ?>">
                            <input type="hidden" name="job_status" value="<?php echo h($jobStatusFilter); ?>">
                            <input type="hidden" name="service_status" value="<?php echo h($serviceStatusFilter); ?>">
                            <input type="hidden" name="priority" value="<?php echo h($priorityFilter); ?>">
                            <input type="hidden" name="upcoming_sort_by" value="<?php echo h($upcomingSortBy); ?>">
                            <input type="hidden" name="upcoming_sort_dir" value="<?php echo h($upcomingSortDir); ?>">
                            <input type="hidden" name="jobs_sort_by" value="<?php echo h($jobsSortBy); ?>">
                            <input type="hidden" name="jobs_sort_dir" value="<?php echo h($jobsSortDir); ?>">
                            <select name="diagnostics_sort_by" class="rounded-lg border-slate-300 text-xs min-w-[170px]">
                                <option value="updated_at" <?php echo $diagnosticsSortBy === 'updated_at' ? 'selected' : ''; ?>>Sort: Last Updated</option>
                                <option value="created_at" <?php echo $diagnosticsSortBy === 'created_at' ? 'selected' : ''; ?>>Sort: Created Time</option>
                                <option value="diagnostic_id" <?php echo $diagnosticsSortBy === 'diagnostic_id' ? 'selected' : ''; ?>>Sort: Diagnostic ID</option>
                                <option value="repair_job_id" <?php echo $diagnosticsSortBy === 'repair_job_id' ? 'selected' : ''; ?>>Sort: Repair Job ID</option>
                                <option value="appointment_id" <?php echo $diagnosticsSortBy === 'appointment_id' ? 'selected' : ''; ?>>Sort: Appointment ID</option>
                                <option value="mechanic_name" <?php echo $diagnosticsSortBy === 'mechanic_name' ? 'selected' : ''; ?>>Sort: Mechanic</option>
                                <option value="estimated_total" <?php echo $diagnosticsSortBy === 'estimated_total' ? 'selected' : ''; ?>>Sort: Estimated Total</option>
                                <option value="customer_approval" <?php echo $diagnosticsSortBy === 'customer_approval' ? 'selected' : ''; ?>>Sort: Approval</option>
                                <option value="diagnosis_status" <?php echo $diagnosticsSortBy === 'diagnosis_status' ? 'selected' : ''; ?>>Sort: Status</option>
                            </select>
                            <select name="diagnostics_sort_dir" class="rounded-lg border-slate-300 text-xs min-w-[110px]">
                                <option value="DESC" <?php echo $diagnosticsSortDir === 'DESC' ? 'selected' : ''; ?>>Descending</option>
                                <option value="ASC" <?php echo $diagnosticsSortDir === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                            </select>
                            <button type="submit" class="px-3 py-2 rounded-lg border border-slate-300 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-100">Apply</button>
                        </form>
                        <span class="text-xs font-bold text-slate-500"><?php echo number_format($diagnosticTotalRows); ?> report(s)</span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                        <tr class="bg-slate-50/50">
                            <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">Report</th>
                            <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">Customer / Vehicle</th>
                            <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">Recommended Sub-Services</th>
                            <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">Estimated Total</th>
                            <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">Approval</th>
                            <th class="px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">Status</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                        <?php if (count($diagnosticRows) === 0): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-slate-500">No diagnostic reports yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($diagnosticRows as $report): ?>
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-sm text-slate-900"><?php echo h($report['job_order_no']); ?></div>
                                        <div class="text-xs text-slate-500">Mechanic: <?php echo h($report['mechanic_name']); ?></div>
                                        <div class="text-[11px] text-slate-400 mt-1"><?php echo h(date('M d, Y h:i A', strtotime((string) $report['updated_at']))); ?></div>
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <div class="font-semibold text-slate-800"><?php echo h($report['customer_name']); ?></div>
                                        <div class="text-xs text-slate-500"><?php echo h(trim((string) $report['vehicle_name']) !== '' ? $report['vehicle_name'] : 'Vehicle record'); ?></div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-700 max-w-md"><?php echo h($report['recommended_services']); ?></td>
                                    <td class="px-6 py-4 text-sm font-bold text-slate-900">₱<?php echo number_format((float) ($report['estimated_total'] ?? 0), 2); ?></td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex px-2 py-1 rounded-full text-xs font-bold <?php echo h(statusBadgeClass((string) $report['customer_approval'])); ?>">
                                            <?php echo h($report['customer_approval']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex px-2 py-1 rounded-full text-xs font-bold <?php echo h(statusBadgeClass((string) $report['diagnosis_status'])); ?>">
                                            <?php echo h($report['diagnosis_status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/40 flex items-center justify-between">
                    <p class="text-xs text-slate-500 font-medium">Showing <?php echo number_format(count($diagnosticRows)); ?> of <?php echo number_format($diagnosticTotalRows); ?> records</p>
                    <div class="flex items-center gap-2">
                        <?php if ($diagnosticsPage > 1): ?>
                            <a href="repairjobsadmin.php?<?php echo h(http_build_query(array_filter([
                                'shop' => $loginSlug,
                                'q' => $search,
                                'job_status' => $jobStatusFilter,
                                'service_status' => $serviceStatusFilter,
                                'priority' => $priorityFilter,
                                'upcoming_sort_by' => $upcomingSortBy,
                                'upcoming_sort_dir' => $upcomingSortDir,
                                'jobs_sort_by' => $jobsSortBy,
                                'jobs_sort_dir' => $jobsSortDir,
                                'diagnostics_sort_by' => $diagnosticsSortBy,
                                'diagnostics_sort_dir' => $diagnosticsSortDir,
                                'upcoming_page' => $upcomingPage,
                                'jobs_page' => $jobsPage,
                                'diagnostics_page' => $diagnosticsPage - 1,
                            ], static fn($v) => $v !== ''))); ?>" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 bg-white hover:bg-slate-100">Previous</a>
                        <?php endif; ?>

                        <span class="px-2 py-1 text-xs font-semibold text-slate-600">Page <?php echo (int) $diagnosticsPage; ?> of <?php echo (int) $diagnosticTotalPages; ?></span>

                        <?php if ($diagnosticsPage < $diagnosticTotalPages): ?>
                            <a href="repairjobsadmin.php?<?php echo h(http_build_query(array_filter([
                                'shop' => $loginSlug,
                                'q' => $search,
                                'job_status' => $jobStatusFilter,
                                'service_status' => $serviceStatusFilter,
                                'priority' => $priorityFilter,
                                'upcoming_sort_by' => $upcomingSortBy,
                                'upcoming_sort_dir' => $upcomingSortDir,
                                'jobs_sort_by' => $jobsSortBy,
                                'jobs_sort_dir' => $jobsSortDir,
                                'diagnostics_sort_by' => $diagnosticsSortBy,
                                'diagnostics_sort_dir' => $diagnosticsSortDir,
                                'upcoming_page' => $upcomingPage,
                                'jobs_page' => $jobsPage,
                                'diagnostics_page' => $diagnosticsPage + 1,
                            ], static fn($v) => $v !== ''))); ?>" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 bg-white hover:bg-slate-100">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>
    </main>
</div>

<?php if ($showDiagnosticModal && $diagnosticModalJob): ?>
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
        <section class="relative w-full max-w-5xl max-h-[90vh] overflow-y-auto bg-white rounded-2xl border border-slate-200 shadow-2xl">
            <div class="sticky top-0 z-10 bg-white border-b border-slate-100 px-6 py-5 flex items-center justify-between gap-3">
                <div>
                    <h3 class="font-bold text-slate-900 text-lg">Diagnostic Report & Recommended Sub-Services</h3>
                    <p class="text-xs text-slate-500 mt-1">
                        <?php echo h($diagnosticModalJob['job_order_no']); ?> |
                        <?php echo h($diagnosticModalJob['customer_name']); ?> |
                        <?php echo h(trim((string) $diagnosticModalJob['vehicle_name'])); ?>
                    </p>
                </div>
                <a href="repairjobsadmin.php?<?php echo h(http_build_query(array_filter([
                    'shop' => $loginSlug,
                    'q' => $search,
                    'job_status' => $jobStatusFilter,
                    'service_status' => $serviceStatusFilter,
                    'priority' => $priorityFilter,
                ], static fn($v) => $v !== ''))); ?>" class="inline-flex items-center justify-center w-8 h-8 rounded-full text-slate-500 hover:text-slate-700 hover:bg-slate-100 transition-colors">
                    <span class="material-symbols-outlined">close</span>
                </a>
            </div>

            <form method="post" class="p-6 space-y-6" id="diagnosticForm">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                <input type="hidden" name="repair_job_id" value="<?php echo (int) $diagnosticModalJobId; ?>">
                <input type="hidden" name="submit_diagnostic_report" value="1">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-bold uppercase text-slate-500">Mechanic Name</label>
                        <input
                            type="text"
                            name="mechanic_name"
                            value="<?php echo h($existingDiagnosticReport['mechanic_name'] ?? $diagnosticModalJob['assigned_technician'] ?? ''); ?>"
                            class="mt-1 w-full rounded-lg border-slate-300 text-sm"
                            required>
                    </div>
                    <div>
                        <label class="text-xs font-bold uppercase text-slate-500">Customer Concern</label>
                        <input
                            type="text"
                            value="<?php echo h($diagnosticModalJob['concern'] ?? ''); ?>"
                            class="mt-1 w-full rounded-lg border-slate-300 text-sm bg-slate-50"
                            readonly>
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-xs font-bold uppercase text-slate-500">Problem Description</label>
                        <textarea name="problem_description" rows="3" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required><?php echo h($existingDiagnosticReport['problem_description'] ?? $diagnosticModalJob['concern'] ?? ''); ?></textarea>
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-xs font-bold uppercase text-slate-500">Findings</label>
                        <textarea name="findings" rows="4" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required><?php echo h($existingDiagnosticReport['findings'] ?? ''); ?></textarea>
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-xs font-bold uppercase text-slate-500">Recommended Action</label>
                        <textarea name="recommended_action" rows="3" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required><?php echo h($existingDiagnosticReport['recommended_action'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 overflow-hidden">
                    <div class="px-5 py-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                        <div>
                            <h4 class="font-bold text-slate-900">Select Recommended Sub-Services</h4>
                            <p class="text-xs text-slate-500">These will be sent to the customer in the mobile app for approval.</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs font-bold text-slate-500 uppercase">Estimated Total</p>
                            <p class="text-xl font-black text-blue-700" id="diagnosticTotal">₱0.00</p>
                            <p class="text-[11px] text-slate-500 mt-1">
                                Main diagnostic: ₱<?php echo number_format((float) $diagnosticMainServiceTotal, 2); ?>
                            </p>
                        </div>
                    </div>

                    <div class="max-h-96 overflow-y-auto divide-y divide-slate-100">
                        <?php if (count($subServiceOptions) === 0): ?>
                            <div class="px-5 py-8 text-sm text-slate-500 text-center">No active sub-services found. Add sub-services in Services first.</div>
                        <?php else: ?>
                            <?php
                            $currentParent = '';
                            foreach ($subServiceOptions as $service):
                                $parentName = (string) ($service['parent_service_name'] ?? 'Other Services');
                                if ($parentName !== $currentParent):
                                    $currentParent = $parentName;
                            ?>
                                <div class="px-5 py-3 bg-slate-50 text-xs font-black uppercase tracking-widest text-slate-500">
                                    <?php echo h($currentParent); ?>
                                </div>
                            <?php endif; ?>

                                <?php
                                $sid = (int) $service['service_id'];
                                $checked = in_array($sid, $existingDiagnosticServiceIds, true);
                                ?>
                                <label class="flex items-start gap-4 px-5 py-4 hover:bg-slate-50 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        name="recommended_service_ids[]"
                                        value="<?php echo $sid; ?>"
                                        data-price="<?php echo h((float) ($service['price'] ?? 0)); ?>"
                                        class="diagnostic-service-checkbox mt-1 rounded border-slate-300 text-blue-600"
                                        <?php echo $checked ? 'checked' : ''; ?>>
                                    <div class="flex-1 min-w-0">
                                        <div class="font-bold text-sm text-slate-900"><?php echo h($service['service_name']); ?></div>
                                        <div class="text-xs text-slate-500 mt-1"><?php echo h($service['description'] ?? ''); ?></div>
                                        <div class="text-xs text-slate-400 mt-1">
                                            <?php echo h($service['category'] ?? 'Other'); ?> ·
                                            <?php echo (int) ($service['duration_minutes'] ?? 0); ?> mins
                                        </div>
                                    </div>
                                    <div class="text-sm font-bold text-slate-900">₱<?php echo number_format((float) ($service['price'] ?? 0), 2); ?></div>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="border-t border-slate-100 pt-4 flex gap-3 justify-end">
                    <a href="repairjobsadmin.php?<?php echo h(http_build_query(array_filter([
                        'shop' => $loginSlug,
                        'q' => $search,
                        'job_status' => $jobStatusFilter,
                        'service_status' => $serviceStatusFilter,
                        'priority' => $priorityFilter,
                    ], static fn($v) => $v !== ''))); ?>" class="px-4 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-700 font-semibold hover:bg-slate-50 transition-colors">
                        Cancel
                    </a>
                    <button type="submit" class="px-6 py-2.5 rounded-lg bg-indigo-600 text-white font-semibold hover:bg-indigo-700 transition-colors">
                        Submit to Customer Approval
                    </button>
                </div>
            </form>
        </section>
    </div>
<?php endif; ?>

<?php if ($showPartsModal && $partsModalJobDetails): ?>
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
        <section class="relative w-full max-w-2xl max-h-[85vh] overflow-y-auto bg-white rounded-2xl border border-slate-200 shadow-2xl">
            <div class="sticky top-0 z-10 bg-white border-b border-slate-100 px-6 py-5 flex items-center justify-between gap-3">
                <div>
                    <h3 class="font-bold text-slate-900 text-lg">Complete Repair Job - Select Parts Used</h3>
                    <p class="text-xs text-slate-500 mt-1"><?php echo h($partsModalJobDetails['job_order_no']); ?> | <?php echo h($partsModalJobDetails['customer_name']); ?></p>
                </div>
                <a href="repairjobsadmin.php?<?php echo h(http_build_query(array_filter([
                    'shop' => $loginSlug,
                    'q' => $search,
                    'job_status' => $jobStatusFilter,
                    'service_status' => $serviceStatusFilter,
                    'priority' => $priorityFilter,
                ], static fn($v) => $v !== ''))); ?>" class="inline-flex items-center justify-center w-8 h-8 rounded-full text-slate-500 hover:text-slate-700 hover:bg-slate-100 transition-colors">
                    <span class="material-symbols-outlined">close</span>
                </a>
            </div>

            <form method="post" class="p-6 space-y-6" id="completePartsForm">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>"/>
                <input type="hidden" name="repair_job_id" value="<?php echo (int) $partsModalJobId; ?>"/>
                <input type="hidden" name="complete_with_parts" value="1"/>

                <div class="space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <p class="text-sm text-slate-600 font-medium">Select the parts/inventory items used in this repair:</p>
                        <button type="button" id="add_sourced_part_btn" onclick="addSourcedPartRow()" class="inline-flex items-center justify-center gap-2 px-3 py-2 rounded-lg bg-slate-900 text-white text-sm font-semibold hover:bg-slate-800 transition-colors">
                            <span class="material-symbols-outlined text-base">add</span>
                            Add Part
                        </button>
                    </div>

                    <div class="border rounded-lg p-4 transition-colors bg-blue-50 border-blue-200">
                        <div class="flex items-start gap-4">
                            <input type="checkbox" name="no_parts_used" value="1" class="mt-1" id="no_parts_checkbox" onchange="toggleNoPartsMode()">
                            <div class="flex-1 min-w-0">
                                <label for="no_parts_checkbox" class="font-semibold text-slate-900 cursor-pointer">No Parts Used</label>
                                <p class="text-xs text-slate-600 mt-1">Complete this job without using inventory parts.</p>
                            </div>
                        </div>
                    </div>

                    <div id="sourced-parts-section" class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 space-y-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">External / sourced-out parts</p>
                            <p class="text-xs text-slate-500 mt-1">Use this for parts not available in shop inventory. These are added to parts total only and will not deduct stock.</p>
                        </div>
                        <div id="sourced-parts-list" class="space-y-3"></div>
                    </div>

                    <div id="parts-selection-container">
                        <?php if (count($inventoryItems) === 0): ?>
                            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                                No active inventory items available. You can mark the job as completed without parts or add items to inventory first.
                            </div>
                        <?php else: ?>
                            <div class="space-y-3 max-h-96 overflow-y-auto">
                                <?php foreach ($inventoryItems as $item): ?>
                                    <div class="border border-slate-200 rounded-lg p-4 hover:bg-slate-50 transition-colors">
                                        <div class="flex items-start gap-4">
                                            <input type="checkbox" name="part_selected" value="<?php echo (int) $item['item_id']; ?>" class="mt-1 part-checkbox" id="part_<?php echo (int) $item['item_id']; ?>" onchange="togglePartQuantity(<?php echo (int) $item['item_id']; ?>)">
                                            <div class="flex-1 min-w-0">
                                                <label for="part_<?php echo (int) $item['item_id']; ?>" class="font-semibold text-slate-900 cursor-pointer"><?php echo h($item['part_name']); ?></label>
                                                <p class="text-xs text-slate-500">Code: <?php echo h($item['part_code'] ?? 'N/A'); ?> | Category: <?php echo h($item['category']); ?></p>
                                                <p class="text-xs text-slate-600 mt-1">Available: <?php echo (int) $item['stock_quantity']; ?> | Price: ₱<?php echo number_format((float) $item['unit_price'], 2); ?></p>
                                            </div>
                                            <input type="number" data-item-id="<?php echo (int) $item['item_id']; ?>" min="1" max="<?php echo (int) $item['stock_quantity']; ?>" value="1" class="part-quantity w-16 rounded-lg border-slate-300 text-sm text-center disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="border-t border-slate-100 pt-4 flex gap-3 justify-end">
                    <a href="repairjobsadmin.php?<?php echo h(http_build_query(array_filter([
                        'shop' => $loginSlug,
                        'q' => $search,
                        'job_status' => $jobStatusFilter,
                        'service_status' => $serviceStatusFilter,
                        'priority' => $priorityFilter,
                    ], static fn($v) => $v !== ''))); ?>" class="px-4 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-700 font-semibold hover:bg-slate-50 transition-colors">
                        Cancel
                    </a>
                    <button type="submit" class="px-6 py-2.5 rounded-lg bg-green-600 text-white font-semibold hover:bg-green-700 transition-colors">
                        Complete Job & Use Parts
                    </button>
                </div>
            </form>
        </section>
    </div>
<?php endif; ?>

<script>
function handleJobStatusChange(selectElement, jobId) {
    const selectedStatus = selectElement.value;

    if (selectedStatus === 'Completed') {
        const params = new URLSearchParams(window.location.search);
        params.set('show_parts_modal', jobId);
        window.location.href = '?' + params.toString();
        return;
    }

    const postForm = document.createElement('form');
    postForm.method = 'post';
    postForm.innerHTML = `
        <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
        <input type="hidden" name="repair_job_id" value="${jobId}">
        <input type="hidden" name="job_status" value="${selectedStatus}">
        <input type="hidden" name="update_job_status" value="1">
    `;
    document.body.appendChild(postForm);
    postForm.submit();
}

function toggleNoPartsMode() {
    const noPartsCheckbox = document.getElementById('no_parts_checkbox');
    const partCheckboxes = document.querySelectorAll('.part-checkbox');
    const noPartsChecked = noPartsCheckbox && noPartsCheckbox.checked;

    partCheckboxes.forEach((checkbox) => {
        checkbox.disabled = noPartsChecked;
        checkbox.checked = false;

        const quantityInput = document.querySelector('input[data-item-id="' + checkbox.value + '"]');
        if (quantityInput) {
            quantityInput.disabled = true;
        }
    });

    const addSourcedPartBtn = document.getElementById('add_sourced_part_btn');
    if (addSourcedPartBtn) {
        addSourcedPartBtn.disabled = noPartsChecked;
        addSourcedPartBtn.classList.toggle('opacity-50', noPartsChecked);
        addSourcedPartBtn.classList.toggle('cursor-not-allowed', noPartsChecked);
    }

    document.querySelectorAll('.sourced-part-input').forEach((input) => {
        input.disabled = noPartsChecked;
    });
}

function togglePartQuantity(itemId) {
    const checkbox = document.getElementById('part_' + itemId);
    const quantityInput = document.querySelector('input[data-item-id="' + itemId + '"]');

    if (checkbox && quantityInput) {
        quantityInput.disabled = !checkbox.checked;
        if (checkbox.checked) {
            quantityInput.focus();
        }
    }
}


let sourcedPartIndex = 0;

function addSourcedPartRow() {
    const noPartsCheckbox = document.getElementById('no_parts_checkbox');
    if (noPartsCheckbox && noPartsCheckbox.checked) {
        alert('Uncheck No Parts Used before adding sourced-out parts.');
        return;
    }

    const list = document.getElementById('sourced-parts-list');
    if (!list) {
        return;
    }

    const index = sourcedPartIndex++;
    const row = document.createElement('div');
    row.className = 'sourced-part-row rounded-lg border border-slate-200 bg-white p-3 space-y-3';
    row.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
            <div class="md:col-span-4">
                <label class="text-[11px] font-bold uppercase text-slate-500">Part Name</label>
                <input type="text" name="sourced_parts[${index}][part_name]" class="sourced-part-input mt-1 w-full rounded-lg border-slate-300 text-sm" placeholder="Example: Brake sensor" required>
            </div>
            <div class="md:col-span-2">
                <label class="text-[11px] font-bold uppercase text-slate-500">Qty</label>
                <input type="number" name="sourced_parts[${index}][quantity]" class="sourced-part-input mt-1 w-full rounded-lg border-slate-300 text-sm" min="1" value="1" required>
            </div>
            <div class="md:col-span-3">
                <label class="text-[11px] font-bold uppercase text-slate-500">Unit Cost</label>
                <input type="number" name="sourced_parts[${index}][unit_cost]" class="sourced-part-input mt-1 w-full rounded-lg border-slate-300 text-sm" min="0" step="0.01" placeholder="0.00" required>
            </div>
            <div class="md:col-span-2">
                <label class="text-[11px] font-bold uppercase text-slate-500">Supplier</label>
                <input type="text" name="sourced_parts[${index}][supplier]" class="sourced-part-input mt-1 w-full rounded-lg border-slate-300 text-sm" placeholder="Optional">
            </div>
            <div class="md:col-span-1 flex justify-end">
                <button type="button" onclick="removeSourcedPartRow(this)" class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-red-200 text-red-600 hover:bg-red-50" title="Remove part">
                    <span class="material-symbols-outlined text-base">delete</span>
                </button>
            </div>
        </div>
    `;
    list.appendChild(row);
}

function removeSourcedPartRow(button) {
    const row = button.closest('.sourced-part-row');
    if (row) {
        row.remove();
    }
}

document.querySelectorAll('form[method="post"]').forEach((form) => {
    if (form.querySelector('input[name="complete_with_parts"]')) {
        form.addEventListener('submit', function () {
            form.querySelectorAll('input[name="selected_parts[]"]').forEach((input) => input.remove());

            const noPartsCheckbox = form.querySelector('input[name="no_parts_used"]');
            if (noPartsCheckbox && noPartsCheckbox.checked) {
                form.querySelectorAll('.sourced-part-row').forEach((row) => row.remove());
                return;
            }

            form.querySelectorAll('.part-checkbox:checked').forEach((checkbox) => {
                const itemId = checkbox.value;
                const quantityInput = document.querySelector('input[data-item-id="' + itemId + '"]');

                if (quantityInput && quantityInput.value && parseInt(quantityInput.value, 10) > 0) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'selected_parts[]';
                    input.value = JSON.stringify({
                        item_id: itemId,
                        quantity: quantityInput.value
                    });
                    form.appendChild(input);
                }
            });
        });
    }
});

document.querySelectorAll('.settings-dropdown-btn').forEach((button) => {
    button.addEventListener('click', function (e) {
        e.preventDefault();
        const dropdown = document.querySelector('[data-dropdown="settings"].settings-dropdown');
        if (dropdown) {
            dropdown.classList.toggle('hidden');
        }
    });
});

document.addEventListener('click', function (e) {
    const dropdownBtn = document.querySelector('.settings-dropdown-btn');
    const dropdown = document.querySelector('[data-dropdown="settings"].settings-dropdown');

    if (dropdown && dropdownBtn && !dropdownBtn.contains(e.target) && !dropdown.contains(e.target)) {
        dropdown.classList.add('hidden');
    }
});

const notificationBtn = document.getElementById('notificationBtn');
const notificationPanel = document.getElementById('notificationPanel');

if (notificationBtn && notificationPanel) {
    let notificationHideTimer = null;

    function hideNotificationPanel() {
        if (notificationHideTimer) {
            clearTimeout(notificationHideTimer);
            notificationHideTimer = null;
        }

        notificationPanel.classList.add('opacity-0', 'translate-y-2');
        notificationPanel.classList.remove('opacity-100', 'translate-y-0');

        window.setTimeout(() => {
            notificationPanel.classList.add('hidden');
        }, 300);
    }

    function showNotificationPanel(autoHide = false) {
        if (notificationHideTimer) {
            clearTimeout(notificationHideTimer);
            notificationHideTimer = null;
        }

        notificationPanel.classList.remove('hidden');
        requestAnimationFrame(() => {
            notificationPanel.classList.remove('opacity-0', 'translate-y-2');
            notificationPanel.classList.add('opacity-100', 'translate-y-0');
        });

        if (autoHide) {
            notificationHideTimer = window.setTimeout(() => {
                hideNotificationPanel();
            }, 5000);
        }
    }

    notificationBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        if (notificationPanel.classList.contains('hidden')) {
            showNotificationPanel(false);
        } else {
            hideNotificationPanel();
        }
    });

    <?php if ($notificationCount > 0): ?>
    showNotificationPanel(true);
    <?php endif; ?>

    document.addEventListener('click', function (e) {
        if (!notificationPanel.contains(e.target) && !notificationBtn.contains(e.target)) {
            hideNotificationPanel();
        }
    });
}


let activeHistoryFilter = 'All';

function applyHistoryFilters() {
    const searchInput = document.getElementById('historySearch');
    const rows = document.querySelectorAll('.history-row');
    const noResultsRow = document.getElementById('historyNoResultsRow');
    const visibleCountElement = document.getElementById('historyVisibleCount');
    const searchValue = searchInput ? searchInput.value.trim().toLowerCase() : '';
    let visibleCount = 0;

    rows.forEach((row) => {
        const rowStatus = row.getAttribute('data-history-status') || '';
        const rowText = row.getAttribute('data-history-search') || row.textContent.toLowerCase();
        const matchesStatus = activeHistoryFilter === 'All' || rowStatus === activeHistoryFilter;
        const matchesSearch = searchValue === '' || rowText.includes(searchValue);
        const isVisible = matchesStatus && matchesSearch;

        row.classList.toggle('hidden', !isVisible);
        if (isVisible) {
            visibleCount += 1;
        }
    });

    if (noResultsRow) {
        noResultsRow.classList.toggle('hidden', visibleCount !== 0 || rows.length === 0);
    }

    if (visibleCountElement) {
        visibleCountElement.textContent = visibleCount.toLocaleString();
    }
}

const historySearchInput = document.getElementById('historySearch');
if (historySearchInput) {
    historySearchInput.addEventListener('input', applyHistoryFilters);
}

document.querySelectorAll('.history-filter-btn').forEach((button) => {
    button.addEventListener('click', function () {
        activeHistoryFilter = button.getAttribute('data-history-filter') || 'All';

        document.querySelectorAll('.history-filter-btn').forEach((btn) => {
            btn.classList.remove('bg-slate-900', 'text-white', 'hover:bg-slate-800');
            btn.classList.add('border', 'border-slate-300', 'bg-white', 'text-slate-700', 'hover:bg-slate-100');
        });

        button.classList.add('bg-slate-900', 'text-white', 'hover:bg-slate-800');
        button.classList.remove('border', 'border-slate-300', 'bg-white', 'text-slate-700', 'hover:bg-slate-100');

        applyHistoryFilters();
    });
});
applyHistoryFilters();

const initialRepairJobsSignature = '<?php echo h($activeJobsSignature ?? ''); ?>';
let latestRepairJobsSignature = initialRepairJobsSignature;
let autoRefreshNoticeShown = false;

function hasOpenRepairModal() {
    return Boolean(
        document.getElementById('diagnosticForm') ||
        document.getElementById('partsCompletionForm') ||
        document.querySelector('[data-modal-open="true"]') ||
        window.location.search.includes('diagnostic_job=') ||
        window.location.search.includes('show_parts_modal=')
    );
}

function userIsTypingOrEditing() {
    const active = document.activeElement;
    if (!active) {
        return false;
    }

    const tagName = active.tagName ? active.tagName.toLowerCase() : '';
    return tagName === 'input' || tagName === 'textarea' || tagName === 'select' || active.isContentEditable;
}

async function checkForRepairJobUpdates() {
    if (hasOpenRepairModal() || userIsTypingOrEditing()) {
        return;
    }

    try {
        const url = new URL(window.location.href);
        url.searchParams.set('ajax', 'repair_job_count');
        url.searchParams.set('_', Date.now().toString());

        const response = await fetch(url.toString(), {
            cache: 'no-store',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        if (!response.ok) {
            return;
        }

        const data = await response.json();
        if (!data || typeof data.signature !== 'string') {
            return;
        }

        if (latestRepairJobsSignature && data.signature !== latestRepairJobsSignature) {
            if (!autoRefreshNoticeShown) {
                autoRefreshNoticeShown = true;
                const notice = document.createElement('div');
                notice.className = 'fixed bottom-5 right-5 z-50 rounded-xl bg-slate-900 px-4 py-3 text-sm font-bold text-white shadow-lg';
                notice.textContent = 'New repair job update detected. Refreshing...';
                document.body.appendChild(notice);
            }

            window.setTimeout(() => {
                window.location.reload();
            }, 700);
        } else {
            latestRepairJobsSignature = data.signature;
        }
    } catch (error) {
        // Keep the page usable even if polling fails.
    }
}

window.setInterval(checkForRepairJobUpdates, 10000);

const diagnosticMainServiceTotal = Number('<?php echo isset($diagnosticMainServiceTotal) ? (float) $diagnosticMainServiceTotal : 0; ?>');

function updateDiagnosticTotal() {
    let total = diagnosticMainServiceTotal;
    document.querySelectorAll('.diagnostic-service-checkbox:checked').forEach((checkbox) => {
        total += Number(checkbox.getAttribute('data-price') || 0);
    });

    const totalElement = document.getElementById('diagnosticTotal');
    if (totalElement) {
        totalElement.textContent = '₱' + total.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
}

document.querySelectorAll('.diagnostic-service-checkbox').forEach((checkbox) => {
    checkbox.addEventListener('change', updateDiagnosticTotal);
});
updateDiagnosticTotal();

const diagnosticForm = document.getElementById('diagnosticForm');
if (diagnosticForm) {
    diagnosticForm.addEventListener('submit', function (e) {
        const checked = diagnosticForm.querySelectorAll('.diagnostic-service-checkbox:checked');
        if (checked.length === 0) {
            e.preventDefault();
            alert('Please select at least one recommended sub-service.');
        }
    });
}
</script>
</body>
</html>